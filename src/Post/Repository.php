<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Post;

use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Editor\LinkAbstractor;
use Concrete\Core\File\File;
use Concrete\Package\SocialMediaScheduler\Src\Entity\Channel;
use Concrete\Package\SocialMediaScheduler\Src\Entity\Posting;
use Concrete\Package\SocialMediaScheduler\Src\Entity\PostingChannel;
use Concrete\Package\SocialMediaScheduler\Src\Entity\SendLog;
use Concrete\Package\SocialMediaScheduler\Src\Util\Crypto;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;

\defined('C5_EXECUTE') or die('Access Denied.');

class Repository
{
    private EntityManagerInterface $em;

    public function __construct(private Connection $db, private Crypto $crypto, ?EntityManagerInterface $em = null)
    {
        if ($em instanceof EntityManagerInterface) {
            $this->em = $em;
        } else {
            $this->em = \Core::make(EntityManagerInterface::class);
        }
    }

    public function getChannels(bool $enabledOnly = false): array
    {
        $criteria = $enabledOnly ? ['isEnabled' => true] : [];
        $channels = $this->em->getRepository(Channel::class)->findBy($criteria, ['channelType' => 'ASC', 'channelName' => 'ASC']);
        return array_map(fn (Channel $channel) => $this->hydrateChannel($this->channelToRow($channel)), $channels);
    }

    public function getChannel(int $id): ?array
    {
        $channel = $this->em->find(Channel::class, $id);
        return $channel instanceof Channel ? $this->hydrateChannel($this->channelToRow($channel)) : null;
    }

    public function saveChannel(string $type, string $name, array $config, bool $enabled = true, ?int $id = null): int
    {
        $now = $this->newDateTime();
        $channel = $id ? $this->em->find(Channel::class, $id) : null;
        if (!$channel instanceof Channel) {
            $channel = new Channel();
            $channel->setDateCreated($now);
            $this->em->persist($channel);
        } elseif (!empty($this->hydrateChannel($this->channelToRow($channel))['config'])) {
            $existing = $this->hydrateChannel($this->channelToRow($channel));
            $config = $this->mergeSecretConfig((string) $type, $existing['config'], $config);
        }

        $channel
            ->setChannelType($type)
            ->setChannelName($name)
            ->setIsEnabled($enabled)
            ->setConfigJson($this->crypto->encryptArray($config))
            ->setPublicEndpointUrl($this->extractPublicEndpointUrl($type, $config))
            ->setDateUpdated($now);

        $this->em->flush();
        return (int) $channel->getId();
    }

    public function deleteChannel(int $id): void
    {
        $this->db->delete('SocialMediaSchedulerPostingChannels', ['channelID' => $id]);
        $channel = $this->em->find(Channel::class, $id);
        if ($channel instanceof Channel) {
            $this->em->remove($channel);
            $this->em->flush();
        }
    }

    public function savePosting(
        string $title,
        string $subject,
        string $bodyHtml,
        string $startAt,
        ?string $endAt,
        int $repeatDays,
        array $channelIDs,
        ?int $id = null,
        string $timezone = '',
        array $attachmentFileIDs = [],
        int $maxAttempts = 3,
        int $retryDelayMinutes = 30
    ): int {
        $now = $this->newDateTime();
        $timezone = $this->normalizeTimezone($timezone);
        $startAtServer = $this->convertToServerDateTime($startAt, $timezone);
        $endAtServer = trim((string) $endAt) !== '' ? $this->convertToServerDateTime((string) $endAt, $timezone) : null;
        $attachmentFileIDs = array_values(array_filter(array_unique(array_map('intval', $attachmentFileIDs))));

        $posting = $id ? $this->em->find(Posting::class, $id) : null;
        if (!$posting instanceof Posting) {
            $posting = new Posting();
            $posting->setDateCreated($now);
            $posting->setIsEnabled(true);
            $this->em->persist($posting);
        }

        $posting
            ->setTitle($title !== '' ? $title : $subject)
            ->setSubject($subject)
            ->setBodyHtml(LinkAbstractor::translateTo($bodyHtml))
            ->setStartAt($startAtServer)
            ->setEndAt($endAtServer)
            ->setNextRunAt($startAtServer)
            ->setRepeatEveryDays(max(0, $repeatDays))
            ->setTimezone($timezone)
            ->setAttachmentFileIDs(json_encode($attachmentFileIDs))
            ->setMaxAttempts(max(1, $maxAttempts))
            ->setRetryDelayMinutes(max(1, $retryDelayMinutes))
            ->setDateUpdated($now);

        if (!$id) {
            $posting->setRetryCount(0);
        }

        $this->em->flush();
        $postingID = (int) $posting->getId();
        $this->replacePostingChannels($postingID, $channelIDs);
        return $postingID;
    }

