{{--
    One printed asset sticker — the only markup in the app that draws a label.

    Rendered by admin.assets.labels (which owns the stylesheet and the print
    behaviour) and fed by App\Support\AssetLabel, so the department page, the
    asset registry and the replacement flow all print the same sticker instead
    of keeping three copies of it.

    Expects $label:
        code      asset code, printed on its own line
        name      asset name, in the header
        location  registered room / office / department
        category  asset category
        acquired  already formatted for print (see AssetLabel::formatAcquired)
        qr        URL of the stored QR image, or null to draw it in the browser
        qr_payload  what the QR encodes when the browser draws it: the asset's
                    signed payload, or its plain code without signing keys
        logo      URL of the official NU seal, or null when it is not available

    Shape: two columns. The left column carries the seal, the name, the code and
    the three fields; the right column is the QR square and nothing else, so no
    text, rule or logo can ever sit on its modules or in its quiet zone.

    Everything is measured in millimetres and the box never grows: long values
    clip inside their line rather than pushing the QR or the footer off the
    sticker.
--}}
@php
    $code     = trim((string) ($label['code'] ?? ''));
    $name     = trim((string) ($label['name'] ?? '')) ?: 'ASSET';
    $location = trim((string) ($label['location'] ?? '')) ?: '—';
    $category = trim((string) ($label['category'] ?? '')) ?: '—';
    $acquired = trim((string) ($label['acquired'] ?? '')) ?: '—';
    $qr       = $label['qr'] ?? null;
    $payload  = trim((string) ($label['qr_payload'] ?? '')) ?: \App\Support\AssetQr::stickerText($code);
    $logo     = $label['logo'] ?? null;
    $logoFallback = $label['logo_fallback'] ?? \App\Support\AssetLabel::logoFallbackUrl();
@endphp
<div class="trace-label" data-asset-code="{{ $code }}">
    <div class="trace-label__left">
        <div class="trace-label__top">
            {{-- Logo slot: rendered only when the official seal is actually
                 available, so the header never prints a broken image. --}}
            <span class="trace-label__mark">
                @if($logo)
                    {{-- src prefers the published copy; if the media disk has not
                         been given the file yet, the bundled copy takes over. --}}
                    <img src="{{ $logo }}" alt="NU seal"
                         onerror="this.onerror=null;this.src='{{ $logoFallback }}'">
                @endif
            </span>

            <span class="trace-label__titles">
                <span class="office">ASSET MANAGEMENT OFFICE</span>
                <span class="name">{{ $name }}</span>
                <span class="campus">NU LIPA</span>
            </span>
        </div>

        <div class="trace-label__mid">
            <div class="trace-label__code">{{ $code }}</div>

            <div class="trace-label__cells">
                <div class="cell"><span>Location</span><span class="value">{{ $location }}</span></div>
                <div class="cell"><span>Category</span><span class="value">{{ $category }}</span></div>
                <div class="cell"><span>Acquired</span><span class="value">{{ $acquired }}</span></div>
            </div>
        </div>

        <div class="trace-label__foot">DO NOT REMOVE THIS TAG</div>
    </div>

    @php
        // A NUTAG is not a QR code: no camera app or scanning website finds a
        // code on the sticker at all. It is drawn here, server-side, as exact
        // vector cells, and it carries no data-qr-payload - there is nothing
        // for the sheet's QR drawing to do.
        $tagSvg = \App\Support\NutTag::isTagText($payload)
            ? \App\Support\NutTag::svgFromText($payload, 'NU Trace tag for '.$code)
            : null;
    @endphp

    @if($tagSvg)
        <div class="trace-label__qr trace-label__qr--tag" data-asset-code="{{ $code }}">
            {!! $tagSvg !!}
        </div>
    @else
        {{-- data-qr-payload is what the sheet draws: the signed payload, never a
             bare code once signing is on. The stored image stays as the fallback
             for a browser that cannot draw (and is what prints with JS disabled);
             the sheet replaces it with a crisp SVG at print resolution. --}}
        <div class="trace-label__qr" data-asset-code="{{ $code }}" data-qr-payload="{{ $payload }}">
            @if($qr)
                <img class="trace-label__qr-image" src="{{ $qr }}" alt="QR code for {{ $code }}">
            @endif
            <span class="trace-label__qr-draw"></span>
        </div>
    @endif
</div>
