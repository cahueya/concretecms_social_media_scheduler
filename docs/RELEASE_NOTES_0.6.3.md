# Social Media Scheduler v0.6.3 Release Notes

## Overview

Version 0.6.3 adds an end date to scheduled postings.

This release keeps the channel posting architecture from v0.6.2 intact and only extends the scheduling model. Existing channel behavior for Telegram, Listmonk, Matrix, Generic Webhook, Bluesky, and Mastodon remains unchanged.

## New Features

### End Date for Postings

Postings now support an explicit end date.

Each posting now has:

- Start Date / Time
- Repeat frequency
- End Date / Time
- Timezone

This allows recurring postings to stop automatically after a defined date.

## Dashboard Changes

The Create and Edit posting forms now include an End Date / Time field.

The scheduling fields are now conceptually:

```text
Start Date / Time
Repeat every X days
End Date / Time
Timezone
