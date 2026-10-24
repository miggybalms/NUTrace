# Importing the existing Fixed Asset Registry

Admin → **Assets** → **Import Inventory** (`/admin/assets/import`).

The page takes the Asset Management Office's *Fixed Asset Registry* (.xlsx or .csv),
shows what every line means, and writes nothing into `assets` until the office
presses **Import**. Every imported asset gets its own signed QR sticker and can
then be scanned and edited anywhere else in NU Trace, including the mobile app.

---

## 1. Schema relationships this relies on

| Relationship | Meaning |
|---|---|
| `assets.user_id → users.id` | the **accountable account**. Now nullable: an imported asset may be accountable to nobody yet. |
| `users.employee_numbers_id → employee_numbers.id` | one masterlist person ↔ their login(s). |
| `employee_numbers.Department_id → departments.id` | where the person is posted (not necessarily where the asset is). |
| `users.department_id → departments.id` | the account's department, used for access. |
| `asset_accountability` | assignment history. An import writes one `Is_Current = 1` row when it assigns somebody. |
| `assets.Asset_code` | the code printed on the sticker and signed into its payload. It is the client's **PROPERTY ID NO** unless the file carries its own `ASSET CODE` column. |
| `assets.department_id` (new) | the department the *asset* belongs to, independent of who holds it. |

Three identifiers are kept apart on purpose:

1. `assets.id` — NU Trace's own row id.
2. `assets.property_id_no` — the client's **PROPERTY ID NO**, the identifier used
   to recognise the same physical asset again. It is also written to
   `assets.Asset_code` (§4), so the sticker carries the number the office already
   keeps on the asset.
3. `assets.inventory_id_no` — the spreadsheet's **ID NO.**, a secondary check only.

The spreadsheet's `ID NO.` is not `assets.id` and is never used as the asset code.
`PROPERTY ID NO` is.

## 2. What the file's columns map to

| Source column | NU Trace field | Notes |
|---|---|---|
| `PROPERTY ID NO` | `assets.property_id_no` **and** `assets.Asset_code` | kept as text (`00014399` stays as written), lower-cased only for comparison; it is the code printed on the sticker |
| `ID NO.` | `assets.inventory_id_no` | text, secondary identity check |
| `PO NO` | `assets.po_no` | text; values such as `Donation` are kept verbatim |
| `DESCRIPTION` | `assets.Asset_name` | this is the asset name |
| `DATE ACQUIRED` | `assets.accusion_date` | Excel date styles resolved, so `19-Jan-22` becomes `2022-01-19` |
| `LOCATION` | `assets.asset_location` | |
| `DEPARTMENT` | `assets.department_id` | matched against `departments.Name`, never created on the fly |
| `TYPE OF ASSET` | `assets.Category` | matched to the eight categories the schema accepts |
| `UNIT COST` | `assets.purchase_Price` | parsed as a number; `accusion_cost` is left empty |
| `ACCOUNTABLE PERSON` | `assets.user_id` + `assets.accountable_name` | see §6 |
| *(optional)* `ASSET CODE` | `assets.Asset_code` | overrides the property number when the workbook keeps a separate code of its own |

Headings are detected through a normalised alias list (`PROPERTY ID NO`,
`Property No.`, `PROPERTY ID NO. (old)` …) and every mapping can be corrected on
the page before checking. A recorded name that no heading matches is left
unmapped rather than guessed.

Defaults for an imported line: `Condition = Existing`, `Lifecycle_Status = Active`,
`completion_status` = `Complete`, `Needs completion` or `Under review` depending
on what is still missing.

## 3. Duplicate detection

* **Primary key: Property ID No.** Case-insensitive, compared against every row
  in `assets`. A property number already in NU Trace is marked **Exists** and
  skipped — never overwritten.
* **Same file twice:** the second occurrence is blocked, both lines are shown.
* **ID NO. on another asset:** only a **warning** (the office's list numbers can
  legitimately repeat), so the line can still be imported.
* **Blank Property ID No.:** blocked, unless the office explicitly ticks
  *Import lines with no Property ID No.* Those assets carry no duplicate key.
* **Race at import time:** the guards run again right before each `INSERT`, and
  on PostgreSQL a partial unique index on `LOWER(property_id_no)` is the last
  word. A lost race is recorded as **Exists**, not as an error.
* Re-uploading the same workbook therefore imports nothing new and creates no
  new stickers.

## 4. Asset codes come from the file

The client's sheet has no Asset Code column — its **PROPERTY ID NO** *is* the
number they keep on the asset. So that number becomes `assets.Asset_code`:

* the sticker prints the PROPERTY ID NO, and scanning it finds the asset by that
  code;
* a line with a property number never blocks for a missing code, and the page
  maps *Asset Code* onto the property column automatically;
* a separate `ASSET CODE` column, when the workbook has one, wins over the
  property number;
* only a line with neither (no property number and no code cell) is listed under
  *Decisions*, where a code can be typed or proposed from a pattern
  (`{property_id}`, `{inventory_id}`, `{seq}`, `{index}`, `{year}`);
* every code is checked against `Asset_code` (existing assets, other lines in the
  same file, and the formats `AssetCode` rejects);
* a code cannot be changed later from the asset page — that is a sticker reissue.

## 5. Department matching

`departments.Name` is compared normalised (case, punctuation and spacing
ignored). A single near match is **offered**, never applied, because departments
steer access and reports. An unmatched department blocks the line until the
office picks a department or ticks *Import lines whose department is not
matched*. No department is created by the importer.

