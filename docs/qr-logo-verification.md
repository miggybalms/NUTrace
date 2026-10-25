# The NU Trace mark: logo inside the sticker QR, and the scan check

The panel's requirement was that an official NU Trace sticker must be readable by
NU Trace, and must not work when its payload is put through a public QR generator
such as QRCode Monkey.

The answer in this app is two things together:

1. **A visual marker.** Every QR the app draws, downloads or prints carries the
   official NU Trace logo in its centre. A QR-generator website reproduces the
   payload but not the logo.
2. **The existing cryptographic + registered-asset check.** The payload is still
   sealed and signed with a key that never leaves the server (`/qr/verify`), and
   the asset must still be registered, not revoked, and permitted for the user.

Neither is proof of physical authenticity. A photograph or photocopy of a real
sticker carries the logo, the payload and the signature. What the app can do is
refuse anything that was not produced by it, and record what it sees.

---

## Where the logo lives

| What | Where |
| --- | --- |
| Official artwork (as supplied) | `public/images/nu-trace-mark.jpg` |
| Draw + check module | `resources/js/nu-trace-mark.js`, served at `/js/nu-trace-mark.js` |
| Config published to the browser | `resources/views/partials/qr_mark_js.blade.php` |
| Server settings | `config/qr.php` → `require_seal`, `seal_logo`; `.env` → `QR_REQUIRE_SEAL` |

The artwork is kept inside the app, not hot-linked, so a printed label never
waits on an external host and a canvas can still be read back for a download.
It is drawn letterboxed inside the logo's own navy plate: proportions are
preserved and nothing is cropped or stretched.

## Where the mark is drawn