    public function deletePosting(int $id): void
    {
        $this->db->delete('SocialMediaSchedulerPostingChannels', ['postingID' => $id]);
        $this->db->delete('SocialMediaSchedulerLog', ['postingID' => $id]);
        $posting = $this->em->find(Posting::class, $id);
        if ($posting instanceof Posting) {
            $this->em->remove($posting);
            $this->em->flush();
        }
    }

    public function setPostingEnabled(int $id, bool $enabled): void
    {
        $posting = $this->em->find(Posting::class, $id);
        if ($posting instanceof Posting) {
            $posting->setIsEnabled($enabled)->setDateUpdated($this->newDateTime());
            $this->em->flush();
        }
    }

    public function getPostings(): array
    {
        $postings = $this->em->getRepository(Posting::class)->findBy([], ['nextRunAt' => 'ASC', 'id' => 'DESC']);
        return array_map(fn (Posting $posting) => $this->hydratePosting($this->postingToRow($posting), false), $postings);
    }

    public function getPosting(int $id, bool $enabledChannelsOnly = false): ?array
    {
        $posting = $this->em->find(Posting::class, $id);
        return $posting instanceof Posting ? $this->hydratePosting($this->postingToRow($posting), $enabledChannelsOnly) : null;
    }

    public function getDuePostings(): array
    {
        $qb = $this->em->createQueryBuilder();
        $postings = $qb->select('p')
            ->from(Posting::class, 'p')
            ->where('p.isEnabled = :enabled')
            ->andWhere('p.nextRunAt <= :now')
            ->andWhere('(p.endAt IS NULL OR p.endAt >= :now)')
            ->setParameter('enabled', true)
            ->setParameter('now', $this->newDateTime())
            ->orderBy('p.nextRunAt', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn (Posting $posting) => $this->hydratePosting($this->postingToRow($posting), true), $postings);
    }

    public function markPostingRun(int $postingID, int $repeatDays): void
    {
        $posting = $this->em->find(Posting::class, $postingID);
        if (!$posting instanceof Posting) {
            return;
        }
        $now = $this->newDateTime();
        $endAt = $posting->getEndAt();
        if ($repeatDays > 0) {
            $next = $now->modify('+' . $repeatDays . ' days');
            if ($endAt instanceof DateTimeInterface && $next > $endAt) {
                $posting->setLastRunAt($now)->setIsEnabled(false)->setRetryCount(0)->setDateUpdated($now);
            } else {
                $posting->setLastRunAt($now)->setNextRunAt($next)->setRetryCount(0)->setDateUpdated($now);
            }
        } else {
            $posting->setLastRunAt($now)->setIsEnabled(false)->setRetryCount(0)->setDateUpdated($now);
        }
        $this->em->flush();
    }

    public function scheduleRetry(int $postingID, int $retryDelayMinutes): void
    {
        $posting = $this->em->find(Posting::class, $postingID);
        if (!$posting instanceof Posting) {
            return;
        }
        $now = $this->newDateTime();
        $next = $now->modify('+' . max(1, $retryDelayMinutes) . ' minutes');
        $endAt = $posting->getEndAt();
        if ($endAt instanceof DateTimeInterface && $next > $endAt) {
            $posting->incrementRetryCount()->setIsEnabled(false)->setDateUpdated($now);
        } else {
            $posting->incrementRetryCount()->setNextRunAt($next)->setDateUpdated($now);
        }
        $this->em->flush();
    }

    public function log(int $postingID, int $channelID, string $status, string $message, int $attempt = 1): void
    {
        $log = new SendLog();
        $log->setPostingID($postingID)
            ->setChannelID($channelID)
            ->setStatus($status)
            ->setAttempt(max(1, $attempt))
            ->setMessage(mb_substr($message, 0, 5000))
            ->setDateCreated($this->newDateTime());
        $this->em->persist($log);
        $this->em->flush();
    }

