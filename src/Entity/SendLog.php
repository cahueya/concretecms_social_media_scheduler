<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Entity;

use Doctrine\ORM\Mapping as ORM;

\defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @ORM\Entity
 * @ORM\Table(name="SocialMediaSchedulerLog", indexes={
 *     @ORM\Index(name="IDX_SMSL_POSTING", columns={"postingID"}),
 *     @ORM\Index(name="IDX_SMSL_CHANNEL", columns={"channelID"}),
 *     @ORM\Index(name="IDX_SMSL_CREATED", columns={"dateCreated"})
 * })
 */
class SendLog
{
    /** @ORM\Id @ORM\Column(type="integer", options={"unsigned": true}) @ORM\GeneratedValue(strategy="AUTO") */
    protected ?int $id = null;

    /** @ORM\Column(type="integer", options={"unsigned": true}) */
    protected int $postingID = 0;

    /** @ORM\Column(type="integer", options={"unsigned": true}) */
    protected int $channelID = 0;

    /** @ORM\Column(type="string", length=32) */
    protected string $status = '';

    /** @ORM\Column(type="integer", options={"unsigned": true, "default": 1}) */
    protected int $attempt = 1;

    /** @ORM\Column(type="text", nullable=true, columnDefinition="LONGTEXT DEFAULT NULL") */
    protected ?string $message = null;

    /** @ORM\Column(type="datetime") */
    protected ?\DateTimeInterface $dateCreated = null;

    public function getId(): ?int { return $this->id; }
    public function getPostingID(): int { return $this->postingID; }
    public function setPostingID(int $value): self { $this->postingID = $value; return $this; }
    public function getChannelID(): int { return $this->channelID; }
    public function setChannelID(int $value): self { $this->channelID = $value; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $value): self { $this->status = $value; return $this; }
    public function getAttempt(): int { return $this->attempt; }
    public function setAttempt(int $value): self { $this->attempt = max(1, $value); return $this; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(?string $value): self { $this->message = $value; return $this; }
    public function getDateCreated(): ?\DateTimeInterface { return $this->dateCreated; }
    public function setDateCreated(\DateTimeInterface $value): self { $this->dateCreated = $value; return $this; }
}
