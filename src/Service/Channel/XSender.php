<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Service\Channel;

defined('C5_EXECUTE') or die('Access Denied.');

class XSender implements ChannelSenderInterface
{
    public const MAX_POST_CHARS = 280;
    public const MAX_IMAGES = 4;
    public const MAX_IMAGE_BYTES = 5000000;


    public function send(array $channel, string $subject, string $bodyHtml, array $attachments = []): string
    {
        $config = $channel['config'] ?? [];
        $apiKey = trim((string) ($config['api_key'] ?? ''));
        $apiSecret = (string) ($config['api_secret'] ?? '');
        $accessToken = trim((string) ($config['access_token'] ?? ''));
        $accessTokenSecret = (string) ($config['access_token_secret'] ?? '');
        $apiBaseUrl = rtrim(trim((string) ($config['api_base_url'] ?? 'https://api.x.com')), '/');
        $uploadBaseUrl = rtrim(trim((string) ($config['upload_base_url'] ?? 'https://upload.twitter.com')), '/');

        if ($apiKey === '' || $apiSecret === '' || $accessToken === '' || $accessTokenSecret === '') {
            throw new \RuntimeException('X API key, API secret, access token or access token secret missing. Use OAuth 1.0a user context credentials with read/write permissions.');
        }
        if ($apiBaseUrl === '') {
            $apiBaseUrl = 'https://api.x.com';
        }
        if ($uploadBaseUrl === '') {
            $uploadBaseUrl = 'https://upload.twitter.com';
        }

        $helper = new LocalMediaHelper();
        $includeExplicitAttachments = !empty($config['include_explicit_attachments']);
        $socialMedia = $helper->buildSocialMediaAttachments($bodyHtml, $attachments, $includeExplicitAttachments);
        $cleanBodyHtml = $helper->removeAllImageTags($bodyHtml);

        $plain = TextNormalizer::htmlToText($cleanBodyHtml);
        $text = $this->trimPostText(trim($subject . "\n\n" . $plain));
        if ($text === '') {
            $text = $this->trimPostText($subject !== '' ? $subject : 'Post');
        }

        $mediaIds = [];
        $ignoredNonImages = 0;
        $ignoredOverLimit = 0;
        foreach ($socialMedia as $attachment) {
            if (count($mediaIds) >= self::MAX_IMAGES) {
                break;
            }
            if (!$helper->isImage($attachment)) {
                $ignoredNonImages++;
                continue;
            }
            $path = (string) ($attachment['path'] ?? '');
            if ($path === '' || !is_readable($path)) {
                throw new \RuntimeException('X image cannot be uploaded because the local file is not readable: ' . $this->attachmentLabel($attachment));
            }
            $size = (int) (($attachment['size'] ?? 0) ?: filesize($path));
            if ($size > self::MAX_IMAGE_BYTES) {
                $ignoredOverLimit++;
                throw new \RuntimeException(sprintf('X image is larger than the API image upload limit of %d bytes: %s (%d bytes). Please resize/compress it before posting.', self::MAX_IMAGE_BYTES, $this->attachmentLabel($attachment), $size));
            }
            $mediaIds[] = $this->uploadMedia($uploadBaseUrl, $attachment, $apiKey, $apiSecret, $accessToken, $accessTokenSecret);
        }

        $payload = ['text' => $text];
        if (!empty($mediaIds)) {
            $payload['media'] = ['media_ids' => $mediaIds];
        }
        $response = $this->requestJson('POST', $apiBaseUrl . '/2/tweets', $payload, $apiKey, $apiSecret, $accessToken, $accessTokenSecret);
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded) || empty($decoded['data']['id'])) {
            throw new \RuntimeException('X returned invalid post response: HTTP ' . $response['code'] . ': ' . mb_substr($response['body'], 0, 1500));
        }
        return sprintf(
            'X post created: %s. Attached %d body image(s). Explicit attachments: %s. Ignored non-images: %d. Ignored oversized images before failure: %d.',
            (string) $decoded['data']['id'],
            count($mediaIds),
            $includeExplicitAttachments ? 'included' : 'ignored',
            $ignoredNonImages,
            $ignoredOverLimit
        );
    }

    public function uploadMedia(string $uploadBaseUrl, array $attachment, string $apiKey, string $apiSecret, string $accessToken, string $accessTokenSecret): string
    {
        $path = (string) ($attachment['path'] ?? '');
        if ($path === '' || !is_readable($path)) {
            throw new \RuntimeException('X media file is not readable.');
        }
        $mimeType = (string) (($attachment['mimeType'] ?? '') ?: 'application/octet-stream');
        $filename = (string) (($attachment['filename'] ?? '') ?: basename($path));
        $url = $uploadBaseUrl . '/1.1/media/upload.json';
        $fields = ['media' => new \CURLFile($path, $mimeType, $filename)];
        $response = $this->requestMultipart('POST', $url, $fields, $apiKey, $apiSecret, $accessToken, $accessTokenSecret);
        $decoded = json_decode($response['body'], true);
        $mediaId = (string) ($decoded['media_id_string'] ?? ($decoded['media_id'] ?? ''));
        if ($mediaId === '') {
            throw new \RuntimeException('X media upload failed: HTTP ' . $response['code'] . ': ' . mb_substr($response['body'], 0, 1500));
        }
        return $mediaId;
    }

    public function requestJson(string $method, string $url, array $payload, string $apiKey, string $apiSecret, string $accessToken, string $accessTokenSecret): array
    {
        $headers = [
            'Content-Type: application/json',
            $this->buildOAuthHeader($method, $url, $apiKey, $apiSecret, $accessToken, $accessTokenSecret),
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($response === false || $error) {
            throw new \RuntimeException('X request failed: ' . $error);
        }
        if ($code >= 400 || $code === 0) {
            throw new \RuntimeException('X returned HTTP ' . $code . ': ' . mb_substr((string) $response, 0, 1500));
        }
        return ['code' => $code, 'body' => (string) $response];
    }

    public function requestMultipart(string $method, string $url, array $fields, string $apiKey, string $apiSecret, string $accessToken, string $accessTokenSecret): array
    {
        $headers = [$this->buildOAuthHeader($method, $url, $apiKey, $apiSecret, $accessToken, $accessTokenSecret)];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_POSTFIELDS => $fields,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, null);
        }
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($response === false || $error) {
            throw new \RuntimeException('X media upload request failed: ' . $error);
        }
        if ($code >= 400 || $code === 0) {
            throw new \RuntimeException('X media upload returned HTTP ' . $code . ': ' . mb_substr((string) $response, 0, 1500));
        }
        return ['code' => $code, 'body' => (string) $response];
    }

    public function buildOAuthHeader(string $method, string $url, string $apiKey, string $apiSecret, string $accessToken, string $accessTokenSecret): string
    {
        $oauth = [
            'oauth_consumer_key' => $apiKey,
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_token' => $accessToken,
            'oauth_version' => '1.0',
        ];
        $signatureParams = $oauth;
        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            parse_str($query, $queryParams);
            foreach ($queryParams as $key => $value) {
                $signatureParams[(string) $key] = $value;
            }
        }
        $baseUrl = $this->baseUrlForSignature($url);
        $baseString = strtoupper($method) . '&' . rawurlencode($baseUrl) . '&' . rawurlencode($this->buildParameterString($signatureParams));
        $signingKey = rawurlencode($apiSecret) . '&' . rawurlencode($accessTokenSecret);
        $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

        $parts = [];
        foreach ($oauth as $key => $value) {
            $parts[] = rawurlencode((string) $key) . '="' . rawurlencode((string) $value) . '"';
        }
        return 'Authorization: OAuth ' . implode(', ', $parts);
    }

    public function baseUrlForSignature(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        if (($scheme === 'https' && $port === ':443') || ($scheme === 'http' && $port === ':80')) {
            $port = '';
        }
        $path = (string) ($parts['path'] ?? '/');
        return $scheme . '://' . $host . $port . $path;
    }

    public function buildParameterString(array $params): string
    {
        $pairs = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $pairs[] = [rawurlencode((string) $key), rawurlencode((string) $item)];
                }
                continue;
            }
            $pairs[] = [rawurlencode((string) $key), rawurlencode((string) $value)];
        }
        usort($pairs, static fn(array $a, array $b): int => $a[0] === $b[0] ? strcmp($a[1], $b[1]) : strcmp($a[0], $b[0]));
        return implode('&', array_map(static fn(array $pair): string => $pair[0] . '=' . $pair[1], $pairs));
    }

    public function trimPostText(string $text): string
    {
        $text = trim($text);
        if (mb_strlen($text, 'UTF-8') <= self::MAX_POST_CHARS) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, self::MAX_POST_CHARS - 1, 'UTF-8')) . '…';
    }

    public function attachmentLabel(array $attachment): string
    {
        return (string) (($attachment['title'] ?? '') ?: ($attachment['filename'] ?? ($attachment['url'] ?? 'unknown file')));
    }
}
