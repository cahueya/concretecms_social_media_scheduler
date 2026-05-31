# Release notes – Social Media Scheduler 0.5.10

Version **0.5.10** is a release-cleanup build. It keeps the working functional logic from the previous development builds and cleans the package structure for a stable baseline.

## Confirmed scope

- Existing package functionality remains in place.
- Channel sender classes in `src/` were not changed during the cleanup.
- Legacy dashboard pages were removed.
- Package uninstall now removes package database tables.

## Dashboard cleanup

The final dashboard structure is:

```text
Social Media Scheduler
├── Posts
├── Create
├── Logs
└── Configuration
```

The parent page redirects to **Posts**.

The following pre-release pages are no longer installed:

```text
content
new_posting
send_log
```

During install/upgrade, the package tries to remove these legacy pages if they exist:

```text
/dashboard/social_media_scheduler/content
/dashboard/social_media_scheduler/new_posting
/dashboard/social_media_scheduler/send_log
```

## Uninstall cleanup

Uninstall now drops the package database tables:

```text
SocialMediaSchedulerPostingChannels
SocialMediaSchedulerLog
SocialMediaSchedulerChannels
SocialMediaSchedulerPostings
```

This prevents stale package tables from remaining after removal.

## Database installation

Version 0.5.10 still creates and updates tables from the package controller. This is intentional for this release because the goal was cleanup without changing the working `src/` logic.

A future major internal refactor may move schema logic into an installer/schema class and may introduce Doctrine ORM entities.

## Supported channels

Version 0.5.10 includes:

- Telegram
- Listmonk
- Matrix
- Generic Webhook
- Bluesky
- Mastodon

## Media behaviour

The media policy remains:

- editor images are body media
- File Manager attachments are explicit attachments
- Listmonk uses body media inline and explicit attachments as email/campaign attachments
- social/messenger channels use body media by default and ignore explicit attachments unless enabled
- Webhook can receive both

## Upgrade notes

When upgrading from an earlier development build:

1. Replace the full `packages/social_media_scheduler` folder.
2. Run the package upgrade from the ConcreteCMS dashboard.
3. Check that the dashboard contains only Posts, Create, Logs and Configuration.
4. Test one known working channel.
5. Run the task manually once before relying on cron.
