# API Changes — v1.7.1

Tracks API param/response/behavior changes shipped in v1.7.1.
Matches `database/migrations/2026_09_08_000000_1_7_1_version.php`.

---

## Newly added response fields

### `GET get_articles`
- **Change:** added `categories` array (id, category, translated_name, slug_id, image, articles_count) to every response — always the full category list, `"General"` (id: 0, uncategorized articles) prepended first. Same on listing and single-article (`id`/`slug_id`) calls.
- **Reason:** avoids a separate `get_categories` call for the Articles page sidebar.
- **Impact:** counts respect `X-Country-Id`. `id: 0` ("General") is synthetic, not a real category row — don't treat it as one elsewhere. "Uncategorized" is matched against `null`, `''`, and `'0'` since the DB stores it inconsistently.
- **Date:** 2026-09-11

## Fixed behavior (no shape change)

### `GET get_articles`
- **Change:** global articles (`country_id = null`) no longer 404 when opened under a specific country.
- **Reason:** the single-article lookup used a naive `!=` check that treated `null` as a mismatch.
- **Impact:** no param/response change. Just works correctly now.
- **Date:** 2026-09-11
