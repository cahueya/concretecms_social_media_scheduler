<?php
namespace Concrete\Package\SocialMediaScheduler\Controller\SinglePage\Dashboard\SocialMediaScheduler;

use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Package\SocialMediaScheduler\Src\Post\Repository;
use Concrete\Package\SocialMediaScheduler\Src\Service\Channel\BlueskySender;
use Concrete\Package\SocialMediaScheduler\Src\Service\Channel\SenderRegistry;
use Concrete\Package\SocialMediaScheduler\Src\Service\Channel\TelegramSender;
use Concrete\Package\SocialMediaScheduler\Src\Util\Crypto;

\defined('C5_EXECUTE') or die('Access Denied.');

class Config extends DashboardPageController
{
    public function repository(): Repository
    {
        return new Repository($this->app->make(Connection::class), new Crypto());
    }

    public function view(): void
    {
        $this->set('channels', $this->repository()->getChannels());
        $this->set('telegramChats', $this->get('telegramChats') ?: []);
    }

    public function save_channel(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        if (!$this->token->validate('save_social_channel')) {
            $this->error->add($this->token->getErrorMessage());
            return $this->buildRedirect('/dashboard/social_media_scheduler/config');
        }

        $type = (string) $this->post('channelType');
        if (!in_array($type, SenderRegistry::types(), true)) {
            $this->flash('error', t('Invalid channel type.'));
            return $this->buildRedirect('/dashboard/social_media_scheduler/config');
        }

        $name = trim((string) $this->post('channelName')) ?: ucfirst($type);
        $this->repository()->saveChannel(
            $type,
            $name,
            $this->buildConfigFromPost($type),
            (bool) $this->post('isEnabled')
        );
        $this->flash('success', t('Channel saved.'));
        return $this->buildRedirect('/dashboard/social_media_scheduler/config');
    }

