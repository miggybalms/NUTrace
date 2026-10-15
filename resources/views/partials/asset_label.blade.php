{{--
    One printed asset sticker — the only markup in the app that draws a label.

    Rendered by admin.assets.labels (which owns the stylesheet and the print
    behaviour) and fed by App\Support\AssetLabel, so the department page, the
    asset registry and the replacement flow all print the same sticker instead
    of keeping three copies of it.

    Expects $label:
        code      asset code, printed on its own centred line
        name      asset name, in the header
        location  registered room / office / department
        category  asset category
        acquired  already formatted for print (see AssetLabel::formatAcquired)
        qr        URL of the stored QR image, or null to draw it in the browser
        qr_payload  what the QR encodes when the browser draws it: the asset's
                    signed payload, or its plain code without signing keys
        logo      URL of the official NU seal, or null when it is not available

    Everything is measured in millimetres and the box never grows: long values
    wrap/clip inside their cell rather than pushing the footer off the sticker.
--}}
@php
    $code     = trim((string) ($label['code'] ?? ''));
    $name     = trim((string) ($label['name'] ?? '')) ?: 'ASSET';
    $location = trim((string) ($label['location'] ?? '')) ?: '—';
    $category = trim((string) ($label['category'] ?? '')) ?: '—';
    $acquired = trim((string) ($label['acquired'] ?? '')) ?: '—';
    $qr       = $label['qr'] ?? null;
    $payload  = trim((string) ($label['qr_payload'] ?? '')) ?: \App\Support\AssetQr::textFor($code);
    $logo     = $label['logo'] ?? null;
    $logoFallback = $label['logo_fallback'] ?? \App\Support\AssetLabel::logoFallbackUrl();
@endphp
<div class="trace-label" data-asset-code="{{ $code }}">
    <div class="trace-label__top">
        {{-- Logo slot: rendered only when the official seal is actually available,
             so the header never prints a broken image. --}}
        <span class="trace-label__mark">
            @if($logo)
                {{-- src prefers the published copy; if the media disk has not been
                     given the file yet, the bundled copy takes over. --}}
                <img src="{{ $logo }}" alt="NU seal"
                     onerror="this.onerror=null;this.src='{{ $logoFallback }}'">
            @endif
        </span>

        <span class="trace-label__titles">
            <span class="office">ASSET MANAGEMENT OFFICE</span>
            <span class="name">{{ $name }}</span>
            <span class="campus">NU LIPA</span>
        </span>

        {{-- Empty twin of the logo slot: keeps the titles optically centred. --}}
        <span class="trace-label__mark" aria-hidden="true"></span>
    </div>

    <div class="trace-label__mid">
        <div class="trace-label__info">
            <div class="trace-label__code">{{ $code }}</div>
            <div class="trace-label__cells">
                <div class="cell"><span>Location</span>{{ $location }}</div>
                <div class="cell"><span>Category</span>{{ $category }}</div>
                <div class="cell"><span>Acquired</span>{{ $acquired }}</div>
            </div>
        </div>

        {{-- data-qr-payload is what the sheet draws when there is no stored
             image: the signed payload, never a bare code once signing is on. --}}
        <div class="trace-label__qr" data-asset-code="{{ $code }}" data-qr-payload="{{ $payload }}">
            @if($qr)
                <img class="trace-label__qr-image" src="{{ $qr }}" alt="QR code for {{ $code }}">
            @endif
            {{-- Landing spot for the in-browser QR when no stored image exists. --}}
            <span class="trace-label__qr-draw"></span>
            <span class="scan">Scan me</span>
        </div>
    </div>

    <div class="trace-label__foot">DO NOT REMOVE THIS TAG</div>
</div>
