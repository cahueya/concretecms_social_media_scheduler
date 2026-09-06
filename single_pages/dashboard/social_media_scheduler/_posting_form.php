<?php
/**
 * @var array $posting
 * @var array $formChannels
 * @var string $timezone
 * @var array $timezones
 * @var string $submitLabel
 * @var string $attachmentPrefix
 * @var string $formClass
 * @var bool $showEmptyChannelWarning
 */
$posting = $posting ?? [];
$id = (int) ($posting['id'] ?? 0);
$selectedChannels = array_map(static fn (array $channel): int => (int) $channel['id'], (array) ($posting['channels'] ?? []));
$attachmentIDs = (array) ($posting['attachmentFileIDsArray'] ?? []);
$postingTimezone = (string) (($posting['timezone'] ?? '') ?: $timezone);
$startValue = $id ? str_replace(' ', 'T', substr((string) ($posting['startAt'] ?? ''), 0, 16)) : '';
$endValue = $id ? str_replace(' ', 'T', substr((string) ($posting['endAt'] ?? ''), 0, 16)) : '';
$repeatValue = $id ? (int) ($posting['repeatEveryDays'] ?? 0) : 7;
$maxAttempts = $id ? (int) ($posting['maxAttempts'] ?? 3) : 3;
$retryDelay = $id ? (int) ($posting['retryDelayMinutes'] ?? 30) : 30;
$editorID = $id ? 'bodyHtml_' . $id : 'bodyHtml';
?>
<form method="post" action="<?= $view->action('submit_posting') ?>"<?= $formClass !== '' ? ' class="' . h($formClass) . '"' : '' ?>>
    <?= $token->output('submit_social_posting') ?>
    <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

    <div class="mb-3">
        <label class="form-label"><?= t('Title') ?></label>
        <input type="text" name="title" class="form-control" value="<?= h((string) (($posting['title'] ?? '') ?: ($posting['subject'] ?? ''))) ?>">
        <div class="form-text"><?= t('Internal dashboard title only. This is not sent to any channel.') ?></div>
    </div>

    <div class="mb-3">
        <label class="form-label"><?= t('Subject') ?></label>
        <input type="text" name="subject" class="form-control" value="<?= h((string) ($posting['subject'] ?? '')) ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label"><?= t('Content') ?></label>
        <?= $editor->outputBlockEditModeEditor($editorID, (string) ($posting['bodyHtml'] ?? '')) ?>
        <div class="form-text"><?= t('Uses the same rich text editor mode as the Concrete CMS Content block. For messenger channels the subject is prepended as the first line.') ?></div>
    </div>

    <div class="row">
        <div class="col-md-3 mb-3">
            <label class="form-label"><?= t('Start Date / Time') ?></label>
            <input type="datetime-local" name="startAt" class="form-control" value="<?= h($startValue) ?>" required>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label"><?= t('End Date / Time') ?></label>
            <input type="datetime-local" name="endAt" class="form-control" value="<?= h($endValue) ?>" required>
            <div class="form-text"><?= t('The posting will not be sent after this date.') ?></div>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label"><?= t('Timezone') ?></label>
            <select name="timezone" class="form-select">
                <?php foreach ($timezones as $tz): ?>
                    <option value="<?= h($tz) ?>" <?= $tz === $postingTimezone ? 'selected' : '' ?>><?= h($tz) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 mb-3">
            <label class="form-label"><?= t('Repeat every X days') ?></label>
            <input type="number" min="0" name="repeatEveryDays" class="form-control" value="<?= $repeatValue ?>">
            <div class="form-text"><?= t('Use 0 for a one-time posting.') ?></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label d-block"><?= t('Attachments') ?></label>
            <?php $renderAttachmentSelectors($attachmentPrefix, $attachmentIDs); ?>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label"><?= t('Max attempts') ?></label>
            <input type="number" min="1" name="maxAttempts" class="form-control" value="<?= $maxAttempts ?>">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label"><?= t('Retry delay in minutes') ?></label>
            <input type="number" min="1" name="retryDelayMinutes" class="form-control" value="<?= $retryDelay ?>">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label"><?= t('Channels') ?></label>
        <?php if ($showEmptyChannelWarning && empty($formChannels)): ?>
            <div class="alert alert-warning"><?= t('No enabled channels configured yet.') ?></div>
        <?php endif; ?>
        <?php foreach ($formChannels as $channel): ?>
            <label class="form-check">
                <input type="checkbox" name="channelIDs[]" value="<?= (int) $channel['id'] ?>" class="form-check-input" <?= in_array((int) $channel['id'], $selectedChannels, true) ? 'checked' : '' ?>>
                <span class="form-check-label"><?= $channelIcon((string) $channel['channelType']) ?> <?= h($channel['channelName']) ?> <small class="text-muted">(<?= h($channel['channelType']) ?>)</small></span>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <div class="float-end">
                <button class="btn btn-primary" type="submit"><?= h($submitLabel) ?></button>
            </div>
        </div>
    </div>
</form>
