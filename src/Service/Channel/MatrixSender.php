<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Service\Channel;

defined('C5_EXECUTE') or die('Access Denied.');

class MatrixSender implements ChannelSenderInterface
{

    public function send(array $channel, string $subject, string $bodyHtml, array $attachments = []): string
    {
        $config = $channel['config'] ?? [];
        $homeserver = rtrim((string) ($config['homeserver'] ?? ''), '/');
        $accessToken = (string) ($config['access_token'] ?? '');
        $roomIds = (array) ($config['room_ids'] ?? []);
        $msgtype = in_array((string) ($config['msgtype'] ?? 'm.text'), ['m.text', 'm.notice'], true) ? (string) ($config['msgtype'] ?? 'm.text') : 'm.text';
        if ($homeserver === '' || $accessToken === '' || empty($roomIds)) {
            throw new \RuntimeException('Matrix homeserver, access token or room IDs missing.');
        }

        $helper = new LocalMediaHelper();
        $includeExplicitAttachments = !empty($config['include_explicit_attachments']);
        $allAttachments = $helper->buildSocialMediaAttachments($bodyHtml, $attachments, $includeExplicitAttachments);
        $cleanBodyHtml = $helper->removeAllImageTags($bodyHtml);

        $subjectText = TextNormalizer::decode($subject);
        $plain = trim($subjectText . "\n\n" . TextNormalizer::htmlToText($cleanBodyHtml));
        $formatted = '<strong>' . htmlspecialchars($subjectText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false) . '</strong><br><br>' . TextNormalizer::decode($cleanBodyHtml);
        $uploaded = 0;
        $linkedFallback = 0;

        foreach ($roomIds as $roomId) {
            $this->sendRoomMessage($homeserver, $accessToken, (string) $roomId, [
                'msgtype' => $msgtype,
                'body' => $plain,
                'format' => 'org.matrix.custom.html',
                'formatted_body' => $formatted,
            ]);

            foreach ($allAttachments as $attachment) {
                $path = (string) ($attachment['path'] ?? '');
                if ($path !== '' && is_readable($path)) {
                    $contentUri = $this->uploadMedia($homeserver, $accessToken, $attachment);
                    $this->sendRoomMessage($homeserver, $accessToken, (string) $roomId, $this->buildMediaMessage($contentUri, $attachment));
                    $uploaded++;
                    continue;
                }

                $url = (string) ($attachment['url'] ?? '');
                if ($url !== '') {
                    $label = TextNormalizer::decode((string) (($attachment['title'] ?? '') ?: ($attachment['filename'] ?? $url)));
                    $this->sendRoomMessage($homeserver, $accessToken, (string) $roomId, [
                        'msgtype' => $msgtype,
                        'body' => $label . "\n" . $url,
                        'format' => 'org.matrix.custom.html',
                        'formatted_body' => '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false) . '">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false) . '</a>',
                    ]);
                    $linkedFallback++;
                }
            }
        }
        return sprintf('Matrix sent to %d room(s), uploaded %d attachment event(s), linked %d fallback attachment(s).', count($roomIds), $uploaded, $linkedFallback);
    }

    public function uploadMedia(string $homeserver, string $token, array $attachment): string
    {
        $path = (string) ($attachment['path'] ?? '');
        if ($path === '' || !is_readable($path)) {
            throw new \RuntimeException('Matrix attachment path is not readable.');
        }
        $filename = (string) (($attachment['filename'] ?? '') ?: basename($path));
        $mime = (string) (($attachment['mimeType'] ?? '') ?: 'application/octet-stream');
        $body = file_get_contents($path);
        if ($body === false) {
            throw new \RuntimeException('Matrix attachment could not be read.');
        }

        $endpoints = [
            $homeserver . '/_matrix/media/v3/upload?filename=' . rawurlencode($filename),
            $homeserver . '/_matrix/media/r0/upload?filename=' . rawurlencode($filename),
        ];
        $lastError = '';
        foreach ($endpoints as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: ' . $mime, 'Authorization: Bearer ' . $token],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 60,
            ]);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($response === false || $error) {
                $lastError = $error;
                continue;
            }
            $decoded = json_decode($response, true) ?: [];
            if ($code < 400 && !empty($decoded['content_uri'])) {
                return (string) $decoded['content_uri'];
            }
            $lastError = $decoded['error'] ?? $response;
            if ($code !== 404) {
                break;
            }
        }
        throw new \RuntimeException('Matrix media upload error: ' . $lastError);
    }

    public function buildMediaMessage(string $contentUri, array $attachment): array
    {
        $path = (string) ($attachment['path'] ?? '');
        $mime = (string) (($attachment['mimeType'] ?? '') ?: 'application/octet-stream');
        $filename = (string) (($attachment['filename'] ?? '') ?: basename($path));
        $helper = new LocalMediaHelper();
        $isImage = $helper->isImage($attachment);
        $info = [
            'mimetype' => $mime,
            'size' => (int) (($attachment['size'] ?? 0) ?: (is_readable($path) ? filesize($path) : 0)),
        ];
        if ($isImage && is_readable($path)) {
            $dimensions = @getimagesize($path);
            if (is_array($dimensions)) {
                $info['w'] = (int) ($dimensions[0] ?? 0);
                $info['h'] = (int) ($dimensions[1] ?? 0);
            }
        }
        return [
            'msgtype' => $isImage ? 'm.image' : 'm.file',
            'body' => $filename,
            'url' => $contentUri,
            'info' => array_filter($info, fn($v) => $v !== 0 && $v !== '' && $v !== null),
        ];
    }

    public function sendRoomMessage(string $homeserver, string $token, string $roomId, array $payload): array
    {
        $txn = bin2hex(random_bytes(8));
        $url = $homeserver . '/_matrix/client/v3/rooms/' . rawurlencode($roomId) . '/send/m.room.message/' . $txn;
        return $this->request($url, $token, $payload, 'PUT');
    }

    public function request(string $url, string $token, array $payload, string $method = 'PUT'): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $token],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false || $error) throw new \RuntimeException('Matrix request failed: ' . $error);
        $decoded = json_decode($body, true) ?: [];
        if ($code >= 400) throw new \RuntimeException('Matrix API error: ' . ($decoded['error'] ?? $body));
        return $decoded;
    }
}
