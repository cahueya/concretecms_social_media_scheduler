<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Dashboard;

use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Package\SocialMediaScheduler\Src\Post\Repository;
use Concrete\Package\SocialMediaScheduler\Src\Util\Crypto;

\defined('C5_EXECUTE') or die('Access Denied.');

abstract class PostingController extends DashboardPageController
{
    protected function repository(): Repository
    {
        return new Repository($this->app->make(Connection::class), new Crypto());
    }

    protected function postingFormData(int $id = 0): array
    {
        $body = (string) $this->post('bodyHtml');
        if ($id > 0 && $body === '') {
            $body = (string) $this->post('bodyHtml_' . $id);
        }

        return [
            'title' => trim((string) $this->post('title')),
            'subject' => trim((string) $this->post('subject')),
            'bodyHtml' => $body,
            'startAt' => (string) $this->post('startAt'),
            'endAt' => (string) $this->post('endAt'),
            'repeatEveryDays' => max(0, (int) $this->post('repeatEveryDays')),
            'channelIDs' => array_values(array_filter(array_unique(array_map('intval', (array) $this->post('channelIDs'))))),
            'timezone' => (string) $this->post('timezone'),
            'attachmentFileIDs' => $this->attachmentFileIDs(),
            'maxAttempts' => max(1, (int) $this->post('maxAttempts')),
            'retryDelayMinutes' => max(1, (int) $this->post('retryDelayMinutes')),
        ];
    }

    protected function validatePostingFormData(array $data): ?string
    {
        if (
            $data['subject'] === ''
            || trim(strip_tags((string) $data['bodyHtml'])) === ''
            || $data['startAt'] === ''
            || $data['endAt'] === ''
            || empty($data['channelIDs'])
        ) {
            return t('Subject, content, start date, end date and at least one channel are required.');
        }

        $start = strtotime((string) $data['startAt']);
        $end = strtotime((string) $data['endAt']);
        if ($start !== false && $end !== false && $end < $start) {
            return t('End date must be after the start date.');
        }

        return null;
    }

    protected function savePostingFormData(array $data, ?int $id = null): int
    {
        return $this->repository()->savePosting(
            $data['title'],
            $data['subject'],
            $data['bodyHtml'],
            $data['startAt'],
            $data['endAt'],
            $data['repeatEveryDays'],
            $data['channelIDs'],
            $id,
            $data['timezone'],
            $data['attachmentFileIDs'],
            $data['maxAttempts'],
            $data['retryDelayMinutes']
        );
    }

    private function attachmentFileIDs(): array
    {
        return array_values(array_filter(array_unique(array_map('intval', (array) $this->post('attachmentFileIDs')))));
    }
}
