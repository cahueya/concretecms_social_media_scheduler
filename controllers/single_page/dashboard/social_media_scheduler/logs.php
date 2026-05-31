<?php
namespace Concrete\Package\SocialMediaScheduler\Controller\SinglePage\Dashboard\SocialMediaScheduler;

use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Package\SocialMediaScheduler\Src\Post\Repository;
use Concrete\Package\SocialMediaScheduler\Src\Util\Crypto;

defined('C5_EXECUTE') or die('Access Denied.');

class Logs extends DashboardPageController
{
    public function repository(): Repository
    {
        return new Repository($this->app->make(Connection::class), new Crypto());
    }

    public function clear_logs(): void
    {
        if (!$this->token->validate('clear_social_logs')) {
            $this->flash('error', $this->token->getErrorMessage());
            $this->redirect('/dashboard/social_media_scheduler/logs');
            return;
        }

        $this->repository()->flushLogs();
        $this->flash('success', t('The send logs have been cleared.'));
        $this->redirect('/dashboard/social_media_scheduler/logs');
    }

    public function view(): void
    {
        $repo = $this->repository();
        $filters = [
            'postingID' => (int) $this->get('filterPostingID'),
            'channelID' => (int) $this->get('filterChannelID'),
            'status' => (string) $this->get('filterStatus'),
        ];
        $this->set('allChannels', $repo->getChannels(false));
        $this->set('postings', $repo->getPostings());
        $this->set('logs', $repo->getLogs(250, $filters));
        $this->set('logFilters', $filters);
    }
}
