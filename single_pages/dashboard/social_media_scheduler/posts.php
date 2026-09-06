<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>
<?php
require __DIR__ . '/_view_helpers.php';

$formatPostingDate = static function (?string $date, string $timezone): string {
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
    } catch (\Throwable) {
        return substr((string) $date, 0, 16);
    }
};
?>

<div class="accordion" id="sms-postings-accordion">
    <?php foreach ($postings as $posting): ?>
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading-<?= (int) $posting['id'] ?>">
                <button class="accordion-button collapsed d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= (int) $posting['id'] ?>">
                    <span class="me-2"><strong><?= h(($posting['title'] ?? '') ?: $posting['subject']) ?></strong></span>
                    <small class="text-muted me-2"><?= h($formatPostingDate((string) $posting['startAt'], (string) ($posting['timezone'] ?: $timezone))) ?> – <?= h($formatPostingDate((string) ($posting['endAt'] ?? ''), (string) ($posting['timezone'] ?: $timezone))) ?> · <?= (int) $posting['repeatEveryDays'] ?> <?= t('days') ?></small>
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
                    <?php
                    $formChannels = $allChannels;
                    $submitLabel = t('Save changes');
                    $attachmentPrefix = 'sms-attachment-' . (int) $posting['id'];
                    $formClass = 'border rounded p-3 mb-3';
                    $showEmptyChannelWarning = false;
                    require __DIR__ . '/_posting_form.php';
                    ?>

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
