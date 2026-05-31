<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Command\Task\Controller;

use Concrete\Core\Command\Task\Controller\AbstractController;
use Concrete\Core\Command\Task\Input\InputInterface;
use Concrete\Core\Command\Task\Runner\CommandTaskRunner;
use Concrete\Core\Command\Task\Runner\TaskRunnerInterface;
use Concrete\Core\Command\Task\TaskInterface;
use Concrete\Package\SocialMediaScheduler\Src\Post\Command\SubmitDuePostingsCommand;

defined('C5_EXECUTE') or die('Access Denied.');

class SubmitSocialPostingsController extends AbstractController
{
    public function getName(): string
    {
        return t('Submit Scheduled Social Postings');
    }

    public function getDescription(): string
    {
        return t('Submits all enabled postings whose next run date is due.');
    }

    public function getTaskRunner(TaskInterface $task, InputInterface $input): TaskRunnerInterface
    {
        return new CommandTaskRunner($task, new SubmitDuePostingsCommand(), t('Scheduled social postings processed.'));
    }
}
