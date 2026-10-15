@extends('layouts.admin_sidebar')

@section('title', 'Official Disposal Forms')

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
    .btn-gold{ background:var(--gold-500); color:#0A1830; font-weight:600; padding:.6rem 1.05rem; border-radius:.6rem; display:inline-flex; align-items:center; justify-content:center; font-size:.875rem; transition:background .15s; }
    .btn-gold:hover{ background:#E0BC44; }
    .btn-ghost{ background:#fff; color:var(--ink-600); border:1px solid var(--gold-500); font-weight:600; padding:.5rem .9rem; border-radius:.6rem; display:inline-flex; align-items:center; justify-content:center; font-size:.8125rem; transition:background .15s; }
    .btn-ghost:hover{ background:var(--paper-2); }
    .btn-quiet{ background:#fff; color:var(--ink-600); border:1px solid var(--line); font-weight:600; padding:.5rem .9rem; border-radius:.6rem; display:inline-flex; align-items:center; justify-content:center; font-size:.8125rem; }
    .btn-quiet:hover{ background:var(--paper-2); }
    .badge{ font-size:.68rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; padding:.2rem .5rem; border-radius:999px; display:inline-flex; align-items:center; gap:.25rem; }
    .badge-draft{ background:var(--bronze-tint); color:var(--bronze); }
    .badge-ready{ background:var(--gold-100); color:#7E5E0E; }
    .badge-printed{ background:var(--steel-tint); color:var(--steel); }
    .badge-signed{ background:var(--forest-tint); color:var(--forest); }
    .badge-finalized{ background:#E6E9F0; color:var(--navy-800); }
    .stat{ border-radius:14px; border:1px solid var(--line); background:#fff; padding:1rem 1.1rem; }
    .stat p.value{ font-size:1.6rem; font-weight:700; line-height:1.2; }
</style>

@include('admin.partials.header', [
    'adminHeaderPage'  => 'disposal',
    'adminHeaderTitle' => 'Disposal',
    'adminHeaderIcon'  => 'ri-delete-bin-line',
    'adminHeaderBadge' => 'Admin',
])

<div class="p-4 sm:p-8">

    @if(session('success'))
        <div class="card p-4 mb-5" style="border-left:4px solid var(--forest);">
            <p class="text-sm text-[#245C3B]"><i class="ri-checkbox-circle-line mr-1.5"></i>{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="card p-4 mb-5" style="border-left:4px solid var(--brick);">
            <p class="text-sm text-[#7E2E27]"><i class="ri-error-warning-line mr-1.5"></i>{{ session('error') }}</p>
        </div>
    @endif

    @include('admin.disposal.partials.tabs')

    @if(! $formsReady)
        <div class="card p-5 mb-6" style="border-left:4px solid var(--bronze);">
            <p class="text-sm font-semibold text-[#8F5F16]"><i class="ri-database-2-line mr-1.5"></i>The disposal form tables are not in the database yet.</p>
            <p class="text-sm text-[#5C6678] mt-1">
                Run <span class="font-mono text-xs">php artisan migrate</span> to create them. Until then the official
                disposal forms cannot be prepared, printed or tracked — approving a disposal request will refuse
                rather than dispose of an asset without its form.
            </p>
        </div>
    @endif

    {{-- ── Where the work sits ─────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        @foreach([
            'draft'     => ['Awaiting preparation', 'ri-edit-2-line', '#B4791E'],
            'ready'     => ['Ready to print', 'ri-printer-line', '#A8841E'],
            'printed'   => ['Printed — awaiting signed form', 'ri-send-plane-line', '#2E5C8A'],
            'signed'    => ['Signed form received', 'ri-file-shield-2-line', '#2F7A4D'],
            'finalized' => ['Disposal finalized', 'ri-checkbox-circle-line', '#15305B'],
        ] as $key => $meta)
            <div class="stat">
                <div class="flex items-start justify-between gap-2 mb-1">
                    <p class="text-[.72rem] font-semibold uppercase tracking-wide text-[#5C6678] leading-tight">{{ $meta[0] }}</p>
                    <i class="{{ $meta[1] }}" style="color:{{ $meta[2] }};"></i>
                </div>
                <p class="value" style="color:{{ $meta[2] }};">{{ $formCounts[$key] ?? 0 }}</p>
            </div>
        @endforeach
    </div>

    {{-- ── Approved requests with no form yet ──────────────────────── --}}
    @if($awaiting->isNotEmpty())
        <div class="card overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-[#EFE9D8] flex items-start justify-between gap-3">
                <div>
                    <p class="font-semibold text-[#0F2143]">
                        <i class="ri-file-add-line mr-1.5"></i>Approved disposal requests with no form yet
                        <span class="badge badge-draft ml-2">{{ $awaiting->count() }}</span>
                    </p>
                    <p class="text-sm text-[#5C6678] mt-1">
                        These were approved before official disposal forms were generated automatically. Preparing a
                        form documents what was disposed of; it does not dispose of anything by itself.
                    </p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b border-[#EFE9D8]">
                            <th class="px-5 py-3 font-semibold">Request</th>
                            <th class="px-5 py-3 font-semibold hidden md:table-cell">Requested by</th>
                            <th class="px-5 py-3 font-semibold">Reason given</th>
                            <th class="px-5 py-3 font-semibold hidden lg:table-cell">Approved</th>
                            <th class="px-5 py-3 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($awaiting as $row)
                            <tr class="border-b border-[#F5F0E2] last:border-0">
                                <td class="px-5 py-3">
                                    <span class="font-mono text-xs text-[#0F2143]">#{{ $row->id }}</span>
                                    @if($row->already_recorded)
                                        <span class="badge badge-finalized ml-2">Disposal already recorded</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 hidden md:table-cell text-[#5B6678]">
                                    {{ $row->requester_name ?: ($row->requester_email ?: '—') }}
                                </td>
                                <td class="px-5 py-3 text-[#5B6678] max-w-[26rem]">
                                    {{ \Illuminate\Support\Str::limit(trim((string) $row->Note) ?: '—', 160) }}
                                </td>
                                <td class="px-5 py-3 hidden lg:table-cell text-[#5B6678]">
                                    {{ $row->created_at ? \Illuminate\Support\Carbon::parse($row->created_at)->format('M j, Y') : '—' }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ url('/admin/disposal/requests/' . $row->id . '/form') }}">
                                        @csrf
                                        <button type="submit" class="btn-gold">
                                            <i class="ri-file-add-line mr-1.5"></i>Prepare form
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ── The queue ───────────────────────────────────────────────── --}}
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-[#EFE9D8] flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-semibold text-[#0F2143]"><i class="ri-file-list-3-line mr-1.5"></i>Disposal form queue</p>
                <p class="text-sm text-[#5C6678] mt-1">
                    Prepare a form, print it, file the signed copy Accountancy returns, and only then finalize the
                    disposal. Printing a form never marks an asset disposed of.
                </p>
            </div>

            {{-- The batch-print form stands on its own; the checkboxes below are
                 attached to it with the HTML form attribute so the table can hold
                 per-row forms as well (a form may not be nested in another). --}}
            <form id="print-batch" method="POST" action="{{ url('/admin/disposal/forms/print') }}">
                @csrf
                <button type="submit" class="btn-gold">
                    <i class="ri-printer-line mr-1.5"></i>Print selected
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-[#EFE9D8]">
                        <th class="px-4 py-3 w-10"></th>
                        <th class="px-4 py-3 font-semibold">Form</th>
                        <th class="px-4 py-3 font-semibold">Assets</th>
                        <th class="px-4 py-3 font-semibold hidden md:table-cell">Office / Requester</th>
                        <th class="px-4 py-3 font-semibold">Stage</th>
                        <th class="px-4 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($forms as $form)
                        @php
                            $printable = \App\Support\DisposalForms::isPrintable($form->status);
                            $badge = [
                                'draft'     => 'badge-draft',
                                'ready'     => 'badge-ready',
                                'printed'   => 'badge-printed',
                                'signed'    => 'badge-signed',
                                'finalized' => 'badge-finalized',
                            ][$form->status] ?? 'badge-draft';
                        @endphp
                        <tr class="border-b border-[#F5F0E2] last:border-0 align-top">
                            <td class="px-4 py-3.5">
                                <input type="checkbox" form="print-batch" name="ids[]" value="{{ $form->id }}"
                                       @disabled(! $printable)
                                       class="w-4 h-4"
                                       title="{{ $printable ? 'Select this form for batch printing' : 'Only a ready, printed or signed form can be printed' }}">
                            </td>
                            <td class="px-4 py-3.5">
                                <p class="font-mono text-xs text-[#0F2143]">#{{ $form->id }}</p>
                                <p class="text-xs text-[#5C6678] mt-0.5">Request <span class="font-mono">#{{ $form->request_id }}</span></p>
                                @if($form->printed_at)
                                    <p class="text-xs text-[#5C6678] mt-0.5">
                                        Printed {{ \Illuminate\Support\Carbon::parse($form->printed_at)->format('M j, Y') }}
                                        · {{ (int) $form->print_count }}×
                                    </p>
                                @endif
                                @if($form->has_signed_file)
                                    <a href="{{ url('/admin/disposal/forms/' . $form->id . '/signed-copy') }}"
                                       class="text-xs font-semibold text-[#2E5C8A] hover:underline">
                                        <i class="ri-attachment-2"></i> Signed copy
                                    </a>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                @forelse($form->items as $item)
                                    <p class="font-mono text-xs text-[#0F2143]">{{ $item->tag_no ?: '—' }}</p>
                                    <p class="text-xs text-[#5C6678] mb-1">{{ \Illuminate\Support\Str::limit(trim((string) $item->description) ?: '—', 46) }}</p>
                                @empty
                                    <p class="text-xs text-[#5C6678]">No assets on this form.</p>
                                @endforelse
                            </td>
                            <td class="px-4 py-3.5 hidden md:table-cell">
                                <p class="text-[#0F2143]">{{ $form->office_department ?: '—' }}</p>
                                <p class="text-xs text-[#5C6678]">{{ $form->requester_name ?: ($form->requester_email ?: '—') }}</p>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="badge {{ $badge }}">{{ $form->status_label }}</span>
                                <p class="text-xs text-[#5C6678] mt-1 max-w-[16rem]">{{ $form->status_hint }}</p>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <a href="{{ url('/admin/disposal/forms/' . $form->id) }}" class="btn-ghost">
                                        <i class="ri-edit-2-line mr-1.5"></i>{{ \App\Support\DisposalForms::isFinalized($form->status) ? 'View' : 'Prepare' }}
                                    </a>
                                    <a href="{{ url('/admin/disposal/forms/' . $form->id . '/preview') }}" target="_blank" rel="noopener" class="btn-quiet">
                                        <i class="ri-eye-line mr-1.5"></i>Review
                                    </a>
                                    @if($printable)
                                        <form method="POST" action="{{ url('/admin/disposal/forms/print') }}">
                                            @csrf
                                            <input type="hidden" name="ids[]" value="{{ $form->id }}">
                                            <button type="submit" class="btn-quiet">
                                                <i class="ri-printer-line mr-1.5"></i>Print
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-[#5C6678]">
                                <i class="ri-inbox-line text-2xl block mb-2" style="color:var(--line);"></i>
                                No official disposal forms yet. Approving a disposal request on the Requests page
                                prepares one automatically.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection
