<?php
namespace Concrete\Package\SocialMediaScheduler\Controller\SinglePage\Dashboard\SocialMediaScheduler;

use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Package\SocialMediaScheduler\Src\Post\Repository;
use Concrete\Package\SocialMediaScheduler\Src\Util\Crypto;

defined('C5_EXECUTE') or die('Access Denied.');

class Create extends DashboardPageController
{
    public function repository(): Repository
    {
        return new Repository($this->app->make(Connection::class), new Crypto());
    }

    public function view(): void
    {
        $repo = $this->repository();
        $this->set('channels', $repo->getChannels(true));
        $this->set('timezone', date_default_timezone_get());
        $this->set('timezones', \DateTimeZone::listIdentifiers());
    }

    public function submit_posting(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        if (!$this->token->validate('submit_social_posting')) {
            $this->flash('error', $this->token->getErrorMessage());
            return $this->buildRedirect('/dashboard/social_media_scheduler/create');
        }
        $title = trim((string) $this->post('title'));
        $subject = trim((string) $this->post('subject'));
        $body = (string) $this->post('bodyHtml');
        $startAt = (string) $this->post('startAt');
        $endAt = (string) $this->post('endAt');
        $repeatDays = max(0, (int) $this->post('repeatEveryDays'));
        $channelIDs = (array) $this->post('channelIDs');
        $timezone = (string) $this->post('timezone');
        $attachmentFileIDs = $this->parseAttachmentFileIDs();
        $maxAttempts = max(1, (int) $this->post('maxAttempts'));
        $retryDelayMinutes = max(1, (int) $this->post('retryDelayMinutes'));
        if ($subject === '' || trim(strip_tags($body)) === '' || $startAt === '' || $endAt === '' || empty($channelIDs)) {
            $this->flash('error', t('Subject, content, start date, end date and at least one channel are required.'));
            return $this->buildRedirect('/dashboard/social_media_scheduler/create');
        }
        if (strtotime($endAt) !== false && strtotime($startAt) !== false && strtotime($endAt) < strtotime($startAt)) {
            $this->flash('error', t('End date must be after the start date.'));
            return $this->buildRedirect('/dashboard/social_media_scheduler/create');
        }
        $this->repository()->savePosting($title, $subject, $body, $startAt, $endAt, $repeatDays, $channelIDs, null, $timezone, $attachmentFileIDs, $maxAttempts, $retryDelayMinutes);
        $this->flash('success', t('Posting submitted.'));
        return $this->buildRedirect('/dashboard/social_media_scheduler/create');
    }

    public function parseAttachmentFileIDs(): array
    {
        $ids = [];
        foreach ((array) $this->post('attachmentFileIDs') as $fileID) {
            $fileID = (int) $fileID;
            if ($fileID > 0) {
                $ids[] = $fileID;
            }
        }
        $singleFileID = (int) $this->post('attachmentFileID');
        if ($singleFileID > 0) {
            $ids[] = $singleFileID;
        }
        $manual = $this->parseIDs((string) $this->post('attachmentFileIDsManual'));
        return array_values(array_unique(array_merge($ids, $manual)));
    }

    public function parseIDs(string $value): array
    {
        return array_values(array_filter(array_map('intval', preg_split('/[\s,;]+/', $value) ?: [])));
    }
}
