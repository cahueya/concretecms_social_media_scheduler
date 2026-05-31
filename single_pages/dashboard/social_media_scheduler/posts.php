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

<div class="accordion" id="sms-postings-accordion">
    <?php foreach ($postings as $posting): ?>
        <?php
        $selected = array_map(static fn($c) => (int) $c['id'], $posting['channels']);
        $attachmentIDs = $posting['attachmentFileIDsArray'] ?? [];
        ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading-<?= (int) $posting['id'] ?>">
                <button class="accordion-button collapsed d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= (int) $posting['id'] ?>">
                    <span class="me-2"><strong><?= h(($posting['title'] ?? '') ?: $posting['subject']) ?></strong></span>
                    <small class="text-muted me-2"><?= h($formatPostingDate((string) $posting['startAt'], (string) ($posting['timezone'] ?: $timezone))) ?> · <?= (int) $posting['repeatEveryDays'] ?> <?= t('days') ?></small>
                    <span class="me-2"><?php foreach ($posting['channels'] as $channel) echo $channelIcon((string) $channel['channelType']) . ' '; ?></span>
                    <?php if (!$posting['isEnabled']): ?><span class="badge bg-secondary ms-auto me-3"><?= t('Disabled') ?></span><?php else: ?><span class="ms-auto me-3"></span><?php endif; ?>
                </button>
            </h2>
            <div id="collapse-<?= (int) $posting['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#sms-postings-accordion">
                <div class="accordion-body">
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <form method="post" action="<?= $view->action('send_now', $posting['id']) ?>" onsubmit="return confirm('<?= h(t('Send this posting now to all enabled selected channels?')) ?>');">
                            <?= $token->output('send_social_posting_now') ?>
                            <button class="btn btn-sm btn-success" type="submit"><?= t('Send now') ?></button>
                        </form>
                        <form method="post" action="<?= $view->action('toggle_posting', $posting['id']) ?>" class="d-flex align-items-center gap-2">
                            <?= $token->output('toggle_social_posting') ?>
                            <label class="form-check form-check-inline mb-0"><input class="form-check-input" type="radio" name="isEnabled" value="1" <?= $posting['isEnabled'] ? 'checked' : '' ?>> <?= t('Enabled') ?></label>
                            <label class="form-check form-check-inline mb-0"><input class="form-check-input" type="radio" name="isEnabled" value="0" <?= !$posting['isEnabled'] ? 'checked' : '' ?>> <?= t('Disabled') ?></label>
                            <button class="btn btn-sm btn-secondary" type="submit"><?= t('Save status') ?></button>
                        </form>
                    </div>

                    <h4><?= t('Final Channel Preview') ?></h4>
                    <?php foreach (($posting['previews'] ?? []) as $preview): ?>
                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="mb-2"><strong><?= h($preview['channel']) ?></strong> <span class="badge bg-secondary"><?= h($preview['format']) ?></span></div>
                            <div><?= $preview['body'] ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($posting['attachments'])): ?>
                        <p><strong><?= t('Attachments') ?>:</strong>
                            <?php foreach ($posting['attachments'] as $file): ?>
                                <a href="<?= h($file['url']) ?>" target="_blank" rel="noopener"><?= h($file['title'] ?: $file['filename']) ?></a>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>

                    <h4><?= t('Edit Posting') ?></h4>
                    <form method="post" action="<?= $view->action('submit_posting') ?>" class="border rounded p-3 mb-3">
                        <?= $token->output('submit_social_posting') ?>
                        <input type="hidden" name="id" value="<?= (int) $posting['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label"><?= t('Title') ?></label>
                            <input type="text" name="title" class="form-control" value="<?= h(($posting['title'] ?? '') ?: $posting['subject']) ?>">
                            <div class="form-text"><?= t('Internal dashboard title only. This is not sent to any channel.') ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= t('Subject') ?></label>
                            <input type="text" name="subject" class="form-control" value="<?= h($posting['subject']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= t('Content') ?></label>
                            <?= $editor->outputBlockEditModeEditor('bodyHtml_' . (int) $posting['id'], (string) $posting['bodyHtml']) ?>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3"><label class="form-label"><?= t('Start Date / Time') ?></label><input type="datetime-local" name="startAt" class="form-control" value="<?= h(str_replace(' ', 'T', substr((string) $posting['startAt'], 0, 16))) ?>" required></div>
                            <div class="col-md-4 mb-3"><label class="form-label"><?= t('Timezone') ?></label><select name="timezone" class="form-select"><?php foreach ($timezones as $tz): ?><option value="<?= h($tz) ?>" <?= $tz === ($posting['timezone'] ?: $timezone) ? 'selected' : '' ?>><?= h($tz) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-4 mb-3"><label class="form-label"><?= t('Repeat every X days') ?></label><input type="number" min="0" name="repeatEveryDays" class="form-control" value="<?= (int) $posting['repeatEveryDays'] ?>"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label d-block"><?= t('Attachments') ?></label>
                                <?php $renderAttachmentSelectors('sms-attachment-' . (int) $posting['id'], $attachmentIDs); ?>
                            </div>
                            <div class="col-md-4 mb-3"><label class="form-label"><?= t('Max attempts') ?></label><input type="number" min="1" name="maxAttempts" class="form-control" value="<?= (int) ($posting['maxAttempts'] ?? 3) ?>"></div>
                            <div class="col-md-4 mb-3"><label class="form-label"><?= t('Retry delay in minutes') ?></label><input type="number" min="1" name="retryDelayMinutes" class="form-control" value="<?= (int) ($posting['retryDelayMinutes'] ?? 30) ?>"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= t('Channels') ?></label>
                            <?php foreach ($allChannels as $channel): ?>
                                <label class="form-check"><input type="checkbox" name="channelIDs[]" value="<?= (int) $channel['id'] ?>" class="form-check-input" <?= in_array((int) $channel['id'], $selected, true) ? 'checked' : '' ?>> <span class="form-check-label"><?= $channelIcon((string) $channel['channelType']) ?> <?= h($channel['channelName']) ?> <small class="text-muted">(<?= h($channel['channelType']) ?>)</small></span></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="ccm-dashboard-form-actions-wrapper">
                            <div class="ccm-dashboard-form-actions">
                                <div class="float-end">
                                    <button class="btn btn-primary" type="submit"><?= t('Save changes') ?></button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <form method="post" action="<?= $view->action('delete_posting', $posting['id']) ?>" onsubmit="return confirm('<?= h(t('Delete this posting?')) ?>');">
                        <?= $token->output('delete_social_posting') ?>
                        <button class="btn btn-danger" type="submit"><?= t('Delete posting') ?></button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($postings)): ?><div class="alert alert-info"><?= t('No postings yet.') ?></div><?php endif; ?>
</div>
