@extends('layouts.admin_sidebar')

@section('title', 'Import Existing Assets')

@section('content')

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
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
    .field{ border:1px solid var(--line); background:#fff; border-radius:10px; padding:.5rem .7rem; font-size:.8125rem; color:var(--ink-900); }
    .field:focus{ outline:none; border-color:var(--gold-500); box-shadow:0 0 0 3px rgba(201,162,39,.18); }
    .btn-gold{ background:var(--gold-500); color:#0A1830; font-weight:600; padding:.6rem 1.05rem; border-radius:.6rem; display:inline-flex; align-items:center; justify-content:center; font-size:.875rem; transition:background .15s; }
    .btn-gold:hover{ background:#E0BC44; }
    .btn-gold:disabled{ opacity:.45; cursor:not-allowed; }
    .btn-ghost{ background:#fff; color:var(--ink-600); border:1px solid var(--gold-500); font-weight:600; padding:.6rem 1.05rem; border-radius:.6rem; display:inline-flex; align-items:center; justify-content:center; font-size:.875rem; transition:background .15s; }
    .btn-ghost:hover{ background:var(--paper-2); }
    .btn-ghost:disabled{ opacity:.45; cursor:not-allowed; }
    .badge{ font-size:.66rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; padding:.18rem .5rem; border-radius:999px; display:inline-flex; align-items:center; gap:.25rem; white-space:nowrap; }
    .badge-ready{ background:var(--forest-tint); color:var(--forest); }
    .badge-review{ background:var(--bronze-tint); color:var(--bronze); }
    .badge-blocked{ background:var(--brick-tint); color:var(--brick); }
    .badge-exists{ background:var(--paper-2); color:var(--ink-600); }
    .badge-imported{ background:var(--steel-tint); color:var(--steel); }
    .badge-role{ background:var(--gold-100); color:var(--navy-900); }
    .step-dot{ width:26px;height:26px;border-radius:999px;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;background:var(--paper-2);color:var(--ink-600);flex-shrink:0; }
    .step-dot.active{ background:var(--navy-900); color:#fff; }
    .step-dot.done{ background:var(--forest); color:#fff; }
    .spinner{ width:15px;height:15px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:999px;animation:spin .7s linear infinite; }
    @keyframes spin{ to{ transform:rotate(360deg);} }
    .code-input{ width:11rem; font-family:'IBM Plex Mono',monospace; font-size:.72rem; text-transform:uppercase; }
    .code-input.is-bad{ border-color:var(--brick); background:#FFF8F7; }
</style>

@include('admin.partials.header', [
    'adminHeaderPage'  => 'assets_import',
    'adminHeaderTitle' => 'Import Existing Assets',
    'adminHeaderIcon'  => 'ri-upload-cloud-2-line',
    'adminHeaderBadge' => 'Admin',
])

@php
    $partMb = number_format(max(1, (int) round(($partBytes ?? 8 * 1048576) / 1048576)));
@endphp

<div class="p-4 sm:p-8 space-y-5" id="importApp">

    {{-- ── What this does ───────────────────────────────────────── --}}
    <div class="card p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-3xl">
                <h3 class="font-display text-lg font-semibold text-[#0F2143] mb-1">Bring the fixed asset registry in</h3>
                <p class="text-sm text-[#5C6474]">
                    Upload the office's Excel or CSV file, confirm which column is which, and check every line before
                    anything is written. <strong>PROPERTY ID NO</strong> is the duplicate key: an asset already in
                    NU&nbsp;Trace is skipped, never overwritten. <strong>ID NO.</strong> is only a second opinion.
                </p>
                <p class="text-sm text-[#5C6474] mt-2">
                    Rows are checked in windows, so a large workbook is fine. Each imported asset gets its own signed
                    QR sticker. An accountable person without an active NU&nbsp;Trace account stays recorded by name and
                    the asset is imported <em>unassigned</em> — no account is invented for them.
                </p>
            </div>
            <div class="flex flex-col gap-2">
                <a href="/admin/assets/import/template" class="btn-ghost" download>
                    <i class="ri-download-2-line mr-1.5"></i>Download template
                </a>
                <a href="/admin/assets" class="btn-ghost">
                    <i class="ri-arrow-left-line mr-1.5"></i>Back to Assets
                </a>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-[#5C6474]">
            <div class="rounded-lg p-3" style="background:var(--forest-tint);">
                <p class="font-semibold" style="color:var(--forest);"><i class="ri-check-double-line mr-1"></i>Safe to re-upload</p>
                A second upload of the same file skips everything already registered instead of duplicating it.
            </div>
            <div class="rounded-lg p-3" style="background:var(--gold-100);">
                <p class="font-semibold" style="color:#7E5E0E;"><i class="ri-price-tag-3-line mr-1"></i>Custom codes only</p>
                Missing Asset Codes are listed for you to fill in or propose from a pattern — nothing is invented.
            </div>
            <div class="rounded-lg p-3" style="background:var(--steel-tint);">
                <p class="font-semibold" style="color:var(--steel);"><i class="ri-qr-code-line mr-1"></i>Sticker included</p>
                The existing 50 × 25 mm sticker is used; imported assets appear in the department lists and scanners.
            </div>
        </div>
    </div>

    {{-- ── Step 1: the file ─────────────────────────────────────── --}}
    <div class="card overflow-hidden" id="uploadCard">
        <div class="px-5 sm:px-6 py-4 border-b border-[#EFE9D8] flex items-center gap-3">
            <span class="step-dot active" id="dotUpload">1</span>
            <div>
                <h3 class="font-display text-base font-semibold text-[#0F2143]">Choose the file</h3>
                <p class="text-xs text-[#5C6474]">.xlsx or .csv · no size limit — a large file is sent in parts of {{ $partMb }} MB</p>
            </div>
        </div>
        <div class="p-5 sm:p-6">
            <div id="batchCard" class="hidden mb-4 p-3 rounded-lg text-sm" style="background:var(--paper-2);">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p><i class="ri-file-excel-2-line mr-1.5"></i><span id="batchName" class="font-medium"></span>
                        <span id="batchMeta" class="text-xs text-[#5C6474] ml-2"></span></p>
                    <div class="flex gap-2">
                        <span class="badge" id="batchStatus"></span>
                        <button type="button" class="text-xs underline text-[#8F5F16]" id="discardBtn" onclick="discardImport()">Start over</button>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[240px]">
                    <label class="eyebrow block mb-1.5" for="importFile">Inventory file</label>
                    <input id="importFile" type="file" accept=".xlsx,.csv,.txt,.tsv" class="field w-full">
                </div>
                <button type="button" class="btn-gold" id="readBtn" onclick="readFile()">
                    <i class="ri-upload-2-line mr-1.5"></i><span id="readBtnLabel">Read headers</span>
                </button>
            </div>

            <p class="text-xs text-[#5C6474] mt-3" id="uploadHint">
                Nothing is imported yet. The file is read on the server and only its column headings are shown next.
            </p>
        </div>
    </div>

    {{-- ── Step 2: column mapping ───────────────────────────────── --}}
    <div class="card overflow-hidden hidden" id="mappingCard">
        <div class="px-5 sm:px-6 py-4 border-b border-[#EFE9D8] flex items-center gap-3">
            <span class="step-dot active" id="dotMapping">2</span>
            <div>
                <h3 class="font-display text-base font-semibold text-[#0F2143]">Confirm the columns</h3>
                <p class="text-xs text-[#5C6474]">Detected automatically from the headings — change any that are wrong.</p>
            </div>
        </div>
        <div class="p-5 sm:p-6">
            <div id="mappingGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3"></div>

            <div class="mt-4 rounded-lg p-3 text-xs" id="mappingWarning" style="background:var(--bronze-tint); color:var(--bronze); display:none;"></div>

            <p class="eyebrow mt-5 mb-2">First rows in the file</p>
            <div class="overflow-x-auto rounded-lg border border-[#EFE9D8]">
                <table class="w-full text-xs" id="sampleTable"></table>
            </div>

            <div class="flex flex-wrap items-center gap-3 mt-4">
                <button type="button" class="btn-gold" id="checkBtn" onclick="checkRows()">
                    <i class="ri-search-eye-line mr-1.5"></i><span id="checkBtnLabel">Check the rows</span>
                </button>
                <p class="text-xs text-[#5C6474]">Checking writes nothing. It only stages what each line means.</p>
            </div>
        </div>
    </div>

    {{-- ── Progress ─────────────────────────────────────────────── --}}
    <div class="card p-4 hidden" id="progressCard">
        <div class="flex items-center gap-3">
            <div class="spinner" style="border-color:rgba(15,33,67,.2); border-top-color:var(--navy-900);"></div>
            <div class="flex-1">
                <p class="text-sm font-medium text-[#0F2143]" id="progressTitle">Reading…</p>
                <p class="text-xs text-[#5C6474]" id="progressDetail"></p>
            </div>
            <span class="font-mono text-sm text-[#0F2143]" id="progressCount"></span>
        </div>
    </div>

    {{-- ── Step 3: review ───────────────────────────────────────── --}}
    <div class="card overflow-hidden hidden" id="reviewCard">
        <div class="px-5 sm:px-6 py-4 border-b border-[#EFE9D8] flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="step-dot active">3</span>
                <div>
                    <h3 class="font-display text-base font-semibold text-[#0F2143]">Review before importing</h3>
                    <p class="text-xs text-[#5C6474]" id="reviewSubtitle"></p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-ghost !py-2 !text-xs" onclick="refreshAll()">
                    <i class="ri-refresh-line mr-1"></i>Refresh
                </button>
                <button type="button" class="btn-gold !py-2 !text-xs" id="importBtn" onclick="startImport()">
                    <i class="ri-database-2-line mr-1"></i><span id="importBtnLabel">Import</span>
                </button>
            </div>
        </div>

        <div class="px-5 sm:px-6 py-4">
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3" id="summaryGrid"></div>
        </div>

        {{-- Decisions --}}
        <div class="px-5 sm:px-6 pb-4" id="decisionsWrap">
            <div class="flex items-center justify-between mb-2">
                <p class="eyebrow">Decisions the file needs from you</p>
                <span class="text-xs text-[#5C6474]" id="decisionsCount"></span>
            </div>
            <div id="decisionsBody" class="space-y-4"></div>
            <div id="optionsRow" class="mt-3 flex flex-wrap gap-4 text-xs text-[#5C6474]" style="display:none;">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" id="optMissingDept"> Import lines whose department is not matched (without a department)
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" id="optBlankProperty"> Import lines with no Property ID No. (no duplicate key for them)
                </label>
            </div>
            <div class="flex flex-wrap items-center gap-3 mt-3" id="decisionsActions" style="display:none;">
                <button type="button" class="btn-ghost !py-2 !text-xs" onclick="saveDecisions()">
                    <i class="ri-save-3-line mr-1"></i>Apply decisions &amp; re-check
                </button>
                <span class="text-xs text-[#5C6474]">Departments and categories are only linked to ones that already exist.</span>
            </div>
        </div>

        {{-- Codes --}}
        <div class="px-5 sm:px-6 pb-4">
            <p class="eyebrow mb-2">Asset codes</p>
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="text-xs text-[#5C6474] block mb-1" for="codePattern">Propose codes from a pattern</label>
                    <input id="codePattern" class="field font-mono" placeholder="{property_id}" style="width:16rem">
                </div>
                <button type="button" class="btn-ghost !py-2 !text-xs" onclick="proposeCodes()">
                    <i class="ri-magic-line mr-1"></i>Fill blank codes
                </button>
                <p class="text-xs text-[#5C6474] max-w-md">
                    Tokens: <span class="font-mono">{property_id}</span>, <span class="font-mono">{inventory_id}</span>,
                    <span class="font-mono">{seq}</span>, <span class="font-mono">{index}</span>, <span class="font-mono">{year}</span>.
                    Codes are checked against NU Trace and against each other; edit any line by hand in the table.
                </p>
            </div>
        </div>

        {{-- Filter tabs --}}
        <div class="px-5 sm:px-6 pb-2 flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap gap-1" id="statusTabs"></div>
            <span class="text-xs text-[#5C6474]" id="rowsCount"></span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="rowsTable">
                <thead>
                    <tr class="bg-[#F5F0E2] text-[#5B6678] uppercase text-[.62rem] tracking-wider">
                        <th class="px-3 py-3 text-left font-semibold">Line</th>
                        <th class="px-3 py-3 text-left font-semibold">Property ID / ID No.</th>
                        <th class="px-3 py-3 text-left font-semibold">Description</th>
                        <th class="px-3 py-3 text-left font-semibold">Asset code</th>
                        <th class="px-3 py-3 text-left font-semibold hidden lg:table-cell">Department</th>
                        <th class="px-3 py-3 text-left font-semibold hidden xl:table-cell">Accountable</th>
                        <th class="px-3 py-3 text-left font-semibold hidden md:table-cell">Date / Cost</th>
                        <th class="px-3 py-3 text-left font-semibold">What happens</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#F0EADB]" id="rowsBody"></tbody>
            </table>
        </div>

        <div class="px-5 py-3 border-t border-[#EFE9D8] flex items-center justify-between gap-2">
            <button type="button" class="btn-ghost !py-1.5 !text-xs" id="prevPage" onclick="changePage(-1)">Previous</button>
            <span class="text-xs text-[#5C6474]" id="pageLabel"></span>
            <button type="button" class="btn-ghost !py-1.5 !text-xs" id="nextPage" onclick="changePage(1)">Next</button>
        </div>
    </div>

    {{-- ── Result ───────────────────────────────────────────────── --}}
    <div class="card overflow-hidden hidden" id="resultCard">
        <div class="px-5 sm:px-6 py-4 border-b border-[#EFE9D8] flex items-center gap-3">
            <span class="step-dot done"><i class="ri-check-line"></i></span>
            <div>
                <h3 class="font-display text-base font-semibold text-[#0F2143]">Import finished</h3>
                <p class="text-xs text-[#5C6474]" id="resultSubtitle"></p>
            </div>
        </div>
        <div class="p-5 sm:p-6 space-y-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3" id="resultGrid"></div>
            <div class="flex flex-wrap gap-2">
                <a href="#" class="btn-gold" id="printLink" target="_blank" rel="noopener">
                    <i class="ri-printer-line mr-1.5"></i>Print stickers for this import
                </a>
                <button type="button" class="btn-ghost" onclick="retryQr()">
                    <i class="ri-qr-code-line mr-1.5"></i>Generate missing stickers
                </button>
                <button type="button" class="btn-ghost" onclick="retryFailed()">
                    <i class="ri-restart-line mr-1.5"></i>Retry failed lines
                </button>
                <a href="/admin/assets" class="btn-ghost">Open Assets</a>
            </div>
            <p class="text-xs text-[#5C6474]">
                Imported assets appear under their department and can be scanned with the Asset Scanner.
                Assets whose accountable person has no account yet are listed as unassigned — the original name is kept.
            </p>
        </div>
    </div>

    {{-- ── Messages ─────────────────────────────────────────────── --}}
    <div class="card p-4 hidden" id="notice"></div>
</div>

{{-- ── Confirmation ─────────────────────────────────────────── --}}
<div id="confirmDialog" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,33,67,.45);">
    <div class="card w-full max-w-md p-5" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
        <h3 class="font-display text-base font-semibold text-[#0F2143]" id="confirmTitle">Are you sure?</h3>
        <p class="text-sm text-[#5C6474] mt-2" id="confirmBody"></p>
        <div class="flex justify-end gap-2 mt-5">
            <button type="button" class="btn-ghost" id="confirmCancel">Cancel</button>
            <button type="button" class="btn-gold" id="confirmOk">Confirm</button>
        </div>
    </div>
</div>

<datalist id="assignablePeople">
    @foreach(($assignable ?? []) as $person)
        <option value="{{ $person['label'] }}" data-user-id="{{ $person['id'] }}"></option>
    @endforeach
</datalist>

<script>
    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    // The largest slice the server will take in one request; a bigger file is
    // uploaded as several slices, so it is not a limit on the workbook.
    const PART_BYTES = {{ (int) ($partBytes ?? 8388608) }};
    const FIELDS = @json($fields);
    const HEADINGS = @json($batch['header'] ?? []);
    const DEPARTMENTS = @json($departments ?? []);
    const CATEGORIES = @json($categories ?? []);
    const ASSIGNABLE = @json($assignable ?? []);
    const INITIAL = {
        batch: @json($batch),
        rows: @json($rows),
        decisions: @json($decisions),
        codes: @json($codes ?? []),
    };

    let batch = INITIAL.batch;
    let decisions = INITIAL.decisions;
    let rowView = INITIAL.rows;
    let page = 1;
    let statusFilter = 'all';
    let importedCodes = INITIAL.codes || [];
    let busy = false;

    const $ = (id) => document.getElementById(id);

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function money(value) {
        if (value === null || value === undefined || value === '') return '—';
        return '₱' + Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function notify(ok, message) {
        const box = $('notice');
        box.classList.remove('hidden');
        box.style.borderLeft = '4px solid ' + (ok ? 'var(--forest)' : 'var(--brick)');
        box.innerHTML = `<p class="text-sm" style="color:${ok ? 'var(--forest)' : 'var(--brick)'}">
            <i class="ri-${ok ? 'checkbox-circle' : 'error-warning'}-line mr-1.5"></i>${escapeHtml(message)}</p>`;
    }

    /* ── Asking before a destructive step ─────────────────────── */

    let confirmResolve = null;

    function askConfirm(title, body, okLabel) {
        $('confirmTitle').textContent = title;
        $('confirmBody').textContent = body;
        $('confirmOk').textContent = okLabel;
        $('confirmDialog').classList.remove('hidden');
        $('confirmCancel').focus();

        return new Promise(resolve => { confirmResolve = resolve; });
    }

    function answerConfirm(answer) {
        $('confirmDialog').classList.add('hidden');
        const resolve = confirmResolve;
        confirmResolve = null;
        if (resolve) resolve(answer);
    }

    $('confirmOk').addEventListener('click', () => answerConfirm(true));
    $('confirmCancel').addEventListener('click', () => answerConfirm(false));
    $('confirmDialog').addEventListener('click', event => {
        if (event.target === $('confirmDialog')) answerConfirm(false);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && confirmResolve) answerConfirm(false);
    });

    async function post(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(body ?? {}),
        });

        const data = await response.json().catch(() => ({ ok: false, message: 'The server did not answer. Reload the page and try again.' }));
        return { ok: response.ok && data.ok !== false, data };
    }

    async function get(url) {
        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
        return response.json().catch(() => ({}));
    }

    /* ── Batch header ─────────────────────────────────────────── */

    function renderBatch() {
        if (!batch) return;

        $('batchCard').classList.remove('hidden');
        $('batchName').textContent = batch.original_name;
        $('batchMeta').textContent = `${(batch.size_bytes / 1048576).toFixed(1)} MB · ${batch.rows_checked} line(s) staged`;

        const map = {
            uploaded: ['badge-review', 'Headers read'],
            checking: ['badge-review', 'Checking'],
            ready: ['badge-ready', 'Ready to review'],
            importing: ['badge-imported', 'Importing'],
            completed: ['badge-imported', 'Completed'],
            failed: ['badge-blocked', 'Stopped'],
        };
        const [cls, label] = map[batch.status] || ['badge-exists', batch.status];
        $('batchStatus').className = 'badge ' + cls;
        $('batchStatus').textContent = label;

        $('dotUpload').className = 'step-dot done';
        $('dotUpload').innerHTML = '<i class="ri-check-line"></i>';
    }

    function renderMapping() {
        const headings = (batch && batch.header) || HEADINGS || [];

        if (!batch || !headings.length) return;

        $('mappingCard').classList.remove('hidden');
        const grid = $('mappingGrid');
        const mapping = batch.mapping || {};

        grid.innerHTML = Object.entries(FIELDS).map(([field, label]) => {
            // A field the app could not find has to read "— not in this file —".
            // An unselected <select> shows its first option anyway, and
            // Number(null) is 0, so every unmatched field used to look (and be
            // saved) as the first column of the file.
            const chosen = mapping[field];
            const detected = chosen !== null && chosen !== undefined && chosen !== '';
            const options = [`<option value="" ${detected ? '' : 'selected'}>— not in this file —</option>`].concat(
                headings.map((heading, index) => `<option value="${index}" ${detected && Number(chosen) === index ? 'selected' : ''}>${escapeHtml(heading || ('Column ' + (index + 1)))}</option>`)
            ).join('');

            return `<div>
                <label class="eyebrow block mb-1">${escapeHtml(label)}</label>
                <select class="field w-full" data-field="${field}">${options}</select>
            </div>`;
        }).join('');

        const missingProperty = mapping.property_id_no === null || mapping.property_id_no === undefined;
        const warning = $('mappingWarning');
        if (missingProperty) {
            warning.style.display = 'block';
            warning.innerHTML = '<i class="ri-alert-line mr-1"></i>No <strong>Property ID No.</strong> column is mapped. Without it nothing can be checked for duplicates, so every line will need a decision before it can be imported.';
        } else {
            warning.style.display = 'none';
        }
    }

    function renderSamples(samples) {
        const table = $('sampleTable');

        if (!samples || !samples.length) {
            table.innerHTML = '<tbody><tr><td class="px-3 py-3 text-xs text-[#5C6474]">The file has a header row but no data rows.</td></tr></tbody>';
            return;
        }

        const mappedFields = Object.keys(FIELDS);
        table.innerHTML = `<thead><tr class="bg-[#F5F0E2] text-[#5B6678] uppercase text-[.6rem] tracking-wider">
                <th class="px-3 py-2 text-left">Line</th>
                ${mappedFields.map(f => `<th class="px-3 py-2 text-left">${escapeHtml(FIELDS[f])}</th>`).join('')}
            </tr></thead>
            <tbody class="divide-y divide-[#F0EADB]">
                ${samples.map(sample => `<tr>
                    <td class="px-3 py-2 text-[#5C6474]">${sample.line ?? ''}</td>
                    ${mappedFields.map(f => `<td class="px-3 py-2 whitespace-nowrap">${escapeHtml(sample.values?.[f] ?? '') || '—'}</td>`).join('')}
                </tr>`).join('')}
            </tbody>`;
    }

    /* ── Uploading the file, at any size ──────────────────────── */

    /* A 32-character hex name for one upload, so its slices keep together. */
    function newUploadId() {
        if (window.crypto && crypto.getRandomValues) {
            return Array.from(crypto.getRandomValues(new Uint8Array(16)))
                .map(byte => byte.toString(16).padStart(2, '0')).join('');
        }

        let id = '';
        for (let i = 0; i < 32; i++) id += Math.floor(Math.random() * 16).toString(16);
        return id;
    }

    /* The first thing a failed response actually said. */
    function validationMessage(data) {
        if (!data) return '';
        if (data.message) return data.message;

        const errors = data.errors || {};
        const first = Object.keys(errors)[0];

        return first && errors[first] && errors[first][0] ? errors[first][0] : '';
    }

    /* Send one slice, retrying a dropped connection a few times. */
    async function uploadPart(url, body) {
        let last = null;

        for (let attempt = 1; attempt <= 3; attempt++) {
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body,
                });
                const data = await response.json().catch(() => null);

                if (response.ok && data && data.ok) return data;

                last = { ok: false, message: validationMessage(data) || 'The server did not answer while part of the file was uploading.' };

                // A refused slice (bad name, expired page) will not upload
                // better on a second try; a dropped one will.
                if (response.status === 419 || response.status === 422) return last;
            } catch (e) {
                last = { ok: false, message: 'The connection dropped while part of the file was uploading.' };
            }

            await new Promise(resolve => setTimeout(resolve, 500 * attempt));
        }

        return last;
    }

    /*
     * Split the file into slices the server can accept and send them one at a
     * time. The server joins them, so the size of the workbook is bounded by
     * the disk, not by PHP's upload limit. The slice that completes the set
     * answers with the batch, exactly like a single-request upload.
     */
    async function uploadInParts(file) {
        const total = Math.ceil(file.size / PART_BYTES);
        const uploadId = newUploadId();
        const megabytes = (file.size / 1048576).toFixed(1);

        for (let index = 0; index < total; index++) {
            const slice = file.slice(index * PART_BYTES, Math.min((index + 1) * PART_BYTES, file.size));
            const body = new FormData();
            body.append('upload_id', uploadId);
            body.append('index', index);
            body.append('total', total);
            body.append('name', file.name);
            body.append('file', slice, file.name);

            const data = await uploadPart('/admin/assets/import/part', body);

            if (!data || !data.ok) {
                return { ok: false, message: (data && data.message) || `Part ${index + 1} of ${total} did not upload.` };
            }

            $('readBtnLabel').textContent = `Uploading ${Math.round(((index + 1) / total) * 100)}%`;
            $('uploadHint').textContent = `Sent ${Math.floor(Math.min(file.size, (index + 1) * PART_BYTES) / 1048576)} MB of ${megabytes} MB (part ${index + 1} of ${total}) — nothing is imported yet.`;

            if (data.batch) return data;
        }

        return { ok: false, message: 'The file finished uploading but the server did not open it. Try again.' };
    }

    /* ── Steps ────────────────────────────────────────────────── */

    async function readFile() {
        const input = $('importFile');

        if (!input.files.length) { notify(false, 'Choose the inventory file first.'); return; }

        const file = input.files[0];
        const hint = $('uploadHint');
        const hintText = hint.textContent;

        setBusy($('readBtn'), true, 'Uploading…');
        hint.textContent = `Uploading ${file.name} (${(file.size / 1048576).toFixed(1)} MB)…`;

        try {
            let data;

            if (file.size > PART_BYTES) {
                data = await uploadInParts(file);
            } else {
                const body = new FormData();
                body.append('file', file);

                const response = await fetch('/admin/assets/import', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body,
                });
                data = await response.json().catch(() => ({ ok: false, message: 'The server did not answer.' }));
            }

            if (!data || !data.ok) {
                notify(false, (data && data.message) || 'That file could not be read.');
                return;
            }

            batch = data.batch;
            decisions = null;
            rowView = null;
            page = 1;

            if (data.message) notify(true, data.message);

            renderBatch();
            renderMapping();
            renderSamples(data.sample || []);
            $('checkBtnLabel').textContent = 'Check the rows';
            window.scrollTo({ top: $('mappingCard').offsetTop - 20, behavior: 'smooth' });
        } catch (e) {
            notify(false, 'That file could not be uploaded. Check the connection and try again — nothing was imported.');
        } finally {
            hint.textContent = hintText;
            setBusy($('readBtn'), false, 'Read headers');
        }
    }

    function currentMapping() {
        const mapping = {};
        document.querySelectorAll('#mappingGrid select[data-field]').forEach(select => {
            mapping[select.dataset.field] = select.value === '' ? null : Number(select.value);
        });
        return mapping;
    }

    async function checkRows() {
        if (!batch) return;

        const saved = await post(`/admin/assets/import/${batch.id}/mapping`, { mapping: currentMapping() });

        if (!saved.ok) { notify(false, saved.data.message || 'The column mapping could not be saved.'); return; }

        batch = saved.data.batch;
        renderBatch();
        $('progressCard').classList.remove('hidden');
        $('reviewCard').classList.add('hidden');
        $('resultCard').classList.add('hidden');
        $('progressTitle').textContent = 'Checking the file…';
        $('progressCount').textContent = '0';
        setBusy($('checkBtn'), true, 'Checking…');

        try {
            let done = false;
            let guard = 0;

            while (!done && guard++ < 2000) {
                const step = await post(`/admin/assets/import/${batch.id}/step`, {});

                if (!step.ok) { notify(false, step.data.message || 'The file could not be checked.'); break; }

                batch = step.data.batch;
                $('progressCount').textContent = batch.rows_checked;
                $('progressDetail').textContent = 'Every line is checked against NU Trace, the department list and the employee masterlist.';
                renderBatch();

                done = !!step.data.done;
            }

            if (done) {
                $('progressTitle').textContent = 'Check finished';
                await refreshAll();
                $('progressCard').classList.add('hidden');
                window.scrollTo({ top: $('reviewCard').offsetTop - 20, behavior: 'smooth' });
            }
        } finally {
            setBusy($('checkBtn'), false, 'Check the rows');
        }
    }

    /* ── Review ───────────────────────────────────────────────── */

    function summaryChip(label, value, colour) {
        const palette = {
            forest: ['var(--forest-tint)', 'var(--forest)'],
            bronze: ['var(--bronze-tint)', 'var(--bronze)'],
            brick:  ['var(--brick-tint)', 'var(--brick)'],
            steel:  ['var(--steel-tint)', 'var(--steel)'],
            ink:    ['#EFEAE0', 'var(--ink-600)'],
        };
        const [background, colourValue] = palette[colour] || palette.ink;

        return `<div class="rounded-lg p-3" style="background:${background};">
            <p class="text-[.65rem] uppercase tracking-wider font-semibold" style="color:${colourValue};">${label}</p>
            <p class="text-xl font-bold" style="color:${colourValue};">${value}</p>
        </div>`;
    }

    function renderSummary(summary) {
        if (!summary) return;

        $('summaryGrid').innerHTML = [
            summaryChip('Lines', summary.total, 'steel'),
            summaryChip('Ready', summary.ready, 'forest'),
            summaryChip('Needs review', summary.review, 'bronze'),
            summaryChip('Blocked', summary.blocked, 'brick'),
            summaryChip('Already in NU Trace', summary.exists, 'ink'),
            summaryChip('Imported', summary.imported, 'steel'),
        ].join('');

        $('reviewSubtitle').textContent = `${summary.importable} line(s) can be imported now · ${summary.blocked} blocked · ${summary.exists} already registered`;
        $('importBtnLabel').textContent = `Import ${summary.importable} line(s)`;
        $('importBtn').disabled = summary.importable === 0 || busy;

        $('rowsCount').textContent = `${summary.total} line(s) in this file`;
    }

    function renderTabs(summary) {
        const tabs = [
            ['all', `All ${summary.total}`],
            ['ready', `Ready ${summary.ready}`],
            ['review', `Review ${summary.review}`],
            ['blocked', `Blocked ${summary.blocked}`],
            ['exists', `Exists ${summary.exists}`],
            ['imported', `Imported ${summary.imported}`],
        ];

        $('statusTabs').innerHTML = tabs.map(([key, label]) =>
            `<button type="button" class="badge ${statusFilter === key ? 'badge-role' : 'badge-exists'}" data-status="${key}">${label}</button>`
        ).join('');

        $('statusTabs').querySelectorAll('button[data-status]').forEach(button => {
            button.addEventListener('click', () => { statusFilter = button.dataset.status; page = 1; loadRows(); });
        });
    }

    function renderDecisions() {
        const body = $('decisionsBody');
        const count = $('decisionsCount');

        if (!decisions) { body.innerHTML = ''; return; }

        const blocks = [];
        let total = 0;

        if (decisions.departments.length) {
            total += decisions.departments.length;
            blocks.push(`<div>
                <p class="text-xs font-semibold text-[#0F2143] mb-2"><i class="ri-building-4-line mr-1"></i>Departments in the file that do not match a department in NU Trace</p>
                <div class="space-y-2">
                    ${decisions.departments.map(entry => {
                        const options = ['<option value="">— choose —</option>',
                            `<option value="none" ${entry.chosen === '' ? 'selected' : ''}>Import without a department</option>`]
                            .concat(DEPARTMENTS.map(d => `<option value="${d.id}" ${Number(entry.chosen) === Number(d.id) || (!entry.chosen && Number(entry.suggested) === Number(d.id)) ? 'selected' : ''}>${escapeHtml(d.Name)}${d.status !== 'Active' ? ' (inactive)' : ''}</option>`))
                            .join('');

                        return `<div class="flex flex-wrap items-center justify-between gap-2 rounded-lg px-3 py-2" style="background:var(--paper-2);">
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-[#0F2143] truncate">${escapeHtml(entry.value) || '(blank)'}</p>
                                <p class="text-[.65rem] text-[#5C6474]">${entry.count} line(s) · ${entry.status === 'possible' ? 'a similar department exists' : 'no match found'}</p>
                            </div>
                            <select class="field" data-dept="${escapeHtml(entry.value)}" style="min-width:16rem;">${options}</select>
                        </div>`;
                    }).join('')}
                </div>
            </div>`);
        }

        if (decisions.categories.length) {
            total += decisions.categories.length;
            blocks.push(`<div>
                <p class="text-xs font-semibold text-[#0F2143] mb-2"><i class="ri-stack-line mr-1"></i>Type of asset values that are not one of NU Trace's categories</p>
                <div class="space-y-2">
                    ${decisions.categories.map(entry => {
                        const options = ['<option value="">— choose —</option>']
                            .concat(CATEGORIES.map(c => `<option value="${escapeHtml(c)}" ${entry.chosen === c ? 'selected' : ''}>${escapeHtml(c)}</option>`))
                            .join('');

                        return `<div class="flex flex-wrap items-center justify-between gap-2 rounded-lg px-3 py-2" style="background:var(--paper-2);">
                            <div><p class="text-xs font-medium text-[#0F2143]">${escapeHtml(entry.value) || '(blank)'}</p>
                                <p class="text-[.65rem] text-[#5C6474]">${entry.count} line(s)</p></div>
                            <select class="field" data-cat="${escapeHtml(entry.value)}" style="min-width:16rem;">${options}</select>
                        </div>`;
                    }).join('')}
                </div>
            </div>`);
        }

        if (decisions.people.length) {
            total += decisions.people.length;
            blocks.push(`<div>
                <p class="text-xs font-semibold text-[#0F2143] mb-2"><i class="ri-user-search-line mr-1"></i>Accountable names that match more than one employee</p>
                <div class="space-y-2">
                    ${decisions.people.map(entry => {
                        const options = ['<option value="">— decide —</option>',
                            `<option value="unassigned" ${entry.decided && !entry.chosen ? 'selected' : ''}>Leave unassigned (keep the name)</option>`]
                            .concat((entry.candidates || []).map(c => `<option value="${c.candidate_id ?? c.id}" ${Number(entry.chosen) === Number(c.candidate_id ?? c.id) ? 'selected' : ''}>${escapeHtml(c.label)}</option>`))
                            .concat(ASSIGNABLE.filter(p => !(entry.candidates || []).some(c => Number(c.candidate_id ?? c.id) === Number(p.id)))
                                .map(p => `<option value="${p.id}" ${Number(entry.chosen) === Number(p.id) ? 'selected' : ''}>${escapeHtml(p.label)}</option>`))
                            .join('');

                        return `<div class="flex flex-wrap items-center justify-between gap-2 rounded-lg px-3 py-2" style="background:var(--paper-2);">
                            <div><p class="text-xs font-medium text-[#0F2143]">${escapeHtml(entry.value)}</p>
                                <p class="text-[.65rem] text-[#5C6474]">${entry.count} line(s) · ${(entry.candidates || []).length} employees match</p></div>
                            <select class="field" data-person="${escapeHtml(entry.value)}" style="min-width:18rem;">${options}</select>
                        </div>`;
                    }).join('')}
                </div>
            </div>`);
        }

        body.innerHTML = total
            ? blocks.join('')
            : `<p class="text-sm" style="color:var(--forest);"><i class="ri-checkbox-circle-line mr-1"></i>Nothing needs a decision — every department, asset type and accountable person was resolved.</p>`;

        count.textContent = total ? `${total} item(s) to resolve` : '';
        $('decisionsActions').style.display = total ? 'flex' : 'none';
        $('optionsRow').style.display = total ? 'flex' : 'none';

        const options = decisions.options || {};
        $('optMissingDept').checked = !!options.allow_missing_department;
        $('optBlankProperty').checked = !!options.allow_blank_property;
    }

    function statusBadge(row) {
        const map = {
            ready: ['badge-ready', 'Ready'],
            review: ['badge-review', 'Review'],
            blocked: ['badge-blocked', 'Blocked'],
            exists: ['badge-exists', 'Already exists'],
            imported: ['badge-imported', 'Imported'],
            failed: ['badge-blocked', 'Failed'],
        };
        const [cls, label] = map[row.status] || ['badge-exists', row.status];
        return `<span class="badge ${cls}">${label}</span>`;
    }

    function renderRows() {
        if (!rowView) return;

        const body = $('rowsBody');

        if (!rowView.rows.length) {
            body.innerHTML = `<tr><td colspan="8" class="px-5 py-8 text-center text-sm text-[#5C6474]">No line matches this filter.</td></tr>`;
        } else {
            body.innerHTML = rowView.rows.map(row => {
                const editable = ['ready', 'review', 'blocked'].includes(row.status);
                const codeCell = editable
                    ? `<input class="field code-input" value="${escapeHtml(row.asset_code || '')}" data-code-line="${row.line}" placeholder="enter code" ${row.status === 'blocked' && !row.asset_code ? 'style="border-color:var(--brick);"' : ''}>`
                    : `<span class="font-mono text-xs">${escapeHtml(row.asset_code || '—')}</span>`;

                const issues = (row.issues || []).map(issue =>
                    `<p class="text-[.68rem] leading-snug" style="color:${issue.level === 'error' ? 'var(--brick)' : (issue.level === 'warning' ? 'var(--bronze)' : 'var(--ink-400)')}">
                        <i class="ri-${issue.level === 'error' ? 'error-warning' : (issue.level === 'warning' ? 'alert' : 'information')}-line mr-0.5"></i>${escapeHtml(issue.text)}</p>`
                ).join('');

                const deptCell = row.department_name || row.department
                    ? `<span class="text-xs">${escapeHtml(row.department_name || row.department)}</span>
                       ${row.department_status === 'possible' ? '<span class="badge badge-review ml-1">check</span>' : ''}
                       ${row.department_status === 'unmatched' ? '<span class="badge badge-blocked ml-1">no match</span>' : ''}`
                    : '<span class="text-xs text-[#5C6474]">—</span>';

                // The accountable person is the one value the office often has to
                // correct by hand: the heading in the workbook may be a nickname,
                // a resigned employee, or someone with no account. The select lets
                // them choose the account for this line, or confirm it stays
                // unassigned, without fixing and re-uploading the workbook.
                const personCell = editable
                    ? (() => {
                        const chosen = row.accountable_user_id ? String(row.accountable_user_id) : '';
                        // Exactly one option is selected: the "use the file"
                        // entry only when nothing else applies.
                        const options = [
                            `<option value="" ${chosen === '' && row.accountable_override !== 0 ? 'selected' : ''}>— use the name in the file —</option>`,
                            `<option value="0" ${row.accountable_override === 0 ? 'selected' : ''}>— leave unassigned —</option>`,
                        ].concat(ASSIGNABLE.map(person =>
                            `<option value="${person.id}" ${chosen === String(person.id) ? 'selected' : ''}>${escapeHtml(person.label)}</option>`
                        )).join('');

                        return `<select class="field person-select" data-person-line="${row.line}" style="min-width:13rem;">${options}</select>
                                <p class="text-[.65rem] text-[#5C6474] mt-1">
                                    ${row.accountable_name ? 'file: ' + escapeHtml(row.accountable_name) + ' · ' : ''}${escapeHtml(personLabel(row.accountable_status))}
                                </p>`;
                    })()
                    : (row.accountable_name
                        ? `<span class="text-xs">${escapeHtml(row.accountable_name)}</span>
                           <p class="text-[.65rem] text-[#5C6474]">${escapeHtml(personLabel(row.accountable_status))}</p>`
                        : '<span class="text-xs text-[#5C6474]">—</span>');

                return `<tr class="hover:bg-[#FBF7EC] align-top">
                    <td class="px-3 py-3 text-xs text-[#5C6474]">${row.line}</td>
                    <td class="px-3 py-3">
                        <p class="font-mono text-xs">${escapeHtml(row.property_id_no) || '—'}</p>
                        <p class="text-[.65rem] text-[#5C6474]">ID ${escapeHtml(row.inventory_id_no) || '—'}${row.po_no ? ' · PO ' + escapeHtml(row.po_no) : ''}</p>
                    </td>
                    <td class="px-3 py-3 max-w-[16rem]">
                        <p class="text-xs font-medium text-[#0F2143]">${escapeHtml(row.name) || '—'}</p>
                        <p class="text-[.65rem] text-[#5C6474]">${escapeHtml(row.location) || 'no location'}${row.category_raw ? ' · ' + escapeHtml(row.category_raw) : ''}</p>
                    </td>
                    <td class="px-3 py-3">${codeCell}</td>
                    <td class="px-3 py-3 hidden lg:table-cell">${deptCell}</td>
                    <td class="px-3 py-3 hidden xl:table-cell">${personCell}</td>
                    <td class="px-3 py-3 hidden md:table-cell text-xs whitespace-nowrap">
                        <p>${escapeHtml(row.date) || '—'}</p>
                        <p class="text-[.65rem] text-[#5C6474]">${money(row.unit_cost)}</p>
                    </td>
                    <td class="px-3 py-3 min-w-[14rem]">
                        ${statusBadge(row)}
                        <div class="mt-1 space-y-0.5">${issues || '<p class="text-[.68rem] text-[#5C6474]">No problems found.</p>'}</div>
                        ${row.message ? `<p class="text-[.68rem] mt-1" style="color:var(--brick);">${escapeHtml(row.message)}</p>` : ''}
                    </td>
                </tr>`;
            }).join('');
        }

        $('pageLabel').textContent = `Page ${rowView.page} of ${rowView.pages} · ${rowView.total} line(s)`;
        $('prevPage').disabled = rowView.page <= 1;
        $('nextPage').disabled = rowView.page >= rowView.pages;

        body.querySelectorAll('input[data-code-line]').forEach(input => {
            input.addEventListener('change', () => saveCode(input.dataset.codeLine, input.value, input));
        });

        body.querySelectorAll('select[data-person-line]').forEach(select => {
            select.addEventListener('change', () => savePerson(select.dataset.personLine, select.value));
        });
    }

    function personLabel(status) {
        return ({
            assigned: 'assigned to an active account',
            no_account: 'no account yet — kept unassigned',
            inactive_account: 'account not active — kept unassigned',
            inactive_employee: 'employee record inactive — kept unassigned',
            no_employee: 'not on the masterlist — kept unassigned',
            ambiguous: 'more than one match — needs a decision',
            unassigned: 'unassigned',
        })[status] || '';
    }

    async function loadRows() {
        if (!batch) return;
        rowView = await get(`/admin/assets/import/${batch.id}/rows?status=${encodeURIComponent(statusFilter)}&page=${page}`);
        renderRows();
    }

    async function refreshAll() {
        if (!batch) return;

        const [rowsData, decisionData] = await Promise.all([
            get(`/admin/assets/import/${batch.id}/rows?status=${encodeURIComponent(statusFilter)}&page=${page}`),
            get(`/admin/assets/import/${batch.id}/decisions`),
        ]);

        rowView = rowsData;
        decisions = decisionData;
        importedCodes = importedCodes || [];

        $('reviewCard').classList.remove('hidden');
        renderSummary(batch.summary);
        renderTabs(batch.summary);
        renderDecisions();
        renderRows();

        if (batch.status === 'completed') showResult();
    }

    function changePage(delta) {
        if (!rowView) return;
        page = Math.min(Math.max(1, rowView.page + delta), rowView.pages);
        loadRows();
    }

    async function saveCode(line, code, input) {
        const result = await post(`/admin/assets/import/${batch.id}/codes`, { codes: { [line]: code } });

        if (!result.ok) {
            notify(false, result.data.message || 'That code could not be saved.');
            return;
        }

        batch = { ...batch, summary: result.data.summary };
        renderSummary(batch.summary);
        renderTabs(batch.summary);
        await loadRows();
    }

    async function savePerson(line, value) {
        const result = await post(`/admin/assets/import/${batch.id}/person`, {
            line: Number(line),
            user_id: value === '' ? null : Number(value),
        });

        if (!result.ok) {
            notify(false, result.data.message || 'That accountable person could not be saved.');
            await loadRows();
            return;
        }

        batch = { ...batch, summary: result.data.summary };
        notify(true, result.data.message || 'Accountable person updated.');
        await refreshAll();
    }

    async function proposeCodes() {
        const pattern = $('codePattern').value.trim();

        if (!pattern) { notify(false, 'Type a pattern first, for example {property_id}.'); return; }

        const result = await post(`/admin/assets/import/${batch.id}/propose`, { pattern });

        if (!result.ok) { notify(false, result.data.message || 'Codes could not be proposed.'); return; }

        batch = { ...batch, summary: result.data.summary };
        notify(true, result.data.message);
        await refreshAll();
    }

    async function saveDecisions() {
        const departments = {};
        const categories = {};
        const people = {};
        const peopleUnassigned = [];

        document.querySelectorAll('#decisionsBody select[data-dept]').forEach(select => {
            if (select.value !== '') departments[select.dataset.dept] = select.value;
        });

        document.querySelectorAll('#decisionsBody select[data-cat]').forEach(select => {
            if (select.value !== '') categories[select.dataset.cat] = select.value;
        });

        document.querySelectorAll('#decisionsBody select[data-person]').forEach(select => {
            if (select.value === 'unassigned') peopleUnassigned.push(select.dataset.person);
            else if (select.value !== '') people[select.dataset.person] = select.value;
        });

        const result = await post(`/admin/assets/import/${batch.id}/decisions`, {
            departments,
            categories,
            people,
            people_unassigned: peopleUnassigned,
            allow_missing_department: $('optMissingDept').checked,
            allow_blank_property: $('optBlankProperty').checked,
        });

        if (!result.ok) { notify(false, result.data.message || 'Those decisions could not be saved.'); return; }

        batch = { ...batch, summary: result.data.summary };
        notify(true, result.data.message || 'Decisions saved.');
        await refreshAll();
    }

    /* ── Import ───────────────────────────────────────────────── */

    async function startImport() {
        if (!batch || busy) return;

        const summary = batch.summary;

        const go = await askConfirm(
            `Import ${summary.importable} line(s) into NU Trace?`,
            `${summary.exists} line(s) already registered will be skipped and ${summary.blocked} blocked line(s) will not be imported. Nothing already in NU Trace is overwritten — every imported asset gets its own signed sticker.`,
            'Import now'
        );

        if (!go) return;

        busy = true;
        setBusy($('importBtn'), true, 'Importing…');
        $('progressCard').classList.remove('hidden');
        $('progressTitle').textContent = 'Importing…';
        $('progressCount').textContent = '0';

        try {
            let done = false;
            let guard = 0;
            let imported = 0;

            while (!done && guard++ < 2000) {
                const result = await post(`/admin/assets/import/${batch.id}/commit`, {});

                if (!result.ok) { notify(false, result.data.message || 'The import could not continue.'); break; }

                batch = result.data.batch || { ...batch, summary: result.data.summary };
                batch.summary = result.data.summary || batch.summary;
                imported += result.data.imported || 0;
                importedCodes = result.data.codes || importedCodes;

                $('progressCount').textContent = imported;
                $('progressDetail').textContent = `${imported} asset(s) written so far.`;

                done = !!result.data.done;
            }

            $('progressCard').classList.add('hidden');

            if (done) {
                await loadRows();
                showResult();
            }
        } finally {
            busy = false;
            setBusy($('importBtn'), false, 'Import');
        }
    }

    function showResult() {
        const summary = batch.summary || {};

        $('resultCard').classList.remove('hidden');
        $('resultSubtitle').textContent = `${batch.original_name} · finished ${batch.finished_at || ''}`;

        $('resultGrid').innerHTML = [
            summaryChip('Imported', summary.imported || 0, 'forest'),
            summaryChip('Already registered', summary.exists || 0, 'ink'),
            summaryChip('Blocked', summary.blocked || 0, 'brick'),
            summaryChip('Unassigned', summary.unassigned || 0, 'bronze'),
        ].join('');

        if (importedCodes && importedCodes.length) {
            $('printLink').href = '/admin/assets/labels?codes=' + encodeURIComponent(importedCodes.join(','));
            $('printLink').classList.remove('opacity-50');
        } else {
            $('printLink').href = '/admin/assets';
        }

        if (summary.qr_pending > 0) {
            notify(false, `${summary.qr_pending} imported asset(s) are saved but their sticker image could not be produced yet. Use “Generate missing stickers” — this does not create the asset again.`);
        }
    }

    async function retryQr() {
        const result = await post(`/admin/assets/import/${batch.id}/qr`, {});
        batch = { ...batch, summary: result.data.summary };
        notify(result.ok, result.data.message || 'Sticker images checked.');
        showResult();
    }

    async function retryFailed() {
        const result = await post(`/admin/assets/import/${batch.id}/retry`, {});
        if (!result.ok) { notify(false, result.data.message || 'Nothing could be re-opened.'); return; }
        notify(true, result.data.message);
        await refreshAll();
    }

    async function discardImport() {
        if (!batch) return;

        const go = await askConfirm(
            'Discard this import?',
            'Lines you have not imported are cleared. Assets already imported are kept.',
            'Discard'
        );

        if (!go) return;

        const result = await post(`/admin/assets/import/${batch.id}/discard`, {});

        if (!result.ok) { notify(false, result.data.message || 'That import could not be discarded.'); return; }

        window.location.reload();
    }

    function setBusy(button, isBusy, label) {
        if (!button) return;
        button.disabled = isBusy;
        const span = button.querySelector('span');
        if (span) span.textContent = label;
        else button.textContent = label;
    }

    /* ── Boot ─────────────────────────────────────────────────── */

    (function boot() {
        if (!batch) return;

        renderBatch();
        renderMapping();

        if (batch.status === 'completed') {
            showResult();
            $('reviewCard').classList.remove('hidden');
            renderSummary(batch.summary);
            renderTabs(batch.summary);
            renderDecisions();
            renderRows();
            return;
        }

        if (INITIAL.rows && INITIAL.decisions) {
            $('reviewCard').classList.remove('hidden');
            renderSummary(batch.summary);
            renderTabs(batch.summary);
            renderDecisions();
            renderRows();
        }
    })();
</script>

@endsection
