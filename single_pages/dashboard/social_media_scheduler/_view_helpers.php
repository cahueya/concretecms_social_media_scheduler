<?php
$icons = [
    'telegram' => '<i class="fab fa-telegram"></i>',
    'listmonk' => '<i class="fas fa-envelope"></i>',
    'matrix' => '<i class="fas fa-comment-alt"></i>',
    'webhook' => '<i class="fas fa-project-diagram"></i>',
    'bluesky' => '<i class="fas fa-cloud"></i>',
    'mastodon' => '<i class="fab fa-mastodon"></i>',
];
$channelIcon = static fn (string $type): string => $icons[$type] ?? '<span aria-hidden="true">•</span>';
$editor = Core::make('editor');
$fileManager = Core::make('helper/concrete/file_manager');
$getFile = static function (int $fileID) {
    if ($fileID <= 0) {
        return null;
    }
    try {
        $file = \Concrete\Core\File\File::getByID($fileID);
        return is_object($file) && !$file->isError() ? $file : null;
    } catch (\Throwable) {
        return null;
    }
};
$renderAttachmentSelectors = static function (string $prefix, array $attachmentIDs = []) use ($fileManager, $getFile): void {
    $slots = max(3, count($attachmentIDs) + 1);
    for ($i = 0; $i < $slots; $i++) {
        $file = isset($attachmentIDs[$i]) ? $getFile((int) $attachmentIDs[$i]) : null;
        echo '<div class="mb-2">' . $fileManager->file($prefix . '-' . $i, 'attachmentFileIDs[]', t('Choose File'), $file) . '</div>';
    }
    echo '<div class="form-text">' . h(t('Use the standard Concrete CMS file selector. Add more files by saving, then editing again if you need additional attachment slots.')) . '</div>';
};
