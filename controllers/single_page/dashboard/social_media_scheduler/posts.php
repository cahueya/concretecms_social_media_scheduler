<?php
namespace Concrete\Package\SocialMediaScheduler\Controller\SinglePage\Dashboard\SocialMediaScheduler;

use Concrete\Package\SocialMediaScheduler\Src\Dashboard\PostingController;
use Concrete\Package\SocialMediaScheduler\Src\Scheduler\PostingRunner;

\defined('C5_EXECUTE') or die('Access Denied.');

class Posts extends PostingController
{
    public function view(): void
    {
        $repo = $this->repository();
        $this->set('allChannels', $repo->getChannels(false));
        $this->set('postings', $repo->getPostingsForDashboard());
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
        $data = $this->postingFormData($id);
        if ($error = $this->validatePostingFormData($data)) {
            $this->flash('error', $error);
            return $this->buildRedirect('/dashboard/social_media_scheduler/posts');
        }

        $this->savePostingFormData($data, $id ?: null);
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
}
