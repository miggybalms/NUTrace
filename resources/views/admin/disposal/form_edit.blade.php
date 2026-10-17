@extends('layouts.admin_sidebar')

@section('title', 'Official Disposal Form #' . $form->id)

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
    .card{ background:#fff; border:1px solid var(--line); border-radius:14px; box-shadow:0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28); }
    .field{ border:1px solid var(--line); background:#fff; border-radius:10px; padding:.5rem .65rem; font-size:.85rem; color:var(--ink-900); width:100%; }
    .field:focus{ outline:none; border-color:var(--gold-500); box-shadow:0 0 0 3px rgba(201,162,39,.18); }
    .field-invalid{ border-color:var(--brick); background:#FFF8F7; }
    .btn-gold{ background:var(--gold-500); color:#0A1830; font-weight:600; padding:.6rem 1.05rem; border-radius:.6rem; display:inline-flex; align-items:center; justify-content:center; font-size:.875rem; }
    .btn-gold:hover{ background:#E0BC44; }
    .btn-ghost{ background:#fff; color:var(--ink-600); border:1px solid var(--gold-500); font-weight:600; padding:.5rem .9rem; border-radius:.6rem; display:inline-flex; align-items:center; justify-content:center; font-size:.8125rem; }
    .btn-ghost:hover{ background:var(--paper-2); }
    .btn-danger{ background:var(--brick); color:#fff; font-weight:600; padding:.6rem 1.05rem; border-radius:.6rem; display:inline-flex; align-items:center; justify-content:center; font-size:.875rem; }
    .btn-danger:hover{ filter:brightness(1.08); }
    .btn-disabled{ opacity:.45; pointer-events:none; }
    .badge{ font-size:.68rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; padding:.2rem .5rem; border-radius:999px; display:inline-flex; align-items:center; gap:.25rem; }
    .badge-draft{ background:var(--bronze-tint); color:var(--bronze); }
    .badge-ready{ background:var(--gold-100); color:#7E5E0E; }
    .badge-printed{ background:var(--steel-tint); color:var(--steel); }
    .badge-signed{ background:var(--forest-tint); color:var(--forest); }
    .badge-finalized{ background:#E6E9F0; color:var(--navy-800); }
    .eyebrow{ font-size:.68rem; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:var(--ink-400); }
    .code-table{ width:100%; border-collapse:collapse; font-size:.78rem; }
    .code-table th, .code-table td{ border:1px solid var(--line); padding:.3rem .5rem; text-align:left; }
    .code-table th{ background:#F7F3E7; font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; }
    .items-table{ width:100%; border-collapse:collapse; font-size:.8rem; }
    .items-table th{ background:#F7F3E7; font-size:.68rem; text-transform:uppercase; letter-spacing:.04em; text-align:left; padding:.45rem .5rem; border-bottom:1px solid var(--line); white-space:nowrap; }
    .items-table td{ padding:.4rem .4rem; border-bottom:1px solid #F1EBDC; vertical-align:top; }
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

    @if($errors->any())
        <div class="card p-4 mb-5" style="border-left:4px solid var(--brick);">
            <p class="text-sm font-semibold text-[#7E2E27]">Please check the form:</p>
            <ul class="text-sm text-[#7E2E27] mt-1 list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $badge = [
            'draft'     => 'badge-draft',
            'ready'     => 'badge-ready',
            'printed'   => 'badge-printed',
            'signed'    => 'badge-signed',
            'finalized' => 'badge-finalized',
        ][$form->status] ?? 'badge-draft';

        $locked     = \App\Support\DisposalForms::isFinalized($form->status);
        $printable  = \App\Support\DisposalForms::isPrintable($form->status);
        $canSign    = in_array($form->status, ['printed', 'signed'], true);
        $canFinalize = $form->status === 'signed';
        $old = fn (string $key, $default = null) => old($key, $default);
    @endphp

    {{-- ── Headline ────────────────────────────────────────────────── --}}
    <div class="card p-5 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="eyebrow">Official NU Lipa Disposal Form</p>
                <h1 class="font-display text-2xl font-bold text-[#0F2143] mt-1">Form #{{ $form->id }}</h1>
                <p class="text-sm text-[#5C6678] mt-1">
                    Disposal request
                    <span class="font-mono text-xs text-[#0F2143]">#{{ $form->request_id }}</span>
                    · requested by {{ $form->requester_name ?: ($form->requester_email ?: '—') }}
                    @if($form->request_date)
                        on {{ \Illuminate\Support\Carbon::parse($form->request_date)->format('M j, Y') }}
                    @endif
                </p>
                <p class="text-sm text-[#5C6678] mt-2 max-w-3xl">
                    <span class="font-semibold text-[#0F2143]">Requester's note:</span>
                    “{{ trim((string) $form->request_note) ?: '—' }}”
                </p>
            </div>
            <div class="text-right">
                <span class="badge {{ $badge }}">{{ $form->status_label }}</span>
                <p class="text-xs text-[#5C6678] mt-2 max-w-[18rem]">{{ $form->status_hint }}</p>
                <div class="mt-3 flex flex-wrap justify-end gap-2">
                    <a href="{{ url('/admin/disposal?tab=forms') }}" class="btn-ghost">
                        <i class="ri-arrow-left-line mr-1.5"></i>Back to the queue
                    </a>
                    <a href="{{ url('/admin/disposal/forms/' . $form->id . '/preview') }}" target="_blank" rel="noopener" class="btn-ghost">
                        <i class="ri-eye-line mr-1.5"></i>Review the printed form
                    </a>
                </div>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-5 pt-4 border-t border-[#EFE9D8] text-xs text-[#5C6678]">
            <p><span class="block eyebrow mb-0.5">Prepared</span>
                {{ $form->prepared_at ? \Illuminate\Support\Carbon::parse($form->prepared_at)->format('M j, Y g:i A') : '—' }}
                @if($form->prepared_by_name || $form->prepared_by_email)
                    <span class="block">{{ $form->prepared_by_name ?: $form->prepared_by_email }}</span>
                @endif
            </p>
            <p><span class="block eyebrow mb-0.5">Printed</span>
                {{ $form->printed_at ? \Illuminate\Support\Carbon::parse($form->printed_at)->format('M j, Y g:i A') : 'Not printed yet' }}
                @if($form->print_count) <span class="block">{{ (int) $form->print_count }} time(s)</span> @endif
            </p>
            <p><span class="block eyebrow mb-0.5">Signed form received</span>
                {{ $form->signed_received_at ? \Illuminate\Support\Carbon::parse($form->signed_received_at)->format('M j, Y') : 'Not recorded yet' }}
                @if($form->has_signed_file) <span class="block">Scanned copy on file</span> @endif
            </p>
            <p><span class="block eyebrow mb-0.5">Disposal finalized</span>
                {{ $form->finalized_at ? \Illuminate\Support\Carbon::parse($form->finalized_at)->format('M j, Y g:i A') : 'Not finalized' }}
                @if($form->actual_disposal_date)
                    <span class="block">Disposed {{ \Illuminate\Support\Carbon::parse($form->actual_disposal_date)->format('M j, Y') }}</span>
                @endif
            </p>
        </div>
    </div>

    @if($missing)
        <div class="card p-4 mb-6" style="border-left:4px solid var(--bronze);">
            <p class="text-sm font-semibold text-[#8F5F16]"><i class="ri-error-warning-line mr-1.5"></i>Still missing before this form can be printed</p>
            <p class="text-sm text-[#5C6678] mt-1">{{ implode(' · ', $missing) }}</p>
        </div>
    @endif

    {{-- Everything that can be completed lives in one form. --}}
    <form method="POST" action="{{ url('/admin/disposal/forms/' . $form->id . '/save') }}" class="card p-5 mb-6">
        @csrf

        <p class="font-semibold text-[#0F2143] mb-1"><i class="ri-edit-2-line mr-1.5"></i>Disposal details</p>
        <p class="text-sm text-[#5C6678] mb-4">
            Prefilled from the asset records and the approved request. Anything the records do not hold is left for
            the Asset Management Office to complete — nothing is guessed at.
        </p>

        <div class="grid md:grid-cols-3 gap-4">
            <div>
                <label class="eyebrow block mb-1.5" for="office_department">Office / Department</label>
                <input id="office_department" name="office_department" type="text" maxlength="150" class="field"
                       value="{{ $old('office_department', $form->office_department) }}">
            </div>
            <div>
                <label class="eyebrow block mb-1.5" for="expected_disposal_date">Expected Date of Disposal</label>
                <input id="expected_disposal_date" name="expected_disposal_date" type="date" class="field"
                       value="{{ $old('expected_disposal_date', $form->expected_disposal_date) }}">
            </div>
            <div>
                <label class="eyebrow block mb-1.5" for="method_key">Method of Disposal Code</label>
                <select id="method_key" name="method_key" class="field">
                    <option value="">— choose a method —</option>
                    @foreach(\App\Support\DisposalCodes::METHOD_CODES as $key => $method)
                        <option value="{{ $key }}" @selected($old('method_key', $form->method_key) === $key)>
                            {{ $method['code'] }} — {{ $method['label'] }}
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-[#5C6678] mt-1">
                    As printed on the official form: T appears twice (Tender/sold and Trade-in).
                </p>
            </div>
        </div>

        <div class="mt-5 pt-4 border-t border-[#EFE9D8]">
            <p class="font-semibold text-[#0F2143] text-sm mb-3">Additional fields for sold items</p>
            <div class="grid md:grid-cols-5 gap-4">
                <div>
                    <label class="eyebrow block mb-1.5" for="sold_amount">Amount</label>
                    <input id="sold_amount" name="sold_amount" type="number" step="0.01" min="0" class="field"
                           value="{{ $old('sold_amount', $form->sold_amount) }}">
                </div>
                <div>
                    <label class="eyebrow block mb-1.5" for="sold_or_no">OR No.</label>
                    <input id="sold_or_no" name="sold_or_no" type="text" maxlength="60" class="field"
                           value="{{ $old('sold_or_no', $form->sold_or_no) }}">
                </div>
                <div>
                    <label class="eyebrow block mb-1.5" for="sold_date">Date</label>
                    <input id="sold_date" name="sold_date" type="date" class="field"
                           value="{{ $old('sold_date', $form->sold_date) }}">
                </div>
                <div>
                    <label class="eyebrow block mb-1.5" for="gate_pass_no">Gate Pass No.</label>
                    <input id="gate_pass_no" name="gate_pass_no" type="text" maxlength="60" class="field"
                           value="{{ $old('gate_pass_no', $form->gate_pass_no) }}">
                </div>
                <div>
                    <label class="eyebrow block mb-1.5" for="gate_pass_date">Date</label>
                    <input id="gate_pass_date" name="gate_pass_date" type="date" class="field"
                           value="{{ $old('gate_pass_date', $form->gate_pass_date) }}">
                </div>
            </div>
            <p class="text-xs text-[#5C6678] mt-2">
                Required when the method of disposal is Tender/sold; otherwise leave blank.
            </p>
        </div>

        <div class="mt-5">
            <label class="eyebrow block mb-1.5" for="remarks">Remarks</label>
            <textarea id="remarks" name="remarks" rows="2" class="field" maxlength="2000">{{ $old('remarks', $form->remarks) }}</textarea>
        </div>

        {{-- ── The items ────────────────────────────────────────────── --}}
        <div class="mt-6">
            <p class="font-semibold text-[#0F2143] text-sm mb-1">
                <i class="ri-list-check-2 mr-1.5"></i>Items on this form
            </p>
            <p class="text-sm text-[#5C6678] mb-3">
                One row per asset covered by request <span class="font-mono text-xs">#{{ $form->request_id }}</span>.
                Tag No., Description, Condition Code and Reason for Disposal Code are required to print.
            </p>

            <div class="overflow-x-auto">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:34px;">#</th>
                            <th style="min-width:120px;">Tag No.</th>
                            <th style="width:74px;">Quantity</th>
                            <th style="min-width:190px;">Description</th>
                            <th style="min-width:130px;">Serial Number</th>
                            <th style="min-width:130px;">Date Acquired</th>
                            <th style="min-width:120px;">Location</th>
                            <th style="min-width:140px;">Custodian</th>
                            <th style="width:150px;">Condition Code</th>
                            <th style="width:180px;">Reason for Disposal Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $index => $item)
                            <tr>
                                <td class="text-[#5C6678] pt-3">{{ $index + 1 }}</td>
                                <td>
                                    <input name="items[{{ $item->id }}][tag_no]" type="text" maxlength="60" class="field"
                                           value="{{ $old('items.' . $item->id . '.tag_no', $item->tag_no) }}">
                                    @if($item->asset_id)
                                        <p class="text-xs text-[#5C6678] mt-1">Asset ID {{ $item->asset_id }}</p>
                                    @else
                                        <p class="text-xs text-[#B4791E] mt-1">Asset record missing</p>
                                    @endif
                                </td>
                                <td>
                                    <input name="items[{{ $item->id }}][quantity]" type="number" min="1" class="field"
                                           value="{{ $old('items.' . $item->id . '.quantity', $item->quantity) }}">
                                </td>
                                <td>
                                    <input name="items[{{ $item->id }}][description]" type="text" maxlength="255" class="field"
                                           value="{{ $old('items.' . $item->id . '.description', $item->description) }}">
                                </td>
                                <td>
                                    <input name="items[{{ $item->id }}][serial_number]" type="text" maxlength="150" class="field"
                                           value="{{ $old('items.' . $item->id . '.serial_number', $item->serial_number) }}">
                                </td>
                                <td>
                                    <input name="items[{{ $item->id }}][date_acquired]" type="date" class="field"
                                           value="{{ $old('items.' . $item->id . '.date_acquired', $item->date_acquired) }}">
                                </td>
                                <td>
                                    <input name="items[{{ $item->id }}][location]" type="text" maxlength="150" class="field"
                                           value="{{ $old('items.' . $item->id . '.location', $item->location) }}">
                                </td>
                                <td>
                                    <input name="items[{{ $item->id }}][custodian]" type="text" maxlength="150" class="field"
                                           value="{{ $old('items.' . $item->id . '.custodian', $item->custodian) }}">
                                </td>
                                <td>
                                    <select name="items[{{ $item->id }}][condition_code]" class="field">
                                        <option value="">—</option>
                                        @foreach(\App\Support\DisposalCodes::REASON_CODES as $code => $label)
                                            <option value="{{ $code }}" @selected($old('items.' . $item->id . '.condition_code', $item->condition_code) === $code)>
                                                {{ $code }} — {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs text-[#5C6678] mt-1">
                                        Condition codes on the form: {{ implode(', ', \App\Support\DisposalCodes::CONDITION_CODES) }}
                                    </p>
                                </td>
                                <td>
                                    <select name="items[{{ $item->id }}][reason_code]" class="field">
                                        <option value="">—</option>
                                        @foreach(\App\Support\DisposalCodes::REASON_CODES as $code => $label)
                                            <option value="{{ $code }}" @selected($old('items.' . $item->id . '.reason_code', $item->reason_code) === $code)>
                                                {{ $code }} — {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-3">
            <button type="submit" class="btn-gold @if($locked) btn-disabled @endif">
                <i class="ri-save-3-line mr-1.5"></i>Save form
            </button>
            <p class="text-xs text-[#5C6678]">
                @if($locked)
                    This disposal is finalized: the form is part of the record and can no longer be changed.
                @else
                    Saving a complete form makes it ready to print. Saving never disposes of an asset.
                @endif
            </p>
        </div>
    </form>

    {{-- ── Codes, as the official form prints them ──────────────────── --}}
    <div class="card p-5 mb-6">
        <p class="font-semibold text-[#0F2143] text-sm mb-3"><i class="ri-bookmark-line mr-1.5"></i>The official code legend</p>
        <div class="grid md:grid-cols-2 gap-5">
            <div>
                <p class="eyebrow mb-2">Reason / condition codes</p>
                <table class="code-table">
                    <thead><tr><th style="width:44px;">Code</th><th>Meaning</th></tr></thead>
                    <tbody>
                        @foreach(\App\Support\DisposalCodes::REASON_CODES as $code => $label)
                            <tr><td class="font-mono">{{ $code }}</td><td>{{ $label }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div>
                <p class="eyebrow mb-2">Method of disposal codes</p>
                <table class="code-table">
                    <thead><tr><th style="width:44px;">Code</th><th>Meaning</th></tr></thead>
                    <tbody>
                        @foreach(\App\Support\DisposalCodes::METHOD_CODES as $method)
                            <tr><td class="font-mono">{{ $method['code'] }}</td><td>{{ $method['label'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="text-xs text-[#5C6678] mt-2">
                    Preserved exactly as the official form prints it — including the duplicated T.
                </p>
            </div>
        </div>
    </div>

    {{-- ── Printing ─────────────────────────────────────────────────── --}}
    <div class="card p-5 mb-6">
        <p class="font-semibold text-[#0F2143] text-sm mb-1"><i class="ri-printer-line mr-1.5"></i>Print</p>
        <p class="text-sm text-[#5C6678] mb-4">
            Prints the official form layout. Printing is recorded as evidence of printing only: it does not confirm a
            signature and it does not dispose of anything.
        </p>
        <div class="flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ url('/admin/disposal/forms/print') }}">
                @csrf
                <input type="hidden" name="ids[]" value="{{ $form->id }}">
                <button type="submit" class="btn-gold @if(! $printable) btn-disabled @endif">
                    <i class="ri-printer-line mr-1.5"></i>{{ $form->printed_at ? 'Print again' : 'Print this form' }}
                </button>
            </form>
            @if(! $printable)
                <p class="text-xs text-[#8F5F16]">
                    @if($locked)
                        This disposal is finalized.
                    @else
                        Not ready to print yet — complete the fields listed above first.
                    @endif
                </p>
            @endif
        </div>
    </div>

    {{-- ── Signed form from Accountancy ─────────────────────────────── --}}
    <div class="card p-5 mb-6">
        <p class="font-semibold text-[#0F2143] text-sm mb-1"><i class="ri-file-shield-2-line mr-1.5"></i>Signed form received</p>
        <p class="text-sm text-[#5C6678] mb-4">
            After the printed form is signed and returned by Accountancy, record it here. Uploading a scan is
            evidence of the receipt, not a signature captured by this system: the disposal still has to be
            finalized below.
        </p>

        @if($form->signed_received_at)
            <div class="p-3 mb-4 rounded-lg text-sm" style="background:var(--forest-tint); color:#245C3B;">
                <i class="ri-checkbox-circle-line mr-1.5"></i>
                Recorded as received on
                {{ \Illuminate\Support\Carbon::parse($form->signed_received_at)->format('F j, Y') }}.
                @if($form->has_signed_file)
                    <a href="{{ url('/admin/disposal/forms/' . $form->id . '/signed-copy') }}" class="font-semibold underline">
                        Open the scanned copy
                    </a>
                @else
                    No scanned copy is on file.
                @endif
            </div>
        @endif

        <form method="POST" action="{{ url('/admin/disposal/forms/' . $form->id . '/signed') }}" enctype="multipart/form-data">
            @csrf
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="eyebrow block mb-1.5" for="received_date">Date received from Accountancy</label>
                    <input id="received_date" name="received_date" type="date" class="field"
                           value="{{ old('received_date', now()->toDateString()) }}">
                </div>
                <div class="md:col-span-2">
                    <label class="eyebrow block mb-1.5" for="signed_copy">Scanned copy (optional)</label>
                    <input id="signed_copy" name="signed_copy" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="field">
                    <p class="text-xs text-[#5C6678] mt-1">
                        JPG, PNG, WEBP or PDF, up to {{ (int) (\App\Support\DisposalForms::UPLOAD_MAX_KB / 1024) }} MB.
                        Stored privately and only readable by the Asset Management Office.
                    </p>
                </div>
            </div>
            <div class="mt-4">
                <label class="eyebrow block mb-1.5" for="signed_notes">Notes</label>
                <textarea id="signed_notes" name="signed_notes" rows="2" maxlength="1000" class="field"
                          placeholder="For example: received by the Accountancy office, signed by the Property Asset Custodian.">{{ old('signed_notes', $form->signed_notes) }}</textarea>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="submit" class="btn-gold @if(! $canSign || $locked) btn-disabled @endif">
                    <i class="ri-upload-2-line mr-1.5"></i>
                    {{ $form->signed_received_at ? 'Update the signed form record' : 'Record the signed form' }}
                </button>
                @if(! $canSign && ! $locked)
                    <p class="text-xs text-[#8F5F16]">Print the form first — the signed copy cannot come back before it is printed.</p>
                @endif
            </div>
        </form>
    </div>

    {{-- ── Supporting documents ─────────────────────────────────────── --}}
    @php
        $documents       = $documents ?? collect();
        $documentsReady  = $documentsReady ?? false;
        $documentLock    = $documentLock ?? null;
    @endphp
    <div class="card p-5 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <p class="font-semibold text-[#0F2143] text-sm mb-1">
                    <i class="ri-attachment-2 mr-1.5"></i>Supporting documents
                    <span class="badge badge-draft ml-1">{{ $documents->count() }}</span>
                </p>
                <p class="text-sm text-[#5C6678] max-w-3xl">
                    Inspection reports, appraisals, photographs or approvals kept with this disposal. Files are stored
                    privately and can only be opened by the Asset Management Office. The signed official form is
                    recorded above, not here.
                </p>
            </div>
        </div>

        @if(! $documentsReady)
            <p class="text-sm text-[#8F5F16]">
                <i class="ri-database-2-line mr-1.5"></i>
                The disposal documents table has not been created yet — run the database migration to attach documents.
            </p>
        @else
            @if($documents->isEmpty())
                <p class="text-sm text-[#5C6678] mb-4">
                    <i class="ri-inbox-line mr-1.5"></i>No supporting documents are attached to this request yet.
                </p>
            @else
                <div class="overflow-x-auto mb-4">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b border-[#EFE9D8]">
                                <th class="py-2 pr-4 font-semibold">Document</th>
                                <th class="py-2 pr-4 font-semibold hidden md:table-cell">Applies to</th>
                                <th class="py-2 pr-4 font-semibold hidden lg:table-cell">Attached</th>
                                <th class="py-2 pr-4 font-semibold text-right">Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documents as $document)
                                <tr class="border-b border-[#F5F0E2] last:border-0 align-top">
                                    <td class="py-2.5 pr-4">
                                        <a href="{{ url('/admin/disposal/documents/' . $document->id) }}"
                                           class="font-semibold text-[#2E5C8A] hover:underline">
                                            <i class="ri-file-line mr-1"></i>{{ $document->title ?: $document->file_name }}
                                        </a>
                                        @if($document->title)
                                            <span class="block text-xs text-[#5C6678]">{{ $document->file_name }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 pr-4 hidden md:table-cell text-[#5C6678]">
                                        @if($document->asset_code)
                                            <span class="font-mono text-xs">{{ $document->asset_code }}</span>
                                        @else
                                            Whole request
                                        @endif
                                    </td>
                                    <td class="py-2.5 pr-4 hidden lg:table-cell text-xs text-[#5C6678]">
                                        {{ $document->uploaded_label }}
                                        <span class="block">{{ $document->uploader_name ?: ($document->uploader_email ?: '—') }}</span>
                                    </td>
                                    <td class="py-2.5 pr-4 text-right text-xs text-[#5C6678]">
                                        {{ $document->size_label }}
                                        @if(! $documentLock)
                                            <form method="POST" action="{{ url('/admin/disposal/documents/' . $document->id . '/delete') }}"
                                                  class="mt-1" onsubmit="return confirm('Remove this document? The file is deleted; the audit entry stays.');">
                                                @csrf
                                                <input type="hidden" name="form_id" value="{{ $form->id }}">
                                                <button type="submit" class="text-xs font-semibold text-[#A23B32] hover:underline">
                                                    Remove
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if($documentLock)
                <div class="p-3 rounded-lg text-sm" style="background:#F7F3E7; color:#8F5F16;">
                    <i class="ri-lock-line mr-1.5"></i>{{ $documentLock }}
                </div>
            @else
                <form method="POST" action="{{ url('/admin/disposal/requests/' . $form->request_id . '/documents') }}"
                      enctype="multipart/form-data" class="pt-4 border-t border-[#EFE9D8]">
                    @csrf
                    <input type="hidden" name="form_id" value="{{ $form->id }}">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <label class="eyebrow block mb-1.5" for="document">Document</label>
                            <input id="document" name="document" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="field">
                            <p class="text-xs text-[#5C6678] mt-1">
                                PDF, JPG, PNG or WEBP, up to {{ (int) (\App\Support\DisposalDocuments::MAX_KB / 1024) }} MB.
                            </p>
                        </div>
                        <div>
                            <label class="eyebrow block mb-1.5" for="title">What is it? (optional)</label>
                            <input id="title" name="title" type="text" maxlength="{{ \App\Support\DisposalDocuments::TITLE_MAX }}"
                                   class="field" value="{{ old('title') }}" placeholder="For example: Property inspection report, June 2026">
                        </div>
                        <div>
                            <label class="eyebrow block mb-1.5" for="asset_id">Applies to</label>
                            <select id="asset_id" name="asset_id" class="field">
                                <option value="">The whole request</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->asset_id }}" @selected((string) old('asset_id') === (string) $item->asset_id)>
                                        {{ $item->tag_no ?: 'Asset #' . $item->asset_id }} — {{ \Illuminate\Support\Str::limit((string) $item->description, 50) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="btn-gold">
                                <i class="ri-upload-2-line mr-1.5"></i>Attach document
                            </button>
                        </div>
                    </div>
                </form>
            @endif
        @endif
    </div>

    {{-- ── Finalize ─────────────────────────────────────────────────── --}}
    <div class="card p-5" style="border-left:4px solid {{ $canFinalize ? 'var(--brick)' : 'var(--line)' }};">
        <p class="font-semibold text-[#0F2143] text-sm mb-1"><i class="ri-checkbox-circle-line mr-1.5"></i>Finalize the disposal</p>
        <p class="text-sm text-[#5C6678] mb-4">
            This is the only step that marks an asset as <span class="font-semibold">Disposed of</span>. It is
            refused until the signed form has been recorded, and it asks you to confirm which assets are affected.
        </p>

        <div class="rounded-lg p-3 mb-4" style="background:#F7F3E7;">
            <p class="eyebrow mb-2">Assets this will retire</p>
            @foreach($items as $item)
                <p class="text-sm text-[#0F2143]">
                    <span class="font-mono text-xs">{{ $item->tag_no ?: '—' }}</span>
                    · {{ $item->description ?: '—' }}
                    @if($item->asset_lifecycle)
                        <span class="text-xs text-[#5C6678]">(currently {{ $item->asset_lifecycle }})</span>
                    @endif
                </p>
            @endforeach
        </div>

        @if($form->status === 'finalized')
            <p class="text-sm text-[#245C3B]">
                <i class="ri-checkbox-circle-line mr-1.5"></i>
                Finalized
                {{ $form->finalized_at ? 'on ' . \Illuminate\Support\Carbon::parse($form->finalized_at)->format('F j, Y g:i A') : '' }}
                @if($form->actual_disposal_date)
                    · disposal date {{ \Illuminate\Support\Carbon::parse($form->actual_disposal_date)->format('F j, Y') }}
                @endif
                . The disposal records are on the <a href="{{ url('/admin/disposal') }}" class="font-semibold underline">Disposal Records</a> tab.
            </p>
        @else
            <form method="POST" action="{{ url('/admin/disposal/forms/' . $form->id . '/finalize') }}">
                @csrf
                <div class="grid md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="eyebrow block mb-1.5" for="actual_disposal_date">Date of the actual disposal</label>
                        <input id="actual_disposal_date" name="actual_disposal_date" type="date" class="field"
                               value="{{ old('actual_disposal_date', now()->toDateString()) }}">
                    </div>
                    <div class="md:col-span-2">
                        <label class="flex items-start gap-2 text-sm text-[#0F2143]">
                            <input type="checkbox" name="confirm" value="1" class="mt-0.5" @checked(old('confirm'))>
                            <span>
                                I confirm the signed form has been received and that the asset(s) listed above have
                                been disposed of. Mark them as disposed of in NU Trace.
                            </span>
                        </label>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="submit" class="btn-danger @if(! $canFinalize) btn-disabled @endif">
                        <i class="ri-check-double-line mr-1.5"></i>Finalize disposal
                    </button>
                    @if(! $canFinalize)
                        <p class="text-xs text-[#8F5F16]">
                            Not available yet — the current stage is “{{ $form->status_label }}”.
                        </p>
                    @endif
                </div>
            </form>
        @endif
    </div>

</div>

@endsection
