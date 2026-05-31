<?php
namespace Concrete\Package\SocialMediaScheduler\Src\Entity;

use Doctrine\ORM\Mapping as ORM;

\defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @ORM\Entity
 * @ORM\Table(name="SocialMediaSchedulerPostingChannels", indexes={
 *     @ORM\Index(name="IDX_SMSPC_CHANNEL", columns={"channelID"})
 * })
 */
class PostingChannel
{
    /** @ORM\Id @ORM\Column(type="integer", options={"unsigned": true}) */
    protected int $postingID = 0;

    /** @ORM\Id @ORM\Column(type="integer", options={"unsigned": true}) */
    protected int $channelID = 0;

    public function __construct(int $postingID = 0, int $channelID = 0)
    {
        $this->postingID = $postingID;
        $this->channelID = $channelID;
    }

    public function getPostingID(): int { return $this->postingID; }
    public function setPostingID(int $value): self { $this->postingID = $value; return $this; }
    public function getChannelID(): int { return $this->channelID; }
    public function setChannelID(int $value): self { $this->channelID = $value; return $this; }
}
