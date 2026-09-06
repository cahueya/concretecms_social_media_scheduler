# Channel Documentation

Version: **0.6.6**

## General content mapping

| Field | Meaning |
|---|---|
| Title | Internal dashboard title; never sent. |
| Subject | Public subject or leading social/messenger text. |
| Body | Rich-text editor content. |
| Body media | Images inserted into the body. |
| Explicit attachments | Files selected with the ConcreteCMS File Manager. |

Telegram, Matrix, Bluesky and Mastodon use body images by default and only include explicit attachments when that channel option is enabled. Listmonk uses both according to its email/media behavior. Webhook always receives attachment metadata and may additionally transfer attachment content depending on the configured attachment mode.

## Telegram

Required:

- Bot token
- one or more chat/group/channel IDs

Options include parse mode, link-preview behavior and explicit attachments.

Telegram chat discovery uses `getUpdates`. The server must be able to reach the Telegram Bot API.

Sending behavior:

- subject and body form the message;
- a single suitable image may carry the message as caption;
- otherwise text and media are sent separately;
- body images are considered media;
- explicit attachments are optional.

## Listmonk

Required:

- base URL
- API username
- API token/password
- one or more list IDs

Optional settings include template ID, from address, messenger and initial campaign status.

Sending behavior:

- subject becomes the campaign subject;
- body remains HTML;
- editor images are uploaded to Listmonk media and their body URLs are replaced;
- explicit attachments are uploaded and referenced as campaign media;
- campaign can remain draft or be moved to the configured start status.

## Matrix

Required:

- homeserver URL
- access token
- one or more room IDs

Options include Matrix message type and explicit attachments.

Sending behavior:

- text is sent first;
- local editor image tags are removed from the text body;
- body images are uploaded to the Matrix media repository and sent as media events;
- explicit attachments are optional.

## Generic Webhook

Required:

- webhook URL

Options:

- method: POST, PUT or PATCH
- payload mode: JSON, form or multipart
- attachment mode: URLs, Base64 or multipart
- authentication: none, Basic or Bearer
- additional headers

Current structured payload:

```json
{
  "posting": {
    "title": "Internal title",
    "subject": "Public subject",
    "content_html": "<p>...</p>",
    "content_text": "..."
  },
  "attachments": [
    {
      "filename": "document.pdf",
      "title": "Document",
      "mime_type": "application/pdf",
      "size": 12345,
      "url": "https://example.com/application/files/.../document.pdf"
    }
  ],
  "source": {
    "system": "concretecms",
    "package": "social_media_scheduler"
  }
}
```

When attachment mode is `base64`, readable local files additionally receive a `base64` field. With multipart transfer, readable local files are sent as multipart file fields in addition to the serialized payload.

If an `Authorization` header is supplied manually, it takes precedence over generated Basic/Bearer authentication.

## Bluesky

Required:

- handle
- App Password
- PDS/API service URL

For standard Bluesky accounts use:

```text
https://bsky.social
```

`https://bsky.app` is the web UI and is normalized to `https://bsky.social`.

Sending behavior:

- subject and body become normalized plain post text;
- body images are uploaded as blobs;
- up to four images are embedded;
- explicit attachments are optional;
- oversized/non-uploadable images fail with a clear error.

Use an App Password rather than the main account password.

## Mastodon

Required:

- instance URL
- access token with write permissions

Options:

- visibility
- language
- sensitive-media flag
- content warning
- explicit attachments

Sending behavior:

- subject and body become normalized status text;
- images are uploaded through the Mastodon media API and attached to the status;
- explicit attachments are optional.

## X / Twitter

X/Twitter is intentionally **not selectable in 0.6.6**.

`src/Service/Channel/XSender.php` remains in the codebase for possible future reactivation, but it is not registered in `SenderRegistry` and no X configuration UI is exposed. Reactivation should only happen after verified API write access and end-to-end testing against the chosen X API plan.
