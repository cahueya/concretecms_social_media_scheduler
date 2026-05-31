<?php
namespace Concrete\Package\SocialMediaScheduler\Controller\SinglePage\Dashboard;

use Concrete\Core\Page\Controller\DashboardPageController;

defined('C5_EXECUTE') or die('Access Denied.');

class SocialMediaScheduler extends DashboardPageController
{
    public function view(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return $this->buildRedirect('/dashboard/social_media_scheduler/posts');
    }
}
