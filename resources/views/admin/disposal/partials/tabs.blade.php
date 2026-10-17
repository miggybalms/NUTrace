{{--
    The Disposal page's two tabs.

    Both tables live on one URL (the one the sidebar already points at), so the
    finished disposal records and the forms being prepared, printed and filed are
    one page with two views. Neither view can drift from the other: this partial
    is the only place the tab bar is written.
--}}
@php
    $disposalTab       = $disposalTab ?? 'records';
    $formCounts        = $formCounts ?? [];
    $awaitingFormCount = $awaitingFormCount ?? 0;
    $ageReviewEnabled  = $ageReviewEnabled ?? false;

    $openForms = ($formCounts['draft'] ?? 0)
        + ($formCounts['ready'] ?? 0)
        + ($formCounts['printed'] ?? 0)
        + ($formCounts['signed'] ?? 0);
@endphp

<div class="mb-6" style="background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden;">
    <div class="flex flex-wrap items-center justify-between gap-x-3 px-2 sm:px-4 border-b border-[#EFE9D8]">
        <div class="flex overflow-x-auto">
            <a href="{{ url('/admin/disposal') }}"
               class="px-4 py-3.5 text-sm font-semibold whitespace-nowrap border-b-2 {{ $disposalTab === 'records' ? 'border-[#C9A227] text-[#0F2143]' : 'border-transparent text-[#5B6678] hover:text-[#0F2143]' }}">
                <i class="ri-archive-2-line mr-1.5"></i>Disposal Records
            </a>
            <a href="{{ url('/admin/disposal') }}?tab=forms"
               class="px-4 py-3.5 text-sm font-semibold whitespace-nowrap border-b-2 {{ $disposalTab === 'forms' ? 'border-[#C9A227] text-[#0F2143]' : 'border-transparent text-[#5B6678] hover:text-[#0F2143]' }}">
                <i class="ri-file-list-3-line mr-1.5"></i>Official Disposal Forms
                @if($openForms > 0)
                    <span class="ml-2 text-[.68rem] font-bold px-2 py-0.5 rounded-full"
                          style="background:#F3E7C4; color:#0F2143;">{{ $openForms }} open</span>
                @endif
            </a>
            @if($ageReviewEnabled)
                <a href="{{ url('/admin/disposal') }}?tab=age"
                   class="px-4 py-3.5 text-sm font-semibold whitespace-nowrap border-b-2 {{ $disposalTab === 'age' ? 'border-[#C9A227] text-[#0F2143]' : 'border-transparent text-[#5B6678] hover:text-[#0F2143]' }}">
                    <i class="ri-time-line mr-1.5"></i>Service-life review
                </a>
            @endif
        </div>
        <p class="text-sm text-[#5C6474] py-3">
            @if($disposalTab === 'forms')
                Official NU Lipa disposal forms and where each one is in the paper trail
            @elseif($disposalTab === 'age')
                Assets at the proposed age mark — for review only, never automatic disposal
            @else
                Assets whose disposal has been finalized and recorded
            @endif
        </p>
    </div>
</div>