    public function delete_channel($id): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        if (!$this->token->validate('delete_social_channel')) {
            $this->flash('error', $this->token->getErrorMessage());
            return $this->buildRedirect('/dashboard/social_media_scheduler/config');
        }
        $this->repository()->deleteChannel((int) $id);
        $this->flash('success', t('Channel deleted.'));
        return $this->buildRedirect('/dashboard/social_media_scheduler/config');
    }

    public function test_channel($id): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        if (!$this->token->validate('test_social_channel')) {
            $this->flash('error', $this->token->getErrorMessage());
            return $this->buildRedirect('/dashboard/social_media_scheduler/config');
        }

        $channel = $this->repository()->getChannel((int) $id);
        if (!$channel) {
            $this->flash('error', t('Channel not found.'));
            return $this->buildRedirect('/dashboard/social_media_scheduler/config');
        }

        try {
            $result = (new SenderRegistry())->get((string) $channel['channelType'])->send(
                $channel,
                t('ConcreteCMS Social Scheduler test'),
                '<p>' . htmlspecialchars(t('This is a test message from your ConcreteCMS Social Media Scheduler package.'), ENT_QUOTES, 'UTF-8') . '</p>'
            );
            $this->flash('success', t('Test sent: %s', $result));
        } catch (\Throwable $e) {
            $this->flash('error', t('Test failed: %s', $e->getMessage()));
        }
        return $this->buildRedirect('/dashboard/social_media_scheduler/config');
    }

    public function refresh_telegram(): void
    {
        if (!$this->token->validate('refresh_telegram')) {
            $this->error->add($this->token->getErrorMessage());
            $this->view();
            return;
        }

        try {
            $this->set('telegramChats', (new TelegramSender())->getUpdates(trim((string) $this->post('bot_token'))));
            $this->set('refreshMessage', t('Telegram chats refreshed.'));
        } catch (\Throwable $e) {
            $this->error->add($e->getMessage());
        }
        $this->view();
    }

    public function buildConfigFromPost(string $type): array
    {
        return match ($type) {
            'telegram' => [
                'bot_token' => trim((string) $this->post('telegram_bot_token')),
                'chat_ids' => $this->splitLines((string) $this->post('telegram_chat_ids')),
                'parse_mode' => in_array((string) $this->post('telegram_parse_mode'), ['plain', 'HTML', 'MarkdownV2'], true) ? (string) $this->post('telegram_parse_mode') : 'plain',
                'disable_web_page_preview' => (bool) $this->post('telegram_disable_web_page_preview'),
                'include_explicit_attachments' => (bool) $this->post('telegram_include_explicit_attachments'),
            ],
            'listmonk' => [
                'base_url' => trim((string) $this->post('listmonk_base_url')),
                'username' => trim((string) $this->post('listmonk_username')),
                'password' => (string) $this->post('listmonk_password'),
                'list_ids' => array_map('intval', $this->splitComma((string) $this->post('listmonk_list_ids'))),
                'template_id' => trim((string) $this->post('listmonk_template_id')),
                'from_email' => trim((string) $this->post('listmonk_from_email')),
                'messenger' => trim((string) $this->post('listmonk_messenger')),
                'start_status' => in_array((string) $this->post('listmonk_start_status'), ['draft', 'running', 'scheduled'], true) ? (string) $this->post('listmonk_start_status') : 'running',
            ],
            'matrix' => [
                'homeserver' => trim((string) $this->post('matrix_homeserver')),
                'access_token' => trim((string) $this->post('matrix_access_token')),
                'room_ids' => $this->splitLines((string) $this->post('matrix_room_ids')),
                'msgtype' => in_array((string) $this->post('matrix_msgtype'), ['m.text', 'm.notice'], true) ? (string) $this->post('matrix_msgtype') : 'm.text',
                'include_explicit_attachments' => (bool) $this->post('matrix_include_explicit_attachments'),
            ],
            'webhook' => [
                'url' => trim((string) $this->post('webhook_url')),
                'method' => in_array(strtoupper((string) $this->post('webhook_method')), ['POST', 'PUT', 'PATCH'], true) ? strtoupper((string) $this->post('webhook_method')) : 'POST',
                'payload_mode' => in_array((string) $this->post('webhook_payload_mode'), ['json', 'form', 'multipart'], true) ? (string) $this->post('webhook_payload_mode') : 'json',
                'attachment_mode' => in_array((string) $this->post('webhook_attachment_mode'), ['urls', 'base64', 'multipart'], true) ? (string) $this->post('webhook_attachment_mode') : 'urls',
                'headers' => $this->parseHeaders((string) $this->post('webhook_headers')),
                'auth_type' => in_array((string) $this->post('webhook_auth_type'), ['none', 'basic', 'bearer'], true) ? (string) $this->post('webhook_auth_type') : 'none',
                'auth_token' => trim((string) $this->post('webhook_auth_token')),
                'basic_username' => trim((string) $this->post('webhook_basic_username')),
                'basic_password' => (string) $this->post('webhook_basic_password'),
            ],
            'bluesky' => [
                'handle' => trim((string) $this->post('bluesky_handle')),
                'app_password' => (string) $this->post('bluesky_app_password'),
                'service_url' => BlueskySender::normalizeServiceUrl((string) $this->post('bluesky_service_url')),
                'include_explicit_attachments' => (bool) $this->post('bluesky_include_explicit_attachments'),
            ],
            'mastodon' => [
                'instance_url' => rtrim(trim((string) $this->post('mastodon_instance_url')), '/'),
                'access_token' => trim((string) $this->post('mastodon_access_token')),
                'visibility' => in_array((string) $this->post('mastodon_visibility'), ['public', 'unlisted', 'private', 'direct'], true) ? (string) $this->post('mastodon_visibility') : 'public',
                'sensitive' => (bool) $this->post('mastodon_sensitive'),
                'spoiler_text' => trim((string) $this->post('mastodon_spoiler_text')),
                'language' => trim((string) $this->post('mastodon_language')),
                'include_explicit_attachments' => (bool) $this->post('mastodon_include_explicit_attachments'),
            ],
            default => [],
        };
    }

    public function splitLines(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R+/', $value) ?: [])));
    }

    public function splitComma(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    public function parseHeaders(string $value): array
    {
        $headers = [];
        foreach ($this->splitLines($value) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $val] = explode(':', $line, 2);
            $name = trim($name);
            if ($name !== '') {
                $headers[$name] = trim($val);
            }
        }
        return $headers;
    }
}
