# APPENDIX B — Relevant Source: where each section's code lives

Two codebases, one appendix. Every line range below was taken from the working copy and is
reproduced **verbatim** in `APPENDIX B. RELEVANT SOURCE_FILLED.docx`:

- **WEB** — `~/projects/NUTrace` (Laravel / PHP). Section "Source code: WEB".
- **MOBILE** — `C:\capstone\assetapp_mobile\assetapp` (React Native / Expo, TypeScript).
  Section "Source code: MOBILE".

## WEB

| Section | File | Lines | What the excerpt shows |
|---|---|---|---|
| B.1 QRBarcode Asset Identification | `routes/web.php` | 906–991 | Lookup endpoint the scanner calls: resolves a scanned code (case-insensitive), returns name, status, department, owner, location, image |
| B.1 | `app/Support/Media.php` | 275–317 | `generateQr()` — builds the QR PNG for an Asset Code and stores it on the media disk |
| B.1 | `resources/views/admin/dashboard.blade.php` | 494–560 | Camera scanning in the browser (html5-qrcode, jsQR fallback) and the lookup call |
| B.1 | `resources/views/admin/assets/asset_registry.blade.php` | 715–760 | QR label rendered live from the Asset Code on the registration form |
| B.2 Bulk Asset Registration | `routes/web.php` | 4574–4621 | Form validation — including `quantity` (1–100) and the Asset Code mode |
| B.2 | `routes/web.php` | 4623–4663 | Auto-generated vs. custom Asset Codes; custom codes are verified before anything is written |
| B.2 | `routes/web.php` | 4697–4714 | QR label saved for each registered asset |
| B.2 | `routes/web.php` | 4716–4791 | The per-unit loop: one asset row, one audit entry, one QR label per unit |
| B.2 | `app/Support/AssetCode.php` | 126–188 | Availability check and validation (no duplicate code, no repeat inside one submission) |
| B.3 Asset Lifecycle Management | `routes/web.php` | 1420–1496 | Automatic transition to “For Checking” when the lifespan expires or maintenance is overdue |
| B.3 | `routes/web.php` | 1499–1570 | Maintenance completion + next maintenance date recalculated from the interval |
| B.3 | `routes/web.php` | 1627–1689 | Lifespan evaluation decisions (return to Active, repair, replacement, disposal) |
| B.3 | `app/Models/Asset.php` | 1–50 | Asset model: lifecycle fields, date casts, in-inventory scope |
| B.4 Repair and Replacement Workflow | `routes/web.php` | 2996–3080 | Repair request: request row + repair row + lifecycle change + audit entry |
| B.4 | `routes/web.php` | 3439–3509 | Repair completion — the result decides whether the asset returns to Active |
| B.4 | `routes/web.php` | 3552–3615 | Repair converted to Replacement, repair closed, owner notified |
| B.5 Disposal and Archiving | `app/Support/Disposals.php` | 149–184 | A disposal record is created from the approved request |
| B.5 | `app/Support/Disposals.php` | 186–263 | Archiving — one transaction archives the record and removes the asset from the inventory |
| B.5 | `routes/web.php` | 2541–2607 | Archive endpoint (Admin only) + audit entry |
| B.6 Bulk Pullout | `routes/web.php` | 62–98 | `createPulloutTransaction()` — one pullout row + one `pullout_items` row per asset |
| B.6 | `routes/web.php` | 5412–5474 | Bulk pullout endpoint: validates every asset, rejects unavailable ones, records the set |
| B.7 Employee Transfer and Accountability | `app/Http/Controllers/TransferController.php` | 1–135 | Who holds which assets, and the confirmation |
| B.7 | `app/Support/AssetTransfer.php` | 293–355 | The transfer itself — assets are re-assigned, never re-created |
| B.7 | `app/Support/AssetTransfer.php` | 449–502 | Accountability history: previous holder closed, new holder opened, per asset |
| B.8 Audit Logging | `app/Support/AuditTrail.php` | 1–67 | Sign-in / sign-out events written to the audit trail (no IP or hostname) |
| B.8 | `app/Http/Middleware/LogUserLogin.php` | 1–34 | Middleware that records a successful sign-in once per session |
| B.8 | `app/Providers/AppServiceProvider.php` | 35–40 | Sign-outs recorded through the Logout event (covers every exit route) |
| B.8 | `routes/web.php` | 1190–1284 | Audit log page: filters, search, pagination, and removal of any leftover address |
| B.9 Authentication and Employee ID Validation | `routes/web.php` | 133–159 | Sign-in and role-based landing (Admin / Department Head / Employee) |
| B.9 | `routes/web.php` | 183–264 | Registration: the employee number must exist in `employee_numbers`, and can register only once |
| B.9 | `routes/web.php` | 100–130 | Password policy helper — rejects passwords built from the name, email or employee number |
| B.9 | `app/Models/EmployeeNumber.php` | 1–18 | Employee number model — the roster row a user account is bound to |

## MOBILE

