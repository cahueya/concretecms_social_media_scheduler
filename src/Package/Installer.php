<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Package;

use Concrete\Core\Application\Application;
use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Single as SinglePage;
use Concrete\Package\SocialMediaScheduler\Controller;

\defined('C5_EXECUTE') or die('Access Denied.');

class Installer
{
    public function __construct(private Application $app)
    {
    }

    public function install(Controller $controller, $pkg): void
    {
        $this->installSinglePages($pkg);
        $controller->installContentFile('tasks.xml');
    }

    public function upgrade(Controller $controller, $pkg): void
    {
        $this->installSinglePages($pkg);
        $controller->installContentFile('tasks.xml');
    }

    public function uninstall(): void
    {
        $this->dropDatabaseTables();
    }

    private function installSinglePages($pkg): void
    {
        $pages = [
            '/dashboard/social_media_scheduler' => t('Social Media Scheduler'),
            '/dashboard/social_media_scheduler/posts' => t('Posts'),
            '/dashboard/social_media_scheduler/create' => t('Create'),
            '/dashboard/social_media_scheduler/logs' => t('Logs'),
            '/dashboard/social_media_scheduler/config' => t('Configuration'),
        ];

        foreach ($pages as $path => $name) {
            $page = Page::getByPath($path);
            if (!is_object($page) || $page->isError()) {
                $page = SinglePage::add($path, $pkg);
            }
            if (is_object($page) && !$page->isError()) {
                $page->update(['cName' => $name]);
            }
        }

        $this->setSinglePageDisplayOrder();
    }

    private function setSinglePageDisplayOrder(): void
    {
        $orders = [
            '/dashboard/social_media_scheduler/posts' => 0,
            '/dashboard/social_media_scheduler/create' => 1,
            '/dashboard/social_media_scheduler/logs' => 2,
            '/dashboard/social_media_scheduler/config' => 3,
        ];

        try {
            $db = $this->app->make(Connection::class);
            foreach ($orders as $path => $displayOrder) {
                $page = Page::getByPath($path);
                if (is_object($page) && !$page->isError()) {
                    $db->update('Pages', ['cDisplayOrder' => $displayOrder], ['cID' => (int) $page->getCollectionID()]);
                }
            }
        } catch (\Throwable) {
            // Display order is cosmetic; installation must not fail because of it.
        }
    }

    private function dropDatabaseTables(): void
    {
        $db = $this->app->make(Connection::class);
        foreach ([
            'SocialMediaSchedulerPostingChannels',
            'SocialMediaSchedulerLog',
            'SocialMediaSchedulerChannels',
            'SocialMediaSchedulerPostings',
        ] as $table) {
            try {
                $db->executeStatement('DROP TABLE IF EXISTS `' . $table . '`');
            } catch (\Throwable) {
            }
        }
    }
}
