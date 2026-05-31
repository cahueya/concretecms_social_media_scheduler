# Changelog

## 0.6.4

- Normalize HTML entities before text-oriented channel sending.
- Fix Telegram plain mode showing entities like `&amp;uuml;` instead of UTF-8 characters such as `ü`.
- Decode subjects for Telegram, Matrix, Bluesky, Mastodon, Webhook and Listmonk campaign subjects.
- Keep Listmonk HTML body output unchanged while normalizing the subject.
- Add shared `TextNormalizer` helper for channel senders.

## 0.6.3

- Added required end date/time field for postings.
- Due postings are no longer selected after their end date has passed.
- Repeating postings are automatically disabled when the next calculated run would be after the end date.
- Retry scheduling now respects the posting end date.
- Added ORM schema upgrade guard for the new `endAt` column.

## 0.6.2

- Removed X/Twitter from the selectable channel list.
- Removed X/Twitter configuration UI.
- Removed X/Twitter from accepted channel types and runner sender mapping.
- Kept the X sender code in the package for possible future reactivation after verified API write access.
- Kept active channels: Telegram, Listmonk, Matrix, Generic Webhook, Bluesky and Mastodon.

## 0.6.1

- Fixed Bluesky API endpoint handling.
- Normalized accidental `https://bsky.app` entries to `https://bsky.social`.
- Updated Bluesky configuration guidance.
- Fixed Mastodon media upload by sending multipart data with field name `file`.

## 0.6.0

- Introduced Doctrine ORM entities for package-owned persistence.
- Added entities for channels, postings, posting-channel mappings and send logs.
- Added dedicated package installer class.
- Simplified package controller setup.
- Added package icon.
- Kept the tested sender and channel behavior from the 0.5.10 line.

## 0.5.10

- Cleaned release baseline before ORM refactor.
- Removed legacy dashboard pages from installation and navigation.
- Dashboard structure finalized:
  - Posts
  - Create
  - Logs
  - Configuration
- Added uninstall cleanup for package database tables.
- Removed old package manifest files from release ZIPs.

## 0.5.9

- Added experimental X/Twitter sender code.
- Not included as a stable selectable channel later because API write access was not verified.

## 0.5.8

- Introduced clear media separation between body media and explicit attachments.
- Social/messenger channels use body media by default.
- Explicit attachments are primarily used by Listmonk/email.
- Added optional channel-level setting to include explicit attachments for social/messenger channels.
- Improved Bluesky and Mastodon media handling.

## 0.5.7

- Added Mastodon channel.
- Added Mastodon media upload and status posting.

## 0.5.6

- Added Bluesky channel.
- Added Bluesky app password configuration and image embeds.

## 0.5.5

- Logs page reset button changed to clear logs.
- Log clearing implemented through repository method and POST token validation.

## 0.5.4

- Dashboard navigation renamed to:
  - Posts
  - Create
  - Logs
  - Configuration
- Parent page redirects to Posts.

## 0.5.3

- Split tab-based dashboard UI into separate pages.
- Removed page-level H1 headings where ConcreteCMS renders dashboard header titles.
- Added ConcreteCMS dashboard form action wrappers for submit buttons.

## 0.5.2

- Added built-in Basic Auth support for Generic Webhook.

## 0.5.1

- Fixed missing Webhook option in channel type dropdown.

## 0.5.0

- Added Generic Webhook channel.
- Added support for JSON, form-data and multipart payloads.
- Added support for Basic, Bearer and custom header authentication.

## 0.4.x

- Added title field for internal dashboard naming.
- Improved media handling across Telegram, Matrix and Listmonk.
- Added send-now, log filters, retry behavior and multi-attachment support.
- Confirmed Telegram, Matrix and Listmonk base functionality through testing.
