# Social Media Scheduler Architecture

Version: **0.6.6**

## Overview

```text
Dashboard
  ↓
Repository / Doctrine entities
  ↓
ConcreteCMS task: submit_social_postings
  ↓
PostingRunner
  ↓
SenderRegistry
  ↓
Channel sender
  ↓
External API
```

## Dashboard

The package installs:

```text
/dashboard/social_media_scheduler
/dashboard/social_media_scheduler/posts
/dashboard/social_media_scheduler/create
/dashboard/social_media_scheduler/logs
/dashboard/social_media_scheduler/config
```

The parent page redirects to `/dashboard/social_media_scheduler/posts`.

Create and edit use the same posting-form partial and the same parsing/validation logic in `src/Dashboard/PostingController.php`.

## Persistence

| Entity | Table |
|---|---|
| `Posting` | `SocialMediaSchedulerPostings` |
| `Channel` | `SocialMediaSchedulerChannels` |
| `PostingChannel` | `SocialMediaSchedulerPostingChannels` |
| `SendLog` | `SocialMediaSchedulerLog` |

`src/Post/Repository.php` is the package persistence boundary. It returns arrays to the dashboard and sender layers so channel implementations do not depend directly on Doctrine entities.

### Purpose-specific hydration

Repository reads are intentionally split by use case:

- **Posts dashboard:** posting data, selected channels, attachment metadata and channel previews.
- **Scheduled/manual sending:** posting data, enabled selected channels and attachment metadata, but no previews.
- **Logs filter:** ID/title/subject only.

For posting lists, channels are loaded in bulk rather than loading each posting/channel combination individually.

## Installer

`src/Package/Installer.php` handles:

- dashboard Single Pages;
- task metadata installation;
- dashboard child-page order;
- package-table removal on uninstall.

Entity schema management is left to the normal ConcreteCMS package/Doctrine lifecycle. The package no longer maintains a second manual SchemaTool/column-repair path.

## Task flow

Task handle:

```text
submit_social_postings
```

Relevant classes:

```text
src/Command/Task/Controller/SubmitSocialPostingsController.php
src/Post/Command/SubmitDuePostingsCommand.php
src/Post/Command/SubmitDuePostingsCommandHandler.php
src/Scheduler/PostingRunner.php
```

Flow:

1. Repository selects enabled due postings that have not passed `endAt`.
2. Repository resolves only enabled channels selected for those postings.
3. `PostingRunner` resolves each sender through `SenderRegistry`.
4. Each send attempt is logged.
5. Failed scheduled runs are retried until `maxAttempts` is reached, subject to `endAt`.
6. Completed recurring postings advance by `repeatEveryDays`; one-time/completed postings are disabled.

## Sender architecture

All active senders implement:

```text
src/Service/Channel/ChannelSenderInterface.php
```

Active types are defined once in:

```text
src/Service/Channel/SenderRegistry.php
```

Active senders:

```text
TelegramSender.php
ListmonkSender.php
MatrixSender.php
WebhookSender.php
BlueskySender.php
MastodonSender.php
```

`XSender.php` is retained as dormant future connector code. It is not present in `SenderRegistry`, not accepted by the configuration controller and not exposed in the dashboard.

## Text normalization

`TextNormalizer.php` provides the shared HTML-to-text and entity-decoding behavior used by text-oriented channels. Bluesky and Mastodon no longer keep private duplicate HTML-to-text implementations.

## Media handling

`LocalMediaHelper.php` handles editor image discovery and local media resolution. Body media and explicit attachments remain separate by design.

Sender-specific policies remain in the individual sender classes because upload APIs and limitations differ materially between platforms.

## Credential storage

`Crypto.php` encrypts new channel configurations with AES-256-CBC. Saving new credentials requires OpenSSL. Legacy Base64 values remain readable for compatibility but are no longer written.

## Logging

Each channel attempt creates a `SendLog` entry containing posting/channel IDs, result status, message, attempt count and timestamp. Manual sends use `manual_success` / `manual_error`; scheduled sends use `success` / `error`.

## Uninstall

Uninstall removes:

```text
SocialMediaSchedulerPostingChannels
SocialMediaSchedulerLog
SocialMediaSchedulerChannels
SocialMediaSchedulerPostings
```
