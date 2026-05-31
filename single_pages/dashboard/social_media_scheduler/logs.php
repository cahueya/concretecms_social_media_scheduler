<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>
<?php
/** @var array $allChannels */
/** @var array $postings */
/** @var array $logs */
/** @var array $logFilters */
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
$statusBadge = static function (string $status) {
    $class = str_contains($status, 'success') ? 'bg-success' : 'bg-danger';
    return '<span class="badge ' . $class . '">' . h($status) . '</span>';
};
?>
<form method="get" action="<?= URL::to('/dashboard/social_media_scheduler/logs') ?>" class="border rounded p-3 mb-3">
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label"><?= t('Posting') ?></label>
            <select name="filterPostingID" class="form-select">
                <option value="0"><?= t('All postings') ?></option>
                <?php foreach ($postings as $posting): ?><option value="<?= (int) $posting['id'] ?>" <?= (int) ($logFilters['postingID'] ?? 0) === (int) $posting['id'] ? 'selected' : '' ?>><?= h(($posting['title'] ?? '') ?: $posting['subject']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label"><?= t('Channel') ?></label>
            <select name="filterChannelID" class="form-select">
                <option value="0"><?= t('All channels') ?></option>
                <?php foreach ($allChannels as $channel): ?><option value="<?= (int) $channel['id'] ?>" <?= (int) ($logFilters['channelID'] ?? 0) === (int) $channel['id'] ? 'selected' : '' ?>><?= h($channel['channelName']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label"><?= t('Status') ?></label>
            <select name="filterStatus" class="form-select">
                <?php foreach (['' => t('All statuses'), 'success' => 'success', 'error' => 'error', 'manual_success' => 'manual_success', 'manual_error' => 'manual_error'] as $value => $label): ?>
                    <option value="<?= h($value) ?>" <?= (string) ($logFilters['status'] ?? '') === (string) $value ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <div class="float-end">
                <button class="btn btn-danger" type="submit" form="sms-clear-logs-form" onclick="return confirm('<?= h(t('Delete all send log entries?')) ?>')"><?= t('Reset') ?></button>
                <button class="btn btn-primary" type="submit"><?= t('Filter logs') ?></button>
            </div>
        </div>
    </div>
</form>
<form method="post" action="<?= $view->action('clear_logs') ?>" id="sms-clear-logs-form">
    <?= $token->output('clear_social_logs') ?>
</form>
<div class="table-responsive">
    <table class="table table-striped table-sm align-middle">
        <thead><tr><th><?= t('Date') ?></th><th><?= t('Posting') ?></th><th><?= t('Channel') ?></th><th><?= t('Status') ?></th><th><?= t('Attempt') ?></th><th><?= t('Message') ?></th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= h($log['dateCreated']) ?></td>
                <td><?= h(($log['title'] ?? '') ?: ($log['subject'] ?? '#' . $log['postingID'])) ?></td>
                <td><?= ($channelIcon((string) ($log['channelType'] ?? '')) . ' ' . h($log['channelName'] ?? '#' . $log['channelID'])) ?></td>
                <td><?= $statusBadge((string) $log['status']) ?></td>
                <td><?= (int) ($log['attempt'] ?? 1) ?></td>
                <td><?= h($log['message']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?><tr><td colspan="6" class="text-muted"><?= t('No log entries match the current filters.') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
