## Problem

A research project can only cite what is already in the archive.

`research_project_resource` has carried `external_url`, `link_type` and `link_metadata` since the table was created, and `resource_type` has listed `external_link` and `document` among its permitted values the whole time. Nothing in the interface ever wrote either. Two templates read `external_url` and render it if present — so the display side was built and the entry side never was.

There is also no way to attach a document. A researcher working on a project keeps their own PDFs, transcriptions, working spreadsheets and correspondence somewhere else entirely, and the project record holds no trace of them.

## What was built

**Link an external source.** URL, optional title, a source type (`academic`, `archive`, `database`, `government`, `website`, `social_media`, `other` — the values already in the column comment), and optional tags. Stored as `resource_type = external_link` using the existing columns; no new modelling was needed for this half.

**Upload a document.** Stored under `uploads/research/project-documents/<project_id>/`, with four new columns on the same table:

| Column | |
|---|---|
| `file_path` | path under `uploads/`, set only when `resource_type = document` |
| `file_name` | the original filename, for display and download |
| `file_size` | bytes, as accepted |
| `mime_type` | detected from magic bytes, not the browser's claim |

Both appear in one panel on the project page, listed together with a download link or an outbound link, the size, the tags, and a remove control.

## The size limit

Admin-settable as `research_document_max_mb` (default 20), because the right ceiling is a local policy question — disk, what researchers actually deposit, what the server permits — not something to hardcode.

**Then clamped to what PHP will actually accept:** `min(configured, upload_max_filesize, post_max_size)`.

That clamp is the part worth keeping. A form advertising 20 MB on a server whose `post_max_size` is 8 MB fails in the worst available way: PHP discards the request body before any application code runs, so the action sees an empty `$_POST`, and the obvious message — "choose a file" — sends the researcher looking in exactly the wrong place. The upload action detects that signature (a POST that arrived with nothing in it) and reports the size limit instead.

The same computed figure drives three things, so they cannot drift apart:

- the visible "Maximum 20 MB per file" help text
- the form's `MAX_FILE_SIZE`, so the browser can refuse early
- the server-side check, which enforces it regardless

## Validation

Uploads go through the framework's `FileValidationService` rather than a hand-rolled check — extension allowlist, size, and MIME from magic bytes rather than the `Content-Type` the browser claims. Written by hand this would have been the sixth copy of that logic in the repository.

Allowed: PDF, Word, OpenDocument, RTF, text, Markdown, CSV, spreadsheets, presentations, common image formats, and zip.

Other decisions worth recording:

- **Stored filenames are generated** (`doc_<researcher>_<time>_<random>.<ext>`); the original is kept in `file_name` for display and download only.
- **URLs are restricted to `http` and `https`.** `filter_var($url, FILTER_VALIDATE_URL)` alone accepts `javascript:` and `data:`, and these values are rendered as an `href`.
- **Removal deletes the file before the row**, and only within `uploads/research` after a `realpath` check. The other order leaves a row pointing at nothing on failure; worse, an orphaned file with no row is invisible and accumulates.
- **Resource ownership is re-checked** against the project on removal rather than trusted from the request, so a researcher cannot delete another project's resource by guessing an id.
- **Access control is `ProjectService::getProject($id, $researcherId)`**, which returns null when the researcher may not see the project — the same gate the project view itself uses, rather than a second interpretation of who may edit what.

## Verified

Routes resolve to the action and hit the authentication gate; a deliberately nonexistent route under the same prefix returns 404 with the error page, which is the control that makes the other three meaningful:

```
/research/project/1/link                    200  user login
/research/project/1/document                200  user login
/research/project/1/resource/remove         200  user login
/research/project/1/definitely-not-a-route  404  admin error
```

Schema applied, upload directory created and owned by the web user, no errors logged.

**Not yet verified end to end.** Uploading a file and linking a source both need an authenticated researcher session, which this work did not have. What remains untested is the round trip: upload → row written with the right size and MIME → download link serves the file → remove deletes both row and file. Also untested is the oversize path against a server whose `post_max_size` is genuinely lower than the configured limit — the branch exists and is reasoned about above, but it has not been observed firing.
