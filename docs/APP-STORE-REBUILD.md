# HENDY App Store — separated storefront, API and security

## Runtime boundaries

- **Storefront:** `website/public/app-store/` contains public HTML, CSS and browser JavaScript only. It has no database credentials, payment logic, Admin session or server-side secrets.
- **API transport:** `website/public/app-store/api-client.js` is the only App Store browser transport. It uses the versioned read-only endpoint `GET /api/v1/store/releases`, rejects unknown backend origins, sends no cookies, disables browser caching and times out stalled requests.
- **Release policy:** `website/public/app-store/security.js` validates download links, supported platform/extension combinations, safe filenames and SHA-256 values before a card can be rendered.
- **Application API:** The existing PHP 8.3 API remains the trusted server boundary and reads the current PostgreSQL product catalogue. The public route exposes only published releases that pass Product Supplier Readiness. Supplier credentials, private metadata, customer records, wallet information and orders are not returned.
- **Admin:** Release upload, publication and hide controls remain behind the existing Telegram Admin authentication at `/admin` and the private `/api/admin/*` API. They are not bundled into the public App Store page.
- **Storage:** Release binaries remain private in Neon Object Storage. The public API issues a short-lived signed URL only for a release that passed the publication gate. API responses and browser requests are not cached because signed URLs expire.

## API contract and compatibility

New storefront endpoint:

```http
GET /api/v1/store/releases
Accept: application/json
```

Optional query parameter: `category` (tools, codes, vip, minigame, otp, proxy).

The response contract remains:

```json
{
  "ok": true,
  "releases": [],
  "generatedAt": "ISO-8601 timestamp"
}
```

The older `GET /api/public/releases` and `GET /api/public/releases/check` routes remain available as compatibility aliases. Existing bot, updater or external clients are not forced to migrate in the same deployment.

## Browser security controls

1. The storefront uses a dedicated API client with `credentials: omit`, `cache: no-store`, JSON content-type checking, a 15-second abort timeout and a 250-item response ceiling.
2. The API base URL can only be the same origin or the approved HENDY Render Bot API host. Arbitrary remote API origins are rejected.
3. The separate release policy rejects non-HTTPS links, URL credentials, protocol-relative URLs, unsupported platform/file combinations, unsafe file names and invalid SHA-256 digests.
4. UI text is rendered through DOM `textContent`; server-provided product text is never concatenated into HTML.
5. The Render Static Site applies a dedicated Content Security Policy to `/app-store/*`. It permits self-hosted scripts, the current Font Awesome CDN, HTTPS product images/download links and the HENDY API host; it blocks objects and framing.
6. The API remains read-only for anonymous visitors. Admin mutations require the server-side Telegram Admin allowlist and are audited by the current application.

A SHA-256 checksum verifies file integrity against the published digest; it does not prove that an artifact is malware-free, signed by its publisher or accepted by Apple/Microsoft/Google.

## Data and deployment safety

- No new database schema or production migration is required.
- Existing `products.metadata.storeRelease` records and release history are retained.
- Existing Neon Object Storage objects are not moved or deleted.
- No wallet, payment, member or order data is changed.
- No Render Environment variable or Neon credential is added to Git.
- Render Static Site publishes only `website/public/`; the Bot/API service continues to use `telegram-bot/Dockerfile` and its current Neon connection.

## Ubuntu/VPS configuration decision

This project has no separate Ubuntu VPS provisioning script or Ubuntu server service in the active Render Blueprint. The `ubuntu-latest` label in GitHub Actions refers to GitHub-hosted CI runners, not an HENDY production server, and is retained so tests can run. The PHP backend is containerized for Render; its Docker base is `php:8.3-apache`, not an Ubuntu host. Debian package installation inside the image is required for PHP extensions and existing OCR/media workers, so it is not removed as part of a Render deployment.

Do not remove the Render Docker runtime or its worker dependencies merely because deployment no longer targets an Ubuntu VPS: the current backend depends on PHP, PostgreSQL, Telegram webhook handling, private storage and optional background workers.

## Validation

CI runs:
- Browser JavaScript syntax validation including the API/security modules.
- Node unit tests for URL, filename, checksum, backend-origin, request credential/cache and malformed-response policy.
- PHP lint and the existing integration suite, including old-route compatibility.
- Neon storage build/tests.

The deployment remains gated by Render's existing `autoDeployTrigger: checksPass`. A green GitHub CI run does not by itself prove a Render deployment or a live release object exists.
