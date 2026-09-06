<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>
<?php
require __DIR__ . '/_view_helpers.php';

/** @var array $channels */
/** @var string $timezone */
/** @var array $timezones */
$posting = [];
$formChannels = $channels;
$submitLabel = t('Submit Posting');
$attachmentPrefix = 'sms-attachment-new';
$formClass = '';
$showEmptyChannelWarning = true;
require __DIR__ . '/_posting_form.php';
