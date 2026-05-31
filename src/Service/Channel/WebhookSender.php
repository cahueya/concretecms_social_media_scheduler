<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Service\Channel;

defined('C5_EXECUTE') or die('Access Denied.');

class WebhookSender implements ChannelSenderInterface
{
    public function supports(string $type): bool
    {
        return $type === 'webhook';
    }

    public function send(array $channel, string $subject, string $bodyHtml, array $attachments = []): string
    {
        $config = $channel['config'] ?? [];
        $url = trim((string) ($config['url'] ?? ($channel['publicEndpointUrl'] ?? '')));
        if ($url === '') {
            throw new \RuntimeException('Webhook URL missing.');
        }

        $method = strtoupper((string) ($config['method'] ?? 'POST'));
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $method = 'POST';
        }
        $payloadMode = (string) ($config['payload_mode'] ?? 'json');
        if (!in_array($payloadMode, ['json', 'form', 'multipart'], true)) {
            $payloadMode = 'json';
        }
        $attachmentMode = (string) ($config['attachment_mode'] ?? 'urls');
        if (!in_array($attachmentMode, ['urls', 'base64', 'multipart'], true)) {
            $attachmentMode = 'urls';
        }

        $plain = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml)), ENT_QUOTES, 'UTF-8'));
        $payload = [
            'posting' => [
                'title' => (string) ($channel['_posting_title'] ?? ''),
                'subject' => $subject,
                'content_html' => $bodyHtml,
                'content_text' => $plain,
            ],
            'attachments' => $this->buildAttachmentPayload($attachments, $attachmentMode),
            'source' => [
                'system' => 'concretecms',
                'package' => 'social_media_scheduler',
            ],
        ];

        $headers = $this->normalizeHeaders((array) ($config['headers'] ?? []));
        $headers = $this->applyAuthenticationHeaders($headers, $config);

        [$body, $headers, $isMultipart] = $this->prepareRequestBody($payload, $attachments, $payloadMode, $attachmentMode, $headers);

        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_POSTFIELDS => $body,
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            unset($options[CURLOPT_CUSTOMREQUEST]);
        }
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response === false || $error) {
            throw new \RuntimeException('Webhook request failed: ' . $error);
        }
        if ($code >= 400 || $code === 0) {
            throw new \RuntimeException('Webhook returned HTTP ' . $code . ': ' . mb_substr((string) $response, 0, 1000));
        }
        return 'Webhook delivered: HTTP ' . $code;
    }


    public function applyAuthenticationHeaders(array $headers, array $config): array
    {
        if ($this->hasHeader($headers, 'Authorization')) {
            return $headers;
        }

        $authType = (string) ($config['auth_type'] ?? 'none');
        if ($authType === '' || $authType === 'token') {
            $authType = 'none';
        }

        if ($authType === 'bearer') {
            $authToken = trim((string) ($config['auth_token'] ?? ''));
            if ($authToken !== '') {
                $headers[] = 'Authorization: Bearer ' . $authToken;
            }
            return $headers;
        }

        if ($authType === 'basic') {
            $username = trim((string) ($config['basic_username'] ?? ''));
            $password = (string) ($config['basic_password'] ?? '');
            if ($username !== '' && $password !== '') {
                $headers[] = 'Authorization: Basic ' . base64_encode($username . ':' . $password);
            }
            return $headers;
        }

        // Backwards compatibility with v0.5.0/v0.5.1 configs that only stored auth_token.
        $legacyToken = trim((string) ($config['auth_token'] ?? ''));
        if ($legacyToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $legacyToken;
        }

        return $headers;
    }

    public function buildAttachmentPayload(array $attachments, string $mode): array
    {
        $out = [];
        foreach ($attachments as $attachment) {
            $item = [
                'filename' => (string) ($attachment['filename'] ?? ''),
                'title' => (string) ($attachment['title'] ?? ''),
                'mime_type' => (string) ($attachment['mimeType'] ?? ''),
                'size' => (int) ($attachment['size'] ?? 0),
                'url' => (string) ($attachment['url'] ?? ''),
            ];
            if ($mode === 'base64') {
                $path = (string) ($attachment['path'] ?? '');
                if ($path !== '' && is_readable($path)) {
                    $item['base64'] = base64_encode((string) file_get_contents($path));
                }
            }
            $out[] = $item;
        }
        return $out;
    }

    public function prepareRequestBody(array $payload, array $attachments, string $payloadMode, string $attachmentMode, array $headers): array
    {
        if ($payloadMode === 'json') {
            $headers[] = 'Content-Type: application/json';
            return [json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $headers, false];
        }

        if ($payloadMode === 'multipart' || $attachmentMode === 'multipart') {
            $fields = ['payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
            $i = 0;
            foreach ($attachments as $attachment) {
                $path = (string) ($attachment['path'] ?? '');
                if ($path !== '' && is_readable($path)) {
                    $fields['file' . $i] = new \CURLFile($path, (string) ($attachment['mimeType'] ?? ''), (string) (($attachment['filename'] ?? '') ?: basename($path)));
                    $i++;
                }
            }
            return [$fields, $headers, true];
        }

        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        return [http_build_query(['payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]), $headers, false];
    }

    public function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                $line = trim((string) $value);
                if ($line !== '') {
                    $out[] = $line;
                }
                continue;
            }
            $name = trim((string) $name);
            if ($name !== '') {
                $out[] = $name . ': ' . trim((string) $value);
            }
        }
        return $out;
    }

    public function hasHeader(array $headers, string $name): bool
    {
        foreach ($headers as $header) {
            if (stripos((string) $header, $name . ':') === 0) {
                return true;
            }
        }
        return false;
    }
}
