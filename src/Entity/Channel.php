<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Entity;

use Doctrine\ORM\Mapping as ORM;

\defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @ORM\Entity
 * @ORM\Table(name="SocialMediaSchedulerChannels", indexes={
 *     @ORM\Index(name="IDX_SMSSC_TYPE", columns={"channelType"}),
 *     @ORM\Index(name="IDX_SMSSC_ENABLED", columns={"isEnabled"})
 * })
 */
class Channel
{
    /** @ORM\Id @ORM\Column(type="integer", options={"unsigned": true}) @ORM\GeneratedValue(strategy="AUTO") */
    protected ?int $id = null;

    /** @ORM\Column(type="string", length=32) */
    protected string $channelType = '';

    /** @ORM\Column(type="string", length=255) */
    protected string $channelName = '';

    /** @ORM\Column(type="boolean", options={"default": true}) */
    protected bool $isEnabled = true;

    /** @ORM\Column(type="text", nullable=true, columnDefinition="LONGTEXT DEFAULT NULL") */
    protected ?string $configJson = null;

    /** @ORM\Column(type="string", length=2048, nullable=true) */
    protected ?string $publicEndpointUrl = null;

    /** @ORM\Column(type="datetime") */
    protected ?\DateTimeInterface $dateCreated = null;

    /** @ORM\Column(type="datetime") */
    protected ?\DateTimeInterface $dateUpdated = null;

    public function getId(): ?int { return $this->id; }
    public function getChannelType(): string { return $this->channelType; }
    public function setChannelType(string $value): self { $this->channelType = $value; return $this; }
    public function getChannelName(): string { return $this->channelName; }
    public function setChannelName(string $value): self { $this->channelName = $value; return $this; }
    public function isEnabled(): bool { return $this->isEnabled; }
    public function setIsEnabled(bool $value): self { $this->isEnabled = $value; return $this; }
    public function getConfigJson(): ?string { return $this->configJson; }
    public function setConfigJson(?string $value): self { $this->configJson = $value; return $this; }
    public function getPublicEndpointUrl(): ?string { return $this->publicEndpointUrl; }
    public function setPublicEndpointUrl(?string $value): self { $this->publicEndpointUrl = $value; return $this; }
    public function getDateCreated(): ?\DateTimeInterface { return $this->dateCreated; }
    public function setDateCreated(\DateTimeInterface $value): self { $this->dateCreated = $value; return $this; }
    public function getDateUpdated(): ?\DateTimeInterface { return $this->dateUpdated; }
    public function setDateUpdated(\DateTimeInterface $value): self { $this->dateUpdated = $value; return $this; }
}
