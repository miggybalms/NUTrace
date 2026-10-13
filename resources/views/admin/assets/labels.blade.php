{{--
    The asset label sheet — the only page that draws printed stickers.

    Opened by:
      * the admin department asset page, for one asset or for the whole
        filtered selection  (route: admin/assets/department/{id}/labels)
      * the asset registry and replacement print buttons, which print values
        straight from their forms  (route: admin/assets/label-preview)

    Layout is fixed at 90mm × 40mm (App\Support\AssetLabel). One label prints on
    its own 90 × 40 page so it can go straight to a label printer; several
    labels print 2-up on A4, matching the sheet the registry has always used.

    Expects: $labels (array of App\Support\AssetLabel arrays),
             $single (bool), $subtitle (string, screen only),
             $emptyMessage (string, shown when there is nothing to print).
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $single ? 'Asset Label' : 'Asset Labels' }}</title>
    <style>
        /* Every measurement in one place. The sticker box is fixed: nothing on
           the label is allowed to make it grow, so the logo, code, location and
           QR can never be pushed off the page. */
        :root {
            --label-w: 90mm;
            --label-h: 40mm;
            --label-border: 0.45mm;
            /* The header and footer are fixed heights, so the middle band gets
               40 - 11 - 4 = 25mm whether or not a logo is present. Without this
               the logo pushed the QR into the footer on a real print. */
            --header-h: 11mm;
            --foot-h: 4mm;
            --qr-size: 20mm;
            --logo-size: 9mm;
            --mark-col: 9.5mm;
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
        .sheet-bar button {
            padding: 8px 16px;
            border: 0;
            border-radius: 8px;
            background: #C9A227;
            color: #0A1830;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .sheet-wrap { padding: 10mm 6mm; }

        .sheet {
            display: grid;
            grid-template-columns: repeat(2, var(--label-w));
            gap: 5mm 6mm;
            justify-content: center;
            margin: 0 auto;
        }
        .sheet--single {
            grid-template-columns: var(--label-w);
            gap: 0;
        }

        /* ---------- one sticker ---------- */
        .trace-label {
            width: var(--label-w);
            height: var(--label-h);
            border: var(--label-border) solid var(--ink);
            background: #fff;
            display: grid;
            grid-template-rows: auto 1fr auto;
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
            padding: 0.6mm 1.5mm;
            height: var(--header-h);
            border-bottom: 0.35mm solid var(--ink);
            text-align: center;
        }
        .trace-label__mark {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        /* Height-driven so the seal keeps its own proportions — the official
           artwork is 318 x 383 — and can never be stretched or spill out of its
           column. The header band is 11mm, so a 9mm mark fits with room spare. */
        .trace-label__mark img {
            height: var(--logo-size);
            width: auto;
            max-width: 100%;
            object-fit: contain;
        }
        .trace-label__titles { display: block; min-width: 0; }
        .trace-label__titles .office {
            display: block;
            font-size: 6pt;
            font-weight: 700;
            letter-spacing: .4px;
        }
        .trace-label__titles .name {
            display: block;
            font-size: 8.5pt;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: .3mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .trace-label__titles .campus { display: block; font-size: 5.5pt; color: #444; }

        .trace-label__mid {
            display: grid;
            grid-template-columns: 1fr 23mm;
            min-height: 0;
        }

        .trace-label__info {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 1mm;
            padding: 1mm 2mm;
            border-right: 0.35mm solid var(--ink);
            min-width: 0;
        }
        /* The asset code sits in the middle of its own line, above the cells. */
        .trace-label__code {
            text-align: center;
            font-family: 'Courier New', monospace;
            font-size: 8pt;
            font-weight: 700;
            letter-spacing: .4px;
            overflow-wrap: anywhere;
        }
        .trace-label__cells {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: .8mm;
            min-width: 0;
        }
        /* A long room name wraps to a few lines instead of being cut off: the
           cell is allowed up to 13mm, which holds the 60-character cap that
           App\Support\AssetLabel puts on a printed value. */
        .trace-label__cells .cell {
            border: 0.25mm solid #888;
            padding: 0.45mm;
            text-align: center;
            font-size: 5pt;
            line-height: 1.15;
            max-height: 13mm;
            overflow: hidden;
            overflow-wrap: anywhere;
        }
        .trace-label__cells .cell span {
            display: block;
            font-size: 4.2pt;
            color: var(--muted);
            font-weight: 600;
        }

        .trace-label__qr {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5mm;
            padding: 0.6mm;
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
            padding: 0.7mm;
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
            font-size: 5.5pt;
            font-weight: 700;
            color: #c00;
            letter-spacing: .4px;
            border-top: 0.35mm solid var(--ink);
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
        @if($single)
        @page { size: 90mm 40mm; margin: 0; }
        @media print {
            body { background: #fff; }
            .sheet-bar, .sheet-empty { display: none !important; }
            .sheet-wrap { padding: 0; }
            .sheet { display: block; }
            .trace-label { border: var(--label-border) solid var(--ink); }
        }
        @else
        @page { size: A4; margin: 8mm; }
        @media print {
            body { background: #fff; }
            .sheet-bar, .sheet-empty { display: none !important; }
            .sheet-wrap { padding: 0; }
            .sheet { margin: 0; justify-content: center; }
        }
        @endif
    </style>
</head>
<body>
    <div class="sheet-bar">
        <div>
            <h1>{{ $labels === [] ? 'Asset Labels' : ($single ? 'Asset Label' : count($labels) . ' Asset Labels') }}</h1>
            <p>{{ $subtitle ?? '' }}</p>
        </div>
        @if($labels !== [])
            <button type="button" onclick="window.print()">Print</button>
        @endif
    </div>

    @if($labels === [])
        <div class="sheet-empty">
            <h2>Nothing to print</h2>
            <p>{{ $emptyMessage ?? 'No assets match the selected filters. Adjust the filters and try again.' }}</p>
        </div>
    @else
        <div class="sheet-wrap">
            <div class="sheet {{ $single ? 'sheet--single' : '' }}">
                @foreach($labels as $label)
                    @include('partials.asset_label', ['label' => $label])
                @endforeach
            </div>
        </div>
    @endif

    @if($labels !== [])
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script>
            // A label without a stored QR image draws its own code in the browser,
            // so a sticker is never printed with an empty square.
            function drawMissingQr(root) {
                const target = root.querySelector('.trace-label__qr-draw');
                const code = root.dataset.assetCode || '';

                if (!target || target.dataset.drawn === '1' || !code) {
                    return Promise.resolve();
                }

                target.dataset.drawn = '1';

                if (typeof QRCode !== 'undefined') {
                    new QRCode(target, {
                        text: code,
                        width: 240,
                        height: 240,
                        colorDark: '#111111',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.H
                    });
                    return Promise.resolve();
                }

                // qrcodejs did not load (offline print station): ask the same
                // QR service the rest of the app falls back to.
                return new Promise((resolve) => {
                    const img = document.createElement('img');
                    img.alt = 'QR code for ' + code;
                    img.onload = img.onerror = () => resolve();
                    img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' + encodeURIComponent(code);
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