    public function getLogs(int $limit = 100, array $filters = []): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['postingID'])) {
            $where[] = 'l.postingID = ?';
            $params[] = (int) $filters['postingID'];
        }
        if (!empty($filters['channelID'])) {
            $where[] = 'l.channelID = ?';
            $params[] = (int) $filters['channelID'];
        }
        if (!empty($filters['status']) && in_array((string) $filters['status'], ['success', 'error', 'manual_success', 'manual_error'], true)) {
            $where[] = 'l.status = ?';
            $params[] = (string) $filters['status'];
        }
        $sql = 'SELECT l.*, p.title, p.subject, c.channelName, c.channelType FROM SocialMediaSchedulerLog l LEFT JOIN SocialMediaSchedulerPostings p ON p.id = l.postingID LEFT JOIN SocialMediaSchedulerChannels c ON c.id = l.channelID';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY l.dateCreated DESC, l.id DESC LIMIT ' . max(1, min(1000, $limit));
        return $this->db->fetchAllAssociative($sql, $params);
    }

    public function flushLogs(): void
    {
        $this->db->executeStatement('DELETE FROM SocialMediaSchedulerLog');
        $this->em->clear(SendLog::class);
    }

    public function buildChannelPreview(array $posting, array $channel): array
    {
        $subject = (string) ($posting['subject'] ?? '');
        $bodyHtml = (string) ($posting['bodyHtml'] ?? '');
        $plainBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml)));
        $config = $channel['config'] ?? [];
        $type = (string) ($channel['channelType'] ?? '');
        $label = (string) ($channel['channelName'] ?? $type);
        if ($type === 'listmonk') {
            return ['channel' => $label, 'title' => $subject, 'format' => 'Email HTML campaign', 'body' => $bodyHtml];
        }
        if ($type === 'telegram') {
            $parseMode = (string) ($config['parse_mode'] ?? 'plain');
            $first = $parseMode === 'HTML' ? '<strong>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</strong>' : $subject;
            return ['channel' => $label, 'title' => $subject, 'format' => 'Telegram ' . $parseMode, 'body' => $parseMode === 'HTML' ? $first . '<br><br>' . $bodyHtml : nl2br(htmlspecialchars(trim($subject) . "\n\n" . $plainBody, ENT_QUOTES, 'UTF-8'))];
        }
        if ($type === 'matrix') {
            return ['channel' => $label, 'title' => $subject, 'format' => (string) ($config['msgtype'] ?? 'm.text') . ' with HTML fallback and body media events', 'body' => '<strong>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</strong><br><br>' . $bodyHtml];
        }
        if ($type === 'webhook') {
            return ['channel' => $label, 'title' => $subject, 'format' => 'Generic Webhook ' . (string) ($config['method'] ?? 'POST') . ' / ' . (string) ($config['payload_mode'] ?? 'json'), 'body' => '<pre>' . htmlspecialchars(json_encode(['title' => $posting['title'] ?? '', 'subject' => $subject, 'content_html' => $bodyHtml, 'attachments' => $posting['attachments'] ?? []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '</pre>'];
        }
        if ($type === 'bluesky') {
            return ['channel' => $label, 'title' => $subject, 'format' => 'Bluesky post with body image embeds (explicit attachments ignored by default)', 'body' => nl2br(htmlspecialchars(trim($subject . "\n\n" . $plainBody), ENT_QUOTES, 'UTF-8'))];
        }
        if ($type === 'mastodon') {
            return ['channel' => $label, 'title' => $subject, 'format' => 'Mastodon status (' . (string) ($config['visibility'] ?? 'public') . ') with body image media (explicit attachments ignored by default)', 'body' => nl2br(htmlspecialchars(trim($subject . "\n\n" . $plainBody), ENT_QUOTES, 'UTF-8'))];
        }
        if ($type === 'x' || $type === 'twitter') {
            return ['channel' => $label, 'title' => $subject, 'format' => 'X/Twitter post with body image media (explicit attachments ignored by default)', 'body' => nl2br(htmlspecialchars(trim($subject . "\n\n" . $plainBody), ENT_QUOTES, 'UTF-8'))];
        }
        return ['channel' => $label, 'title' => $subject, 'format' => $type, 'body' => $bodyHtml];
    }

    public function extractPublicEndpointUrl(string $type, array $config): ?string
    {
        $key = match ($type) {
            'listmonk' => 'base_url',
            'matrix' => 'homeserver',
            'webhook' => 'url',
            'bluesky' => 'service_url',
            'mastodon' => 'instance_url',
            'x', 'twitter' => 'api_base_url',
            default => null,
        };
        if ($key === null) {
            return null;
        }
        $url = trim((string) ($config[$key] ?? ''));
        return $url !== '' ? $url : null;
    }

    public function hydrateChannel(array $row): array
    {
        $row['config'] = $this->crypto->decryptArray($row['configJson'] ?? null);
        $row['publicEndpointUrl'] = ($row['publicEndpointUrl'] ?? '') ?: $this->extractPublicEndpointUrl((string) ($row['channelType'] ?? ''), $row['config']);
        return $row;
    }

    public function hydratePosting(array $row, bool $enabledChannelsOnly): array
    {
        $row['title'] = trim((string) ($row['title'] ?? '')) !== '' ? (string) $row['title'] : (string) ($row['subject'] ?? '');
        $row['bodyHtml'] = LinkAbstractor::translateFrom((string) ($row['bodyHtml'] ?? ''));
        $row['channels'] = $this->getChannelsForPosting((int) $row['id'], $enabledChannelsOnly);
        $row['attachmentFileIDsArray'] = $this->decodeAttachmentIDs($row['attachmentFileIDs'] ?? null);
        $row['attachments'] = $this->getAttachments($row['attachmentFileIDsArray']);
        $row['previews'] = [];
        foreach ($row['channels'] as $channel) {
            $row['previews'][] = $this->buildChannelPreview($row, $channel);
        }
        return $row;
    }

    public function replacePostingChannels(int $postingID, array $channelIDs): void
    {
        $this->db->delete('SocialMediaSchedulerPostingChannels', ['postingID' => $postingID]);
        foreach (array_unique(array_map('intval', $channelIDs)) as $channelID) {
            if ($channelID > 0) {
                $postingChannel = new PostingChannel($postingID, $channelID);
                $this->em->persist($postingChannel);
            }
        }
        $this->em->flush();
    }

    public function getChannelsForPosting(int $postingID, bool $enabledOnly = false): array
    {
        $links = $this->em->getRepository(PostingChannel::class)->findBy(['postingID' => $postingID]);
        $rows = [];
        foreach ($links as $link) {
            if (!$link instanceof PostingChannel) {
                continue;
            }
            $channel = $this->em->find(Channel::class, $link->getChannelID());
            if (!$channel instanceof Channel) {
                continue;
            }
            if ($enabledOnly && !$channel->isEnabled()) {
                continue;
            }
            $rows[] = $this->hydrateChannel($this->channelToRow($channel));
        }
        usort($rows, static fn (array $a, array $b) => [$a['channelType'], $a['channelName']] <=> [$b['channelType'], $b['channelName']]);
        return $rows;
    }

    public function decodeAttachmentIDs(?string $value): array
    {
        $decoded = json_decode((string) $value, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('intval', $decoded)));
        }
        return array_values(array_filter(array_map('intval', explode(',', (string) $value))));
    }

    public function getAttachments(array $fileIDs): array
    {
        $attachments = [];
        foreach ($fileIDs as $fileID) {
            try {
                $file = File::getByID((int) $fileID);
                if (!is_object($file) || $file->isError()) continue;
                $version = $file->getApprovedVersion();
                if (!is_object($version)) continue;
                $url = method_exists($version, 'getURL') ? (string) $version->getURL() : '';
                $path = $this->resolveFileVersionPath($version, $file);
                if ((!$path || !is_readable($path)) && $url !== '') {
                    $path = $this->resolvePublicUrlToLocalPath($url);
                }
                $mimeType = $this->resolveFileVersionMimeType($version, $path);
                $size = $path && is_readable($path) ? (int) filesize($path) : 0;
                $attachments[] = ['id' => (int) $fileID, 'title' => method_exists($version, 'getTitle') ? $version->getTitle() : '', 'filename' => method_exists($version, 'getFilename') ? $version->getFilename() : ($path ? basename($path) : ''), 'url' => $url, 'path' => $path, 'mimeType' => $mimeType, 'size' => $size];
            } catch (\Throwable) {}
        }
        return $attachments;
    }

    public function resolveFileVersionPath($version, $file): ?string
    {
        foreach ([$version, $file] as $object) {
            if (is_object($object) && method_exists($object, 'getPath')) {
                try {
                    $path = (string) $object->getPath();
                    if ($path !== '' && is_readable($path)) return $path;
                } catch (\Throwable) {}
            }
        }
        foreach ([$version, $file] as $object) {
            if (!is_object($object) || !method_exists($object, 'getFileResource')) continue;
            try {
                $resource = $object->getFileResource();
                if (is_object($resource)) {
                    foreach (['getPath', 'getFilePath'] as $method) {
                        if (method_exists($resource, $method)) {
                            $path = (string) $resource->{$method}();
                            if ($path !== '' && is_readable($path)) return $path;
                        }
                    }
                }
            } catch (\Throwable) {}
        }
        return null;
    }

    public function resolvePublicUrlToLocalPath(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
        if ($url === '') return null;
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') $path = $url;
        $path = rawurldecode($path);
        $candidates = [];
        if (defined('DIR_BASE') && str_starts_with($path, '/')) $candidates[] = rtrim((string) DIR_BASE, '/') . $path;
        if (defined('DIR_BASE')) $candidates[] = rtrim((string) DIR_BASE, '/') . '/' . ltrim($path, '/');
        $docRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($docRoot !== '' && str_starts_with($path, '/')) $candidates[] = rtrim($docRoot, '/') . $path;
        foreach (array_unique($candidates) as $candidate) {
            if ($candidate !== '' && is_readable($candidate) && is_file($candidate)) return $candidate;
        }
        return null;
    }

    public function resolveFileVersionMimeType($version, ?string $path): string
    {
        foreach (['getMimeType', 'getMimetype'] as $method) {
            if (is_object($version) && method_exists($version, $method)) {
                try {
                    $mime = (string) $version->{$method}();
                    if ($mime !== '') return $mime;
                } catch (\Throwable) {}
            }
        }
        if ($path && is_readable($path) && function_exists('mime_content_type')) {
            $mime = (string) @mime_content_type($path);
            if ($mime !== '') return $mime;
        }
        return 'application/octet-stream';
    }

    public function convertToServerTime(string $value, string $timezone): string
    {
        return $this->convertToServerDateTime($value, $timezone)->format('Y-m-d H:i:s');
    }

    public function convertToServerDateTime(string $value, string $timezone): DateTimeImmutable
    {
        $value = str_replace('T', ' ', trim($value));
        if (strlen($value) === 16) $value .= ':00';
        try {
            return (new DateTimeImmutable($value, new DateTimeZone($timezone)))
                ->setTimezone(new DateTimeZone(date_default_timezone_get()));
        } catch (\Throwable) {
            return $this->newDateTime($value ?: 'now');
        }
    }

    public function normalizeTimezone(string $timezone): string
    {
        try {
            return (new DateTimeZone($timezone ?: date_default_timezone_get()))->getName();
        } catch (\Throwable) {
            return date_default_timezone_get();
        }
    }

    public function mergeSecretConfig(string $type, array $old, array $new): array
    {
        $secretKeys = match ($type) {
            'telegram' => ['bot_token'],
            'listmonk' => ['password'],
            'matrix' => ['access_token'],
            'webhook' => ['auth_token', 'basic_username', 'basic_password'],
            'bluesky' => ['app_password'],
            'mastodon' => ['access_token'],
            'x', 'twitter' => ['api_secret', 'access_token', 'access_token_secret'],
            default => [],
        };
        foreach ($secretKeys as $key) {
            if (($new[$key] ?? '') === '' && ($old[$key] ?? '') !== '') {
                $new[$key] = $old[$key];
            }
        }
        return $new;
    }

    public function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public function newDateTime(string $value = 'now'): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone(date_default_timezone_get()));
    }

    public function formatDate(?DateTimeInterface $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d H:i:s') : null;
    }

    public function channelToRow(Channel $channel): array
    {
        return [
            'id' => $channel->getId(),
            'channelType' => $channel->getChannelType(),
            'channelName' => $channel->getChannelName(),
            'isEnabled' => $channel->isEnabled() ? 1 : 0,
            'configJson' => $channel->getConfigJson(),
            'publicEndpointUrl' => $channel->getPublicEndpointUrl(),
            'dateCreated' => $this->formatDate($channel->getDateCreated()),
            'dateUpdated' => $this->formatDate($channel->getDateUpdated()),
        ];
    }

    public function postingToRow(Posting $posting): array
    {
        return [
            'id' => $posting->getId(),
            'title' => $posting->getTitle(),
            'subject' => $posting->getSubject(),
            'bodyHtml' => $posting->getBodyHtml(),
            'startAt' => $this->formatDate($posting->getStartAt()),
            'nextRunAt' => $this->formatDate($posting->getNextRunAt()),
            'endAt' => $this->formatDate($posting->getEndAt()),
            'repeatEveryDays' => $posting->getRepeatEveryDays(),
            'timezone' => $posting->getTimezone(),
            'attachmentFileIDs' => $posting->getAttachmentFileIDs(),
            'maxAttempts' => $posting->getMaxAttempts(),
            'retryDelayMinutes' => $posting->getRetryDelayMinutes(),
            'retryCount' => $posting->getRetryCount(),
            'isEnabled' => $posting->isEnabled() ? 1 : 0,
            'dateCreated' => $this->formatDate($posting->getDateCreated()),
            'dateUpdated' => $this->formatDate($posting->getDateUpdated()),
            'lastRunAt' => $this->formatDate($posting->getLastRunAt()),
        ];
    }
}
