<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Entity;

use Doctrine\ORM\Mapping as ORM;

\defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @ORM\Entity
 * @ORM\Table(name="SocialMediaSchedulerPostings", indexes={
 *     @ORM\Index(name="IDX_SMSP_NEXT_RUN", columns={"nextRunAt"}),
 *     @ORM\Index(name="IDX_SMSP_ENABLED", columns={"isEnabled"})
 * })
 */
class Posting
{
    /** @ORM\Id @ORM\Column(type="integer", options={"unsigned": true}) @ORM\GeneratedValue(strategy="AUTO") */
    protected ?int $id = null;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    protected ?string $title = null;

    /** @ORM\Column(type="string", length=255) */
    protected string $subject = '';

    /** @ORM\Column(type="text", columnDefinition="LONGTEXT NOT NULL") */
    protected string $bodyHtml = '';

    /** @ORM\Column(type="datetime") */
    protected ?\DateTimeInterface $startAt = null;

    /** @ORM\Column(type="datetime") */
    protected ?\DateTimeInterface $nextRunAt = null;

    /** @ORM\Column(type="datetime", nullable=true) */
    protected ?\DateTimeInterface $endAt = null;

    /** @ORM\Column(type="integer", options={"unsigned": true, "default": 0}) */
    protected int $repeatEveryDays = 0;

    /** @ORM\Column(type="string", length=64, nullable=true) */
    protected ?string $timezone = null;

    /** @ORM\Column(type="text", nullable=true, columnDefinition="LONGTEXT DEFAULT NULL") */
    protected ?string $attachmentFileIDs = null;

    /** @ORM\Column(type="integer", options={"unsigned": true, "default": 3}) */
    protected int $maxAttempts = 3;

    /** @ORM\Column(type="integer", options={"unsigned": true, "default": 30}) */
    protected int $retryDelayMinutes = 30;

    /** @ORM\Column(type="integer", options={"unsigned": true, "default": 0}) */
    protected int $retryCount = 0;

    /** @ORM\Column(type="boolean", options={"default": true}) */
    protected bool $isEnabled = true;

    /** @ORM\Column(type="datetime") */
    protected ?\DateTimeInterface $dateCreated = null;

    /** @ORM\Column(type="datetime") */
    protected ?\DateTimeInterface $dateUpdated = null;

    /** @ORM\Column(type="datetime", nullable=true) */
    protected ?\DateTimeInterface $lastRunAt = null;

    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $value): self { $this->title = $value; return $this; }
    public function getSubject(): string { return $this->subject; }
    public function setSubject(string $value): self { $this->subject = $value; return $this; }
    public function getBodyHtml(): string { return $this->bodyHtml; }
    public function setBodyHtml(string $value): self { $this->bodyHtml = $value; return $this; }
    public function getStartAt(): ?\DateTimeInterface { return $this->startAt; }
    public function setStartAt(\DateTimeInterface $value): self { $this->startAt = $value; return $this; }
    public function getNextRunAt(): ?\DateTimeInterface { return $this->nextRunAt; }
    public function setNextRunAt(\DateTimeInterface $value): self { $this->nextRunAt = $value; return $this; }
    public function getEndAt(): ?\DateTimeInterface { return $this->endAt; }
    public function setEndAt(?\DateTimeInterface $value): self { $this->endAt = $value; return $this; }
    public function getRepeatEveryDays(): int { return $this->repeatEveryDays; }
    public function setRepeatEveryDays(int $value): self { $this->repeatEveryDays = max(0, $value); return $this; }
    public function getTimezone(): ?string { return $this->timezone; }
    public function setTimezone(?string $value): self { $this->timezone = $value; return $this; }
    public function getAttachmentFileIDs(): ?string { return $this->attachmentFileIDs; }
    public function setAttachmentFileIDs(?string $value): self { $this->attachmentFileIDs = $value; return $this; }
    public function getMaxAttempts(): int { return $this->maxAttempts; }
    public function setMaxAttempts(int $value): self { $this->maxAttempts = max(1, $value); return $this; }
    public function getRetryDelayMinutes(): int { return $this->retryDelayMinutes; }
    public function setRetryDelayMinutes(int $value): self { $this->retryDelayMinutes = max(1, $value); return $this; }
    public function getRetryCount(): int { return $this->retryCount; }
    public function setRetryCount(int $value): self { $this->retryCount = max(0, $value); return $this; }
    public function incrementRetryCount(): self { $this->retryCount++; return $this; }
    public function isEnabled(): bool { return $this->isEnabled; }
    public function setIsEnabled(bool $value): self { $this->isEnabled = $value; return $this; }
    public function getDateCreated(): ?\DateTimeInterface { return $this->dateCreated; }
    public function setDateCreated(\DateTimeInterface $value): self { $this->dateCreated = $value; return $this; }
    public function getDateUpdated(): ?\DateTimeInterface { return $this->dateUpdated; }
    public function setDateUpdated(\DateTimeInterface $value): self { $this->dateUpdated = $value; return $this; }
    public function getLastRunAt(): ?\DateTimeInterface { return $this->lastRunAt; }
    public function setLastRunAt(?\DateTimeInterface $value): self { $this->lastRunAt = $value; return $this; }
}
