# Social Media Scheduler v0.6.4 Release Notes

## Overview

Version 0.6.4 fixes HTML entity handling in text-oriented channel payloads.

The main visible fix is Telegram plain mode: words such as `für` should no longer appear as `f&uuml;r`.

## Fixed

### HTML entities in Telegram plain mode

ConcreteCMS editor content may contain HTML entities such as `&uuml;`, `&auml;` or `&amp;`. These entities are valid in HTML, but Telegram plain text does not render them like a browser.

The Telegram sender now decodes entities before sending plain text, captions, MarkdownV2 text or HTML-mode text.

## Broader text normalization

The same normalization is applied to other text-oriented channel payloads:

- Telegram message text and captions
- Matrix plain `body` text
- Bluesky post text and image alt text
- Mastodon status text and media descriptions
- Webhook `posting.subject` and `posting.content_text`
- Listmonk campaign subject

Listmonk HTML body content is intentionally left as HTML so e-mail clients can render it normally.

## New Internal Helper

Added:

```text
src/Service/Channel/TextNormalizer.php
```

This helper centralizes:

- HTML entity decoding
- HTML-to-text conversion
- safe HTML escaping for channel payloads

## Compatibility

This release does not change:

- ORM entities
- database schema
- scheduling logic
- end date behavior from v0.6.3
- configured channels
- media handling rules
- dashboard page structure

## Upgrade Notes

No database migration is required for v0.6.4.

After upgrading, test at least one Telegram plain-mode post containing German umlauts, for example:

```text
für Gäste, schöne Grüße, Übernachtung
```

Expected Telegram output:

```text
für Gäste, schöne Grüße, Übernachtung
```

not:

```text
f&uuml;r G&auml;ste, sch&ouml;ne Gr&uuml;&szlig;e
```
