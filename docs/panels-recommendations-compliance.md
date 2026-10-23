# Panel recommendations — compliance check (prototype / system)

Source form: `Updated_Panels_Recommendations_Prototype_Only.docx`
(Presentation 12 October 2026 · Capstone Project 2 · NU Lipa's Centralized Asset
Lifecycle System Integrating QR Technology)

This is the working record behind the filled-in compliance form. Each row names
what exists in the system today and where it lives. The panel revised the form
on 23 October (22 rows → 19: the printer-specification, printing-supplies and
asset-category items were dropped). The filled form
`Updated_Panels_Recommendations_Prototype_Only_COMPLIED.docx` next to the
original was written against the earlier list, so it needs regenerating for the
19-row version; the original file was never modified, and the panel-initial
column is left blank for signatures.

Checked against the code on 23 October 2026.

| # | Recommendation | Panelist | Done | Where it lives |
|---|---|---|---|---|
| 1 | Improve loading time and responsiveness for large lists of assets and QR codes | Aramil | **Y** | Filtering/paging in SQL — 10 assets/page (employee, department-head, department pages), 15 requests/page; lazy photos; indexes on `property_id_no`, `department_id`, `inventory_removed_at`. `/admin/assets` is a department index whose totals and lifecycle splits come from one `GROUP BY` rather than loading every asset (the full registry is the CSV download), so the page runs the same number of queries at 60 assets or 60,000 (`AdminAssetOverviewTest`) |
| 2 | Monthly filtering and sorting for request records | Menisis | **Y** | `/admin/requests` — **Period** (All time / This month / Last month / This year / Last year / Custom range) and **Sort** (Pending first, Newest first, Oldest first), both applied in SQL before the page is cut (`app/Support/RequestFilters.php`), combined with the status tab; **Export Report** covers the same rows in the same order (`tests/Feature/RequestFilterSortTest.php`) |
| 3 | Reports for newly acquired / repaired / disposed assets by month, year or date range | Pastrana | **Y** | `/admin/reports`, `/department-head/reports` — 6 report types, presets This month / Last month / This year / Last year / Custom (Reports.php) |
| 4 | CSV export for authorized users | Aramil | **Y** | `/admin/reports/export`, `/admin/inventory-download`, `/admin/requests/export`, audit-log CSV |
| 5 | Bulk QR printing, assets selectable by location | Pastrana | **Y** | `/admin/assets/department/{id}` → Print Labels (one sticker per asset matching Location/Category/Status/Search); label sheet by codes at `/admin/assets/labels` |
| 6 | QR sticker design: NU logo (transparent), asset code, location | Aramil | **Y** | `resources/views/partials/asset_label.blade.php` — NU seal (RGBA PNG), name, code, location, department |
| 7 | Printing settings: sticker dimensions, code size, page layout, asset selection | Aramil | **Y** | Web only (not the mobile app). Print-ready page: **Printing settings** panel — Sticker width / height, QR code size slider (17–35 mm), Layout (Label roll / A4 sheet), Refresh / Reset. Applied live and validated server-side (`AssetLabel::settings()`, clamped; sizes below the printable minimum warn instead of printing an unreadable sticker); kept for the session. Defaults live in `config/asset_label.php`. Asset selection comes from the filters or the codes list |
| 8 | Digital disposal form with supporting documents attached | Pastrana | **Y** | `/admin/disposal` → Forms; `DisposalForms.php`, `DisposalDocuments.php` (PDF/JPG/PNG/WEBP, 5 MB), signed scan recorded |
| 9 | Module to migrate existing university asset records | Pastrana | **Y** | `/admin/assets/import`; `docs/existing-asset-import.md` — upload, map, review, commit in batches, audit |
| 10 | Bulk CSV import of faculty-member records | Pastrana | **Y** | `/admin/employees` → Import, with preview; `EmployeeDirectory.php` |
| 11 | Digitally signed QR authenticity checks | Pastrana | **Y** | `QrSigner.php`, `QrPayload.php` (NUT1, Ed25519), `QrVerification.php`; scanner states Verified / Unsigned / Failed authenticity / Unknown key / Revoked |
| 12 | Disposal notifications; confirm the three-year rule first | Pastrana | **Y** | Requester + officers notified on approval, requester again on finalization; 3-year mark is an advisory Service-life review list (`DISPOSAL_AGE_REVIEW_YEARS`), not enforced |
| 13 | Required approval workflow; no editing until approved | Aramil | **Y** | `/admin/requests` → Approve/Reject, Asset-Office only, Pending only; self-approval refused and audited (`Approval.php`); submitted requests have no edit path |
| 14 | Separate accounts and role-based permissions | Aramil | **Y** | Admin / Department Head / Employee roles; `RequireAdminArea.php`, `Access.php`; accounts created in `/admin/employees` |
| 15 | Optimize DB queries for asset retrieval, monthly filtering, reports | Menisis | **Y** | SQL-side filtering/paging; indexes on `property_id_no`, `department_id`, `inventory_removed_at`, `notifications(user_id,is_read)`, `request_items.asset_id`; listing measured ~1.8 ms over 400 assets (`AssetListingPerformanceTest.php`) |
| 16 | Photographs mandatory where photographic evidence is required | Aramil | **Y** | Web: every request from the employee and department-head request pages must carry a photo — checked in the form and again on the server; the photo shows in the office's Request Details panel and the disposal record. Mobile: the Submit Request screen marks the photo required and refuses to submit without it or if the upload fails, and `submitUserRequest` rejects a request with no photo, so the rule holds on the phone too. (The office's own end-of-lifespan disposal action is not a user request and collects no photo.) 5–10 MB per image |
| 17 | Better disposal handling and notifications after approval | Aramil | **Y** | Approval → form preparation (requester + officers notified); finalization records the disposal, drops the asset from inventory, notifies the requester |
| 18 | Reporting for acquired / repaired / disposed assets, monthly and yearly summaries | Pastrana | **Y** | Six reports with month/year presets, totals and a printable sheet; same criteria feed CSV |
| 19 | Lazy loading where appropriate | Aramil | **Y** | `loading="lazy"` on asset photos in `resources/views/users/asset/asset.blade.php` and `department_head/asset/asset.blade.php` |

## Score

- **19 fully implemented**
- **0 partial, 0 not implemented**

#12's second half stays a policy question rather than a code gap: the three-year
mark is advisory until the office decides whether a younger asset may be
disposed of, and how that override is recorded.