| Surface | How |
| --- | --- |
| Printed sticker sheet | `resources/views/admin/assets/labels.blade.php` draws the code as an SVG and centres the mark on it as a plain `<img>` (white ring + the artwork's own navy plate, from `/images/nu-trace-mark.jpg`), so a printout and a download are the same artwork |
| Registry preview, bulk QR preview, QR modal, department, user and head asset modals | `NUTraceQr.draw()` in `resources/views/partials/qr_scan_js.blade.php` → `NUTraceQrMark.sealHolder()` paints the mark on the canvas, which is also what the Download button exports |
| Asset detail page | `resources/views/admin/assets/show.blade.php` draws the code into `#asset-qr-seal` |
| Replacement preview | `resources/views/admin/replacement/replacement.blade.php` → `NUTraceQrMark.dataUrl()` |
| Stored PNG fallback (`App\Support\Media::generateQr`) | **Not marked.** It is the no-JavaScript fallback only; every screen prefers the drawn code |

The mark covers roughly 20% of the code's edge, about 4–5% of its modules. The
three finder patterns are never touched, and the codes are drawn at error
correction level M (≈15% recoverable), so a scuffed sticker still scans.

## How the scan check works

1. **Decode.** The camera reader (`html5-qrcode`, or `jsQR` on the fallback path)
   produces the payload. No payload → "Unable to read QR code. Please try
   scanning again."
2. **Check the mark.** `NUTraceQrMark.checkScan(payload)` re-reads the live camera
   frame with `jsQR`, which also returns the code's four corners. A box of about
   30% of the code's edge is sampled at its centre and classified by colour:
   - the artwork is navy + gold + white, so a genuine centre is strongly
     *coloured* (≈77% coloured pixels, with gold present);
   - the centre of an ordinary QR is black and white (≈0% coloured).
   Verdicts: `sealed`, `missing`, `mismatch`, `dark`, `unreadable`. A dark or
   unreadable frame is retried before anything is decided, so bad light does not
   turn into a false accusation.
   A centre with no colour in it at all - a label printed on the office's
   black-only ribbon - is then compared, in grey, against the same artwork; see
   the next section.
3. **Payload verification.** `/qr/verify` checks the signature, opens the sealed
   body, and returns the asset only for a valid, unrevoked, registered sticker.
4. **Authorisation and result.** The existing per-role rules decide what is
   shown; nothing is displayed when the scan was refused.

Wired into: admin dashboard, admin pullout, employee request page, department
head request page. NUTAG symbols have no centre to check and are skipped by the
module (they are verified server-side by their own key).

## Printing on a black-only ribbon (Zebra ZD230)

The office's label printer has a black ribbon, so a printed sticker carries no
navy and no gold: the plate prints black, the letter white, and the gold orbit
as a mid-grey dither. Judging that print by hue would refuse the office's own
labels, so the mark is also checked as a **shape**:

- the artwork is reduced to a 16 × 16 grid of grey levels, built from the same
  file the sticker prints (`/images/nu-trace-mark.jpg`), letterboxed in its
  plate exactly as the label draws it;
- the middle of the code is sampled the same way - the plate at roughly 19-25%
  of the code's edge, with a few pixel offsets and all four rotations - with a
  small patch averaged inside each cell, so a dithered print reads back as the
  grey it represents;
- brightness and contrast are normalised, so the lamp and the exposure do not
  matter, and the two grids are correlated.

Measured with the real artwork, dithered to one ink, blurred and noisy, at 0,
15, 90 and 180 degrees, with the code 180-480 px across:

| Centre | Score |
| --- | --- |
| Genuine printed mark | 0.71 – 0.91 |
| A code from a QR website, no mark | 0.15 – 0.22 |
| A dark square with a white disc in it | 0.54 – 0.62 |

`PRINT_MATCH = 0.66` sits in that gap. A near miss (80% of it) is retried like
a dark frame rather than refused on one unlucky image.

This is a print check, not proof: a hand-made dark square with a bright blob in
it can score close to the line, and an exact copy of the artwork still passes.
The signature and the registered-asset check remain what make a payload real.

Nothing changes for the sticker itself: the label still prints the colour
artwork and the printer turns it into ink, and no re-print is needed for the
reader to accept it.

If the mark is missing or wrong the scan is refused with:

> Invalid or Unrecognized QR Code. The required NU Trace logo could not be verified.

## Signature verification (unchanged, and still the real proof)

- Payload: `NUT2-<key id>-<sealed body>-<signature>`; the asset code is sealed
  with AES-256-GCM (`QR_PAYLOAD_KEY`) before signing.
- Signature: Ed25519 (libsodium) or ECDSA P-256 (OpenSSL), key ring in
  `QR_SIGNING_KEYS`, private key never sent to a browser.
- `App\Support\QrPayload` parses, `App\Support\QrVerification` verifies, and the
  registered asset is looked up by the code the server opened.
- Revocation (`assets.qr_revoked_at`) and the duplicate-scan window
  (`QR_DUPLICATE_WINDOW_MINUTES`) are unchanged.

## Existing stickers

Stickers already on assets were printed without the mark, so they cannot pass
step 2. The policy is explicit and controlled on the server:

- `QR_REQUIRE_SEAL=true` (default): a sticker without the mark is refused. Use
  this once the office has reprinted.
- `QR_REQUIRE_SEAL=false`: scanning keeps working while labels are being
  replaced. Nothing is silently trusted — the payload is still verified, and the
  browser simply does not enforce the mark.

Reprinting is the migration: open the department page and print its labels (or
reprint from the registry). New stickers are marked automatically; no database
migration is involved and no asset record changes.

## Duplicate-scan monitoring

Already present in `App\Support\QrVerification`: each verified sticker is
fingerprinted, and the same sticker seen from another account or network inside
the window raises a "possible duplicate sticker" warning. It is a warning, not
proof, and never blocks a scan. Revoking a sticker is what makes every copy of
it dead at once.

## Tests run

- `php artisan test --filter=QrSealTest` — 9 tests: module is served, artwork
  ships, the check is on by default and can be turned off, every scanning page
  loads the module and calls it, a refused scan returns before the lookup, the
  label sheet carries the mark in its SVG, the registry/replacement/asset pages
  draw sealed codes.
- `node scripts/qr-mark-check.cjs` — the classifier itself: a genuine centre is
  `sealed`, a plain black-and-white code is `missing`, another picture is
  `mismatch`, a dark frame is `dark` (retry, not accusation); a black-only print
  has no colour (`missing`) but still passes the grey comparison, while an
  ordinary code fails it.
- Browser check of the grey comparison (the real artwork, the real module, in
  Chromium, with a simulated one-ink print and camera blur): genuine 0.71-0.91,
  no mark 0.15-0.22, plain dark plate with a white disc 0.54-0.62, threshold
  0.66.
- Full suite: run `php artisan test`.
- Browser check: the real `qrcodejs` + `jsQR` + the real artwork, compiled and
  decoded in Chromium (see the session notes): a drawn sticker decodes and
  classifies as `sealed`; the same payload drawn without the mark classifies as
  `missing`.

## Known limitations

- **A copy of a whole sticker passes.** Photographing or photocopying the label
  reproduces the logo and the payload. Only duplicate-scan monitoring and the
  physical check of the object can catch that.
- **The check is a browser check.** It runs in the web scanners; the mobile app
  still relies on the signature and the registered-asset check alone.
- **A good print of the logo on a forged code passes the mark check** - in
  colour or, now, in grey. It still has to pass the signature check to show any
  asset, which it cannot without the signing key.
- **A hand-made dark square with a bright blob is close to the line.** The grey
  comparison rejects a code that was re-created without the logo (0.15-0.22), but
  an image invented to look like a dark plate with a light mark can reach 0.6 and
  is decided by a thin margin. Do not describe the mark as unforgeable.
- **The stored PNG has no mark.** It exists for a browser with no JavaScript;
  every interactive surface draws the marked code instead.
- **Very poor light or a badly scuffed centre is refused after a few retries.**
  That is the conservative direction: no asset information is shown for an
  unverified scan.
- **The check needs the `jsQR` decoder** (loaded from jsDelivr, as the other
  scanner libraries already are). If that host is unreachable, every QR scan is
  refused as unverified; `QR_REQUIRE_SEAL=false` is the deliberate escape hatch
  for that situation, not an automatic fallback.
- The mark is a visual marker, not a cryptographic one.
