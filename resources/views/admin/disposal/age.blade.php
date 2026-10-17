@extends('layouts.admin_sidebar')

@section('title', 'Service-life review')

@section('content')

<style>
    :root{
        --navy-950:#0A1830; --navy-900:#0F2143; --navy-800:#15305B;
        --gold-500:#C9A227; --gold-100:#F3E7C4;
        --paper:#F3EEE0; --paper-2:#EAE2C9;
        --ink-900:#1A2233; --ink-600:#4B5468; --ink-400:#5C6474;
        --line:#DED2AE;
        --forest:#2F7A4D; --forest-tint:#EAF4EE;
        --bronze:#B4791E; --bronze-tint:#FBF1DE;
        --brick:#A23B32; --brick-tint:#F7E9E6;
        --steel:#2E5C8A; --steel-tint:#E9F0F7;
    }
    body{ background:var(--paper) !important; font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif !important; color:var(--ink-900); }
    .font-display{ font-family:'Fraunces',Georgia,serif; }
    .font-mono{ font-family:'IBM Plex Mono',monospace; }
    .eyebrow{ font-size:.68rem; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:var(--ink-400); }
    .card{ background:#fff; border:1px solid var(--line); border-radius:14px; box-shadow:0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28); }
    .field{ border:1px solid var(--line); background:#fff; border-radius:10px; padding:.55rem .75rem; font-size:.875rem; color:var(--ink-900); }
    .field:focus{ outline:none; border-color:var(--gold-500); box-shadow:0 0 0 3px rgba(201,162,39,.18); }
    .btn-gold{ background:var(--gold-500); color:#0A1830; font-weight:600; padding:.55rem 1rem; border-radius:.6rem; display:inline-flex; align-items:center; justify-content:center; font-size:.85rem; }
    .btn-gold:hover{ background:#E0BC44; }
    .btn-ghost{ background:#fff; color:var(--ink-600); border:1px solid var(--gold-500); font-weight:600; padding:.45rem .85rem; border-radius:.6rem; display:inline-flex; align-items:center; justify-content:center; font-size:.8rem; }
    .btn-ghost:hover{ background:var(--paper-2); }
    .btn-quiet{ background:#fff; color:var(--ink-600); border:1px solid var(--line); font-weight:600; padding:.45rem .85rem; border-radius:.6rem; display:inline-flex; align-items:center; justify-content:center; font-size:.8rem; }
    .btn-quiet:hover{ background:var(--paper-2); }
    .badge{ font-size:.66rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; padding:.2rem .5rem; border-radius:999px; display:inline-flex; align-items:center; gap:.25rem; }
    .badge-review{ background:var(--bronze-tint); color:var(--bronze); }
    .badge-progress{ background:var(--steel-tint); color:var(--steel); }
    .stat{ border-radius:14px; border:1px solid var(--line); background:#fff; padding:1rem 1.1rem; }
    .stat p.value{ font-size:1.55rem; font-weight:700; line-height:1.2; }
</style>

@include('admin.partials.header', [
    'adminHeaderPage'  => 'disposal',
    'adminHeaderTitle' => 'Disposal',
    'adminHeaderIcon'  => 'ri-delete-bin-line',
    'adminHeaderBadge' => 'Admin',
])

<div class="p-4 sm:p-8">

    @include('admin.disposal.partials.tabs')

    {{-- ── What this list is (and is not) ─────────────────────────── --}}
    <div class="card p-5 mb-6" style="border-left:4px solid var(--bronze);">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-3xl">
                <p class="font-semibold text-[#0F2143]">
                    <i class="ri-time-line mr-1.5"></i>Assets at or past the proposed
                    {{ \App\Support\AssetAgeReview::thresholdLabel() }} service-life mark
                </p>
                <p class="text-sm text-[#5C6678] mt-1">
                    This is a <span class="font-semibold">review list, not a disposal list</span>. Reaching the age
                    mark is a prompt for the Asset Management Office to inspect the asset and decide whether a
                    disposal request is appropriate. Nothing here approves a disposal, authorizes one, or changes an
                    asset's lifecycle status.
                </p>
                <p class="text-sm text-[#5C6678] mt-2">
                    The mark is counted from the recorded acquisition date. Assets without one, and assets already
                    Disposed or Pulled out, are not listed. Warranty and expected-lifespan dates are different
                    measures and are deliberately not used as disposal eligibility.
                </p>
            </div>
            <div class="text-xs text-[#5C6678] max-w-xs">
                <p class="eyebrow mb-1">Configurable policy</p>
                <p>
                    The proposed mark is {{ \App\Support\AssetAgeReview::years() }} year(s), set in
                    <span class="font-mono">config/disposal.php</span> and
                    <span class="font-mono">DISPOSAL_AGE_REVIEW_YEARS</span>. Change it there once the university
                    confirms its actual policy; no code change is needed.
                </p>
            </div>
        </div>
    </div>

    {{-- ── Counts ───────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="stat">
            <p class="eyebrow">At the mark</p>
            <p class="value" style="color:var(--bronze);">{{ $summary['total'] }}</p>
            <p class="text-xs text-[#5C6678] mt-1">asset{{ $summary['total'] === 1 ? '' : 's' }} to look at</p>
        </div>
        <div class="stat">
            <p class="eyebrow">Oldest</p>
            <p class="value" style="color:var(--navy-800);">
                {{ $summary['oldest'] ? $summary['oldest']->age_label : '—' }}
            </p>
            <p class="text-xs text-[#5C6678] mt-1">
                {{ $summary['oldest'] ? ($summary['oldest']->Asset_code . ' · ' . ($summary['oldest']->Asset_name ?? '')) : 'No assets at the mark' }}
            </p>
        </div>
        <div class="stat">
            <p class="eyebrow">Already in process</p>
            <p class="value" style="color:var(--steel);">{{ $summary['already_requested'] }}</p>
            <p class="text-xs text-[#5C6678] mt-1">with a pending or approved disposal request</p>
        </div>
        <div class="stat">
            <p class="eyebrow">Departments</p>
            <p class="value" style="color:var(--navy-800);">{{ $summary['departments'] }}</p>
            <p class="text-xs text-[#5C6678] mt-1">represented on this list</p>
        </div>
    </div>

    {{-- ── The list ─────────────────────────────────────────────────── --}}
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-[#EFE9D8] flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-semibold text-[#0F2143]"><i class="ri-list-check-2 mr-1.5"></i>For review</p>
                <p class="text-sm text-[#5C6678] mt-1">Oldest acquisition first.</p>
            </div>
            <form method="GET" action="{{ url('/admin/disposal') }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="tab" value="age">
                <input type="search" name="q" value="{{ $search }}" placeholder="Asset, custodian, department…"
                       class="field" style="min-width:220px;" aria-label="Search assets for review">
                <button type="submit" class="btn-gold"><i class="ri-search-line mr-1.5"></i>Search</button>
                @if($search !== '')
                    <a href="{{ url('/admin/disposal?tab=age') }}" class="btn-quiet">Clear</a>
                @endif
            </form>
        </div>

        @if(! $ready)
            <div class="px-5 py-10 text-center text-sm text-[#5C6678]">
                <i class="ri-database-2-line text-2xl block mb-2" style="color:var(--line);"></i>
                The assets table does not have the acquisition-date column this review reads.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b border-[#EFE9D8]">
                            <th class="px-5 py-3 font-semibold">Asset</th>
                            <th class="px-5 py-3 font-semibold hidden md:table-cell">Custodian / department</th>
                            <th class="px-5 py-3 font-semibold hidden lg:table-cell">Acquired</th>
                            <th class="px-5 py-3 font-semibold">Age</th>
                            <th class="px-5 py-3 font-semibold hidden xl:table-cell">Condition</th>
                            <th class="px-5 py-3 font-semibold">Review status</th>
                            <th class="px-5 py-3 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($candidates as $asset)
                            <tr class="border-b border-[#F5F0E2] last:border-0 align-top">
                                <td class="px-5 py-3.5">
                                    <p class="font-mono text-xs text-[#0F2143]">{{ $asset->Asset_code }}</p>
                                    <p class="text-[#0F2143]">{{ $asset->Asset_name ?: '—' }}</p>
                                    <p class="text-xs text-[#5C6678]">
                                        {{ $asset->Category ?: '—' }}
                                        @if($asset->asset_location)
                                            · {{ $asset->asset_location }}
                                        @endif
                                    </p>
                                </td>
                                <td class="px-5 py-3.5 hidden md:table-cell text-[#5C6678]">
                                    {{ $asset->custodian }}
                                    <span class="block text-xs">{{ $asset->department ?: 'No department recorded' }}</span>
                                </td>
                                <td class="px-5 py-3.5 hidden lg:table-cell text-[#5C6678]">
                                    {{ $asset->date_acquired ? \Illuminate\Support\Carbon::parse($asset->date_acquired)->format('M j, Y') : '—' }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="badge badge-review">{{ $asset->age_label }}</span>
                                </td>
                                <td class="px-5 py-3.5 hidden xl:table-cell text-[#5C6678]">
                                    {{ $asset->Condition ?: '—' }}
                                    <span class="block text-xs">{{ $asset->Lifecycle_Status ?: '—' }}</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if($asset->has_open_disposal)
                                        <span class="badge badge-progress"><i class="ri-hourglass-line"></i>Already in a disposal request</span>
                                    @else
                                        <span class="text-xs text-[#5C6678]">Not yet reviewed</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="{{ url('/admin/assets/' . $asset->id) }}" class="btn-ghost">
                                        <i class="ri-eye-line mr-1.5"></i>Open asset
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-sm text-[#5C6678]">
                                    <i class="ri-checkbox-circle-line text-2xl block mb-2" style="color:var(--forest);"></i>
                                    @if($search !== '')
                                        No asset at the {{ \App\Support\AssetAgeReview::thresholdLabel() }} mark matches
                                        “{{ $search }}”.
                                    @else
                                        No assets have reached the {{ \App\Support\AssetAgeReview::thresholdLabel() }}
                                        mark with an acquisition date on record.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>

@endsection
