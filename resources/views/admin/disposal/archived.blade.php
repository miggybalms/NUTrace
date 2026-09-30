{{-- resources/views/admin/disposal/archived.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Archived Disposal Assets - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root{
            --navy-950:#0A1830; --navy-900:#0F2143; --navy-800:#15305B; --navy-700:#1D3F73;
            --gold-500:#C9A227; --gold-600:#A8841E; --gold-100:#F3E7C4;
            --paper:#F3EEE0; --paper-2:#EAE2C9;
            --ink-900:#1A2233; --ink-600:#4B5468; --ink-400:#8991A0;
            --line:#DED2AE;
            --forest:#2F7A4D; --forest-tint:#EAF4EE;
            --bronze:#B4791E; --bronze-dark:#8F5F16; --bronze-tint:#FBF1DE;
            --steel:#2E5C8A; --steel-dark:#234869; --steel-tint:#E9F0F7;
            --brick:#A23B32; --brick-dark:#7E2E27; --brick-tint:#F7E9E6;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: var(--paper);
            color: var(--ink-900);
        }

        .font-display{ font-family:'Fraunces',serif; }
        .font-mono{ font-family:'IBM Plex Mono',monospace; }
        .eyebrow{ font-size:.68rem; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-400); }

        .sidebar-item { border-left: 3px solid transparent; transition: all .2s ease; cursor: pointer; }
        .sidebar-item:hover { background-color: rgba(255,255,255,.05); }
        .sidebar-item.active { background-color: rgba(201,162,39,.10); color:#E9C766; border-left-color:#C9A227; }

        .topbar{ background:#fff; border-bottom:1px solid var(--line); position:relative; }
        .topbar::after{ content:""; position:absolute; left:0; right:0; bottom:-2px; height:2px; background:linear-gradient(90deg, transparent, var(--gold-500) 20%, var(--gold-500) 80%, transparent); opacity:.7; }

        .btn-ghost{ font-family:'Inter',sans-serif; font-weight:500; border-radius:9px; padding:.55rem 1.1rem; color:var(--navy-800); border:1px solid var(--line); background:#fff; transition:background .15s; display:inline-flex; align-items:center; }
        .btn-ghost:hover{ background:var(--paper-2); }

        .hero-card{ background:linear-gradient(135deg,var(--navy-950),var(--navy-800)); border-radius:14px; position:relative; overflow:hidden; }
        .hero-card::after{ content:""; position:absolute; left:0; right:0; bottom:0; height:3px; background:linear-gradient(90deg,transparent, var(--gold-500), transparent); }

        .archive-card {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            box-shadow: 0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28);
            transition: all .3s ease;
        }
        .archive-card:hover { border-color: var(--gold-500); box-shadow: 0 2px 4px rgba(10,24,48,.06), 0 16px 32px -16px rgba(10,24,48,.3); }

        @keyframes fadeIn { from { opacity:0; transform: scale(.97); } to { opacity:1; transform: scale(1); } }
        .modal { transition: all .3s ease; }
        .modal.show { display: flex; animation: fadeIn .25s ease; }

        .modal-head{ background:linear-gradient(135deg,var(--navy-950),var(--navy-800)); position:relative; }
        .modal-head::after{ content:""; position:absolute; left:0; right:0; bottom:0; height:2px; background:var(--gold-500); }
        .form-input{ width:100%; border:1px solid var(--line); border-radius:9px; padding:.55rem .9rem; font-size:.9rem; outline:none; transition:border-color .15s, box-shadow .15s; }
        .form-input:focus{ border-color:var(--gold-500); box-shadow:0 0 0 3px rgba(201,162,39,.18); }

        .chip{ display:inline-flex; align-items:center; gap:.3rem; padding:.18rem .55rem; border-radius:999px; font-size:.7rem; font-weight:600; }
        .chip-archived{ background:var(--paper-2); color:var(--ink-600); }
        .chip-historic{ background:var(--bronze-tint); color:var(--bronze-dark); }

        table { width: 100%; border-collapse: collapse; }
        thead th { text-align: left; }
        tbody tr { border-top: 1px solid var(--line); }
        tbody tr:hover { background: var(--paper-2); }

        .detail-value { overflow-wrap:anywhere; word-break:break-word; }
        .detail-note { white-space:pre-line; overflow-wrap:anywhere; word-break:break-word; }
    </style>
    @include('partials.ui')
</head>
<body class="nt-ui">
    @php
        $disposalRecords = $disposalRecords ?? collect();
        $archiveReady    = $archiveReady ?? true;
    @endphp
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto" style="background:var(--paper);">
            @include('admin.partials.header', [
                'adminHeaderPage'     => 'disposal',
                'adminHeaderTitle'    => 'Archived Disposal Assets',
                'adminHeaderIcon'     => 'ri-archive-line',
                'adminHeaderBadge'    => 'Admin',
            ])

            <!-- Content -->
            <div class="p-4 sm:p-8">
                <div class="hero-card p-6 mb-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="eyebrow" style="color:var(--gold-500);">Archived Disposal Assets</p>
                            <p class="font-display text-4xl font-bold mt-2">{{ $disposalRecords->count() }}</p>
                            <p class="text-xs mt-2" style="color:#C7D2E3;">
                                Older disposal records are stored here for historical reference.
                            </p>
                        </div>
                        <div class="w-20 h-20 rounded-full flex items-center justify-center" style="background:rgba(201,162,39,.28); border:1px solid rgba(255,255,255,.15);">
                            <i class="ri-archive-line text-4xl" style="color:#F3E7C4;"></i>
                        </div>
                    </div>
                </div>

                <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <a href="/admin/disposal" class="btn-ghost self-start">
                        <i class="ri-arrow-left-line mr-2"></i>
                        Back to Disposal
                    </a>
                    <p class="text-xs sm:max-w-md sm:text-right" style="color:var(--ink-600);">
                        These records are no longer part of the active Disposal list. Archiving also took their
                        assets out of the inventory — nothing here can be deleted, so the record and the asset's
                        history stay readable.
                    </p>
                </div>

                <!-- Search -->
                <div class="mb-6">
                    <div class="relative">
                        <i class="ri-search-line absolute left-4 top-1/2 -translate-y-1/2 text-lg" style="color:var(--ink-400);"></i>
                        <input type="text" id="archivedDisposalSearch" oninput="filterArchivedRecords(this.value)"
                               placeholder="Search archived disposals by asset name, asset code, date, reason, or archived by..."
                               class="form-input w-full" style="padding-left:2.75rem;" />
                    </div>
                    <p id="archivedSearchHint" class="hidden mt-2 text-xs" style="color:var(--gold-600);">
                        <i class="ri-focus-3-line mr-1"></i><span></span>
                    </p>
                </div>

                @if($disposalRecords->count() > 0)
                    <div class="archive-card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table>
                                <thead style="background:var(--paper-2);">
                                    <tr>
                                        <th class="eyebrow py-3 px-4 whitespace-nowrap">Asset Name</th>
                                        <th class="eyebrow py-3 px-4 whitespace-nowrap">Asset Code</th>
                                        <th class="eyebrow py-3 px-4 whitespace-nowrap">Disposal Date</th>
                                        <th class="eyebrow py-3 px-4 whitespace-nowrap">Reason</th>
                                        <th class="eyebrow py-3 px-4 whitespace-nowrap">Archived Date</th>
                                        <th class="eyebrow py-3 px-4 whitespace-nowrap">Archived By</th>
                                        <th class="eyebrow py-3 px-4 whitespace-nowrap">Details</th>
                                    </tr>
                                </thead>
                                <tbody id="archivedRecordsBody">
                                    @foreach($disposalRecords as $record)
                                    <tr class="archive-row"
                                        data-id="{{ $record->id }}"
                                        data-search="{{ strtolower(($record->asset_name ?? '') . ' ' . ($record->asset_code ?? '') . ' ' . ($record->disposal_date ?? '') . ' ' . ($record->reason ?? '') . ' ' . ($record->archived_by_name ?? '') . ' ' . ($record->archived_at ?? '')) }}">
                                        <td class="py-3 px-4">
                                            <p class="text-sm font-medium detail-value" style="color:var(--navy-900);">{{ $record->asset_name ?? 'Asset' }}</p>
                                            @unless($record->asset_still_exists)
                                                <span class="chip chip-historic mt-1" title="The asset row itself is gone; this disposal record is the only remaining trace. Nothing can be deleted from here."><i class="ri-history-line"></i>Asset record removed</span>
                                            @else
                                                @if($record->inventory_removed ?? false)
                                                    <span class="chip chip-archived mt-1" title="Taken out of the inventory when this record was archived. The asset row and its history are still kept."><i class="ri-archive-line"></i>Removed from inventory</span>
                                                @endif
                                            @endunless
                                        </td>
                                        <td class="py-3 px-4 text-xs font-mono detail-value" style="color:var(--ink-600);">{{ $record->asset_code ?? 'N/A' }}</td>
                                        <td class="py-3 px-4 text-sm" style="color:var(--ink-600);">{{ $record->disposal_date ?? '—' }}</td>
                                        <td class="py-3 px-4 text-sm detail-value" style="color:var(--ink-600);">{{ $record->reason ?? '—' }}</td>
                                        <td class="py-3 px-4 text-sm" style="color:var(--ink-600);">{{ $record->archived_at ? \Illuminate\Support\Carbon::parse($record->archived_at)->format('M d, Y') : '—' }}</td>
                                        <td class="py-3 px-4 text-sm detail-value" style="color:var(--ink-600);">{{ $record->archived_by_name ?: '—' }}</td>
                                        <td class="py-3 px-4">
                                            <button type="button" onclick="viewDisposalDetails({{ $record->id }})"
                                                    class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                                                    style="color:var(--steel); border:1px solid var(--line);"
                                                    onmouseover="this.style.background='var(--steel-tint)'" onmouseout="this.style.background='transparent'"
                                                    title="View details">
                                                <i class="ri-eye-line mr-1"></i>View Details
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="p-12 text-center" style="background:#fff; border-radius:14px; border:1px solid var(--line);">
                        <div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-4" style="background:var(--paper-2);">
                            <i class="ri-archive-line text-4xl" style="color:var(--ink-400);"></i>
                        </div>
                        <h3 class="text-lg font-semibold mb-2" style="color:var(--navy-900);">Nothing archived yet</h3>
                        <p style="color:var(--ink-400);">
                            Disposal records you archive from the
                            <a href="/admin/disposal" class="font-medium" style="color:var(--steel);">Disposal</a> page are kept here.
                        </p>
                    </div>
                @endif

                @unless($archiveReady)
                    <div class="mt-6 p-4 rounded-xl text-sm flex items-start" style="background:var(--bronze-tint); border-left:4px solid var(--bronze); color:var(--bronze-dark);">
                        <i class="ri-error-warning-line text-xl mr-3 mt-0.5"></i>
                        <div>
                            <p class="font-semibold">The archive migration has not been run on this server.</p>
                            <p class="mt-1">Run <span class="font-mono">php artisan migrate --force</span> so disposal records can be archived.</p>
                        </div>
                    </div>
                @endunless

                <div class="text-center text-sm mt-10 pt-7" style="color:var(--ink-400); border-top:1px solid var(--line);">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- View Disposal Details Modal (same details as the main Disposal page) -->
    <div id="viewDisposalModal" class="hidden fixed inset-0 z-50 items-center justify-center modal p-4" style="background:rgba(10,24,48,.55);">
        <div class="rounded-xl shadow-2xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto" style="background:#fff;">
            <div class="modal-head p-6 sticky top-0 z-10 flex justify-between items-center">
                <h3 class="font-display text-xl font-semibold text-white">Archived Disposal Details</h3>
                <button type="button" onclick="closeViewDisposalModal()" class="text-white/60 hover:text-white">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            <div class="p-6" id="viewDisposalContent">
                <!-- filled by JS -->
            </div>
            <div class="px-6 py-5 flex justify-end sticky bottom-0" style="border-top:1px solid var(--line); background:#fff;">
                <button type="button" onclick="closeViewDisposalModal()" class="btn-ghost">Close</button>
            </div>
        </div>
    </div>

    <script>
        function esc(value) {
            if (value === null || value === undefined) return '';
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function isEmptyVal(v) {
            return v === null || v === undefined || String(v).trim() === '' ||
                   String(v).trim() === '-' ||
                   String(v).toLowerCase() === 'n/a' ||
                   String(v).toLowerCase() === 'null';
        }

        function money(v) {
            if (isEmptyVal(v) || isNaN(v)) return null;
            return '₱' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2 });
        }

        function dateTime(v) {
            if (isEmptyVal(v)) return null;
            const parsed = new Date(String(v).replace(' ', 'T'));
            if (isNaN(parsed)) return String(v);
            return parsed.toLocaleString('en-US', {
                year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
            });
        }

        function fieldRow(label, value) {
            if (isEmptyVal(value)) return '';
            return `
                <div class="rounded-lg p-3.5" style="background:var(--paper-2);">
                    <p class="text-xs mb-1" style="color:var(--ink-400);">${esc(label)}</p>
                    <p class="text-sm font-medium detail-value" style="color:var(--navy-900);">${esc(value)}</p>
                </div>`;
        }

        function section(title, inner, icon) {
            if (!inner) return '';
            return `
                <div class="pt-4 mt-5" style="border-top:1px solid var(--line);">
                    <p class="eyebrow mb-3">${icon ? `<i class="${esc(icon)} mr-1"></i>` : ''}${esc(title)}</p>
                    ${inner}
                </div>`;
        }

        function rows(items) {
            const html = items.filter(Boolean).join('');
            return html ? `<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">${html}</div>` : '';
        }

        function historyList(entries, render) {
            if (!entries || entries.length === 0) {
                return `<p class="text-xs italic" style="color:var(--ink-400);">No records found.</p>`;
            }
            return `<div class="space-y-2">${entries.map(render).join('')}</div>`;
        }

        function historyRow(title, meta, body) {
            return `
                <div class="rounded-lg p-3.5" style="background:var(--paper-2);">
                    <p class="text-sm font-medium detail-value" style="color:var(--navy-900);">${esc(title)}</p>
                    ${meta ? `<p class="text-xs mt-0.5 detail-value" style="color:var(--ink-400);">${esc(meta)}</p>` : ''}
                    ${body ? `<p class="text-sm mt-2 detail-note" style="color:var(--ink-600);">${esc(body)}</p>` : ''}
                </div>`;
        }

        function filterArchivedRecords(query) {
            const term = (query || '').trim().toLowerCase();
            const body = document.getElementById('archivedRecordsBody');
            if (!body) return;

            let visible = 0;
            body.querySelectorAll('.archive-row').forEach((row) => {
                const match = !term || (row.dataset.search || '').includes(term);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            const hint = document.getElementById('archivedSearchHint');
            if (hint) {
                const span = hint.querySelector('span');
                if (term && visible === 0) {
                    span.textContent = 'No archived disposals match "' + term + '".';
                    hint.classList.remove('hidden');
                } else if (term) {
                    span.textContent = visible + ' archived record' + (visible === 1 ? '' : 's') + ' match.';
                    hint.classList.remove('hidden');
                } else {
                    hint.classList.add('hidden');
                }
            }
        }

        function closeViewDisposalModal() {
            const modal = document.getElementById('viewDisposalModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        async function viewDisposalDetails(id) {
            const modal = document.getElementById('viewDisposalModal');
            const content = document.getElementById('viewDisposalContent');

            content.innerHTML = '<p class="text-center py-10" style="color:var(--ink-400);">Loading…</p>';
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            try {
                const res = await fetch(`/admin/disposal/${id}/details`, { headers: { 'Accept': 'application/json' } });

                if (!res.ok) {
                    const json = await res.json().catch(() => ({}));
                    throw new Error(json.message || 'Failed to load details');
                }

                renderDisposalDetails(await res.json());
            } catch (err) {
                content.innerHTML = `<p class="text-center py-10" style="color:var(--brick);">${esc(err.message)}</p>`;
            }
        }

        function renderDisposalDetails(d) {
            const content = document.getElementById('viewDisposalContent');
            const h = d.history || {};

            const header = `
                <div class="flex items-center mb-5">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3 flex-shrink-0" style="background:var(--paper-2);">
                        <i class="ri-archive-line text-xl" style="color:var(--ink-600);"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center flex-wrap gap-2">
                            <h4 class="font-semibold detail-value" style="color:var(--navy-900);">${esc(d.asset_name || 'Asset')}</h4>
                            <span class="chip chip-archived"><i class="ri-archive-line"></i>Archived Disposal Asset</span>
                        </div>
                        ${!isEmptyVal(d.asset_code) ? `<p class="text-xs font-mono" style="color:var(--ink-400);">${esc(d.asset_code)}</p>` : ''}
                    </div>
                </div>`;

            const r = d.request;

            content.innerHTML = `
                ${header}
                ${rows([
                    fieldRow('Disposal Date', d.disposal_date),
                    fieldRow('Reason', d.reason),
                    fieldRow('Disposed / Approved By', d.disposed_by),
                    fieldRow('Original Value', money(d.original_value)),
                    fieldRow('Archived On', dateTime(d.archived_at)),
                    fieldRow('Archived By', d.archived_by),
                    fieldRow('Inventory', d.inventory_removed
                        ? ('Removed from inventory' + (d.inventory_removed_at ? ' · ' + dateTime(d.inventory_removed_at) : ''))
                        : ''),
                ])}
                ${!isEmptyVal(d.description) ? `
                    <div class="rounded-lg p-3.5 mt-3" style="background:var(--paper-2);">
                        <p class="text-xs mb-1" style="color:var(--ink-400);">Description</p>
                        <p class="text-sm detail-note" style="color:var(--ink-600);">${esc(d.description)}</p>
                    </div>` : ''}
                ${!isEmptyVal(d.notes) ? `
                    <div class="rounded-lg p-3.5 mt-3" style="background:var(--paper-2);">
                        <p class="text-xs mb-1" style="color:var(--ink-400);">Notes</p>
                        <p class="text-sm detail-note" style="color:var(--ink-600);">${esc(d.notes)}</p>
                    </div>` : ''}
                ${r ? section('Disposal Request', `
                    ${rows([
                        fieldRow('Request ID', '#REQ-' + String(r.id).padStart(4, '0')),
                        fieldRow('Request Status', r.status),
                        fieldRow('Requested By', r.requester_name || r.requester_email),
                        fieldRow('Request Date', r.created_at),
                    ])}
                    ${!isEmptyVal(r.note) ? `
                        <div class="rounded-lg p-3.5 mt-3" style="background:var(--paper-2);">
                            <p class="text-xs mb-1" style="color:var(--ink-400);">Original Request Note</p>
                            <p class="text-sm detail-note" style="color:var(--ink-600);">${esc(r.note)}</p>
                        </div>` : ''}
                `, 'ri-file-text-line') : ''}
                ${d.asset_still_exists ? section('Asset Information', rows([
                    fieldRow('Category', d.category),
                    fieldRow('Condition', d.condition),
                    fieldRow('Serial Number', d.serial_number),
                    fieldRow('Location', d.asset_location),
                ]), 'ri-information-line') : ''}
                ${section('Accountability History', historyList(h.accountability, (a) => historyRow(
                    a.holder || a.holder_email || 'Unnamed holder',
                    [a.Assign_date, a.Is_Current ? 'Currently accountable' : null].filter(Boolean).join(' · '),
                    a.notes
                )), 'ri-user-follow-line')}
                ${section('Repair History', historyList(h.repairs, (x) => historyRow(
                    x.Repair_Description || 'Repair',
                    [x.Repair_Date, x.status, x.Repair_result].filter(Boolean).join(' · '),
                    x.notes
                )), 'ri-tools-line')}
                ${section('Disposal History', historyList(h.disposals, (x) => historyRow(
                    'Disposal #' + x.Disposal_ID + ' · ' + (x.disposal_reason || '—'),
                    [x.disposal_date, x.is_archived ? 'Archived' : 'Active'].filter(Boolean).join(' · '),
                    x.notes
                )), 'ri-delete-bin-line')}
                ${section('Audit History', historyList(h.audit, (x) => historyRow(
                    x.action_description || x.action_type || 'Activity',
                    [x.created_at, x.action_type, x.actor || x.actor_email].filter(Boolean).join(' · '),
                    x.notes
                )), 'ri-history-line')}
            `;
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeViewDisposalModal();
        });
    </script>
</body>
</html>
