# Social Media Scheduler for ConcreteCMS 9.4+

Social Media Scheduler is a ConcreteCMS package for scheduled and recurring publishing to multiple communication and social-media channels.

Current version: **0.6.3**

The package is built for:

- **ConcreteCMS 9.4+**
- **PHP 8+**

## Supported channels

Version **0.6.3** supports:

- Telegram via Bot API
- Listmonk email campaigns
- Matrix rooms
- Generic Webhook, for example n8n, Make, Zapier or custom APIs
- Bluesky
- Mastodon

X/Twitter code is not exposed as a selectable channel in this release because the connector has not been fully verified with paid X API write access.

## What this package does

The package lets editors create reusable scheduled postings in the ConcreteCMS dashboard. Each posting can be sent once or repeatedly in a defined cycle.

A posting has:

| Field | Purpose |
|---|---|
| Title | Internal dashboard title. This is never sent. |
| Subject | Public subject. Used as Listmonk email subject and as first line/part of social messages. |
| Body | Rich-text content from the ConcreteCMS editor. |
| Start date/time | First scheduled send time. |
| End date/time | Last date/time after which the posting will no longer be sent. |
| Repeat every X days | Recurrence interval. `0` means one-time posting. |
| Timezone | Timezone used for the scheduled date/time. |
| Attachments | Explicit ConcreteCMS File Manager attachments, mainly for Listmonk/email. |
| Channels | Target channels selected for this posting. |

## Dashboard structure

After installation the dashboard contains:

```text
Dashboard
└── Social Media Scheduler
    ├── Posts
    ├── Create
    ├── Logs
    └── Configuration
```

The parent page **Social Media Scheduler** redirects to **Posts**.

## Installation

1. Copy the folder `social_media_scheduler` into the ConcreteCMS `packages/` directory.
2. Open **Dashboard > Extend Concrete > Add Functionality**.
3. Install **Social Media Scheduler**.
4. Open **Dashboard > Social Media Scheduler > Configuration**.
5. Configure at least one channel.
6. Create a posting at **Dashboard > Social Media Scheduler > Create**.
7. Run the task manually or via cron.

## Fresh install recommendation for 0.6.x

Version 0.6.0 introduced Doctrine ORM entities for package-owned persistence. Version 0.6.3 is the current clean release line after the pre-release 0.5.x builds.

For a clean release setup, start from a fresh install:

1. Back up any old pre-release data if needed.
2. Uninstall the old pre-release package.
3. Confirm that the package tables were removed.
4. Install version 0.6.3.
5. Recreate channel configurations and test postings.

## Uninstall

Uninstalling the package removes these package-owned tables:

```text
SocialMediaSchedulerPostingChannels
SocialMediaSchedulerLog
SocialMediaSchedulerChannels
SocialMediaSchedulerPostings
```

Uninstalling deletes scheduled postings, channel configuration and send logs. Export or back up data first if it must be kept.

## Automated task

Task handle:

```text
submit_social_postings
```

The task sends all enabled postings whose `nextRunAt` value is due and whose end date has not passed.

It can be run manually from the ConcreteCMS task dashboard or through the normal ConcreteCMS cron/task runner setup.

Task flow:

1. Find due enabled postings.
2. Find the selected enabled channels for each posting.
3. Send the posting to every selected channel.
4. Write a log entry for each channel attempt.
5. Apply retry logic if a send fails.
6. Advance the posting to the next cycle, or disable it if it is a one-time posting or if the next cycle would be after the end date.

## Media policy

The package intentionally separates **body media** from **explicit attachments**.

### Body media

Images inserted directly into the rich-text editor body are treated as content media.

Example:

```html
<p>Text</p>
<img src="/application/files/.../image.jpg">
```

These images are used by social and messenger channels as post media.

### Explicit attachments

Files selected with the attachment selector are treated as explicit attachments.

They are primarily intended for Listmonk/email. Social and messenger channels ignore explicit attachments by default unless the channel option to include them is enabled.

### Channel behavior

| Channel | Body images | Explicit attachments |
|---|---|---|
| Listmonk | Inline newsletter images | Campaign media/attachments |
| Telegram | Sent as media after or with message | Ignored by default; optional |
| Matrix | Sent as separate Matrix media events | Ignored by default; optional |
| Bluesky | Sent as image embeds, max. 4 | Ignored by default; optional |
| Mastodon | Uploaded as status media | Ignored by default; optional |
| Webhook | Included as structured payload data | Included as structured payload data |

## Channel setup summary

| Channel | Required credentials |
|---|---|
| Telegram | Bot token and chat ID. Chat discovery can use Telegram `getUpdates`. |
| Listmonk | Base URL, API username and API token/password. |
| Matrix | Homeserver URL, access token and room ID. |
| Generic Webhook | URL, method and optional auth headers/basic/bearer credentials. |
| Bluesky | Handle and App Password. API/PDS URL should normally be `https://bsky.social`, not `https://bsky.app`. |
| Mastodon | Instance URL and Access Token. |

## Basic usage

1. Configure channels in **Configuration**.
2. Create a posting in **Create**.
3. Use **Title** for internal dashboard organisation.
4. Use **Subject** for the public email/social subject.
5. Put text and post images into the rich-text editor.
6. Use explicit attachments mainly for Listmonk/email.
7. Choose the target channels.
8. Save the posting.
9. Run the task manually for testing or let cron process it.
10. Check **Logs** for channel responses.

## Security notes

- Secret channel configuration values are stored encrypted in `configJson`.
- Non-secret endpoint URLs may be stored in `publicEndpointUrl` for visibility and filtering.
- Use app passwords or dedicated API tokens wherever possible.
- Do not use personal master passwords when a platform supports app-specific credentials.
- Review channel permissions before enabling automated posting.

## Developer notes

Version 0.6.x moves package-owned persistence to Doctrine ORM entities while keeping the tested sender/channel behavior from the stable 0.5.10 line.

Persistence entities:

- `src/Entity/Channel.php` → `SocialMediaSchedulerChannels`
- `src/Entity/Posting.php` → `SocialMediaSchedulerPostings`
- `src/Entity/PostingChannel.php` → `SocialMediaSchedulerPostingChannels`
- `src/Entity/SendLog.php` → `SocialMediaSchedulerLog`

The package controller registers the entity path through ConcreteCMS' package provider mechanism. Installation and dashboard/page setup are orchestrated by `src/Package/Installer.php`.

## Documentation

Additional documentation:

- `docs/ARCHITECTURE.md`
- `docs/CHANNELS.md`
- `docs/RELEASE_NOTES_0.6.3.md`
- `docs/RELEASE_NOTES_0.6.2.md`
- `CHANGELOG.md`
