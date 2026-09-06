<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Scheduler;

use Concrete\Package\SocialMediaScheduler\Src\Post\Repository;
use Concrete\Package\SocialMediaScheduler\Src\Service\Channel\SenderRegistry;

defined('C5_EXECUTE') or die('Access Denied.');

class PostingRunner
{
    private SenderRegistry $senders;

    public function __construct(private Repository $repository, ?SenderRegistry $senders = null)
    {
        $this->senders = $senders ?? new SenderRegistry();
    }

    public function runDue(): array
    {
        $summary = ['postings' => 0, 'sent' => 0, 'failed' => 0, 'retries' => 0];
        foreach ($this->repository->getDuePostings() as $posting) {
            $summary['postings']++;
            $attempt = ((int) ($posting['retryCount'] ?? 0)) + 1;
            $result = $this->sendPosting($posting, $attempt, '');
            $summary['sent'] += $result['sent'];
            $summary['failed'] += $result['failed'];
            $maxAttempts = max(1, (int) ($posting['maxAttempts'] ?? 3));
            if ($result['failed'] > 0 && $attempt < $maxAttempts) {
                $this->repository->scheduleRetry((int) $posting['id'], (int) ($posting['retryDelayMinutes'] ?? 30));
                $summary['retries']++;
            } else {
                $this->repository->markPostingRun((int) $posting['id'], (int) $posting['repeatEveryDays']);
            }
        }
        return $summary;
    }

    public function runPostingNow(int $postingID): array
    {
        $posting = $this->repository->getPostingForSending($postingID, true);
        if (!$posting) {
            throw new \RuntimeException('Posting not found.');
        }
        $result = $this->sendPosting($posting, 1, 'manual_');
        return ['postings' => 1, 'sent' => $result['sent'], 'failed' => $result['failed'], 'retries' => 0];
    }

    public function sendPosting(array $posting, int $attempt, string $statusPrefix): array
    {
        $result = ['sent' => 0, 'failed' => 0];
        foreach ($posting['channels'] as $channel) {
            $channel['_posting_title'] = (string) ($posting['title'] ?? '');
            try {
                $message = $this->senders->get((string) $channel['channelType'])->send(
                    $channel,
                    (string) $posting['subject'],
                    (string) $posting['bodyHtml'],
                    (array) ($posting['attachments'] ?? [])
                );
                $this->repository->log((int) $posting['id'], (int) $channel['id'], $statusPrefix . 'success', $message, $attempt);
                $result['sent']++;
            } catch (\Throwable $e) {
                $this->repository->log((int) $posting['id'], (int) $channel['id'], $statusPrefix . 'error', $e->getMessage(), $attempt);
                $result['failed']++;
            }
        }
        return $result;
    }
}
