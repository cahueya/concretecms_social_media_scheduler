<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Service\Channel;

defined('C5_EXECUTE') or die('Access Denied.');

interface ChannelSenderInterface
{
    public function send(array $channel, string $subject, string $bodyHtml, array $attachments = []): string;
}
