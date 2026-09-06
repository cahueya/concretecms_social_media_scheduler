<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Service\Channel;

defined('C5_EXECUTE') or die('Access Denied.');

class BlueskySender implements ChannelSenderInterface
{
    public const SAFE_IMAGE_LIMIT_BYTES = 1000000;


    public function send(array $channel, string $subject, string $bodyHtml, array $attachments = []): string
    {
        $config = $channel['config'] ?? [];
        $handle = trim((string) ($config['handle'] ?? ''));
        $password = (string) ($config['app_password'] ?? '');
        $service = self::normalizeServiceUrl((string) ($config['service_url'] ?? 'https://bsky.social'));
        if ($handle === '' || $password === '') {
            throw new \RuntimeException('Bluesky handle or app password missing.');
        }

        $helper = new LocalMediaHelper();
        $includeExplicitAttachments = !empty($config['include_explicit_attachments']);
        $socialMedia = $helper->buildSocialMediaAttachments($bodyHtml, $attachments, $includeExplicitAttachments);
        $cleanBodyHtml = $helper->removeAllImageTags($bodyHtml);

        $session = $this->createSession($service, $handle, $password);
        $subjectText = TextNormalizer::decode($subject);
        $plain = TextNormalizer::htmlToText($cleanBodyHtml);
        $text = $this->trimPostText(trim($subjectText . "\n\n" . $plain));
        if ($text === '') {
            $text = $this->trimPostText($subjectText !== '' ? $subjectText : 'Post');
        }

        $embedImages = [];
        $ignoredNonImages = 0;
        $ignoredOverLimit = 0;
        foreach ($socialMedia as $attachment) {
            if (count($embedImages) >= 4) {
                break;
            }
            if (!$helper->isImage($attachment)) {
                $ignoredNonImages++;
                continue;
            }
            $path = (string) ($attachment['path'] ?? '');
            if ($path === '' || !is_readable($path)) {
                throw new \RuntimeException('Bluesky image cannot be uploaded because the local file is not readable: ' . $this->attachmentLabel($attachment));
            }
            $size = (int) (($attachment['size'] ?? 0) ?: filesize($path));
            if ($size > self::SAFE_IMAGE_LIMIT_BYTES) {
                $ignoredOverLimit++;
                throw new \RuntimeException(sprintf('Bluesky image is larger than the safe upload limit of %d bytes: %s (%d bytes). Please resize/compress it before posting.', self::SAFE_IMAGE_LIMIT_BYTES, $this->attachmentLabel($attachment), $size));
            }
            $mime = (string) (($attachment['mimeType'] ?? '') ?: $helper->guessMimeFromFilename($path));
            if (!str_starts_with($mime, 'image/')) {
                $ignoredNonImages++;
                continue;
            }
            $blob = $this->uploadBlob($service, (string) $session['accessJwt'], $path, $mime);
            $embedImages[] = [
                'alt' => TextNormalizer::decode((string) (($attachment['title'] ?? '') ?: ($attachment['filename'] ?? basename($path)))),
                'image' => $blob,
            ];
        }

        $record = [
            '$type' => 'app.bsky.feed.post',
            'text' => $text,
            'createdAt' => gmdate('c'),
        ];
        if ($embedImages) {
            $record['embed'] = [
                '$type' => 'app.bsky.embed.images',
                'images' => $embedImages,
            ];
        }

        $response = $this->postJson($service . '/xrpc/com.atproto.repo.createRecord', [
            'repo' => (string) $session['did'],
            'collection' => 'app.bsky.feed.post',
            'record' => $record,
        ], (string) $session['accessJwt']);

        return sprintf(
            'Bluesky post created: %s. Embedded %d body image(s). Explicit attachments: %s. Ignored non-images: %d.',
            (string) ($response['uri'] ?? 'ok'),
            count($embedImages),
            $includeExplicitAttachments ? 'included' : 'ignored',
            $ignoredNonImages + $ignoredOverLimit
        );
    }

    public static function normalizeServiceUrl(string $service): string
    {
        $service = trim($service);
        if ($service === '') {
            return 'https://bsky.social';
        }
        if (!preg_match('#^https?://#i', $service)) {
            $service = 'https://' . $service;
        }
        $parts = parse_url($service);
        $host = strtolower((string) ($parts['host'] ?? ''));
        // bsky.app is the web application, not the AT Protocol PDS API host.
        // Most bsky.social accounts should use https://bsky.social for /xrpc calls.
        if ($host === 'bsky.app' || $host === 'www.bsky.app') {
            return 'https://bsky.social';
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        return rtrim($scheme . '://' . $host, '/');
    }

    public function createSession(string $service, string $handle, string $password): array
    {
        $session = $this->postJson($service . '/xrpc/com.atproto.server.createSession', [
            'identifier' => $handle,
            'password' => $password,
        ]);
        if (empty($session['accessJwt']) || empty($session['did'])) {
            throw new \RuntimeException('Bluesky login did not return a valid session.');
        }
        return $session;
    }

    public function uploadBlob(string $service, string $accessJwt, string $path, string $mimeType): array
    {
        $body = (string) file_get_contents($path);
        $headers = [
            'Authorization: Bearer ' . $accessJwt,
            'Content-Type: ' . ($mimeType ?: 'application/octet-stream'),
        ];
        $response = $this->request('POST', $service . '/xrpc/com.atproto.repo.uploadBlob', $body, $headers);
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded) || empty($decoded['blob'])) {
            throw new \RuntimeException('Bluesky blob upload failed: ' . mb_substr($response['body'], 0, 1000));
        }
        return $decoded['blob'];
    }

    public function postJson(string $url, array $payload, string $accessJwt = ''): array
    {
        $headers = ['Content-Type: application/json'];
        if ($accessJwt !== '') {
            $headers[] = 'Authorization: Bearer ' . $accessJwt;
        }
        $response = $this->request('POST', $url, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $headers);
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Bluesky returned invalid JSON: HTTP ' . $response['code']);
        }
        return $decoded;
    }

    public function request(string $method, string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_POSTFIELDS => $body,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($response === false || $error) {
            throw new \RuntimeException('Bluesky request failed: ' . $error);
        }
        if ($code >= 400 || $code === 0) {
            $hint = '';
            if (str_contains($url, 'bsky.app')) {
                $hint = ' The configured service URL points to bsky.app, which is the web UI. Use https://bsky.social or your account PDS instead.';
            }
            throw new \RuntimeException('Bluesky returned HTTP ' . $code . ': ' . mb_substr((string) $response, 0, 1000) . $hint);
        }
        return ['code' => $code, 'body' => (string) $response];
    }

    public function trimPostText(string $text): string
    {
        $text = trim($text);
        if (mb_strlen($text, 'UTF-8') <= 300) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, 297, 'UTF-8')) . '…';
    }

    public function attachmentLabel(array $attachment): string
    {
        return TextNormalizer::decode((string) (($attachment['title'] ?? '') ?: ($attachment['filename'] ?? ($attachment['url'] ?? 'unknown file'))));
    }
}
