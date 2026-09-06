<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Service\Channel;

defined('C5_EXECUTE') or die('Access Denied.');

class SenderRegistry
{
    /**
     * X/Twitter is intentionally not registered until API write access has been verified.
     * XSender remains in the package so it can be re-enabled later without restoring legacy code.
     */
    private const SENDERS = [
        'telegram' => TelegramSender::class,
        'listmonk' => ListmonkSender::class,
        'matrix' => MatrixSender::class,
        'webhook' => WebhookSender::class,
        'bluesky' => BlueskySender::class,
        'mastodon' => MastodonSender::class,
    ];

    public static function types(): array
    {
        return array_keys(self::SENDERS);
    }

    public function get(string $type): ChannelSenderInterface
    {
        $class = self::SENDERS[$type] ?? null;
        if ($class === null) {
            throw new \RuntimeException('Unsupported channel type: ' . $type);
        }

        return new $class();
    }
}
