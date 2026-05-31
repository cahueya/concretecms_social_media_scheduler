<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>
<?php
$icons = [
    'telegram' => '<i class="fab fa-telegram"></i>',
    'listmonk' => '<i class="fas fa-envelope"></i>',
    'matrix' => '<i class="fas fa-comment-alt"></i>',
    'webhook' => '<i class="fas fa-project-diagram"></i>',
    'bluesky' => '<i class="fas fa-cloud"></i>',
    'mastodon' => '<i class="fab fa-mastodon"></i>',
];
$channelIcon = static function (string $type) use ($icons): string {
    return $icons[$type] ?? '<span aria-hidden="true">•</span>';
};
$formatPostingDate = static function (?string $date, string $timezone) {
    if (!$date) {
        return '';
    }
    try {
        $dt = new \DateTimeImmutable($date);
        if ($timezone !== '') {
            $dt = $dt->setTimezone(new \DateTimeZone($timezone));
        }
        if (class_exists('\\IntlDateFormatter')) {
            $locale = class_exists('\\Concrete\\Core\\Localization\\Localization') ? \Concrete\Core\Localization\Localization::activeLocale() : \Locale::getDefault();
            $formatter = new \IntlDateFormatter($locale ?: \Locale::getDefault(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT, $timezone ?: null);
            $formatted = $formatter->format($dt);
            return is_string($formatted) ? $formatted : $dt->format('Y-m-d H:i');
        }
        return $dt->format('Y-m-d H:i');
    } catch (\Throwable $e) {
        return substr((string) $date, 0, 16);
    }
};
$editor = Core::make('editor');
$fileManager = Core::make('helper/concrete/file_manager');
$getFile = static function (int $fileID) {
    if ($fileID <= 0) return null;
    try {
        $file = \Concrete\Core\File\File::getByID($fileID);
        return is_object($file) && !$file->isError() ? $file : null;
    } catch (\Throwable $e) {
        return null;
    }
};
$renderAttachmentSelectors = static function (string $prefix, array $attachmentIDs = []) use ($fileManager, $getFile) {
    $slots = max(3, count($attachmentIDs) + 1);
    for ($i = 0; $i < $slots; $i++) {
        $file = isset($attachmentIDs[$i]) ? $getFile((int) $attachmentIDs[$i]) : null;
        echo '<div class="mb-2">' . $fileManager->file($prefix . '-' . $i, 'attachmentFileIDs[]', t('Choose File'), $file) . '</div>';
    }
    echo '<div class="form-text">' . h(t('Use the standard Concrete CMS file selector. Add more files by saving, then editing again if you need additional attachment slots.')) . '</div>';
};
?>

<?php /** @var array $channels */ /** @var string $timezone */ /** @var array $timezones */ ?>
<form method="post" action="<?= $view->action('submit_posting') ?>">
    <?= $token->output('submit_social_posting') ?>
    <input type="hidden" name="id" value="">
    <div class="mb-3">
        <label class="form-label"><?= t('Title') ?></label>
        <input type="text" name="title" class="form-control">
        <div class="form-text"><?= t('Internal dashboard title only. This is not sent to any channel.') ?></div>
    </div>
    <div class="mb-3">
        <label class="form-label"><?= t('Subject') ?></label>
        <input type="text" name="subject" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label"><?= t('Content') ?></label>
        <?= $editor->outputBlockEditModeEditor('bodyHtml', '') ?>
        <div class="form-text"><?= t('Uses the same rich text editor mode as the Concrete CMS Content block. For messenger channels the subject is prepended as the first line.') ?></div>
    </div>
    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label"><?= t('Start Date / Time') ?></label>
            <input type="datetime-local" name="startAt" class="form-control" required>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label"><?= t('End Date / Time') ?></label>
            <input type="datetime-local" name="endAt" class="form-control" required>
            <div class="form-text"><?= t('The posting will not be sent after this date.') ?></div>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label"><?= t('Timezone') ?></label>
            <select name="timezone" class="form-select">
                <?php foreach ($timezones as $tz): ?>
                    <option value="<?= h($tz) ?>" <?= $tz === $timezone ? 'selected' : '' ?>><?= h($tz) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label"><?= t('Repeat every X days') ?></label>
            <input type="number" min="0" name="repeatEveryDays" class="form-control" value="7">
            <div class="form-text"><?= t('Use 0 for a one-time posting.') ?></div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label d-block"><?= t('Attachments') ?></label>
            <?php $renderAttachmentSelectors('sms-attachment-new'); ?>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label"><?= t('Max attempts') ?></label>
            <input type="number" min="1" name="maxAttempts" class="form-control" value="3">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label"><?= t('Retry delay in minutes') ?></label>
            <input type="number" min="1" name="retryDelayMinutes" class="form-control" value="30">
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label"><?= t('Channels') ?></label>
        <?php if (empty($channels)): ?>
            <div class="alert alert-warning"><?= t('No enabled channels configured yet.') ?></div>
        <?php endif; ?>
        <?php foreach ($channels as $channel): ?>
            <label class="form-check">
                <input type="checkbox" name="channelIDs[]" value="<?= (int) $channel['id'] ?>" class="form-check-input">
                <span class="form-check-label"><?= $channelIcon((string) $channel['channelType']) ?> <?= h($channel['channelName']) ?> <small class="text-muted">(<?= h($channel['channelType']) ?>)</small></span>
            </label>
        <?php endforeach; ?>
    </div>
    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <div class="float-end">
                <button class="btn btn-primary" type="submit"><?= t('Submit Posting') ?></button>
            </div>
        </div>
    </div>
</form>
