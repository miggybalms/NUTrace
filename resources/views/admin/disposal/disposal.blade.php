{{-- resources/views/admin/disposal/disposal.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Disposal Management - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root{
            --navy-950:#0A1830; --navy-900:#0F2143; --navy-800:#15305B; --navy-700:#1D3F73;
            --gold-500:#C9A227; --gold-600:#A8841E; --gold-ink:#7E5E0E; --gold-100:#F3E7C4;
            --paper:#F3EEE0; --paper-2:#EAE2C9;
            --ink-900:#1A2233; --ink-600:#4B5468; --ink-400:#5C6474;
            --line:#DED2AE;
            --forest:#2F7A4D; --forest-dark:#245C3B; --forest-tint:#EAF4EE;
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

        .btn-gold{ font-family:'Inter',sans-serif; font-weight:600; border-radius:9px; padding:.55rem 1.1rem; background:var(--gold-500); color:var(--navy-950); display:inline-flex; align-items:center; transition:filter .15s ease; }
        .btn-gold:hover{ filter:brightness(1.06); }
        .btn-ghost{ font-family:'Inter',sans-serif; font-weight:500; border-radius:9px; padding:.55rem 1.1rem; color:var(--navy-800); border:1px solid var(--line); background:#fff; transition:background .15s; display:inline-flex; align-items:center; }
        .btn-ghost:hover{ background:var(--paper-2); }
        .btn-brick{ font-family:'Inter',sans-serif; font-weight:600; border-radius:9px; padding:.55rem 1.1rem; background:var(--brick); color:#fff; display:inline-flex; align-items:center; transition:filter .15s ease; }
        .btn-brick:hover{ filter:brightness(1.08); }

        .hero-card{ background:linear-gradient(135deg,var(--navy-950),var(--navy-800)); border-radius:14px; position:relative; overflow:hidden; }
        .hero-card::after{ content:""; position:absolute; left:0; right:0; bottom:0; height:3px; background:linear-gradient(90deg,transparent, var(--gold-500), transparent); }

        .disposal-card {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            box-shadow: 0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28);
            transition: all .3s ease;
        }
        .disposal-card:hover {
            transform: translateY(-2px);
            border-color: var(--gold-500);
            box-shadow: 0 2px 4px rgba(10,24,48,.06), 0 16px 32px -16px rgba(10,24,48,.3);
        }

        @keyframes fadeIn { from { opacity:0; transform: scale(.97); } to { opacity:1; transform: scale(1); } }
        .modal { transition: all .3s ease; }
        .modal.show { display: flex; animation: fadeIn .25s ease; }

        .modal-head{ background:linear-gradient(135deg,var(--navy-950),var(--navy-800)); position:relative; }
        .modal-head::after{ content:""; position:absolute; left:0; right:0; bottom:0; height:2px; background:var(--gold-500); }
        .form-input{ width:100%; border:1px solid var(--line); border-radius:9px; padding:.55rem .9rem; font-size:.9rem; outline:none; transition:border-color .15s, box-shadow .15s; }
        .form-input:focus{ border-color:var(--gold-500); box-shadow:0 0 0 3px rgba(201,162,39,.18); }

        .chip{ display:inline-flex; align-items:center; gap:.3rem; padding:.18rem .55rem; border-radius:999px; font-size:.7rem; font-weight:600; }
        .chip-active{ background:var(--brick-tint); color:var(--brick-dark); }
        .chip-archived{ background:var(--paper-2); color:var(--ink-600); }
        .chip-historic{ background:var(--bronze-tint); color:var(--bronze-dark); }

        .detail-value { overflow-wrap:anywhere; word-break:break-word; }
        .detail-note { white-space:pre-line; overflow-wrap:anywhere; word-break:break-word; }
    </style>
    @include('partials.ui')
</head>
<body class="nt-ui">
    @php
        $disposalRecords = $disposalRecords ?? collect();
        $archivedCount   = $archivedCount ?? 0;
        $archiveReady    = $archiveReady ?? true;
        $totalDisposed   = $totalDisposed ?? $disposalRecords->count();
    @endphp
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')

        <!-- Main Content -->
<main    <div class="flex-1 overflow-y-auto" style="background:var(--paper);">
            @include('admin.partials.header', [
                'adminHeaderPage'     => 'disposal',
                'adminHeaderTitle'    => 'Disposal',
                'adminHeaderIcon'     => 'ri-delete-bin-line',
                'adminHeaderBadge'    => 'Admin',
            ])

            <!-- Content -->
            <div class="p-4 sm:p-8">
                {{-- Archiving is a normal form POST, so its result comes back as a
                     flash message instead of a silent fetch. --}}
                @if(session('success'))
                    <div class="mb-6 p-4 rounded-xl text-sm flex items-start" style="background:var(--forest-tint); border-left:4px solid var(--forest); color:var(--forest-dark);">
                        <i class="ri-checkbox-circle-line text-xl mr-3 mt-0.5"></i>
                        <p>{{ session('success') }}</p>
                    </div>
                @endif
                @if(session('error'))
                    <div class="mb-6 p-4 rounded-xl text-sm flex items-start" style="background:var(--brick-tint); border-left:4px solid var(--brick); color:var(--brick-dark);">
                        <i class="ri-error-warning-line text-xl mr-3 mt-0.5"></i>
                        <p>{{ session('error') }}</p>
                    </div>
                @endif

                <!-- Stats Card -->
                <div class="hero-card p-6 mb-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="eyebrow" style="color:var(--gold-500);">Total Disposed Assets</p>
                            <p class="font-display text-4xl font-bold mt-2" id="totalDisposedCount">{{ $totalDisposed }}</p>
                            <p class="text-xs mt-2" style="color:#C7D2E3;">
                                Complete log of every retired institutional asset. Records are kept — nothing here is deleted.
                            </p>
                        </div>
                        <div class="w-20 h-20 rounded-full flex items-center justify-center" style="background:rgba(162,59,50,.35); border:1px solid rgba(255,255,255,.15);">
                            <i class="ri-delete-bin-line text-4xl" style="color:#F0C4BE;"></i>
                        </div>
                    </div>
                </div>

                {{-- Archived Disposal Assets: separated from the list below so the two
                     never look like one set of records. --}}
                <div class="mb-6 p-4 rounded-xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                     style="background:#fff; border:1px solid var(--line); border-left:4px solid var(--gold-500);">
                    <div class="flex items-start">
                        <i class="ri-archive-line text-2xl mr-3 mt-0.5" style="color:var(--gold-ink);"></i>
                        <div>
                            <p class="text-sm font-semibold" style="color:var(--navy-900);">Archived Disposal Assets</p>
                            <p class="text-xs mt-1" style="color:var(--ink-600);">
                                Archived records, and the assets that left the inventory with them,
                                are kept separately for historical reference.
                                @if($archivedCount > 0)
                                    <span class="font-medium">{{ $archivedCount }}</span> record{{ $archivedCount === 1 ? '' : 's' }} archived.
                                @endif
                            </p>
                        </div>
                    </div>
                    <a href="/admin/disposal/archived" class="btn-ghost whitespace-nowrap self-start sm:self-auto">
                        <i class="ri-archive-line mr-2"></i>
                        View Archived Assets
                    </a>
                </div>

                <!-- Search Disposal Records -->
                <div class="mb-6">
                    <div class="relative">
                        <i class="ri-search-line absolute left-4 top-1/2 -translate-y-1/2 text-lg" style="color:var(--ink-400);"></i>
                        <input type="text" id="disposalRecordSearch" oninput="filterDisposalRecords(this.value)"
                               placeholder="Search disposals by asset name, asset code, date, reason, or disposed by..."
                               class="form-input w-full" style="padding-left:2.75rem;" />
                    </div>
                    <p id="disposalSearchHint" class="hidden mt-2 text-xs" style="color:var(--gold-ink);">
                        <i class="ri-focus-3-line mr-1"></i><span></span>
                    </p>
                </div>

                <!-- Disposal Records List -->
                <div id="disposalRecordsContainer">
                    @if($disposalRecords->count() > 0)
                        <div class="grid grid-cols-1 gap-4" id="disposalRecordsList">
                            @foreach($disposalRecords as $record)
                            <div class="disposal-card p-6" data-id="{{ $record->id }}"
                                 data-search="{{ strtolower(($record->asset_name ?? '') . ' ' . ($record->asset_code ?? '') . ' ' . ($record->disposal_date ?? '') . ' ' . ($record->reason ?? '') . ' ' . ($record->disposed_by ?? '') . ' ' . ($record->Description ?? '') . ' ' . ($record->notes ?? '')) }}">
                                <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center mb-3">
                                            <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3 flex-shrink-0" style="background:var(--brick-tint);">
                                                <i class="ri-delete-bin-line text-xl" style="color:var(--brick);"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex items-center flex-wrap gap-2">
                                                    <h3 class="font-semibold detail-value" style="color:var(--navy-900);">{{ $record->asset_name ?? 'Asset' }}</h3>
                                                    <span class="chip chip-active"><i class="ri-checkbox-circle-line"></i>Disposed</span>
                                                    @unless($record->asset_still_exists)
                                                        <span class="chip chip-historic" title="The asset row is no longer in the inventory; the disposal record is the only remaining trace.">
                                                            <i class="ri-history-line"></i>Asset record removed
                                                        </span>
                                                    @else
                                                        @if($record->inventory_removed ?? false)
                                                            <span class="chip chip-archived" title="This asset is no longer part of the institution's inventory. Its row and its history are kept.">
                                                                <i class="ri-archive-line"></i>Removed from inventory
                                                            </span>
                                                        @endif
                                                    @endunless
                                                </div>
                                                <p class="text-xs font-mono detail-value" style="color:var(--ink-400);">{{ $record->asset_code ?? 'N/A' }}</p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                            <div>
                                                <p class="text-xs" style="color:var(--ink-400);">Disposal Date</p>
                                                <p class="text-sm font-medium" style="color:var(--navy-900);">{{ $record->disposal_date ?? '—' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs" style="color:var(--ink-400);">Reason</p>
                                                <p class="text-sm font-medium detail-value" style="color:var(--navy-900);">{{ $record->reason ?? '—' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs" style="color:var(--ink-400);">Disposed By</p>
                                                <p class="text-sm font-medium detail-value" style="color:var(--navy-900);">{{ $record->disposed_by ?? '—' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs" style="color:var(--ink-400);">Original Value</p>
                                                <p class="text-sm font-medium font-mono" style="color:var(--navy-900);">
                                                    {{ $record->original_value !== null ? '₱' . number_format((float) $record->original_value, 2) : '—' }}
                                                </p>
                                            </div>
                                        </div>
                                        @if($record->request_id)
                                            <p class="text-xs mt-3" style="color:var(--ink-400);">
                                                <i class="ri-links-line mr-1"></i>From request #REQ-{{ str_pad($record->request_id, 4, '0', STR_PAD_LEFT) }}
                                                @if($record->requester_name || $record->requester_email)
                                                    · {{ $record->requester_name ?: $record->requester_email }}
                                                @endif
                                            </p>
                                        @endif
                                    </div>

                                    <div class="flex flex-row sm:flex-col gap-2 flex-shrink-0">
                                        <button type="button" onclick="viewDisposalDetails({{ $record->id }})"
                                                class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition-colors"
                                                style="color:var(--steel); border:1px solid var(--line);"
                                                onmouseover="this.style.background='var(--steel-tint)'" onmouseout="this.style.background='transparent'"
                                                title="View details">
                                            <i class="ri-eye-line mr-1.5"></i>View Details
                                        </button>
                                        @if($archiveReady)
                                        <button type="button" onclick="openArchiveModal({{ $record->id }}, this)"
                                                data-label="{{ $record->asset_name ?? 'Asset' }} · {{ $record->asset_code ?? 'N/A' }}"
                                                class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all"
                                                style="color:#fff; background:var(--brick);"
                                                onmouseover="this.style.filter='brightness(1.08)'" onmouseout="this.style.filter='none'"
                                                title="Archive this disposal record">
                                            <i class="ri-archive-line mr-1.5"></i>Archive
                                        </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div id="emptyState" class="p-12 text-center" style="background:#fff; border-radius:14px; border:1px solid var(--line);">
                            <div class="w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-4" style="background:var(--paper-2);">
                                <i class="ri-inbox-line text-4xl" style="color:var(--ink-400);"></i>
                            </div>
                            <h3 class="text-lg font-semibold mb-2" style="color:var(--navy-900);">No disposal records yet</h3>
                            <p style="color:var(--ink-400);">
                                A disposal record appears here automatically once the Asset Management Office approves a
                                disposal request on the <a href="/admin/requests" class="font-medium" style="color:var(--steel);">Requests</a> page.
                            </p>
                        </div>
                    @endif
                </div>

                @unless($archiveReady)
                    <div class="mt-6 p-4 rounded-xl text-sm flex items-start" style="background:var(--bronze-tint); border-left:4px solid var(--bronze); color:var(--bronze-dark);">
                        <i class="ri-error-warning-line text-xl mr-3 mt-0.5"></i>
                        <div>
                            <p class="font-semibold">Archiving is not available yet.</p>
                            <p class="mt-1">The database migration that adds the archive columns has not been run on this server. Run <span class="font-mono">php artisan migrate --force</span> and reload this page.</p>
                        </div>
                    </div>
                @endunless

                <!-- Footer -->
                <div class="text-center text-sm mt-10 pt-7" style="color:var(--ink-400); border-top:1px solid var(--line);">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </main>
    </div>

    <!-- Archive Confirmation Modal.

         Archiving posts a real form that is set to the clicked record's URL. It
         deliberately does NOT depend on JavaScript doing a background request:
         the browser performs an ordinary POST, the server redirects back to this
         page and the result arrives as a flash message. That keeps the action
         working even when a fetch is blocked, dropped or never answered. -->
    <div id="archiveModal" class="hidden fixed inset-0 z-50 items-center justify-center modal p-4" style="background:rgba(10,24,48,.55);" onclick="closeArchiveModal()">
        <div class="rounded-xl shadow-2xl max-w-md w-full" style="background:#fff;" onclick="event.stopPropagation();">
            <div class="modal-head p-6">
                <div class="flex justify-between items-center">
                    <h3 class="font-display text-xl font-semibold text-white">Archive Disposal Record?</h3>
                    <button type="button" onclick="closeArchiveModal()" class="text-white/60 hover:text-white" aria-label="Close">
                        <i class="ri-close-line text-2xl"></i>
                    </button>
                </div>
            </div>
            <form id="archiveForm" method="POST" action="/admin/disposal" class="p-6">
                @csrf
                <p class="text-sm leading-relaxed" style="color:var(--ink-600);">
                    This record will be removed from the main Disposal list and moved to
                    <strong style="color:var(--navy-900);">Archived Disposal Assets</strong>.
                </p>
                <p class="text-sm leading-relaxed mt-3" style="color:var(--ink-600);">
                    The asset then leaves the inventory: it stops appearing in the Assets lists,
                    the department views and the inventory download. Nothing is deleted — the asset
                    row and its accountability, repair, replacement and audit history are kept, so
                    this archived record can still name it.
                </p>
                <p class="text-xs mt-3 p-3 rounded-lg" id="archiveModalRecord" style="background:var(--paper-2); color:var(--ink-600);"></p>

                <div class="flex justify-end gap-2 mt-6 pt-5" style="border-top:1px solid var(--line);">
                    <button type="button" onclick="closeArchiveModal()" class="btn-ghost">Cancel</button>
                    <button type="submit" id="archiveModalConfirm" class="btn-brick">
                        <i class="ri-archive-line mr-1.5"></i>Archive
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Disposal Details Modal -->
    <div id="viewDisposalModal" class="hidden fixed inset-0 z-50 items-center justify-center modal p-4" style="background:rgba(10,24,48,.55);">
        <div class="rounded-xl shadow-2xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto" style="background:#fff;">
            <div class="modal-head p-6 sticky top-0 z-10 flex justify-between items-center">
                <h3 class="font-display text-xl font-semibold text-white">Disposal Details</h3>
                <button type="button" onclick="closeViewDisposalModal()" class="text-white/60 hover:text-white" aria-label="Close">
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
        /* ── helpers ─────────────────────────────────────────────── */
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

        function fieldRow(label, value, raw) {
            if (isEmptyVal(value)) return '';
            return `
                <div class="rounded-lg p-3.5" style="background:var(--paper-2);">
                    <p class="text-xs mb-1" style="color:var(--ink-400);">${esc(label)}</p>
                    <p class="text-sm font-medium detail-value" style="color:var(--navy-900);">${raw ? value : esc(value)}</p>
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

        /* ── search ──────────────────────────────────────────────── */
        function filterDisposalRecords(query) {
            const term = (query || '').trim().toLowerCase();
            const list = document.getElementById('disposalRecordsList');
            if (!list) return;

            const cards = list.querySelectorAll('.disposal-card');
            let visible = 0;

            cards.forEach((card) => {
                const haystack = (card.dataset.search || '').toLowerCase();
                const match = !term || haystack.includes(term);
                card.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            const hint = document.getElementById('disposalSearchHint');
            if (hint) {
                const span = hint.querySelector('span');
                if (term && visible === 0) {
                    span.textContent = 'No disposals match "' + term + '". Try an asset code or asset name.';
                    hint.classList.remove('hidden');
                } else if (term) {
                    span.textContent = visible + ' disposal' + (visible === 1 ? '' : 's') + ' match.';
                    hint.classList.remove('hidden');
                } else {
                    hint.classList.add('hidden');
                }
            }
        }

        /* ── archive ───────────────────────────────────────────────
           The form itself does the work; all this has to do is point it at the
           record whose Archive button was clicked. */
        function openArchiveModal(disposalId, button) {
            const modal = document.getElementById('archiveModal');
            const form = document.getElementById('archiveForm');
            const record = document.getElementById('archiveModalRecord');
            const confirmBtn = document.getElementById('archiveModalConfirm');

            if (form) form.action = '/admin/disposal/' + disposalId + '/archive';
            if (record) record.textContent = button?.dataset?.label || ('Disposal record #' + disposalId);
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="ri-archive-line mr-1.5"></i>Archive';
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeArchiveModal() {
            const modal = document.getElementById('archiveModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // One archive at a time: the second click can only ever be a duplicate.
        document.getElementById('archiveForm')?.addEventListener('submit', function () {
            const confirmBtn = document.getElementById('archiveModalConfirm');
            if (confirmBtn) {
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<i class="ri-loader-4-line mr-1.5"></i>Archiving…';
            }
        });

        /* ── view details ────────────────────────────────────────── */
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

        function renderDisposalDetails(d) {
            const content = document.getElementById('viewDisposalContent');

            const statusChip = d.is_archived
                ? '<span class="chip chip-archived"><i class="ri-archive-line"></i>Archived Disposal Asset</span>'
                : '<span class="chip chip-active"><i class="ri-checkbox-circle-line"></i>Disposed</span>';

            const header = `
                <div class="flex items-center mb-5">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-3 flex-shrink-0" style="background:var(--brick-tint);">
                        <i class="ri-delete-bin-line text-xl" style="color:var(--brick);"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center flex-wrap gap-2">
                            <h4 class="font-semibold detail-value" style="color:var(--navy-900);">${esc(d.asset_name || 'Asset')}</h4>
                            ${statusChip}
                        </div>
                        ${!isEmptyVal(d.asset_code) ? `<p class="text-xs font-mono" style="color:var(--ink-400);">${esc(d.asset_code)}</p>` : ''}
                    </div>
                </div>`;

            const descriptionBlock = !isEmptyVal(d.description) ? `
                <div class="rounded-lg p-3.5 mt-3" style="background:var(--paper-2);">
                    <p class="text-xs mb-1" style="color:var(--ink-400);">Description</p>
                    <p class="text-sm detail-note" style="color:var(--ink-600);">${esc(d.description)}</p>
                </div>` : '';

            const notesBlock = !isEmptyVal(d.notes) ? `
                <div class="rounded-lg p-3.5 mt-3" style="background:var(--paper-2);">
                    <p class="text-xs mb-1" style="color:var(--ink-400);">Notes</p>
                    <p class="text-sm detail-note" style="color:var(--ink-600);">${esc(d.notes)}</p>
                </div>` : '';

            const archivedBlock = d.is_archived ? rows([
                fieldRow('Archived On', dateTime(d.archived_at)),
                fieldRow('Archived By', d.archived_by),
                fieldRow('Inventory', d.inventory_removed
                    ? ('Removed from inventory' + (d.inventory_removed_at ? ' · ' + dateTime(d.inventory_removed_at) : ''))
                    : ''),
            ]) : '';

            /* ── the request that produced this record ── */
            const r = d.request;
            const requestSection = r ? section('Disposal Request', `
                ${rows([
                    fieldRow('Request ID', '#REQ-' + String(r.id).padStart(4, '0')),
                    fieldRow('Request Status', r.status),
                    fieldRow('Requested By', r.requester_name || r.requester_email),
                    fieldRow('Requester Email', r.requester_name ? r.requester_email : null),
                    fieldRow('Request Date', r.created_at),
                    fieldRow('Request Type', r.type),
                ])}
                ${!isEmptyVal(r.note) ? `
                    <div class="rounded-lg p-3.5 mt-3" style="background:var(--paper-2);">
                        <p class="text-xs mb-1" style="color:var(--ink-400);">Original Request Note</p>
                        <p class="text-sm detail-note" style="color:var(--ink-600);">${esc(r.note)}</p>
                    </div>` : ''}
                ${!isEmptyVal(r.attachment_url) ? `
                    <p class="text-xs mt-3">
                        <i class="ri-attachment-2 mr-1"></i>
                        <a href="${esc(r.attachment_url)}" target="_blank" rel="noopener" class="font-medium" style="color:var(--steel);">
                            ${esc(r.attachment_name || 'Supporting file')}
                        </a>
                    </p>` : ''}
                ${!isEmptyVal(r.admin_remarks) ? `
                    <div class="rounded-lg p-3.5 mt-3" style="background:var(--paper-2);">
                        <p class="text-xs mb-1" style="color:var(--ink-400);">Admin Remarks</p>
                        <p class="text-sm detail-note" style="color:var(--ink-600);">${esc(r.admin_remarks)}</p>
                    </div>` : ''}
            `, 'ri-file-text-line') : section('Disposal Request', `
                <p class="text-xs italic" style="color:var(--ink-400);">
                    This record was created before disposal requests were linked to their record.
                </p>`);

            /* ── asset information ── */
            const assetSection = d.asset_still_exists ? section('Asset Information', rows([
                fieldRow('Category', d.category),
                fieldRow('Condition', d.condition),
                fieldRow('Lifecycle Status', d.lifecycle_status),
                fieldRow('Serial Number', d.serial_number),
                fieldRow('Location', d.asset_location),
                fieldRow('Supplier', d.supplier),
                fieldRow('Model', d.model),
                fieldRow('Manufacturer', d.manufacture),
                fieldRow('Purchase Price', money(d.purchase_price)),
                fieldRow('Warranty (months)', d.warranty_months),
                fieldRow('Lifespan (months)', d.lifespan_months),
            ]), 'ri-information-line') : section('Asset Information', `
                <p class="text-xs italic" style="color:var(--ink-400);">
                    The asset is no longer in the inventory. This disposal record is the remaining trace of it.
                </p>`);

            /* ── lifecycle history ── */
            const h = d.history || {};

            const accountabilitySection = section('Accountability History', historyList(h.accountability, (a) => historyRow(
                a.holder || a.holder_email || 'Unnamed holder',
                [a.Assign_date, a.Is_Current ? 'Currently accountable' : null, a.transfer_reason].filter(Boolean).join(' · '),
                a.notes
            )), 'ri-user-follow-line');

            const repairSection = section('Repair History', historyList(h.repairs, (x) => historyRow(
                x.Repair_Description || 'Repair',
                [x.Repair_Date, x.status, x.Repair_result, money(x.Repair_Cost)].filter(Boolean).join(' · '),
                x.notes
            )), 'ri-tools-line');

            const replacementSection = section('Replacement History', historyList(h.replacements, (x) => historyRow(
                x.new_asset_name ? ('Replaced by ' + x.new_asset_name) : 'Replacement',
                [x.Replacement_Date, x.status, x.replacement_reason || x.reason, x.new_asset_code].filter(Boolean).join(' · '),
                x.notes
            )), 'ri-refresh-line');

            const disposalHistorySection = section('Disposal History', historyList(h.disposals, (x) => historyRow(
                'Disposal #' + x.Disposal_ID + ' · ' + (x.disposal_reason || '—'),
                [x.disposal_date, x.is_archived ? 'Archived' : 'Active', x.Approve_by].filter(Boolean).join(' · '),
                x.notes
            )), 'ri-delete-bin-line');

            const auditSection = section('Audit History', historyList(h.audit, (x) => historyRow(
                x.action_description || x.action_type || 'Activity',
                [x.created_at, x.action_type, x.actor || x.actor_email].filter(Boolean).join(' · '),
                x.notes
            )), 'ri-history-line');

            content.innerHTML = `
                ${header}
                ${rows([
                    fieldRow('Disposal Date', d.disposal_date),
                    fieldRow('Reason', d.reason),
                    fieldRow('Disposed / Approved By', d.disposed_by),
                    fieldRow('Original Value', money(d.original_value)),
                ])}
                ${rows([fieldRow('Disposal Record', d.id ? '#' + d.id : null)])}
                ${archivedBlock}
                ${descriptionBlock}
                ${notesBlock}
                ${requestSection}
                ${assetSection}
                ${accountabilitySection}
                ${repairSection}
                ${replacementSection}
                ${disposalHistorySection}
                ${auditSection}
            `;
        }

        /* Escape closes whichever modal is open */
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            closeArchiveModal();
            closeViewDisposalModal();
        });
    </script>
</body>
</html>
