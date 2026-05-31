<?php defined('C5_EXECUTE') or die('Access Denied.'); ?>
<p><?= t('Redirecting to posts...') ?></p>
<script>window.location.href = <?= json_encode((string) URL::to('/dashboard/social_media_scheduler/posts')) ?>;</script>
<p><a href="<?= URL::to('/dashboard/social_media_scheduler/posts') ?>"><?= t('Open posts') ?></a></p>
