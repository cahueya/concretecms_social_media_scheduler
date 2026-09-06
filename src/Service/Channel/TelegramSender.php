<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Service\Channel;

defined('C5_EXECUTE') or die('Access Denied.');

class TelegramSender implements ChannelSenderInterface
{

    public function send(array $channel, string $subject, string $bodyHtml, array $attachments = []): string
    {
        $config = $channel['config'] ?? [];
        $token = trim((string) ($config['bot_token'] ?? ''));
        $chatIds = (array) ($config['chat_ids'] ?? []);
        $parseMode = (string) ($config['parse_mode'] ?? 'plain');
        $disablePreview = !empty($config['disable_web_page_preview']);
        if ($token === '' || empty($chatIds)) {
            throw new \RuntimeException('Telegram bot token or chat IDs missing.');
        }

        $helper = new LocalMediaHelper();
        $includeExplicitAttachments = !empty($config['include_explicit_attachments']);
        $allAttachments = $helper->buildSocialMediaAttachments($bodyHtml, $attachments, $includeExplicitAttachments);
        $cleanBodyHtml = $helper->removeLocalImageTags($bodyHtml);
        $messageText = $this->messageText($subject, $cleanBodyHtml, $parseMode);
        $sentAttachments = 0;
        foreach ($chatIds as $chatId) {
            $imageAttachments = array_values(array_filter($allAttachments, fn($a) => $this->isImageAttachment($a)));
            $canUseSingleCaption = count($allAttachments) === 1 && count($imageAttachments) === 1 && mb_strlen($messageText) <= 1024;

            if ($canUseSingleCaption) {
                $this->sendAttachment($token, (string) $chatId, $allAttachments[0], $messageText, $parseMode);
                $sentAttachments++;
                continue;
            }

            $payload = [
                'chat_id' => $chatId,
                'text' => $messageText,
                'disable_web_page_preview' => $disablePreview ? 'true' : 'false',
            ];
            if (in_array($parseMode, ['HTML', 'MarkdownV2'], true)) {
                $payload['parse_mode'] = $parseMode;
            }
            $this->request($token, 'sendMessage', $payload);

            foreach ($allAttachments as $attachment) {
                $this->sendAttachment($token, (string) $chatId, $attachment, '', $parseMode);
                $sentAttachments++;
            }
        }
        return sprintf('Telegram sent to %d chat(s), %d body media item(s), %d uploaded media item(s). Explicit attachments: %s.', count($chatIds), count($allAttachments), $sentAttachments, $includeExplicitAttachments ? 'included' : 'ignored');
    }

    public function sendAttachment(string $token, string $chatId, array $attachment, string $caption = '', string $parseMode = 'plain'): void
    {
        $method = $this->isImageAttachment($attachment) ? 'sendPhoto' : 'sendDocument';
        $field = $method === 'sendPhoto' ? 'photo' : 'document';
        $payload = ['chat_id' => $chatId];

        $path = (string) ($attachment['path'] ?? '');
        if ($path !== '' && is_readable($path)) {
            $mime = (string) ($attachment['mimeType'] ?? '');
            $filename = (string) (($attachment['filename'] ?? '') ?: basename($path));
            $payload[$field] = new \CURLFile($path, $mime ?: null, $filename ?: null);
        } else {
            $url = (string) ($attachment['url'] ?? '');
            if ($url === '') {
                throw new \RuntimeException('Telegram attachment has neither a readable local path nor a public URL.');
            }
            $payload[$field] = $url;
        }

        if ($caption !== '') {
            $payload['caption'] = mb_substr($caption, 0, 1024);
            if (in_array($parseMode, ['HTML', 'MarkdownV2'], true)) {
                $payload['parse_mode'] = $parseMode;
            }
        } elseif (!empty($attachment['title']) || !empty($attachment['filename'])) {
            $payload['caption'] = mb_substr(TextNormalizer::decode((string) (($attachment['title'] ?? '') ?: ($attachment['filename'] ?? ''))), 0, 1024);
        }

        $this->request($token, $method, $payload);
    }

    public function isImageAttachment(array $attachment): bool
    {
        $mime = (string) ($attachment['mimeType'] ?? '');
        if (str_starts_with($mime, 'image/')) {
            return true;
        }
        return (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) ($attachment['filename'] ?? $attachment['url'] ?? ''));
    }

    public function getUpdates(string $token): array
    {
        $response = $this->request($token, 'getUpdates', ['timeout' => 0, 'limit' => 50]);
        $chats = [];
        foreach (($response['result'] ?? []) as $update) {
            $message = $update['message'] ?? $update['channel_post'] ?? $update['my_chat_member'] ?? null;
            if (!is_array($message)) continue;
            $chat = $message['chat'] ?? null;
            if (!is_array($chat) || !isset($chat['id'])) continue;
            $chats[(string) $chat['id']] = [
                'id' => (string) $chat['id'],
                'title' => $chat['title'] ?? trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? '')) ?: (string) $chat['id'],
                'type' => $chat['type'] ?? 'unknown',
            ];
        }
        return array_values($chats);
    }

    public function messageText(string $subject, string $bodyHtml, string $parseMode): string
    {
        $subject = TextNormalizer::decode($subject);
        if ($parseMode === 'HTML') {
            return '<b>' . htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false) . '</b>' . "\n\n" . TextNormalizer::decode($bodyHtml);
        }
        $plain = TextNormalizer::htmlToText($bodyHtml);
        if ($parseMode === 'MarkdownV2') {
            return '*' . $this->escapeMarkdownV2($subject) . "*\n\n" . $this->escapeMarkdownV2($plain);
        }
        return trim($subject) . "\n\n" . $plain;
    }

    public function escapeMarkdownV2(string $value): string
    {
        return preg_replace('/([_\*\[\]\(\)~`>#+\-=|{}.!])/', '\\\\$1', $value) ?? $value;
    }

    public function request(string $token, string $method, array $payload): array
    {
        $ch = curl_init('https://api.telegram.org/bot' . rawurlencode($token) . '/' . $method);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false || $error) {
            throw new \RuntimeException('Telegram request failed: ' . $error);
        }
        $decoded = json_decode($body, true);
        if ($code >= 400 || !($decoded['ok'] ?? false)) {
            throw new \RuntimeException('Telegram API error: ' . ($decoded['description'] ?? $body));
        }
        return $decoded;
    }
}
