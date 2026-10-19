{{--
    The asset label sheet — the only page that draws printed stickers.

    Opened by:
      * the admin department asset page, for one asset or for the whole
        filtered selection  (route: admin/assets/department/{id}/labels)
      * the asset registry and replacement print buttons, which print values
        straight from their forms  (route: admin/assets/label-preview)

    The sticker is 90mm × 40mm (App\Support\AssetLabel), the size of the roll
    loaded in the Zebra ZD230. Two layouts exist:

      * roll (default, and the only one for a single asset) — each sticker is
        its own page at exactly 90mm × 40mm with no margin, so the printer
        feeds one die-cut label per sticker.
      * sheet (opt-in with ?layout=sheet) — the same sticker one per row on A4,
        for printing on an ordinary office printer.

    The artwork is inset 1mm from the die line on every side: a ZD230 cannot
    print right up to the edge of the stock, and a border drawn on the cut line
    comes out clipped (or missing) on the physical label.

    Expects: $labels (array of App\Support\AssetLabel arrays),
             $single (bool), $layout ('roll'|'sheet'), $subtitle (string, screen only),
             $emptyMessage (string, shown when there is nothing to print).
--}}
@php
    $layout = ($layout ?? 'roll') === 'sheet' ? 'sheet' : 'roll';
    $isRoll = $layout === 'roll';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $single ? 'Asset Label' : 'Asset Labels' }}</title>
    <style>
        /* Every measurement in one place. The sticker box is fixed: nothing on
           the label is allowed to make it grow, so the logo, code, location and
           QR can never be pushed off the page.

           90 × 40mm is the die-cut label on the Zebra ZD230 roll. The bands are
           fixed (10 + 23.3 + 4 = 37.3mm of artwork) so the middle always has
           room for the code, the three cells and the QR.

           --label-inset keeps the box 1mm inside the stock all round: the
           thermal head cannot print on the die line, so anything drawn there is
           clipped (or dropped) when the label comes out of the printer. */
        :root {
            --label-w: 90mm;
            --label-h: 40mm;
            --label-inset: 1mm;
            --label-border: 0.35mm;
            --header-h: 10mm;
            --foot-h: 4mm;
            --qr-size: 20mm;
            --qr-col: 22mm;
            --logo-size: 8mm;
            --mark-col: 9mm;
            --ink: #111;
            --muted: #555;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: var(--ink);
            background: #eef1f5;
        }

        /* ---------- screen-only toolbar (never printed) ---------- */
        .sheet-bar {
            position: sticky;
            top: 0;
            z-index: 5;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 16px;
            background: #0F2143;
            color: #fff;
        }
        .sheet-bar h1 { font-size: 15px; font-weight: 700; }
        .sheet-bar p { font-size: 12px; opacity: .8; margin-top: 2px; }
        .sheet-bar__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
        .sheet-bar button,
        .sheet-bar .layout-link {
            display: inline-block;
            padding: 8px 16px;
            border: 0;
            border-radius: 8px;
            background: #C9A227;
            color: #0A1830;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .sheet-bar .layout-link {
            background: rgba(255, 255, 255, .12);
            color: #fff;
        }
        .sheet-bar .layout-link.is-active {
            background: #C9A227;
            color: #0A1830;
        }
        .sheet-bar .print-tip {
            flex-basis: 100%;
            font-size: 11.5px;
            line-height: 1.5;
            opacity: .85;
        }
        .sheet-bar .print-tip code { font-size: 11px; }

        .sheet-wrap { padding: 10mm 6mm; }

        .sheet {
            display: grid;
            grid-template-columns: repeat(2, var(--label-w));
            gap: 4mm 5mm;
            justify-content: center;
            margin: 0 auto;
        }

        /* ---------- one sticker ---------- */
        .trace-label {
            /* The label is 90 × 40mm; the box drawn on it is 88 × 38mm, centred,
               so the print never touches the edge of the die-cut. */
            width: calc(var(--label-w) - 2 * var(--label-inset));
            height: calc(var(--label-h) - 2 * var(--label-inset));
            margin: var(--label-inset);
            border: var(--label-border) solid var(--ink);
            background: #fff;
            display: grid;
            grid-template-rows: var(--header-h) 1fr var(--foot-h);
            overflow: hidden;
            break-inside: avoid;
            page-break-inside: avoid;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        .trace-label__top {
            display: grid;
            grid-template-columns: var(--mark-col) 1fr var(--mark-col);
            align-items: center;
            gap: 0.6mm;
            padding: 0.4mm 1.8mm;
            height: var(--header-h);
            border-bottom: 0.3mm solid var(--ink);
            text-align: center;
            overflow: hidden;
        }
        .trace-label__mark {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 0;
            overflow: hidden;
        }
        /* Height-driven so the seal keeps its own proportions — the official
           artwork is 318 x 383 — and can never be stretched or spill out of its
           column. The header band is 10mm, so an 8mm mark fits with room. */
        .trace-label__mark img {
            height: var(--logo-size);
            width: auto;
            max-width: 100%;
            object-fit: contain;
        }
        .trace-label__titles { display: block; min-width: 0; overflow: hidden; }
        /* Three stacked lines inside a 10mm band: line-heights are pinned so the
           campus line never rides up into the asset name. */
        .trace-label__titles .office {
            display: block;
            font-size: 5.8pt;
            line-height: 1.05;
            font-weight: 700;
            letter-spacing: .4px;
        }
        .trace-label__titles .name {
            display: block;
            font-size: 8pt;
            line-height: 1.1;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: .1mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .trace-label__titles .campus {
            display: block;
            font-size: 4.6pt;
            line-height: 1.05;
            color: #444;
        }

        .trace-label__mid {
            display: grid;
            grid-template-columns: 1fr var(--qr-col);
            min-height: 0;
        }

        .trace-label__info {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 1mm;
            padding: 1mm 2mm;
            border-right: 0.3mm solid var(--ink);
            min-width: 0;
        }
        /* The asset code sits in the middle of its own line, above the cells. */
        .trace-label__code {
            text-align: center;
            font-family: 'Courier New', monospace;
            font-size: 9pt;
            font-weight: 700;
            letter-spacing: .4px;
            overflow-wrap: anywhere;
        }
        .trace-label__cells {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1mm;
            min-width: 0;
        }
        /* A long room name wraps to a few lines instead of being cut off: the
           cell is allowed up to 13mm, which holds the 60-character cap that
           App\Support\AssetLabel puts on a printed value. */
        .trace-label__cells .cell {
            border: 0.25mm solid #888;
            padding: 0.5mm;
            text-align: center;
            font-size: 5.5pt;
            line-height: 1.15;
            max-height: 13mm;
            overflow: hidden;
            overflow-wrap: anywhere;
        }
        .trace-label__cells .cell span {
            display: block;
            font-size: 4.5pt;
            color: var(--muted);
            font-weight: 600;
        }

        .trace-label__qr {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.4mm;
            padding: 0.5mm;
        }
        .trace-label__qr img,
        .trace-label__qr canvas {
            width: var(--qr-size);
            height: var(--qr-size);
            /* a square QR stays square even if the stored PNG is not */
            aspect-ratio: 1 / 1;
            object-fit: contain;
            image-rendering: pixelated;
            /* Quiet zone. An in-browser code (qrcodejs) has no white margin of
               its own, and a code printed hard against the caption below it
               does not scan reliably. box-sizing is border-box, so this margin
               comes out of the 20mm footprint instead of growing the sticker. */
            box-sizing: border-box;
            padding: 0.8mm;
            background: #fff;
        }
        .trace-label__qr .scan { font-size: 4.5pt; color: var(--ink); }

        /* the fallback <img> qrcodejs hides must not reserve space */
        .trace-label__qr img[style*="display: none"] { display: none; }
        .trace-label__qr-draw { display: flex; align-items: center; justify-content: center; }

        .trace-label__foot {
            display: flex;
            align-items: center;
            justify-content: center;
            height: var(--foot-h);
            text-align: center;
            font-size: 6pt;
            font-weight: 700;
            color: #c00;
            letter-spacing: .4px;
            border-top: 0.3mm solid var(--ink);
        }

        /* ---------- empty state (never printed) ---------- */
        .sheet-empty {
            max-width: 520px;
            margin: 12mm auto;
            padding: 24px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #fff;
            text-align: center;
        }
        .sheet-empty h2 { font-size: 16px; margin-bottom: 6px; }
        .sheet-empty p { font-size: 13px; color: #475569; }

        /* ---------- print ---------- */
        @if($isRoll)
        /* Exactly one die-cut label per page: the ZD230 feeds one sticker per
           page break, so the page has to be the sticker and nothing else. */
        @page { size: 90mm 40mm; margin: 0; }
        @media print {
            html, body { width: 90mm; background: #fff; }
            .sheet-bar, .sheet-empty { display: none !important; }
            .sheet-wrap { padding: 0; }
            .sheet { display: block; margin: 0; }
            .trace-label {
                margin: var(--label-inset) auto;
                break-after: page;
                page-break-after: always;
            }
            /* No trailing page break on the last sticker, or the printer feeds
               one blank label. */
            .trace-label:last-child {
                break-after: auto;
                page-break-after: auto;
            }
        }
        @else
        /* One 90mm sticker per row on A4, with a page break every six. */
        @page { size: A4; margin: 8mm; }
        @media print {
            body { background: #fff; }
            .sheet-bar, .sheet-empty { display: none !important; }
            .sheet-wrap { padding: 0; }
            .sheet {
                display: block;
                margin: 0 auto;
                width: 90mm;
            }
            .trace-label {
                margin: 0 auto 4mm;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .trace-label:nth-child(6n) { margin-bottom: 0; break-after: page; page-break-after: always; }
            .trace-label:last-child { break-after: auto; page-break-after: auto; }
        }
        @endif
    </style>
</head>
<body>
    @php
        // Which stickers on this sheet still carry an unsigned code - printed
        // before signing existed, or signed with a key this install no longer
        // holds. Shown on screen (the bar is not printed) so the office can see
        // what a reprint would cover, instead of finding out by scanning.
        $unsignedCount = collect($labels)
            ->filter(fn ($label) => ! \App\Support\QrPayload::isPayload((string) ($label['qr_payload'] ?? '')))
            ->count();

        // Keep every other query value (filters, codes, asset) when switching
        // between the roll and the A4 layout.
        $layoutUrl = function (string $mode) {
            $query = request()->query();
            unset($query['layout']);
            if ($mode === 'sheet') {
                $query['layout'] = 'sheet';
            }

            $qs = http_build_query($query);

            return url()->current().($qs === '' ? '' : '?'.$qs);
        };
    @endphp
    <div class="sheet-bar">
        <div>
            <h1>{{ $labels === [] ? 'Asset Labels' : ($single ? 'Asset Label' : count($labels) . ' Asset Labels') }}</h1>
            <p>{{ $subtitle ?? '' }}</p>
            @if($unsignedCount > 0)
                <p style="margin-top:4px;color:#F2CE62;font-size:12px;">
                    {{ $unsignedCount }} of these {{ $unsignedCount === 1 ? 'sticker carries' : 'stickers carry' }}
                    an unsigned code (no signature). They still scan as “unsigned”, and
                    <code>php artisan qr:resign</code> re-mints the stored images.
                </p>
            @endif
        </div>
        <div class="sheet-bar__actions">
            @if($labels !== [] && ! $single)
                <a class="layout-link {{ $isRoll ? 'is-active' : '' }}" href="{{ $layoutUrl('roll') }}"
                   title="One 90 × 40 mm sticker per page, for the Zebra ZD230">Label roll (90 × 40)</a>
                <a class="layout-link {{ $isRoll ? '' : 'is-active' }}" href="{{ $layoutUrl('sheet') }}"
                   title="The same stickers on A4, for an office printer">A4 sheet</a>
            @endif
            @if($labels !== [])
                <button type="button" onclick="window.print()">Print</button>
            @endif
        </div>
        @if($labels !== [])
            <p class="print-tip">
                @if($isRoll)
                    In the print dialog pick <strong>ZDesigner ZD230-203dpi</strong>, paper size <strong>90 × 40 mm</strong>,
                    margins <strong>None</strong>, scale <strong>100%</strong> and turn <strong>Headers and footers</strong> off.
                    The first time, add that paper size in Windows: Printer properties → Preferences → Options →
                    Media size → <em>Custom</em> (width 90 mm, height 40 mm).
                @else
                    A4 layout, one sticker per row. Set margins to <strong>None</strong>, scale to <strong>100%</strong>
                    and turn <strong>Headers and footers</strong> off so the sticker stays 90 × 40 mm.
                @endif
            </p>
        @endif
    </div>

    @if($labels === [])
        <div class="sheet-empty">
            <h2>Nothing to print</h2>
            <p>{{ $emptyMessage ?? 'No assets match the selected filters. Adjust the filters and try again.' }}</p>
        </div>
    @else
        <div class="sheet-wrap">
            <div class="sheet">
                @foreach($labels as $label)
                    @include('partials.asset_label', ['label' => $label])
                @endforeach
            </div>
        </div>
    @endif

    @if($labels !== [])
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script>
            // What a sticker's QR carries when the browser has to draw it: the
            // signed payload the server put in the label, or the plain code of
            // an install without signing keys.
            function stickerText(root) {
                return (root.dataset.qrPayload || root.dataset.assetCode || '').trim();
            }

            function isSignedText(text) {
                return /^NUT[0-9]{1,2}[-:]/i.test(text);
            }

            // A label without a stored QR image draws its own code in the browser,
            // so a sticker is never printed with an empty square.
            function drawMissingQr(root) {
                const target = root.querySelector('.trace-label__qr-draw');
                const payload = stickerText(root);

                if (!target || target.dataset.drawn === '1' || !payload) {
                    return Promise.resolve();
                }

                target.dataset.drawn = '1';

                if (typeof QRCode !== 'undefined') {
                    new QRCode(target, {
                        text: payload,
                        width: 240,
                        height: 240,
                        colorDark: '#111111',
                        colorLight: '#ffffff',
                        // A signed payload is around 130 characters. Measured in
                        // this browser: level H puts about 75 modules across the
                        // 20mm square (0.27mm each), level M about 50 (0.40mm),
                        // and a short legacy code stays on H at 30 modules.
                        correctLevel: isSignedText(payload) ? QRCode.CorrectLevel.M : QRCode.CorrectLevel.H
                    });
                    return Promise.resolve();
                }

                // qrcodejs did not load (offline print station): ask the same
                // QR service the rest of the app falls back to. Level M with
                // the quiet zone, the pair the stored images are made with.
                return new Promise((resolve) => {
                    const img = document.createElement('img');
                    img.alt = 'QR code for ' + (root.dataset.assetCode || '');
                    img.onload = img.onerror = () => resolve();
                    img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&qzone=1&ecc='
                        + (isSignedText(payload) ? 'M' : 'L')
                        + '&data=' + encodeURIComponent(payload);
                    target.replaceWith(img);
                });
            }

            // Print only once every code is on the sticker: a QR that is still
            // loading would print as a blank square.
            function whenLabelsReady() {
                const qrRoots = Array.from(document.querySelectorAll('.trace-label__qr'));

                const images = qrRoots
                    .map((root) => root.querySelector('.trace-label__qr-image'))
                    .filter(Boolean)
                    .map((img) => (img.complete
                        ? Promise.resolve()
                        : new Promise((resolve) => {
                            img.addEventListener('load', resolve, { once: true });
                            img.addEventListener('error', () => {
                                // A dead stored image is replaced by a drawn code.
                                img.remove();
                                drawMissingQr(img.closest('.trace-label__qr')).then(resolve);
                            }, { once: true });
                        })));

                const draws = qrRoots
                    .filter((root) => !root.querySelector('.trace-label__qr-image'))
                    .map((root) => drawMissingQr(root));

                // The seal is part of the printed header, so it has to be on the
                // sticker too — including when the published copy 404s and the
                // bundled copy is still swapping in.
                const marks = Array.from(document.querySelectorAll('.trace-label__mark img'))
                    .filter((img) => img.naturalWidth === 0)
                    .map((img) => new Promise((resolve) => {
                        // Already failed and gave up: nothing left to wait for.
                        if (img.complete && img.naturalWidth === 0) {
                            resolve();
                            return;
                        }

                        const settle = () => resolve();

                        img.addEventListener('load', settle, { once: true });
                        img.addEventListener('error', function () {
                            // The inline onerror has already swapped in the bundled
                            // copy, so there is one more load attempt to wait for.
                            img.addEventListener('load', settle, { once: true });
                            img.addEventListener('error', settle, { once: true });
                        }, { once: true });
                    }));

                // Never hold the print job hostage to a slow image.
                const timeout = new Promise((resolve) => setTimeout(resolve, 2500));

                return Promise.race([Promise.all([...images, ...draws, ...marks]), timeout]);
            }

            window.addEventListener('load', function () {
                whenLabelsReady().then(function () {
                    window.print();
                });
            });
        </script>
    @endif
</body>
</html>
