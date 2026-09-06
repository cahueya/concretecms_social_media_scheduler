<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Service\Channel;

defined('C5_EXECUTE') or die('Access Denied.');

class ListmonkSender implements ChannelSenderInterface
{

    public function send(array $channel, string $subject, string $bodyHtml, array $attachments = []): string
    {
        $config = $channel['config'] ?? [];
        $subject = TextNormalizer::decode($subject);
        $baseUrl = $this->normalizeBaseUrl((string) ($config['base_url'] ?? ($channel['publicEndpointUrl'] ?? '')));
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $listIds = array_values(array_filter(array_map('intval', (array) ($config['list_ids'] ?? []))));
        if ($baseUrl === '' || $username === '' || $password === '' || empty($listIds)) {
            throw new \RuntimeException('Listmonk URL, credentials or list IDs missing.');
        }

        $helper = new LocalMediaHelper();

        // Important separation:
        // - Images embedded in the rich-text editor are part of the newsletter body.
        //   They are uploaded to Listmonk media and their <img src> is replaced.
        // - Explicitly selected files are real campaign attachments/downloads.
        //   They are uploaded and assigned through the campaign media payload only.
        //   They must not be rendered into the body a second time.
        $editorImages = $helper->extractLocalImages($bodyHtml);
        $explicitAttachments = $helper->dedupeAttachments($attachments);
        $this->assertUploadableAttachments($explicitAttachments);

        $uploadedEditorMedia = $this->uploadAttachments($baseUrl, $username, $password, $editorImages);
        $uploadedAttachmentMedia = $this->uploadAttachments($baseUrl, $username, $password, $explicitAttachments);

        // Replace images that were embedded in the Concrete editor with Listmonk media URLs.
        // This prevents relative /application/files/... URLs from leaking into e-mails.
        $body = $helper->replaceImageSources($bodyHtml, $this->buildUploadedSourceMap($uploadedEditorMedia));

        $mediaIds = $this->extractMediaIds($uploadedAttachmentMedia);
        $uploadedMedia = array_merge($uploadedEditorMedia, $uploadedAttachmentMedia);
        $payload = [
            'name' => $subject,
            'subject' => $subject,
            'lists' => $listIds,
            'type' => (string) ($config['campaign_type'] ?? 'regular'),
            'content_type' => (string) ($config['content_type'] ?? 'richtext'),
            'body' => $body,
        ];
        if (!empty($mediaIds)) {
            // Only explicitly selected attachments go into the campaign media payload.
            // Editor images are already embedded in the body through their replaced <img src>.
            $payload['media'] = $mediaIds;
        }

        foreach (['template_id' => 'template_id', 'from_email' => 'from_email', 'messenger' => 'messenger'] as $cfgKey => $payloadKey) {
            if (($config[$cfgKey] ?? '') !== '') {
                $payload[$payloadKey] = is_numeric($config[$cfgKey]) ? (int) $config[$cfgKey] : (string) $config[$cfgKey];
            }
        }

        $campaign = $this->request('POST', $baseUrl . '/api/campaigns', $username, $password, $payload);
        $id = $campaign['data']['id'] ?? $campaign['id'] ?? null;
        $uploadText = count($uploadedMedia) ? sprintf(' with %d body media file(s), %d attachment file(s) and %d attachment media id(s)', count($uploadedEditorMedia), count($uploadedAttachmentMedia), count($mediaIds)) : '';
        if ($id) {
            $status = (string) ($config['start_status'] ?? 'running');
            if ($status !== 'draft') {
                $this->request('PUT', $baseUrl . '/api/campaigns/' . (int) $id . '/status', $username, $password, ['status' => $status]);
                return 'Listmonk campaign created' . $uploadText . ' and set to ' . $status . ': #' . $id;
            }
            return 'Listmonk draft campaign created' . $uploadText . ': #' . $id;
        }
        return 'Listmonk campaign created' . $uploadText . '.';
    }