| Section | File | Lines | What the excerpt shows |
|---|---|---|---|
| B.1 QRBarcode Asset Identification | `components/asset-scanner.tsx` | 29–75 | The scanned payload is decoded to an asset key and resolved against the live `assets` table |
| B.1 | `components/asset-scanner.tsx` | 91–143 | One scan fires once; the resolved asset record is opened |
| B.1 | `components/QRViewModal.tsx` | 135–154 | The QR sticker rendered from the Asset Code value |
| B.2 Bulk Asset Registration | `lib/assetCode.ts` | 15–109 | Asset-code rules: auto codes, the office's own codes, the duplicate guard |
| B.2 | `lib/assetService.ts` | 527–624 | Registering one asset: asset row, photo record, audit entry |
| B.2 | `lib/assetService.ts` | 626–659 | Bulk duplicate guard — every planned code checked against `assets.Asset_code` first |
| B.2 | `app/asset-registry.tsx` | 245–337 | The registry plans a batch's codes and validates them before saving |
| B.3 Asset Lifecycle Management | `lib/lifecycle.ts` | 17–67 | Which lifecycle states a requester may see, and why an asset is unavailable |
| B.3 | `lib/lifecycle.ts` | 69–116 | Maintenance eligibility per status; the status each request type produces |
| B.3 | `lib/maintenanceService.ts` | 290–356 | Recording preventive maintenance: completion date, next schedule, audit trail |
| B.3 | `lib/maintenanceService.ts` | 431–524 | Asset evaluation run for expired lifespan / overdue maintenance |
| B.4 Repair and Replacement | `lib/repairService.ts` | 607–692 | Repair submission: validation, request row, one repairs row per asset |
| B.4 | `lib/repairService.ts` | 780–869 | Admin decision on a repair: status, cost, result, fields written back |
| B.4 | `lib/assetService.ts` | 1055–1113 | Repair sent to replacement: the new asset is created and linked to the old one |
| B.5 Disposal and Archiving | `lib/disposalService.ts` | 379–461 | Archiving a disposal record — asset leaves the inventory, record is kept |
| B.5 | `lib/disposalService.ts` | 560–633 | Creating the disposal record(s) and flagging the asset as Disposal |
| B.6 Bulk Pullout | `lib/pulloutService.ts` | 69–174 | Bulk pullout: one request, one pullout row per asset, one audit entry per asset |
| B.7 Employee Transfer and Accountability | `lib/transferService.ts` | 311–403 | Employee transfer: request created first, then the assets are repointed |
| B.8 Audit Logging | `lib/auditService.ts` | 39–67 | Network details stripped out of audit text before an admin can read it |
| B.8 | `lib/auditService.ts` | 88–109 | A mobile sign-in / sign-out written into the shared audit trail |
| B.8 | `lib/auditService.ts` | 139–166 | Appending to `audit_logs`, best-effort so logging never blocks the action |
| B.9 Authentication and Employee ID Validation | `app/login.tsx` | 29–83 | Sign-in: credentials verified in the database, role chooses the landing area |
| B.9 | `lib/userService.ts` | 928–1005 | Employee-number lookup — the number must be on file and the row still active |
| B.9 | `lib/userService.ts` | 1090–1170 | Creating the account against the employee record, behind the duplicate checks |
| B.9 | `lib/passwordHash.ts` | 1–37 | Password hashing both the mobile app and the Laravel web app can verify |

## Notes for the write-up

- The web application is Laravel, so most request logic lives in `routes/web.php`; the reusable parts
  live in `app/Support/*` (business rules) and `app/Http/Controllers/*` (Transfer, UserRequest).
- B.1 has two halves worth showing: the **identification** half (scan → lookup, server side) and the
  **label** half (`Media::generateQr` + the on-screen QR). The lookups are case-insensitive, which is
  why a scanned lower-case code still finds the asset.
- B.2 is one route: `POST /admin/assets`. The loop at 4716 is what makes it *bulk* — the same form
  registers 1 to 100 units, each with its own code and QR label.
- B.5 draws a deliberate line: archiving never deletes. Only a separate, explicit delete on an
  already-archived record removes rows, which is why the archive path is the one quoted for
  “Disposal and Archiving”.
- B.8 and B.9 overlap in file but not in purpose: B.8 is *who did what* (audit trail), B.9 is *who may
  get in* (authentication + employee-number validation).
- The two clients share one database, so the mobile excerpts frequently mirror the web ones:
  `lib/disposalService.ts` states in its own comment that it “mirrors the web's `Disposals::archive()`”.
  That is deliberate — the appendix shows the same rule enforced on both clients.
- Mobile reads and writes go through Supabase / PostgREST directly; the web goes through Laravel.
  Where the mobile code says “spec §…”, it is quoting the functional specification the two clients
  are built against.
- Two mobile files are referenced from more than one section (`lib/assetService.ts` for B.2 and B.4,
  `lib/userService.ts` for B.9) because a registration and a replacement both create an asset row.
