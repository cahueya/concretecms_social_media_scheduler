# Social Media Scheduler Architecture

Version: **0.6.2**

This document describes the internal architecture of the Social Media Scheduler package for ConcreteCMS 9.4+.

## 1. Package purpose

The package manages scheduled postings and sends them to configured channels through a ConcreteCMS automated task.

The core workflow is:

```text
Dashboard
  ↓
Postings and Channels stored in package tables
  ↓
ConcreteCMS task submit_social_postings
  ↓
PostingRunner
  ↓
Channel sender classes
  ↓
External APIs
```

## 2. Dashboard pages

The dashboard structure is:

```text
/dashboard/social_media_scheduler
/dashboard/social_media_scheduler/posts
/dashboard/social_media_scheduler/create
/dashboard/social_media_scheduler/logs
/dashboard/social_media_scheduler/config
```

The parent page redirects to `/dashboard/social_media_scheduler/posts`.

The pages are:

| Page | Purpose |
|---|---|
| Posts | List, view, enable/disable, edit, send now and delete postings. |
| Create | Create new scheduled postings. |
| Logs | View and clear send logs. |
| Configuration | Configure channel credentials and channel-specific options. |

Legacy pages from early pre-release builds are removed and are not installed in 0.6.x.

## 3. Persistence layer

Version 0.6.x introduces Doctrine ORM entities for package-owned data.

Entities:

| Entity | Table |
|---|---|
| `src/Entity/Posting.php` | `SocialMediaSchedulerPostings` |
| `src/Entity/Channel.php` | `SocialMediaSchedulerChannels` |
| `src/Entity/PostingChannel.php` | `SocialMediaSchedulerPostingChannels` |
| `src/Entity/SendLog.php` | `SocialMediaSchedulerLog` |

The repository layer keeps compatibility with the existing controller and sender flow by returning the same array-like structures used by the pre-ORM versions.

This means the sender classes and API integrations remain stable while the storage implementation is modernized internally.

## 4. Installer

Package setup is orchestrated by:

```text
src/Package/Installer.php
```

The installer is responsible for:

- installing dashboard pages
- registering required page paths
- installing task metadata through the package lifecycle
- ensuring package schema setup
- removing obsolete dashboard pages if necessary
- uninstall cleanup

The package controller delegates install, upgrade and uninstall work to this installer instead of keeping all setup logic in the controller.

## 5. Automated task

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

Task flow:

1. The task command is executed manually or by cron.
2. The command handler invokes the scheduler/runner.
3. The repository finds due postings.
4. The runner resolves enabled channels for each posting.
5. Each channel sender attempts delivery.
6. Each attempt is logged.
7. Failures are retried according to retry settings.
8. Successful recurring postings are advanced to the next due date.

## 6. Channel sender architecture

Every sender implements the common sender interface:

```text
src/Service/Channel/ChannelSenderInterface.php
```

Current active senders:

```text
TelegramSender.php
ListmonkSender.php
MatrixSender.php
WebhookSender.php
BlueskySender.php
MastodonSender.php
```

`XSender.php` may exist in the codebase, but X/Twitter is not exposed as a selectable channel in 0.6.2 because the connector has not been fully verified with paid X API write access.

The runner maps the selected channel type to a sender and passes:

- posting data
- channel configuration
- resolved media information

The senders are responsible for channel-specific API formatting and delivery.

## 7. Media architecture

The package separates media into two groups.

### 7.1 Body media

Body media are images inserted into the ConcreteCMS rich-text editor.

They are part of the message content and are used by social/messenger channels as post media.

### 7.2 Explicit attachments

Explicit attachments are files selected through the ConcreteCMS File Manager attachment selector.

They are primarily used for Listmonk/email-style attachments.

Social and messenger channels ignore explicit attachments by default unless the channel option to include them is enabled.

### 7.3 Local media resolving

`LocalMediaHelper.php` resolves ConcreteCMS file references and editor image URLs into usable media metadata:

- filename
- public URL
- local path if available
- MIME type
- file size

If a sender needs binary upload, it uses the local path when available. If not available, the sender may fall back to a URL or log a failure depending on channel requirements.

## 8. Channel behavior summary

| Channel | Text behavior | Media behavior |
|---|---|---|
| Listmonk | Subject becomes email subject, body remains HTML. | Body images are uploaded and embedded inline; explicit attachments are campaign media/attachments. |
| Telegram | Subject and body become message text/caption. | Body images are uploaded via Bot API media methods. |
| Matrix | Text message is sent first. | Body images are uploaded to Matrix media repository and sent as media events. |
| Webhook | Structured payload is sent. | Body media and attachments are included as structured data or multipart, depending on mode. |
| Bluesky | Subject and body become post text. | Body images are uploaded as blobs and embedded, max. 4. |
| Mastodon | Subject and body become status text. | Body images are uploaded as media and attached to the status. |

## 9. Logging

Each send attempt creates a row in:

```text
SocialMediaSchedulerLog
```

The log page allows filtering and clearing logs.

Typical log data includes:

- posting ID
- channel ID
- channel type
- status
- message/error
- attempt count
- timestamp

## 10. Uninstall

Uninstall removes the package-owned tables:

```text
SocialMediaSchedulerPostingChannels
SocialMediaSchedulerLog
SocialMediaSchedulerChannels
SocialMediaSchedulerPostings
```

This is intentional for a clean release package. Users should back up data before uninstalling.
