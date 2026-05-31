<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Service\Channel;

defined('C5_EXECUTE') or die('Access Denied.');

class MastodonSender implements ChannelSenderInterface
{
    public function supports(string $type): bool
    {
        return $type === 'mastodon';
    }

    public function send(array $channel, string $subject, string $bodyHtml, array $attachments = []): string
    {
        $config = $channel['config'] ?? [];
        $instanceUrl = rtrim(trim((string) ($config['instance_url'] ?? ($channel['publicEndpointUrl'] ?? ''))), '/');
        $accessToken = trim((string) ($config['access_token'] ?? ''));
        if ($instanceUrl === '' || $accessToken === '') {
            throw new \RuntimeException('Mastodon instance URL or access token missing.');
        }

        $helper = new LocalMediaHelper();
        $includeExplicitAttachments = !empty($config['include_explicit_attachments']);
        $socialMedia = $helper->buildSocialMediaAttachments($bodyHtml, $attachments, $includeExplicitAttachments);
        $cleanBodyHtml = $helper->removeAllImageTags($bodyHtml);

        $subjectText = TextNormalizer::decode($subject);
        $plain = $this->htmlToText($cleanBodyHtml);
        $status = trim($subjectText . "\n\n" . $plain);
        if ($status === '') {
            $status = $subjectText !== '' ? $subjectText : 'Post';
        }
        $status = $this->trimStatus($status);

        $mediaIds = [];
        $ignoredNonImages = 0;
        foreach ($socialMedia as $attachment) {
            if (!$helper->isImage($attachment)) {
                $ignoredNonImages++;
                continue;
            }
            $path = (string) ($attachment['path'] ?? '');
            if ($path === '' || !is_readable($path)) {
                throw new \RuntimeException('Mastodon media file is not readable: ' . $this->attachmentLabel($attachment));
            }
            $mediaIds[] = $this->uploadMedia($instanceUrl, $accessToken, $attachment);
        }

        $payload = [
            'status' => $status,
            'visibility' => $this->normalizeVisibility((string) ($config['visibility'] ?? 'public')),
            'sensitive' => !empty($config['sensitive']) ? 'true' : 'false',
        ];
        if (!empty($mediaIds)) {
            $payload['media_ids'] = $mediaIds;
        }
        $spoilerText = trim((string) ($config['spoiler_text'] ?? ''));
        if ($spoilerText !== '') {
            $payload['spoiler_text'] = $spoilerText;
        }
        $language = trim((string) ($config['language'] ?? ''));
        if ($language !== '') {
            $payload['language'] = $language;
        }

        $response = $this->requestForm('POST', $instanceUrl . '/api/v1/statuses', $payload, $accessToken);
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Mastodon returned invalid JSON: HTTP ' . $response['code'] . ': ' . mb_substr($response['body'], 0, 1000));
        }
        return sprintf(
            'Mastodon status created: %s. Attached %d body image(s). Explicit attachments: %s. Ignored non-images: %d.',
            (string) ($decoded['url'] ?? ($decoded['uri'] ?? 'ok')),
            count($mediaIds),
            $includeExplicitAttachments ? 'included' : 'ignored',
            $ignoredNonImages
        );
    }

    public function uploadMedia(string $instanceUrl, string $accessToken, array $attachment): string
    {
        $path = (string) ($attachment['path'] ?? '');
        if ($path === '' || !is_readable($path)) {
            throw new \RuntimeException('Mastodon media file is not readable.');
        }
        $mimeType = (string) (($attachment['mimeType'] ?? '') ?: 'application/octet-stream');
        $filename = (string) (($attachment['filename'] ?? '') ?: basename($path));
        $description = trim(TextNormalizer::decode((string) (($attachment['title'] ?? '') ?: $filename)));

        $response = $this->requestMultipartFile(
            $instanceUrl . '/api/v2/media',
            $accessToken,
            'file',
            $path,
            $mimeType,
            $filename,
            $description !== '' ? ['description' => $description] : []
        );
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded) || empty($decoded['id'])) {
            throw new \RuntimeException('Mastodon media upload failed: HTTP ' . $response['code'] . ': ' . mb_substr($response['body'], 0, 1500));
        }
        return (string) $decoded['id'];
    }


    public function requestMultipartFile(string $url, string $accessToken, string $fieldName, string $path, string $mimeType, string $filename, array $fields = []): array
    {
        if ($path === '' || !is_readable($path) || !is_file($path)) {
            throw new \RuntimeException('Mastodon media file is not readable: ' . $path);
        }

        $boundary = '----SocialMediaScheduler' . bin2hex(random_bytes(12));
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="' . addcslashes((string) $name, "\"\\") . '"' . "\r\n\r\n";
            $body .= (string) $value . "\r\n";
        }
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="' . addcslashes($fieldName, "\"\\") . '"; filename="' . addcslashes($filename, "\"\\") . '"' . "\r\n";
        $body .= 'Content-Type: ' . ($mimeType ?: 'application/octet-stream') . "\r\n\r\n";
        $body .= (string) file_get_contents($path) . "\r\n";
        $body .= '--' . $boundary . '--' . "\r\n";

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: multipart/form-data; boundary=' . $boundary,
            'Content-Length: ' . strlen($body),
        ];

        return $this->requestRaw('POST', $url, $body, $headers);
    }

    public function requestRaw(string $method, string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_POSTFIELDS => $body,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        }
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($response === false || $error) {
            throw new \RuntimeException('Mastodon request failed: ' . $error);
        }
        if ($code >= 400 || $code === 0) {
            throw new \RuntimeException('Mastodon returned HTTP ' . $code . ': ' . mb_substr((string) $response, 0, 1500));
        }
        return ['code' => $code, 'body' => (string) $response];
    }

    public function requestForm(string $method, string $url, array $fields, string $accessToken, bool $multipart = false): array
    {
        $headers = ['Authorization: Bearer ' . $accessToken];
        $body = $multipart ? $fields : $this->buildFormBody($fields);
        if (!$multipart) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_POSTFIELDS => $body,
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
            throw new \RuntimeException('Mastodon request failed: ' . $error);
        }
        if ($code >= 400 || $code === 0) {
            throw new \RuntimeException('Mastodon returned HTTP ' . $code . ': ' . mb_substr((string) $response, 0, 1500));
        }
        return ['code' => $code, 'body' => (string) $response];
    }

    public function buildFormBody(array $fields): string
    {
        $pairs = [];
        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $pairs[] = rawurlencode($key . '[]') . '=' . rawurlencode((string) $item);
                }
                continue;
            }
            $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }
        return implode('&', $pairs);
    }

    public function normalizeVisibility(string $visibility): string
    {
        return in_array($visibility, ['public', 'unlisted', 'private', 'direct'], true) ? $visibility : 'public';
    }

    public function htmlToText(string $html): string
    {
        $text = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'], "\n", $html);
        $text = TextNormalizer::decode(strip_tags($text));
        $text = preg_replace('/[ \t]+/', ' ', (string) $text);
        $text = preg_replace('/\n{3,}/', "\n\n", (string) $text);
        return trim((string) $text);
    }

    public function trimStatus(string $text): string
    {
        $text = trim($text);
        if (mb_strlen($text, 'UTF-8') <= 500) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, 497, 'UTF-8')) . '…';
    }

    public function attachmentLabel(array $attachment): string
    {
        return TextNormalizer::decode((string) (($attachment['title'] ?? '') ?: ($attachment['filename'] ?? ($attachment['url'] ?? 'unknown file'))));
    }
}
