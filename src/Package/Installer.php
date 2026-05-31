<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Package;

use Concrete\Core\Application\Application;
use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Page\Page;
use Concrete\Core\Page\Single as SinglePage;
use Concrete\Core\Package\Package;
use Concrete\Package\SocialMediaScheduler\Controller;
use Concrete\Package\SocialMediaScheduler\Src\Entity\Channel;
use Concrete\Package\SocialMediaScheduler\Src\Entity\Posting;
use Concrete\Package\SocialMediaScheduler\Src\Entity\PostingChannel;
use Concrete\Package\SocialMediaScheduler\Src\Entity\SendLog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

\defined('C5_EXECUTE') or die('Access Denied.');

class Installer
{
    public function __construct(private Application $app)
    {
    }

    public function install(Controller $controller, $pkg): void
    {
        $this->ensureOrmSchema($controller);
        $this->installSinglePages($pkg);
        $controller->installContentFile('tasks.xml');
    }

    public function upgrade(Controller $controller, $pkg): void
    {
        $this->ensureOrmSchema($controller);
        $this->installSinglePages($pkg);
        $controller->installContentFile('tasks.xml');
    }

    public function uninstall(Controller $controller): void
    {
        $this->dropDatabaseTables();
    }

    public function installSinglePages($pkg): void
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
                $sp = SinglePage::add($path, $pkg);
                if (is_object($sp) && !$sp->isError()) {
                    $sp->update(['cName' => $name]);
                }
            } else {
                $page->update(['cName' => $name]);
            }
        }

        $this->setSinglePageDisplayOrder();
    }

    public function setSinglePageDisplayOrder(): void
    {
        $orders = [
            '/dashboard/social_media_scheduler/posts' => 0,
            '/dashboard/social_media_scheduler/create' => 1,
            '/dashboard/social_media_scheduler/logs' => 2,
            '/dashboard/social_media_scheduler/config' => 3,
        ];

        try {
            /** @var Connection $db */
            $db = $this->app->make(Connection::class);
            foreach ($orders as $path => $displayOrder) {
                $orderedPage = Page::getByPath($path);
                if (is_object($orderedPage) && !$orderedPage->isError()) {
                    $db->update('Pages', ['cDisplayOrder' => $displayOrder], ['cID' => (int) $orderedPage->getCollectionID()]);
                }
            }
        } catch (\Throwable $e) {
        }
    }

    public function ensureOrmSchema(Controller $controller): void
    {
        try {
            $em = $this->resolveEntityManager($controller);
            $schema = $em->getConnection()->createSchemaManager();
            $existingTables = array_map('strtolower', $schema->listTableNames());
            $metadata = [];
            foreach ($this->getEntityClasses() as $class) {
                $classMetadata = $em->getClassMetadata($class);
                if (!in_array(strtolower($classMetadata->getTableName()), $existingTables, true)) {
                    $metadata[] = $classMetadata;
                }
            }
            if ($metadata) {
                (new SchemaTool($em))->createSchema($metadata);
            }
            $this->ensurePostingEndAtColumn();
        } catch (\Throwable $e) {
            // parent::install()/parent::upgrade() normally creates package entity tables.
            // Keep install resilient; runtime errors will reveal schema issues during testing.
        }
    }


    public function ensurePostingEndAtColumn(): void
    {
        /** @var Connection $db */
        $db = $this->app->make(Connection::class);
        try {
            $schema = $db->createSchemaManager();
            if (!$schema->tablesExist(['SocialMediaSchedulerPostings'])) {
                return;
            }
            $columns = array_change_key_case($schema->listTableColumns('SocialMediaSchedulerPostings'), CASE_LOWER);
            if (!isset($columns['endat'])) {
                $db->executeStatement('ALTER TABLE SocialMediaSchedulerPostings ADD endAt DATETIME DEFAULT NULL AFTER nextRunAt');
            }
        } catch (\Throwable $e) {
        }
    }

    public function dropDatabaseTables(): void
    {
        /** @var Connection $db */
        $db = $this->app->make(Connection::class);
        $tables = [
            'SocialMediaSchedulerPostingChannels',
            'SocialMediaSchedulerLog',
            'SocialMediaSchedulerChannels',
            'SocialMediaSchedulerPostings',
        ];

        foreach ($tables as $table) {
            try {
                $db->executeStatement('DROP TABLE IF EXISTS `' . $table . '`');
            } catch (\Throwable $e) {
            }
        }
    }

    public function resolveEntityManager(Controller $controller): EntityManagerInterface
    {
        try {
            $em = $controller->getPackageEntityManager();
            if ($em instanceof EntityManagerInterface) {
                return $em;
            }
        } catch (\Throwable $e) {
        }
        return $this->app->make(EntityManagerInterface::class);
    }

    public function getEntityClasses(): array
    {
        return [
            Channel::class,
            Posting::class,
            PostingChannel::class,
            SendLog::class,
        ];
    }
}
