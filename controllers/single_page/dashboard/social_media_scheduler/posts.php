<?php
namespace Concrete\Package\SocialMediaScheduler\Controller\SinglePage\Dashboard\SocialMediaScheduler;

use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Package\SocialMediaScheduler\Src\Post\Repository;
use Concrete\Package\SocialMediaScheduler\Src\Scheduler\PostingRunner;
use Concrete\Package\SocialMediaScheduler\Src\Util\Crypto;

defined('C5_EXECUTE') or die('Access Denied.');

class Posts extends DashboardPageController
{
    public function repository(): Repository
    {
        return new Repository($this->app->make(Connection::class), new Crypto());
    }

    public function view(): void
    {
        $repo = $this->repository();
        $this->set('channels', $repo->getChannels(true));
        $this->set('allChannels', $repo->getChannels(false));
        $this->set('postings', $repo->getPostings());
        $this->set('timezone', date_default_timezone_get());
        $this->set('timezones', \DateTimeZone::listIdentifiers());
    }

    public function toggle_posting($id): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        if (!$this->token->validate('toggle_social_posting')) {
            $this->flash('error', $this->token->getErrorMessage());
            return $this->buildRedirect('/dashboard/social_media_scheduler/posts');
        }
        $this->repository()->setPostingEnabled((int) $id, (bool) $this->post('isEnabled'));
        $this->flash('success', t('Posting updated.'));
        return $this->buildRedirect('/dashboard/social_media_scheduler/posts');
    }

    public function send_now($id): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        if (!$this->token->validate('send_social_posting_now')) {
            $this->flash('error', $this->token->getErrorMessage());
            return $this->buildRedirect('/dashboard/social_media_scheduler/posts');
        }
        try {
            $summary = (new PostingRunner($this->repository()))->runPostingNow((int) $id);
            if ((int) $summary['failed'] > 0) {
                $this->flash('warning', t('Manual send finished with %s success(es) and %s error(s).', $summary['sent'], $summary['failed']));
            } else {
                $this->flash('success', t('Manual send finished: %s channel(s) sent.', $summary['sent']));
            }
        } catch (\Throwable $e) {
            $this->flash('error', t('Manual send failed: %s', $e->getMessage()));
        }
        return $this->buildRedirect('/dashboard/social_media_scheduler/posts');
    }

    public function submit_posting(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        if (!$this->token->validate('submit_social_posting')) {
            $this->flash('error', $this->token->getErrorMessage());
            return $this->buildRedirect('/dashboard/social_media_scheduler/posts');
        }
        $id = (int) $this->post('id');
        $title = trim((string) $this->post('title'));
        $subject = trim((string) $this->post('subject'));
        $body = (string) $this->post('bodyHtml');
        if ($id > 0 && $body === '') {
            $body = (string) $this->post('bodyHtml_' . $id);
        }
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
            return $this->buildRedirect('/dashboard/social_media_scheduler/posts');
        }
        if (strtotime($endAt) !== false && strtotime($startAt) !== false && strtotime($endAt) < strtotime($startAt)) {
            $this->flash('error', t('End date must be after the start date.'));
            return $this->buildRedirect('/dashboard/social_media_scheduler/posts');
        }
        $this->repository()->savePosting($title, $subject, $body, $startAt, $endAt, $repeatDays, $channelIDs, $id ?: null, $timezone, $attachmentFileIDs, $maxAttempts, $retryDelayMinutes);
        $this->flash('success', t('Posting updated.'));
        return $this->buildRedirect('/dashboard/social_media_scheduler/posts');
    }

    public function delete_posting($id): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        if (!$this->token->validate('delete_social_posting')) {
            $this->flash('error', $this->token->getErrorMessage());
            return $this->buildRedirect('/dashboard/social_media_scheduler/posts');
        }
        $this->repository()->deletePosting((int) $id);
        $this->flash('success', t('Posting deleted.'));
        return $this->buildRedirect('/dashboard/social_media_scheduler/posts');
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