    public function uploadAttachments(string $baseUrl, string $username, string $password, array $attachments): array
    {
        $uploaded = [];
        foreach ($attachments as $attachment) {
            $path = (string) ($attachment['path'] ?? '');
            if ($path !== '' && is_readable($path)) {
                $uploaded[] = $this->uploadMedia($baseUrl, $username, $password, $attachment);
            }
        }
        return $uploaded;
    }

    public function assertUploadableAttachments(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            $path = (string) ($attachment['path'] ?? '');
            if ($path === '' || !is_readable($path)) {
                $label = TextNormalizer::decode((string) (($attachment['title'] ?? '') ?: ($attachment['filename'] ?? ($attachment['url'] ?? 'unknown file'))));
                throw new \RuntimeException('Listmonk attachment cannot be uploaded because the local file is not readable: ' . $label);
            }
        }
    }

    public function uploadMedia(string $baseUrl, string $username, string $password, array $attachment): array
    {
        $path = (string) ($attachment['path'] ?? '');
        if ($path === '' || !is_readable($path)) {
            throw new \RuntimeException('Listmonk attachment path is not readable.');
        }
        $mime = (string) ($attachment['mimeType'] ?? '');
        $filename = (string) (($attachment['filename'] ?? '') ?: basename($path));
        $ch = curl_init($baseUrl . '/api/media');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $username . ':' . $password,
            CURLOPT_POSTFIELDS => ['file' => new \CURLFile($path, $mime ?: null, $filename ?: null)],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false || $error) throw new \RuntimeException('Listmonk media upload failed: ' . $error);
        $decoded = json_decode($body, true) ?: [];
        if ($code >= 400) throw new \RuntimeException('Listmonk media upload error: HTTP ' . $code . ' - ' . ($decoded['message'] ?? $body));
        $data = $decoded['data'] ?? $decoded;
        if (!is_array($data)) {
            $data = [];
        }
        $data['_original'] = $attachment;
        $data['_url'] = $this->extractMediaUrl($data, $baseUrl);
        return $data;
    }


    public function extractMediaIds(array $uploadedMedia): array
    {
        $ids = [];
        foreach ($uploadedMedia as $media) {
            if (isset($media['id']) && is_numeric($media['id'])) {
                $ids[] = (int) $media['id'];
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    public function buildUploadedSourceMap(array $uploadedMedia): array
    {
        $map = [];
        foreach ($uploadedMedia as $media) {
            $original = (array) ($media['_original'] ?? []);
            if (($original['source'] ?? '') !== 'editor') {
                continue;
            }
            $src = (string) ($original['url'] ?? '');
            $url = (string) ($media['_url'] ?? '');
            if ($src !== '' && $url !== '') {
                $map[$src] = $url;
            }
        }
        return $map;
    }

    public function extractMediaUrl(array $media, string $baseUrl = ''): string
    {
        foreach (['url', 'full_url', 'public_url', 'uri', 'path'] as $key) {
            if (!empty($media[$key]) && is_string($media[$key])) {
                $url = (string) $media[$key];
                if ($baseUrl !== '' && str_starts_with($url, '/')) {
                    return rtrim($baseUrl, '/') . $url;
                }
                return $url;
            }
        }
        return '';
    }

    public function normalizeBaseUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        if (str_ends_with($url, '/api')) {
            $url = substr($url, 0, -4);
        }
        return $url;
    }

    public function request(string $method, string $url, string $username, string $password, array $payload): array
    {
        $ch = curl_init($url);
        $method = strtoupper($method);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_USERPWD => $username . ':' . $password,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false || $error) throw new \RuntimeException('Listmonk request failed: ' . $error);
        $decoded = json_decode($body, true) ?: [];
        if ($code >= 400) throw new \RuntimeException('Listmonk API error: HTTP ' . $code . ' - ' . ($decoded['message'] ?? $body));
        return $decoded;
    }
}
