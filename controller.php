<?php
namespace Concrete\Package\SocialMediaScheduler;

use Concrete\Core\Command\Task\Manager as TaskManager;
use Concrete\Core\Database\EntityManager\Provider\ProviderAggregateInterface;
use Concrete\Core\Database\EntityManager\Provider\StandardPackageProvider;
use Concrete\Core\Package\Package;
use Concrete\Package\SocialMediaScheduler\Src\Command\Task\Controller\SubmitSocialPostingsController;
use Concrete\Package\SocialMediaScheduler\Src\Package\Installer;

\defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends Package implements ProviderAggregateInterface
{
    protected $pkgHandle = 'social_media_scheduler';
    protected $appVersionRequired = '9.4.0';
    protected $pkgVersion = '0.6.4';
    protected $pkgAutoloaderRegistries = [
        'src' => 'Concrete\\Package\\SocialMediaScheduler\\Src',
    ];

    public function getPackageName()
    {
        return t('Social Media Scheduler');
    }

    public function getPackageDescription()
    {
        return t('Schedule recurring posts to Telegram, Listmonk, Matrix, generic webhooks, Bluesky and Mastodon with channel-specific media handling.');
    }

    public function getEntityManagerProvider()
    {
        return new StandardPackageProvider($this->app, $this, [
            'src/Entity' => 'Concrete\\Package\\SocialMediaScheduler\\Src\\Entity',
        ]);
    }

    public function on_start()
    {
        $manager = $this->app->make(TaskManager::class);
        $manager->extend('submit_social_postings', function () {
            return new SubmitSocialPostingsController();
        });
    }

    public function install()
    {
        $pkg = parent::install();
        $this->installer()->install($this, $pkg);
        return $pkg;
    }

    public function upgrade()
    {
        parent::upgrade();
        $this->installer()->upgrade($this, $this);
    }

    public function uninstall()
    {
        $this->installer()->uninstall($this);
        parent::uninstall();
    }

    public function installer(): Installer
    {
        return new Installer($this->app);
    }
}
