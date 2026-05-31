<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Post\Command;

use Concrete\Package\SocialMediaScheduler\Src\Scheduler\PostingRunner;

defined('C5_EXECUTE') or die('Access Denied.');

class SubmitDuePostingsCommandHandler
{
    public function __construct(private PostingRunner $runner) {}

    public function __invoke(SubmitDuePostingsCommand $command): void
    {
        $this->runner->runDue();
    }
}