`TYPE OF ASSET` is matched the same way against
`Furnitures and Fixtures`, `General and Office Equipment`, `Info and Equipment`,
`laboratory Apparatus and equipment`, `library books`, `Motor vehicles`,
`P.E Equipment`, `Low value Asset`, with aliases such as
`Furnitures and Fittings` read as `Furnitures and Fixtures`.

## 6. Accountable persons

A name is compared token-by-token, so `Atienza, Rio`, `Rio Atienza` and
`ATIENZA, RIO` are the same person. Then:

| Situation | Result |
|---|---|
| one active masterlist person with exactly one **Active** account | asset assigned; `asset_accountability` records it |
| masterlist person with **no login** | imported **unassigned**, name kept in `accountable_name` |
| masterlist person marked inactive | imported unassigned, name kept |
| login exists but is not **Active** | imported unassigned, name kept |
| two people share the name, or one person has two active accounts | **Blocked** until the office picks one or explicitly chooses *leave unassigned* |
| nobody on the masterlist matches | imported unassigned, name kept, flagged as a review note |

No user account is ever created to satisfy the import, and an employee id is never
written into `assets.user_id`. When the person later gets an eligible account, an
administrator assigns the asset from the asset page (the dropdown lists only
active accounts) — that is the existing editing path, not a second one.

## 7. QR stickers and the scanners

Each imported asset goes through the same `AssetQr::saveSticker()` used by normal
registration, so it gets the same signed payload (`QrPayload`) — carrying the
PROPERTY ID NO as the asset code — the same 50 × 25 mm sticker, and it appears in

* the Admin Asset Scanner and asset page,
* `/admin/assets/labels?codes=…` — the "Print stickers for this import" link,
* department asset lists (through `assets.department_id`),
* the mobile app, which reads the same payload.

If Supabase Storage is unreachable the asset is still saved; the row remembers
`qr_status = failed` and **Generate missing stickers** retries the image without
creating the asset again. **Retry failed lines** re-opens only rows whose write
failed, and the property-number guard stops a retry from duplicating an asset
that was in fact created.

## 8. Large workbooks

Nothing loads the whole file:

* an `.xlsx` is unzipped once into `storage/app/asset-imports/<batch>/`, then read
  in `</row>` windows; a `.csv` is streamed;
* each request checks up to `ASSET_IMPORT_CHECK_ROWS` (default 400) rows or
  `ASSET_IMPORT_CHECK_SECONDS` (8 s), then the page asks for the next window;
* each request writes up to `ASSET_IMPORT_IMPORT_ROWS` (100) assets or
  `ASSET_IMPORT_IMPORT_SECONDS` (12 s);
* progress is stored in `asset_import_batches` / `asset_import_rows`, so a reload
  or a lost connection resumes the same batch instead of starting over.

The upload itself has no size limit. A file larger than `ASSET_IMPORT_PART_MB`
is sent by the page in slices to `POST /admin/assets/import/part`; each slice is
retried on its own if the connection drops, and the server joins them in order
before reading anything. The slice size is automatically capped at
`min(upload_max_filesize, post_max_size) − 64 KB`, so the default PHP limits
(2 MB) need no `php.ini` change: a 1 GB registry is simply ~500 slices.

There is no file-size check at all (`ASSET_IMPORT_MAX_MB` is no longer read, so
a value left in a deployment's environment changes nothing). The real ceiling is
the disk the staging file is written to — staging is removed when the import
finishes, and parts of an abandoned upload are pruned after `stale_hours`
(48 hours by default).

## 9. Migrations

`php artisan migrate` runs two additive migrations:

* `2026_10_21_100000_add_import_fields_to_assets_table.php`
  adds `property_id_no`, `inventory_id_no`, `po_no`, `accountable_name`,
  `completion_status`, `department_id` (+ FK, index), makes `assets.user_id`
  nullable, indexes `property_id_no`, and on PostgreSQL creates the partial
  unique index `assets_property_id_no_unique`. It is skipped if existing rows
  already share a property number (the importer still flags those rows).
* `2026_10_21_100100_create_asset_import_tables.php`
  creates `asset_import_batches` and `asset_import_rows` (staging only).

Both have `down()` methods. No existing column is dropped or rewritten.

Every step is guarded, so `php artisan migrate` can simply be run again after a
failure. That matters on managed PostgreSQL (Supabase): its pooler does not keep
a migration's statements in one transaction, so a migration that fails halfway
leaves the columns, keys and indexes it already made in place while staying
unrecorded. Re-running finishes the job instead of failing on "already exists".

## 10. Routine

1. **Assets → Import Inventory**, choose the file, **Read headers**.
2. Check the detected columns, fix any that are wrong, **Check the rows**. Asset
   Code arrives already pointed at PROPERTY ID NO.
3. Work through *Decisions*: departments, `TYPE OF ASSET`, ambiguous names. A
   code only has to be entered for a line that has no property number either.
   Apply and re-check.
4. Filter by **Blocked / Review / Exists**, inspect the lines, then **Import**.
5. Print the stickers from the result card, or open Assets to see them listed.
6. **Retry failed lines** / **Generate missing stickers** are safe to repeat;
   **Start over** discards the staging rows and keeps anything already imported.

## 11. Tests

`tests/Feature/AssetImportTest.php` — 42 tests covering column detection (and
Asset Code defaulting to PROPERTY ID NO), an upload split into parts (joined
into one file, cleaned up, resumed by fingerprint), the
template, access control, a full import, duplicate and same-file skips, blank
property numbers, department/category matching and decisions, the five
accountable-person outcomes, custom codes and collisions, code patterns,
re-import idempotence, retry without duplication, xlsx dates and text
identifiers, and the asset-page edit guards.

Run them with `php artisan test --filter=AssetImportTest`.
