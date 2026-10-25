{{--
    The asset label sheet — the only page that draws printed stickers.

    Opened by:
      * the admin department asset page, for one asset or for the whole
        filtered selection  (route: admin/assets/department/{id}/labels)
      * the asset registry and replacement print buttons, which print values
        straight from their forms  (route: admin/assets/label-preview)

    The sticker size starts from config/asset_label.php (env
    ASSET_LABEL_WIDTH_MM / ASSET_LABEL_HEIGHT_MM / ASSET_LABEL_INSET_MM /
    ASSET_LABEL_QR_MM). The default is the stock loaded in the Zebra ZD230 —
    50mm × 25mm, the driver's 1.969" × 0.984" media — and the Asset Management
    Office can change the size, QR square and layout for its own browser from
    the "Print settings" panel on this page (web only; the mobile app never
    prints labels). Two layouts exist:

      * roll (default, and the only one for a single asset) — each sticker is
        its own page at exactly the configured size with no margin, so the
        printer feeds one die-cut label per sticker.
      * sheet (opt-in with ?layout=sheet) — the same sticker on A4, for printing
        on an ordinary office printer.

    The artwork is inset 1mm from the die line on every side: a ZD230 cannot
    print right up to the edge of the stock, and a border drawn on the cut line
    comes out clipped (or missing) on the physical label.

    The QR is drawn here as an SVG at print resolution (4-module quiet zone,
    one path, crisp edges) instead of being scaled from a 400px PNG. A signed
    payload is 45 modules across; on a 20mm square that is 3 printer dots per
    module at 203dpi, and only an exactly-placed vector survives that.

    Expects: $labels (array of App\Support\AssetLabel arrays),
             $single (bool), $layout ('roll'|'sheet'), $subtitle (string, screen only),
             $emptyMessage (string, shown when there is nothing to print).
--}}
@php
    $layout = ($layout ?? 'roll') === 'sheet' ? 'sheet' : 'roll';
    $isRoll = $layout === 'roll';

    $labelW = \App\Support\AssetLabel::css(\App\Support\AssetLabel::widthMm());
    $labelH = \App\Support\AssetLabel::css(\App\Support\AssetLabel::heightMm());
    $inset  = \App\Support\AssetLabel::css(\App\Support\AssetLabel::insetMm());
    $qrMm   = \App\Support\AssetLabel::css(\App\Support\AssetLabel::qrMm());

    // Print settings panel: sticker size, QR size and layout. Web only, and
    // only for the Asset Management Office - it changes what a printout looks
    // like, not what the system records.
    $labelSettings = \App\Support\AssetLabel::settings();
    $qrDots = \App\Support\AssetLabel::moduleDots($labelSettings['qr_mm']);
    $minStickerW = \App\Support\AssetLabel::css(\App\Support\AssetLabel::minimumStickerWidthMm());
    $minStickerH = \App\Support\AssetLabel::css(\App\Support\AssetLabel::minimumStickerHeightMm());
    $minQrMm = \App\Support\AssetLabel::css(\App\Support\AssetLabel::minimumQrMm());
    // The slider's ceiling follows the sticker, but never drops under its floor:
    // a sticker too small for any readable code makes min > max, and then the
    // server's clamp is the only bound that means anything.
    $maxQrMm = \App\Support\AssetLabel::css(max(
        \App\Support\AssetLabel::minimumQrMm(),
        min($labelSettings['width_mm'], $labelSettings['height_mm'])
    ));
    $canLabelSettings = \App\Support\Access::isAssetOffice(auth()->user());
    $labelSettingsFlash = session('label_settings');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $single ? 'Asset Label' : 'Asset Labels' }}</title>
    <style>
        /* Every measurement in one place. The sticker box is fixed: nothing on
           the label is allowed to make it grow, so the seal, code, fields and
           QR can never be pushed off the page.

           50 × 25mm is the die-cut label on the Zebra ZD230 roll (the driver's
           1.969" × 0.984"). The box is drawn 1mm inside the stock all round —
           the thermal head cannot print on the die line, so anything drawn
           there is clipped (or dropped) when the label comes out.

           --qr-size is the QR square including its quiet zone; it takes almost
           the full height of the sticker because that is the only way a
           45-module code reaches 3 printer dots per module on a 203dpi head. */
        :root {
            --label-w: {{ $labelW }}mm;
            --label-h: {{ $labelH }}mm;
            --label-inset: {{ $inset }}mm;
            --label-border: 0.3mm;
            --qr-size: {{ $qrMm }}mm;
            --qr-col: calc(var(--qr-size) + 0.7mm);
            --head-h: 6.6mm;
            --foot-h: 2.2mm;
            --logo-size: 3.6mm;
            /* Pure black: greys dither into unreadable noise on thermal stock. */
            --ink: #000;
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

        /* ---------- print settings (screen only, never printed) ---------- */
        .sheet-bar .print-settings {
            flex-basis: 100%;
            padding: 10px 12px;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 10px;
            background: rgba(255, 255, 255, .06);
            font-size: 12px;
        }
        .sheet-bar .print-settings summary { cursor: pointer; font-weight: 700; }
        .sheet-bar .print-settings form {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 10px;
            margin-top: 10px;
        }
        .sheet-bar .print-settings label {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 10.5px;
            letter-spacing: .05em;
            text-transform: uppercase;
            opacity: .9;
        }
        .sheet-bar .print-settings input,
        .sheet-bar .print-settings select {
            width: 110px;
            padding: 6px 8px;
            border: 1px solid rgba(255, 255, 255, .35);
            border-radius: 6px;
            background: #fff;
            color: #0A1830;
            font-size: 13px;
        }
        .sheet-bar .print-settings select { width: auto; }
        .sheet-bar .print-settings button {
            padding: 8px 14px;
            border: 0;
            border-radius: 8px;
            background: #C9A227;
            color: #0A1830;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .sheet-bar .print-settings button.print-settings__reset {
            background: rgba(255, 255, 255, .14);
            color: #fff;
        }
        .sheet-bar .print-settings__msg { margin-top: 8px; color: #B6E8C4; }
        .sheet-bar .print-settings__msg.is-warning { color: #F2CE62; }
        .sheet-bar .print-settings__warning { margin-top: 4px; color: #F2CE62; }
        .sheet-bar .print-settings__note { margin-top: 8px; font-size: 11px; opacity: .8; }

        /* On screen the stickers show at their real size, as a preview of the
           page that is about to print. */
        .sheet-wrap { padding: 10mm 6mm; }

        .sheet {
            display: grid;
            grid-template-columns: repeat(auto-fill, var(--label-w));
            gap: 4mm;
            justify-content: center;
            margin: 0 auto;
        }

        /* ---------- one sticker ---------- */
        .trace-label {
            width: calc(var(--label-w) - 2 * var(--label-inset));
            height: calc(var(--label-h) - 2 * var(--label-inset));
            margin: var(--label-inset);
            border: var(--label-border) solid var(--ink);
            background: #fff;
            display: grid;
            grid-template-columns: 1fr var(--qr-col);
            overflow: hidden;
            break-inside: avoid;
            page-break-inside: avoid;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        /* Left column: seal + name, code + fields, footer. */
        .trace-label__left {
            display: grid;
            grid-template-rows: var(--head-h) 1fr var(--foot-h);
            min-width: 0;
            border-right: 0.25mm solid var(--ink);
        }

        .trace-label__top {
            display: grid;
            grid-template-columns: var(--logo-size) 1fr;
            align-items: center;
            gap: 0.5mm;
            padding: 0.3mm 1.2mm;
            height: var(--head-h);
            border-bottom: 0.25mm solid var(--ink);
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
           artwork is 318 x 383 — and can never be stretched. */
        .trace-label__mark img {
            height: var(--logo-size);
            width: auto;
            max-width: 100%;
            object-fit: contain;
        }
        .trace-label__titles { display: block; min-width: 0; overflow: hidden; }
        .trace-label__titles .office {
            display: block;
            font-size: 3pt;
            line-height: 1.05;
            font-weight: 700;
            letter-spacing: .2px;
            white-space: nowrap;
            overflow: hidden;
        }
        .trace-label__titles .name {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            font-size: 6pt;
            line-height: 1.02;
            font-weight: 700;
            text-transform: uppercase;
            overflow: hidden;
        }
        .trace-label__titles .campus {
            display: block;
            font-size: 2.9pt;
            line-height: 1.05;
            font-weight: 600;
        }

        /* Middle of the left column: the code on its own line, then the three
           fields as label/value rows so a long room name clips on one line
           instead of pushing the footer out. */
        .trace-label__mid {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            gap: 0.6mm;
            padding: 0.7mm 1.2mm 0.5mm;
            min-height: 0;
            overflow: hidden;
        }
        .trace-label__code {
            font-family: 'Courier New', monospace;
            font-size: 6.6pt;
            line-height: 1.05;
            font-weight: 700;
            /* No tracking: a 19-character code has to share the column with a
               21mm QR, and the 0.2px per character was enough to push the end
               of the identifier past the edge. */
            letter-spacing: 0;
            white-space: nowrap;
            overflow: hidden;
            /* Never reached while the script can measure the line: it shrinks
               the code until the whole identifier fits (fitLabelText below).
               Only a browser with JS disabled clips with the ellipsis. */
            text-overflow: ellipsis;
        }

        /* The fields wrap instead of ending in "...": "Furnitures and
           Fixtures" and a full room name are information the sticker exists to
           carry. --cell-pt is stepped down on the sticker by the script when
           two lines each still do not fit, so the rows always end above the
           footer band. */
        .trace-label__cells { display: flex; flex-direction: column; gap: 0.45mm; min-width: 0; }
        .trace-label__cells .cell {
            display: grid;
            grid-template-columns: 7.4mm 1fr;
            align-items: baseline;
            gap: 0.5mm;
            font-size: var(--cell-pt, 4.1pt);
            line-height: 1.06;
            overflow: hidden;
            overflow-wrap: anywhere;
        }
        .trace-label__cells .cell span:not(.value) {
            display: block;
            font-size: 3.2pt;
            font-weight: 700;
            letter-spacing: .2px;
            white-space: nowrap;
        }
        .trace-label__cells .cell .value {
            display: block;
            min-width: 0;
            font-size: var(--cell-pt, 4.1pt);
            font-weight: 400;
            letter-spacing: 0;
            /* The one thing a value must not inherit is the label's nowrap. */
            white-space: normal;
            overflow: hidden;
        }

        .trace-label__foot {
            display: flex;
            align-items: center;
            justify-content: center;
            height: var(--foot-h);
            text-align: center;
            font-size: 4.2pt;
            font-weight: 700;
            letter-spacing: .3px;
            border-top: 0.25mm solid var(--ink);
        }

        /* ---------- QR column: the square and nothing else ---------- */
        .trace-label__qr {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            overflow: hidden;
            background: #fff;
        }
        .trace-label__qr svg,
        .trace-label__qr img {
            width: var(--qr-size);
            height: var(--qr-size);
            /* a square QR stays square even if the stored PNG is not */
            aspect-ratio: 1 / 1;
            object-fit: contain;
            display: block;
            /* the SVG carries its own 4-module quiet zone in white */
            shape-rendering: crispEdges;
            image-rendering: pixelated;
        }
        .trace-label__qr-draw { display: flex; align-items: center; justify-content: center; }
        .trace-label__qr-draw:empty { display: none; }

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
        @page { size: {{ $labelW }}mm {{ $labelH }}mm; margin: 0; }
        @media print {
            html, body { width: {{ $labelW }}mm; background: #fff; }
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
        /* The same sticker on A4, three per row, a page break every nine. */
        @page { size: A4; margin: 8mm; }
        @media print {
            body { background: #fff; }
            .sheet-bar, .sheet-empty { display: none !important; }
            .sheet-wrap { padding: 0; }
            .sheet {
                display: grid;
                grid-template-columns: repeat(3, var(--label-w));
                gap: 4mm;
                margin: 0 auto;
                width: 100%;
            }
            .trace-label {
                margin: 0 auto;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .trace-label:nth-child(9n):not(:last-child) { margin-bottom: 0; break-after: page; page-break-after: always; }
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
            ->filter(function ($label) {
                $text = (string) ($label['qr_payload'] ?? '');

                return ! \App\Support\QrPayload::isPayload($text)
                    && ! \App\Support\NutTag::isTagText($text);
            })
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
                   title="One {{ $labelW }} × {{ $labelH }} mm sticker per page, for the Zebra ZD230">Label roll ({{ $labelW }} × {{ $labelH }})</a>
                <a class="layout-link {{ $isRoll ? '' : 'is-active' }}" href="{{ $layoutUrl('sheet') }}"
                   title="The same stickers on A4, for an office printer">A4 sheet</a>
            @endif
            @if($labels !== [])
                <button type="button" onclick="window.print()">Print</button>
            @endif
        </div>
        @if($canLabelSettings)
            @if($labelSettingsFlash)
                {{-- What the last save did, including anything that had to be
                     adjusted so the sticker stays scannable. --}}
                <p class="print-settings__msg {{ ! empty($labelSettingsFlash['warnings']) ? 'is-warning' : '' }}">
                    {{ $labelSettingsFlash['message'] ?? '' }}
                </p>
                @foreach(($labelSettingsFlash['warnings'] ?? []) as $warning)
                    <p class="print-settings__warning">{{ $warning }}</p>
                @endforeach
            @endif

            <details class="print-settings">
                <summary>Print settings — sticker size, QR size and layout</summary>

                <form method="POST" action="{{ url('/admin/assets/labels/settings') }}">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">

                    <label>Sticker width (mm)
                        <input type="number" name="width_mm" step="0.1" required
                               min="{{ $minStickerW }}" max="{{ \App\Support\AssetLabel::MAX_WIDTH_MM }}" value="{{ $labelW }}">
                    </label>

                    <label>Sticker height (mm)
                        <input type="number" name="height_mm" step="0.1" required
                               min="{{ $minStickerH }}" max="{{ \App\Support\AssetLabel::MAX_HEIGHT_MM }}" value="{{ $labelH }}">
                    </label>

                    <label>QR size (mm)
                        <input type="number" name="qr_mm" step="0.1" required
                               min="{{ $minQrMm }}" max="{{ $maxQrMm }}" value="{{ $qrMm }}">
                    </label>

                    <label>Layout
                        <select name="layout">
                            <option value="roll" @selected($isRoll)>Label roll (one sticker per page)</option>
                            <option value="sheet" @selected(! $isRoll)>A4 sheet</option>
                        </select>
                    </label>

                    <button type="submit">Save settings</button>
                    <button type="submit" name="reset" value="1" class="print-settings__reset"
                            title="Go back to the configured label stock">Reset to stock</button>
                </form>

                <p class="print-settings__note">
                    QR at {{ $qrMm }} mm is {{ $qrDots }} printer dots per module on a 203 dpi head; below 2.5 the
                    modules merge after printing. Sizes are kept for this browser only, and the printer driver's
                    media size must still match the sticker size above.
                </p>
            </details>
        @endif

        @if($labels !== [])
            <p class="print-tip">
                @if($isRoll)
                    In the print dialog pick <strong>ZDesigner ZD230-203dpi</strong>, paper size
                    <strong>{{ $labelW }} × {{ $labelH }} mm</strong> ({{ $labelW }}mm ≈ 1.969 in,
                    {{ $labelH }}mm ≈ 0.984 in), margins <strong>None</strong>, scale <strong>100%</strong>
                    and turn <strong>Headers and footers</strong> off. The driver's media size must be the
                    same {{ $labelW }} × {{ $labelH }} mm: printer properties → Preferences → Options →
                    Media size → <em>Custom</em>. If the driver says something larger, Chrome shrinks the
                    sticker and the QR stops scanning.
                @else
                    A4 layout, three stickers per row. Set margins to <strong>None</strong>, scale to <strong>100%</strong>
                    and turn <strong>Headers and footers</strong> off so each sticker stays {{ $labelW }} × {{ $labelH }} mm.
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
        {{-- qrcode-generator selects QR's alphanumeric mode, which is what the
             signed payload was designed for (A-Z, 0-9 and '-'). Byte mode needs
             49 modules for the same string; alphanumeric needs 45, so every
             module is wider on a 203dpi head. qrcodejs stays as the fallback. --}}
        <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
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

            // QR readers only see pixels, and a bitmap gets resampled on its way
            // to the print head: at ~45 modules across a 21mm square the grey
            // edges merge and the code stops reading. Both generators below keep
            // an exact module matrix, so the sticker is built as one SVG path
            // with a real 4-module quiet zone and crisp edges — the printer then
            // rasterises it at its own 203dpi.
            function moduleMatrix(text) {
                // Preferred: alphanumeric mode. The payload is designed for it
                // (A-Z, 0-9 and '-'), and it is the difference between 45 and 49
                // modules — 9% wider modules on the same sticker.
                if (typeof qrcode === 'function' && /^[0-9A-Z $%*+\-./:]+$/.test(text)) {
                    try {
                        var gen = qrcode(0, isSignedText(text) ? 'M' : 'H');
                        gen.addData(text, 'Alphanumeric');
                        gen.make();

                        return {
                            count: gen.getModuleCount(),
                            isDark: function (row, col) { return gen.isDark(row, col); }
                        };
                    } catch (e) {
                        // fall through to the byte-mode generator
                    }
                }

                if (typeof QRCode === 'undefined') {
                    return null;
                }

                var holder = document.createElement('div');
                var qr = null;

                try {
                    qr = new QRCode(holder, {
                        text: text,
                        width: 256,
                        height: 256,
                        colorDark: '#000000',
                        colorLight: '#ffffff',
                        // A signed payload is ~120 characters. Level M still
                        // reads through a scuff; a short code with no signature
                        // stays on H, where there is room to spare.
                        correctLevel: isSignedText(text) ? QRCode.CorrectLevel.M : QRCode.CorrectLevel.H
                    });
                } catch (e) {
                    return null;
                }

                // qrcodejs keeps the matrix on the instance; if a future build
                // renames that, the stored image / QR service below takes over.
                var model = qr && qr._oQRCode;

                if (!model || typeof model.getModuleCount !== 'function' || typeof model.isDark !== 'function') {
                    return null;
                }

                return {
                    count: model.getModuleCount(),
                    isDark: function (row, col) { return model.isDark(row, col); }
                };
            }

            function qrSvg(text, code) {
                var model = moduleMatrix(text);

                if (!model) {
                    return null;
                }

                var count = model.count;
                var quiet = 4;
                var total = count + quiet * 2;
                var path = '';
                var row, col;

                for (row = 0; row < count; row++) {
                    for (col = 0; col < count; col++) {
                        if (model.isDark(row, col)) {
                            path += 'M' + (col + quiet) + ' ' + (row + quiet) + 'h1v1h-1z';
                        }
                    }
                }

                return {
                    modules: count,
                    markup: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + total + ' ' + total + '"'
                        + ' shape-rendering="crispEdges" role="img" focusable="false"'
                        + ' aria-label="QR code for ' + (code || '') + '">'
                        + '<rect width="' + total + '" height="' + total + '" fill="#ffffff"/>'
                        + '<path d="' + path + '" fill="#000000"/>'
                        + '</svg>'
                };
            }

            // Draw the crisp code into its sticker. Returns false when this
            // browser cannot draw one, so the stored image stays as fallback.
            function drawQr(root) {
                var target = root.querySelector('.trace-label__qr-draw');
                var payload = stickerText(root);

                if (!target || !payload || target.dataset.drawn === '1') {
                    return false;
                }

                var svg = qrSvg(payload, root.dataset.assetCode);

                if (!svg) {
                    return false;
                }

                target.dataset.drawn = '1';
                target.innerHTML = svg.markup;
                root.dataset.qrModules = String(svg.modules);

                // The vector replaces the stored 400px PNG: nothing is gained by
                // printing the same code twice, and the image is the blurrier of
                // the two.
                var stored = root.querySelector('.trace-label__qr-image');

                if (stored) {
                    stored.remove();
                }

                return true;
            }

            // A sticker's box never grows, but nothing on it should be cut
            // with an ellipsis: "AST-J5BH5BV-192…" is not an identifier anyone
            // can type into the system, and a truncated room hides where the
            // asset actually is. The code shrinks until the whole identifier
            // fits its line; the fields wrap onto a second line first and are
            // only stepped down when the three rows still overflow the band
            // between the header and the footer.
            function fitOneLine(el, minPt) {
                el.style.fontSize = '';

                var size = parseFloat(getComputedStyle(el).fontSize);
                var floor = minPt * 4 / 3;
                var guard = 0;

                if (!size || !el.clientWidth) {
                    return;
                }

                while (el.scrollWidth > el.clientWidth + 0.5 && size > floor && guard++ < 60) {
                    size = Math.max(floor, size - 0.25);
                    el.style.fontSize = size + 'px';
                }
            }

            function fitLabelText(label) {
                var code = label.querySelector('.trace-label__code');
                var mid = label.querySelector('.trace-label__mid');

                if (!code || !mid || !mid.clientHeight) {
                    return;
                }

                fitOneLine(code, 3.6);

                // Step the fields down one tenth of a point at a time, and stop
                // at the first size the box holds — on a normal sticker the
                // wrap alone is enough and nothing changes.
                var pt = 4.1;

                while (mid.scrollHeight > mid.clientHeight + 0.5 && pt > 2.9) {
                    pt = Math.round((pt - 0.1) * 10) / 10;
                    label.style.setProperty('--cell-pt', pt + 'pt');
                }
            }

            function fitLabels() {
                Array.prototype.forEach.call(document.querySelectorAll('.trace-label'), fitLabelText);
            }

            // Last resort for a browser that cannot build the SVG: the stored
            // PNG if there is one, otherwise the same QR service the rest of
            // the app falls back to.
            function drawFallbackQr(root) {
                var target = root.querySelector('.trace-label__qr-draw');
                var payload = stickerText(root);

                if (!target || !payload || target.dataset.drawn === '1' || target.querySelector('img')) {
                    return Promise.resolve();
                }

                target.dataset.drawn = '1';

                return new Promise(function (resolve) {
                    var img = document.createElement('img');
                    img.alt = 'QR code for ' + (root.dataset.assetCode || '');
                    img.onload = img.onerror = function () { resolve(); };
                    img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&qzone=2&ecc='
                        + (isSignedText(payload) ? 'M' : 'L')
                        + '&data=' + encodeURIComponent(payload);
                    target.appendChild(img);
                });
            }

            // Print only once every code is on the sticker: a QR that is still
            // loading would print as a blank square.
            function whenLabelsReady() {
                var qrRoots = Array.prototype.slice.call(document.querySelectorAll('.trace-label__qr'));

                var drawn = qrRoots.map(function (root) {
                    if (drawQr(root)) {
                        return Promise.resolve();
                    }

                    var stored = root.querySelector('.trace-label__qr-image');

                    if (stored) {
                        return stored.complete ? Promise.resolve() : new Promise(function (resolve) {
                            stored.addEventListener('load', resolve, { once: true });
                            stored.addEventListener('error', function () {
                                // A dead stored image is replaced by a drawn code.
                                stored.remove();
                                drawFallbackQr(root).then(resolve);
                            }, { once: true });
                        });
                    }

                    return drawFallbackQr(root);
                });

                // The seal is part of the printed header, so it has to be on the
                // sticker too — including when the published copy 404s and the
                // bundled copy is still swapping in.
                var marks = Array.prototype.slice.call(document.querySelectorAll('.trace-label__mark img'))
                    .filter(function (img) { return img.naturalWidth === 0; })
                    .map(function (img) {
                        return new Promise(function (resolve) {
                            var settle = function () { resolve(); };
                            img.addEventListener('load', settle, { once: true });
                            img.addEventListener('error', function () {
                                img.addEventListener('load', settle, { once: true });
                                img.addEventListener('error', settle, { once: true });
                            }, { once: true });
                        });
                    });

                // Never hold the print job hostage to a slow image.
                var timeout = new Promise(function (resolve) { setTimeout(resolve, 2500); });

                return Promise.race([Promise.all(drawn.concat(marks)), timeout]);
            }

            window.addEventListener('load', function () {
                // Lay the text out before anything is measured: the sticker is
                // about to be printed, and a clipped code is the one thing that
                // cannot be fixed after the label leaves the printer.
                fitLabels();

                whenLabelsReady().then(function () {
                    window.print();
                });
            });
        </script>
    @endif
</body>
</html>
