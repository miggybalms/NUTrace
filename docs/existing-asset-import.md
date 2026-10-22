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
| `assets.Asset_code` | the code printed on the sticker and signed into its payload. **Never** a property number. |
| `assets.department_id` (new) | the department the *asset* belongs to, independent of who holds it. |

Three identifiers are kept apart on purpose:

1. `assets.id` — NU Trace's own row id.
2. `assets.Asset_code` — the office's custom code, printed on the sticker.
3. `assets.property_id_no` — the client's **PROPERTY ID NO**, the identifier used
   to recognise the same physical asset again. `assets.inventory_id_no` keeps the
   spreadsheet's **ID NO.** as a secondary check only.

The spreadsheet's `ID NO.` is not `assets.id`, and `PROPERTY ID NO` is never
written into `Asset_code`.

## 2. What the file's columns map to

| Source column | NU Trace field | Notes |
|---|---|---|
| `PROPERTY ID NO` | `assets.property_id_no` | kept as text (`00014399` stays as written), lower-cased only for comparison |
| `ID NO.` | `assets.inventory_id_no` | text, secondary identity check |
| `PO NO` | `assets.po_no` | text; values such as `Donation` are kept verbatim |
| `DESCRIPTION` | `assets.Asset_name` | this is the asset name |
| `DATE ACQUIRED` | `assets.accusion_date` | Excel date styles resolved, so `19-Jan-22` becomes `2022-01-19` |
| `LOCATION` | `assets.asset_location` | |
| `DEPARTMENT` | `assets.department_id` | matched against `departments.Name`, never created on the fly |
| `TYPE OF ASSET` | `assets.Category` | matched to the eight categories the schema accepts |
| `UNIT COST` | `assets.purchase_Price` | parsed as a number; `accusion_cost` is left empty |
| `ACCOUNTABLE PERSON` | `assets.user_id` + `assets.accountable_name` | see §6 |
| *(optional)* `ASSET CODE` | `assets.Asset_code` | only when the workbook already carries the office's custom codes |

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

## 4. Custom asset codes

The client's sheet has no Asset Code column. For every line without a code:

* the line is **Blocked** and listed under *Decisions the file needs from you*;
* the page offers a pattern (`{property_id}`, `{inventory_id}`, `{seq}`,
  `{index}`, `{year}`) to fill the blanks, or the code can be typed per line;
* every code is checked against `Asset_code` (existing assets, other lines in the
  same file, and the reserved/unusable formats `AssetCode` already rejects);
* nothing is invented without the office pressing the button, and a code cannot
  be changed later from the asset page — that is a sticker reissue.

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
registration, so it gets the same signed payload (`QrPayload`), the same 50 × 25 mm
sticker, and it appears in

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

The only hard limit is the upload itself: `ASSET_IMPORT_MAX_MB` (default 200 in
`config/asset_import.php`) and PHP's `upload_max_filesize` / `post_max_size`,
whichever is smaller — the page prints the real number. For a ~300 MB workbook
either raise both (`php.ini`: `upload_max_filesize = 300M`,
`post_max_size = 310M`) or split it by year/department; re-importing is safe
because already-registered property numbers are skipped.

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
2. Check the detected columns, fix any that are wrong, **Check the rows**.
3. Work through *Decisions*: departments, `TYPE OF ASSET`, ambiguous names, and
   fill Asset Codes (pattern or by hand). Apply and re-check.
4. Filter by **Blocked / Review / Exists**, inspect the lines, then **Import**.
5. Print the stickers from the result card, or open Assets to see them listed.
6. **Retry failed lines** / **Generate missing stickers** are safe to repeat;
   **Start over** discards the staging rows and keeps anything already imported.

## 11. Tests

`tests/Feature/AssetImportTest.php` — 26 tests covering column detection, the
template, access control, a full import, duplicate and same-file skips, blank
property numbers, department/category matching and decisions, the five
accountable-person outcomes, custom codes and collisions, code patterns,
re-import idempotence, retry without duplication, xlsx dates and text
identifiers, and the asset-page edit guards.

Run them with `php artisan test --filter=AssetImportTest`.
