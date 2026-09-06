<?php
namespace Concrete\Package\SocialMediaScheduler\Controller\SinglePage\Dashboard\SocialMediaScheduler;

use Concrete\Package\SocialMediaScheduler\Src\Dashboard\PostingController;

\defined('C5_EXECUTE') or die('Access Denied.');

class Create extends PostingController
{
    public function view(): void
    {
        $this->set('channels', $this->repository()->getChannels(true));
        $this->set('timezone', date_default_timezone_get());
        $this->set('timezones', \DateTimeZone::listIdentifiers());
    }

    public function submit_posting(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        if (!$this->token->validate('submit_social_posting')) {
            $this->flash('error', $this->token->getErrorMessage());
            return $this->buildRedirect('/dashboard/social_media_scheduler/create');
        }

        $data = $this->postingFormData();
        if ($error = $this->validatePostingFormData($data)) {
            $this->flash('error', $error);
            return $this->buildRedirect('/dashboard/social_media_scheduler/create');
        }

        $this->savePostingFormData($data);
        $this->flash('success', t('Posting submitted.'));
        return $this->buildRedirect('/dashboard/social_media_scheduler/create');
    }
}
