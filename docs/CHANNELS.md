# Channel Documentation

Version: **0.6.2**

This document describes how each channel behaves and which credentials are required.

## General content mapping

Each posting contains:

| Field | Meaning |
|---|---|
| Title | Internal title only. Never sent. |
| Subject | Public subject. Used as email subject or first line/part of social text. |
| Body | Rich-text editor content. |
| Body media | Images inserted into the editor body. |
| Attachments | Files selected with the File Manager attachment fields. |

## Media rules

Default behavior:

- **Listmonk** uses body images inline and explicit attachments as email/campaign attachments.
- **Telegram, Matrix, Bluesky and Mastodon** use body images as social media assets.
- **Telegram, Matrix, Bluesky and Mastodon** ignore explicit attachments by default.
- Explicit attachments can be included for social/messenger channels only when the channel option is enabled.
- **Webhook** receives structured data for both body media and attachments.

## Telegram

### Credentials

Required:

- Bot token
- Chat ID

The bot must be allowed to post in the target group/channel.

### Chat ID discovery

Telegram chat discovery uses Bot API updates. If discovery does not work because Telegram is blocked by the local network, the chat ID can be obtained manually using `getUpdates` from a network that can reach Telegram.

### Sending behavior

- Subject and body are combined into the Telegram message.
- If one body image exists and the text is short enough, the text can be used as image caption.
- If multiple media items exist, the text is sent first and the media follows.
- Images use Telegram photo upload where possible.
- Other files use document upload when explicit attachment sending is enabled.

### Notes

Telegram API access must be possible from the server where ConcreteCMS runs. A local VPN/DNS issue can prevent discovery or sending even when the token is correct.

## Listmonk

### Credentials

Required:

- Base URL
- API username
- API token/password

Example base URL:

```text
https://newsletter.example.com
```

### Sending behavior

- Subject becomes the Listmonk campaign subject.
- Body is sent as HTML email content.
- Body images are uploaded to Listmonk media and embedded into the HTML body.
- Explicit attachments are uploaded as Listmonk media/campaign attachments.
- Body images and explicit attachments are not mixed.

### Options

Depending on configuration, a campaign can be created as:

- draft
- running
- scheduled

## Matrix

### Credentials

Required:

- Homeserver URL
- Access token
- Room ID

### Sending behavior

- The text message is sent first.
- `<img>` tags are removed from the Matrix text message to avoid broken placeholders.
- Body images are uploaded to the Matrix media repository.
- Uploaded media is sent as separate `m.image` or `m.file` events.
- Explicit attachments are ignored unless enabled in the channel option.

### Recommended behavior

For Matrix, use body images in the editor when the image should be part of the post. Avoid using explicit attachments unless you intentionally want them sent as files.

## Generic Webhook

### Credentials

Required:

- Webhook URL

Optional:

- HTTP method: POST, PUT or PATCH
- Authentication: none, basic, bearer, custom headers
- Payload mode: JSON, form-data or multipart

### Basic Auth

For n8n Basic Auth, configure:

- Authentication: Basic Auth
- Username
- API token/password

The package creates the `Authorization: Basic ...` header automatically.

### Sending behavior

Webhook receives structured posting data. It is the most flexible connector and is useful for n8n, Make, Zapier or custom APIs.

Typical JSON structure:

```json
{
  "posting": {
    "id": 12,
    "title": "Internal title",
    "subject": "Public subject",
    "content_html": "<p>...</p>",
    "content_text": "...",
    "timezone": "Africa/Dar_es_Salaam"
  },
  "schedule": {
    "next_run_at": "2026-05-31 10:00:00",
    "repeat_every_days": 7
  },
  "media": {
    "body_media": [],
    "attachments": []
  },
  "source": {
    "system": "concretecms",
    "package": "social_media_scheduler"
  }
}
```

## Bluesky

### Credentials

Required:

- Handle
- App Password
- PDS/API Service URL

For normal Bluesky accounts, the service URL should be:

```text
https://bsky.social
```

Do not use:

```text
https://bsky.app
```

`bsky.app` is the web client, not the API/PDS endpoint. Version 0.6.1+ normalizes `https://bsky.app` to `https://bsky.social`, but it is better to enter the correct API URL.

### Sending behavior

- Subject and body are converted to post text.
- Body images are uploaded as blobs and embedded in the post.
- Bluesky supports a limited number of images per post. The package uses up to 4 images.
- Explicit attachments are ignored unless enabled in the channel option.

### Recommended credentials

Use a Bluesky App Password, not your main account password.

## Mastodon

### Credentials

Required:

- Instance URL
- Access Token

Optional/channel-specific:

- Visibility
- Language
- Sensitive flag
- Content warning

### Sending behavior

- Subject and body are converted to status text.
- Body images are uploaded to the Mastodon media endpoint.
- The returned media IDs are attached to the status.
- Explicit attachments are ignored unless enabled in the channel option.

### Access token

Create a Mastodon application in your instance settings and copy the access token. Required scopes normally include write access, for example `write` or more specific write scopes depending on the instance UI.

## X / Twitter

X/Twitter is not selectable in version 0.6.2.

Reason:

- Posting requires X API write access.
- In testing, access led to the X payment/API plan flow.
- The connector should not be exposed until it has been verified with a suitable paid API plan and write permissions.

The code may remain in the package for later reactivation, but it is intentionally not part of the channel configuration UI in this release.
