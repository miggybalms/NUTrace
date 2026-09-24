{{--
    NU Trace logo mark — one source for every place the brand appears, so the
    admin, employee, department-head and auth shells can't drift apart.

    The artwork is a derived asset. The supplied logo was a flat navy rectangle
    whose navy (#011941) and amber (#F8B008) both sat outside this system's
    palette, so it read as a pasted-on rectangle. That background has been keyed
    out to real transparency and the artwork's gold re-hued onto our own gold
    ramp, which means the mark can sit directly on the sidebar navy with no seam.
    Where the background is light, wrap it in $logoBoxSize so the white half of
    the artwork still has navy behind it.

    The file itself is served from Supabase Storage (Media::brand) so branding
    can be changed by uploading one file to the "brand" folder of the bucket —
    no rebuild, no redeploy — and it resolves on any device. When the bucket
    copy is missing the browser drops back to the copy bundled in public/.

    Usage:
        @include('partials.logo', ['logoMarkSize' => 27, 'logoBoxSize' => 36])
        @include('partials.logo', ['logoMarkSize' => 26])   // no chip
        @include('partials.logo', ['logoAutoSize' => true]) // size comes from CSS
--}}
@php
    $logoMarkSize = $logoMarkSize ?? 32;
    $logoBoxSize  = $logoBoxSize  ?? null;
    $logoAlt      = $logoAlt      ?? 'NU Trace';
    $logoClass    = $logoClass    ?? 'block flex-shrink-0';
    $logoAutoSize = $logoAutoSize ?? false;

    // Prefer the copy published to Supabase; fall back to the bundled file.
    $logoSrc      = \App\Support\Media::brand('logo-mark.png');
    $logoFallback = \App\Support\Media::brandLocalUrl('logo-mark.png');
@endphp
@if ($logoBoxSize)
    <span class="inline-flex items-center justify-center rounded-[10px] bg-[#142442] border border-[#C9A227]/25 flex-shrink-0"
          style="width:{{ $logoBoxSize }}px;height:{{ $logoBoxSize }}px">
        <img src="{{ $logoSrc }}" alt="{{ $logoAlt }}"
             @if ($logoClass) class="{{ $logoClass }}" @endif
             @unless ($logoAutoSize) style="width:{{ $logoMarkSize }}px;height:auto" @endunless
             onerror="this.onerror=null;this.src='{{ $logoFallback }}'">
    </span>
@else
    <img src="{{ $logoSrc }}" alt="{{ $logoAlt }}"
         @if ($logoClass) class="{{ $logoClass }}" @endif
         @unless ($logoAutoSize) style="width:{{ $logoMarkSize }}px;height:auto" @endunless
         onerror="this.onerror=null;this.src='{{ $logoFallback }}'">
@endif
