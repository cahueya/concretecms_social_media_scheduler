# Release Notes — Social Media Scheduler 0.6.2

Version **0.6.2** is the current clean ORM-based release candidate for GitHub publication.

## Status

This release is intended for clean installation and verification after the pre-release 0.5.x line.

The confirmed 0.5.10 base functionality was preserved, and the persistence layer was moved to Doctrine ORM entities in the 0.6.x line.

## Highlights

- Doctrine ORM entities for package-owned persistence.
- Dedicated package installer class.
- Clean dashboard structure.
- Package icon included.
- X/Twitter removed from the selectable channel list until verified.
- Bluesky endpoint handling improved.
- Mastodon media upload corrected.

## Active channels

- Telegram
- Listmonk
- Matrix
- Generic Webhook
- Bluesky
- Mastodon

## Not selectable in this release

- X / Twitter

The X/Twitter sender file may exist in the codebase, but the channel is intentionally not offered in the UI or runner mapping because the API access has not been confirmed with a paid write-enabled X Developer plan.

## Changes since 0.5.10

### ORM persistence

Added Doctrine entities:

- `src/Entity/Channel.php`
- `src/Entity/Posting.php`
- `src/Entity/PostingChannel.php`
- `src/Entity/SendLog.php`

Mapped package tables:

- `SocialMediaSchedulerChannels`
- `SocialMediaSchedulerPostings`
- `SocialMediaSchedulerPostingChannels`
- `SocialMediaSchedulerLog`

### Installer cleanup

Added:

- `src/Package/Installer.php`

The package controller delegates installation, upgrade and uninstall orchestration.

### Icon

Added package icon:

```text
icon.png
```

Size:

```text
184 × 184 px
```

### Bluesky fix

`https://bsky.app` is not the API endpoint. Version 0.6.1+ normalizes accidental `bsky.app` entries to `https://bsky.social`.

Recommended Bluesky API/PDS URL:

```text
https://bsky.social
```

### Mastodon media fix

Mastodon media upload now sends a proper multipart request using field name:

```text
file
```

This fixes validation errors such as:

```text
File muss ausgefüllt werden
```

### X/Twitter removed from selectable channels

The X/Twitter option was removed from:

- channel type dropdown
- configuration UI
- accepted channel types
- runner sender mapping

This avoids exposing an unverified connector.

## Fresh install test checklist

1. Install package.
2. Confirm dashboard structure:
   - Social Media Scheduler
   - Posts
   - Create
   - Logs
   - Configuration
3. Configure one or more channels.
4. Create a text-only posting.
5. Run task manually.
6. Create a posting with a body image.
7. Test social channels.
8. Test Listmonk with body image and explicit attachment.
9. Confirm logs are written.
10. Use **Clear Logs** on Logs page.
11. Uninstall package.
12. Confirm package tables are removed.

## Known notes

- Version 0.6.2 is intended as a clean release candidate. Production upgrades from earlier unpublished pre-release builds are not the primary target.
- Social channels use body images by default and ignore explicit attachments unless enabled per channel.
- Listmonk treats body images and explicit attachments separately.
