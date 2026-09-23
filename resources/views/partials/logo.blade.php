{{--
    NU Trace logo mark — one source for every place the brand appears, so the
    admin, employee, department-head and auth shells can't drift apart.

    The artwork is a derived asset (public/images/logo-mark.png). The supplied
    logo was a flat navy rectangle whose navy (#011941) and amber (#F8B008) both
    sat outside this system's palette, so it read as a pasted-on rectangle. That
    background has been keyed out to real transparency and the artwork's gold
    re-hued onto our own gold ramp, which means the mark can sit directly on the
    sidebar navy with no seam. Where the background is light, wrap it in
    $logoBoxSize so the white half of the artwork still has navy behind it.

    Usage:
        @include('partials.logo', ['logoMarkSize' => 27, 'logoBoxSize' => 36])
        @include('partials.logo', ['logoMarkSize' => 26])   // no chip
--}}
@php
    $logoMarkSize = $logoMarkSize ?? 32;
    $logoBoxSize  = $logoBoxSize  ?? null;
    $logoAlt      = $logoAlt      ?? 'NU Trace';
@endphp
@if ($logoBoxSize)
    <span class="inline-flex items-center justify-center rounded-[10px] bg-[#142442] border border-[#C9A227]/25 flex-shrink-0"
          style="width:{{ $logoBoxSize }}px;height:{{ $logoBoxSize }}px">
        <img src="/images/logo-mark.png" alt="{{ $logoAlt }}"
             style="width:{{ $logoMarkSize }}px;height:auto" class="block">
    </span>
@else
    <img src="/images/logo-mark.png" alt="{{ $logoAlt }}"
         style="width:{{ $logoMarkSize }}px;height:auto" class="block flex-shrink-0">
@endif
