<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Transfer - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root{
            --navy-950:#0A1830; --navy-900:#0F2143; --navy-800:#15305B; --navy-700:#1D3F73;
            --gold-500:#C9A227; --gold-600:#A8841E; --gold-300:#E9C766; --gold-100:#F3E7C4;
            --paper:#F3EEE0; --paper-2:#EAE2C9; --paper-3:#F5F0E2;
            --ink-900:#1A2233; --ink-600:#4B5468; --ink-400:#8991A0;
            --line:#DED2AE;
            --forest:#2F7A4D; --forest-dark:#245C3B; --forest-tint:#EAF4EE;
            --bronze:#B4791E; --bronze-dark:#8F5F16; --bronze-tint:#FBF1DE;
            --steel:#2E5C8A; --steel-dark:#234869; --steel-tint:#E9F0F7;
            --brick:#A23B32; --brick-dark:#7E2E27; --brick-tint:#F7E9E6;
            --plum:#6B4C82; --plum-dark:#523A64; --plum-tint:#EFE7F3;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: var(--paper);
            color: var(--ink-900);
        }

        .font-display{ font-family:'Fraunces',serif; }
        .font-mono{ font-family:'IBM Plex Mono',monospace; }
        .eyebrow{ font-family:'Inter',sans-serif; font-size:.68rem; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:var(--ink-400); }

        .sidebar-item {
            border-left: 3px solid transparent;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .sidebar-item:hover { background-color: rgba(255, 255, 255, 0.05); }
        .sidebar-item.active {
            background-color: rgba(201, 162, 39, 0.10);
            color: #E9C766;
            border-left-color: #C9A227;
        }

        .topbar{ background:#fff; border-bottom:1px solid var(--line); position:relative; }
        .topbar::after{ content:""; position:absolute; left:0; right:0; bottom:-2px; height:2px; background:linear-gradient(90deg, transparent, var(--gold-500) 20%, var(--gold-500) 80%, transparent); opacity:.7; }

        .stat-card{ background:#fff; border:1px solid var(--line); border-radius:14px; padding:1.5rem; box-shadow: 0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28); }
        .stat-icon{ width:2.5rem; height:2.5rem; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }

        .panel{ background:#fff; border:1px solid var(--line); box-shadow: 0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28); }

        .tab-btn{ position:relative; }
        .tab-btn.tab-active{ color:var(--navy-900) !important; font-weight:600; }
        .tab-btn.tab-active::after{ content:""; position:absolute; left:0; right:0; bottom:-1px; height:2px; background:var(--gold-500); }

        .filter-chip{ border:1px solid var(--line); background:#fff; color:var(--ink-600); border-radius:999px; padding:.375rem .875rem; font-size:.78rem; font-weight:500; transition:all .15s ease; }
        .filter-chip:hover{ border-color:var(--gold-500); color:var(--navy-900); }
        .filter-chip.chip-active{ background:var(--navy-900); border-color:var(--navy-900); color:#fff; }

        .employee-row{ cursor:pointer; transition:background-color .15s ease; }
        .employee-row:hover{ background:var(--paper-3); }
        .employee-row.is-dimmed{ display:none; }

        .asset-pick{ display:flex; align-items:flex-start; gap:.75rem; padding:.75rem .875rem; border:1px solid var(--line); border-radius:10px; cursor:pointer; transition:all .15s ease; }
        .asset-pick:hover{ border-color:var(--gold-500); background:var(--paper-3); }
        .asset-pick.is-checked{ border-color:var(--gold-500); background:#FDF9EE; }
        .asset-pick input{ accent-color:var(--gold-500); width:1rem; height:1rem; margin-top:.15rem; }

        .step-dot{ width:1.75rem; height:1.75rem; border-radius:999px; display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:700; flex-shrink:0; }
        .step-dot.is-on{ background:var(--gold-500); color:var(--navy-950); }
        .step-dot.is-off{ background:var(--paper-2); color:var(--ink-400); }

        .pick-row{ display:flex; align-items:center; gap:.625rem; padding:.625rem .75rem; border-radius:10px; cursor:pointer; transition:background-color .15s ease; }
        .pick-row:hover{ background:var(--paper-3); }
        .pick-row.is-selected{ background:var(--gold-100); }

        .flow-arrow{ display:flex; align-items:center; justify-content:center; color:var(--gold-600); }
        .scroll-area{ max-height:60vh; overflow-y:auto; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        input[type=checkbox], input[type=radio]{ accent-color:var(--gold-500); }
    </style>
    @include('partials.ui')
</head>
<body class="nt-ui">
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')

        <div class="flex-1 overflow-y-auto" style="background:var(--paper);">
            @include('admin.partials.header', [
                'adminHeaderPage'     => 'transfer',
                'adminHeaderTitle'    => 'Transfer',
                'adminHeaderIcon'     => 'ri-swap-line',
                'adminHeaderBadge'    => 'Employee Relocation',
            ])

            <div class="p-4 sm:p-8">
                @if(session('success'))
                    <div class="mb-6 px-4 py-3 rounded-xl flex items-start gap-3" style="background:var(--forest-tint); border:1px solid #CFE3D4;">
                        <i class="ri-checkbox-circle-line text-lg mt-0.5" style="color:var(--forest);"></i>
                        <div class="min-w-0">
                            <p class="text-sm font-medium" style="color:var(--forest-dark);">{{ session('success') }}</p>
                            @if(session('transfer_receipt'))
                                <button type="button" class="text-xs font-semibold mt-1 underline" style="color:var(--forest-dark);"
                                        onclick="openHistoryModal({{ (int) session('transfer_receipt')['id'] }})">
                                    View transfer #TR-{{ str_pad(session('transfer_receipt')['id'], 4, '0', STR_PAD_LEFT) }}
                                </button>
                            @endif
                        </div>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-6 px-4 py-3 rounded-xl flex items-start gap-3" style="background:var(--brick-tint); border:1px solid #EFD5D0;">
                        <i class="ri-error-warning-line text-lg mt-0.5" style="color:var(--brick);"></i>
                        <p class="text-sm" style="color:var(--brick-dark);">{{ $errors->first() }}</p>
                    </div>
                @endif

                <!-- Overview -->
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
                    <div class="stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm mb-1" style="color:var(--ink-600);">Employees with assets</p>
                                <p class="text-3xl font-bold" style="color:var(--navy-900);">{{ $stats['employees_with_assets'] }}</p>
                                <p class="text-xs mt-2" style="color:var(--ink-400);">Currently accountable</p>
                            </div>
                            <div class="stat-icon" style="background:var(--steel-tint);">
                                <i class="ri-team-line text-xl" style="color:var(--steel);"></i>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm mb-1" style="color:var(--ink-600);">Assets in custody</p>
                                <p class="text-3xl font-bold" style="color:var(--navy-900);">{{ $stats['assets_assigned'] }}</p>
                                <p class="text-xs mt-2" style="color:var(--ink-400);">Ready to be transferred</p>
                            </div>
                            <div class="stat-icon" style="background:var(--gold-100);">
                                <i class="ri-computer-line text-xl" style="color:var(--gold-600);"></i>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm mb-1" style="color:var(--ink-600);">Completed transfers</p>
                                <p class="text-3xl font-bold" style="color:var(--navy-900);">{{ $stats['transfers_completed'] }}</p>
                                <p class="text-xs mt-2" style="color:var(--ink-400);">All time relocations</p>
                            </div>
                            <div class="stat-icon" style="background:var(--plum-tint);">
                                <i class="ri-swap-line text-xl" style="color:var(--plum);"></i>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm mb-1" style="color:var(--ink-600);">Assets transferred</p>
                                <p class="text-3xl font-bold" style="color:var(--navy-900);">{{ $stats['assets_transferred'] }}</p>
                                <p class="text-xs mt-2" style="color:var(--ink-400);">Across every transfer</p>
                            </div>
                            <div class="stat-icon" style="background:var(--forest-tint);">
                                <i class="ri-arrow-left-right-line text-xl" style="color:var(--forest);"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="panel rounded-t-xl mb-0">
                    <div class="flex space-x-6 sm:space-x-8 px-4 sm:px-6 pt-4 overflow-x-auto scrollbar-hide">
                        <button class="tab-btn pb-3 text-sm font-medium transition whitespace-nowrap" style="color:var(--ink-400);" data-tab="employees">
                            Employees
                        </button>
                        <button class="tab-btn pb-3 text-sm font-medium transition whitespace-nowrap" style="color:var(--ink-400);" data-tab="history">
                            Transfer History
                        </button>
                    </div>
                </div>

                <!-- Employees -->
                <div id="tab-employees" class="panel rounded-b-xl overflow-hidden" style="border-top:0;">
                    <div class="px-4 sm:px-6 py-4 flex flex-wrap items-center gap-2" style="border-bottom:1px solid var(--line);">
                        <span class="eyebrow mr-1">Department</span>
                        <button type="button" class="filter-chip chip-active" data-dept="*">All</button>
                        @foreach($departments as $department)
                            <button type="button" class="filter-chip" data-dept="{{ $department }}">{{ $department }}</button>
                        @endforeach
                    </div>

                    <div class="overflow-x-auto scrollbar-hide">
                        <table class="w-full">
                            <thead class="sticky top-0" style="background:var(--paper-2); border-bottom:1px solid var(--line);">
                                <tr>
                                    <th class="eyebrow text-left py-3 px-3">Employee</th>
                                    <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Employee No.</th>
                                    <th class="eyebrow text-left py-3 px-3">Position / Department</th>
                                    <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Assigned Assets</th>
                                    <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Status</th>
                                    <th class="eyebrow text-right py-3 px-3 whitespace-nowrap">Action</th>
                                </tr>
                            </thead>
                            <tbody id="employees-table-body">
                                @forelse($employees as $employee)
                                    <tr class="employee-row" style="border-bottom:1px solid var(--line);"
                                        data-employee-id="{{ $employee['id'] }}"
                                        data-department="{{ $employee['department'] ?? '' }}"
                                        data-search="{{ strtolower(trim(($employee['name'] ?? '') . ' ' . ($employee['employee_number'] ?? '') . ' ' . ($employee['department'] ?? '') . ' ' . ($employee['role'] ?? ''))) }}"
                                        onclick="openAssetsModal({{ $employee['id'] }})">
                                        <td class="py-3 px-3">
                                            <div class="flex items-center gap-3">
                                                <span class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                                                      style="background:var(--navy-900); color:var(--gold-300);">
                                                    {{ strtoupper(mb_substr($employee['name'] ?? 'U', 0, 1) . (str_contains($employee['name'] ?? '', ' ') ? mb_substr(strrchr($employee['name'], ' '), 1, 1) : '')) }}
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="text-sm font-medium truncate" style="color:var(--navy-900);">{{ $employee['name'] }}</p>
                                                    <p class="text-xs truncate" style="color:var(--ink-400);">{{ $employee['email'] }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-sm font-mono whitespace-nowrap" style="color:var(--ink-600);">{{ $employee['employee_number'] ?? '—' }}</td>
                                        <td class="py-3 px-3 text-sm" style="color:var(--ink-600);">
                                            <span style="color:var(--navy-900);">{{ $employee['role'] ?: 'Employee' }}</span>
                                            <span class="mx-1" style="color:var(--ink-400);">•</span>
                                            {{ $employee['department'] ?? 'No department' }}
                                        </td>
                                        <td class="py-3 px-3 whitespace-nowrap">
                                            @if($employee['assigned_count'] > 0)
                                                <span class="status-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold"
                                                      style="background:var(--steel-tint); color:var(--steel-dark);">
                                                    <i class="ri-computer-line mr-1 text-xs"></i>{{ $employee['assigned_count'] }}
                                                </span>
                                            @else
                                                <span class="text-xs" style="color:var(--ink-400);">None</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 whitespace-nowrap">
                                            @if($employee['status'] === 'reassigned')
                                                <span class="status-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold"
                                                      style="background:var(--gold-100); color:var(--gold-600);"
                                                      title="Has handed assets over in a completed transfer">
                                                    <i class="ri-swap-line mr-1 text-xs"></i>Reassigned
                                                </span>
                                            @else
                                                <span class="status-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold"
                                                      style="background:var(--forest-tint); color:var(--forest-dark);">
                                                    <span class="w-1.5 h-1.5 rounded-full mr-1.5" style="background:var(--forest);"></span>Current
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                                            <button type="button" onclick="openAssetsModal({{ $employee['id'] }})"
                                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                                                    style="background:var(--paper-2); color:var(--navy-900);">
                                                <i class="ri-eye-line mr-1"></i>View
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-12 text-center">
                                            <i class="ri-user-line text-5xl mb-3 block" style="color:var(--gold-500);"></i>
                                            <p style="color:var(--ink-400);">No employees found.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div id="employees-no-match" class="hidden py-12 text-center">
                        <i class="ri-search-line text-4xl mb-3 block" style="color:var(--gold-500);"></i>
                        <p class="text-sm font-medium" style="color:var(--navy-900);">No matching employees</p>
                        <p class="text-xs mt-1" style="color:var(--ink-400);">Try a different name, employee number or department — or clear the search box.</p>
                    </div>
                </div>

                <!-- History -->
                <div id="tab-history" class="panel rounded-b-xl overflow-hidden hidden" style="border-top:0;">
                    <div class="overflow-x-auto scrollbar-hide">
                        <table class="w-full">
                            <thead class="sticky top-0" style="background:var(--paper-2); border-bottom:1px solid var(--line);">
                                <tr>
                                    <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Transfer</th>
                                    <th class="eyebrow text-left py-3 px-3">From → To</th>
                                    <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Assets</th>
                                    <th class="eyebrow text-left py-3 px-3">Reason</th>
                                    <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Date</th>
                                    <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Recorded By</th>
                                    <th class="eyebrow text-right py-3 px-3 whitespace-nowrap">Action</th>
                                </tr>
                            </thead>
                            <tbody id="history-table-body">
                                @forelse($transfers as $transfer)
                                    <tr class="request-row" style="border-bottom:1px solid var(--line);"
                                        data-search="{{ strtolower(trim(($transfer['from']['name'] ?? '') . ' ' . ($transfer['to']['name'] ?? '') . ' ' . ($transfer['reason'] ?? '') . ' ' . $transfer['reference'])) }}">
                                        <td class="py-3 px-3 text-sm font-mono whitespace-nowrap" style="color:var(--navy-900);">{{ $transfer['reference'] }}</td>
                                        <td class="py-3 px-3 text-sm" style="color:var(--ink-600);">
                                            <span style="color:var(--navy-900);">{{ $transfer['from']['name'] ?? 'Unknown' }}</span>
                                            <i class="ri-arrow-right-line mx-1 text-xs" style="color:var(--gold-600);"></i>
                                            <span style="color:var(--navy-900);">{{ $transfer['to']['name'] ?? 'Unknown' }}</span>
                                        </td>
                                        <td class="py-3 px-3 whitespace-nowrap">
                                            <span class="status-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold"
                                                  style="background:var(--steel-tint); color:var(--steel-dark);">
                                                {{ $transfer['asset_count'] }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 text-sm" style="color:var(--ink-600);">{{ $transfer['reason'] ?: '—' }}</td>
                                        <td class="py-3 px-3 text-sm whitespace-nowrap" style="color:var(--ink-600);">
                                            {{ $transfer['date'] ? \Carbon\Carbon::parse($transfer['date'])->format('M d, Y') : '—' }}
                                        </td>
                                        <td class="py-3 px-3 text-sm" style="color:var(--ink-600);">{{ $transfer['recorded_by'] ?? '—' }}</td>
                                        <td class="py-3 px-3 text-right whitespace-nowrap">
                                            <button type="button" onclick="openHistoryModal({{ $transfer['id'] }})"
                                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                                                    style="background:var(--paper-2); color:var(--navy-900);">
                                                <i class="ri-eye-line mr-1"></i>View Details
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-12 text-center">
                                            <i class="ri-swap-line text-5xl mb-3 block" style="color:var(--line);"></i>
                                            <p style="color:var(--ink-400);">No transfers yet.</p>
                                            <p class="text-xs mt-1" style="color:var(--ink-400);">Pick an employee on the Employees tab to hand their assets to someone else.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div id="history-no-match" class="hidden py-12 text-center">
                        <i class="ri-search-line text-4xl mb-3 block" style="color:var(--gold-500);"></i>
                        <p class="text-sm font-medium" style="color:var(--navy-900);">No matching transfers</p>
                        <p class="text-xs mt-1" style="color:var(--ink-400);">Try another employee name, reason or transfer number.</p>
                    </div>
                </div>

                <div class="text-center text-sm mt-10 pt-7" style="color:var(--ink-400); border-top:1px solid var(--line);">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- Step 1: the employee's assigned assets -->
    <div id="assetsModal" class="fixed inset-0 hidden items-center justify-center z-50 p-4" style="background:rgba(10,24,48,.6);" onclick="closeAssetsModal()">
        <div class="panel modal-panel rounded-xl w-full max-w-3xl flex flex-col max-h-[92vh]" onclick="event.stopPropagation();">
            <div class="modal-head px-6 py-5 flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="eyebrow mb-1">Employee Transfer</p>
                    <h3 class="font-display text-lg font-semibold truncate" id="assets-modal-name" style="color:var(--navy-900);">—</h3>
                    <p class="text-sm mt-0.5" id="assets-modal-meta" style="color:var(--ink-400);">—</p>
                </div>
                <button type="button" onclick="closeAssetsModal()" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors flex-shrink-0" style="color:var(--ink-400);">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <div class="px-6 py-4 grid grid-cols-2 sm:grid-cols-3 gap-3" style="border-bottom:1px solid var(--line);">
                <div class="rounded-xl px-3 py-2.5" style="background:var(--paper-3);">
                    <p class="eyebrow mb-0.5">Assigned</p>
                    <p class="text-xl font-bold" id="assets-modal-count" style="color:var(--navy-900);">0</p>
                </div>
                <div class="rounded-xl px-3 py-2.5" style="background:var(--paper-3);">
                    <p class="eyebrow mb-0.5">Status</p>
                    <p class="text-sm font-semibold pt-1" id="assets-modal-status" style="color:var(--navy-900);">—</p>
                </div>
                <div class="rounded-xl px-3 py-2.5 col-span-2 sm:col-span-1" style="background:var(--paper-3);">
                    <p class="eyebrow mb-0.5">Selected</p>
                    <p class="text-xl font-bold" id="assets-modal-selected" style="color:var(--navy-900);">0</p>
                </div>
            </div>

            <div class="px-6 py-4 flex items-center justify-between gap-3" style="border-bottom:1px solid var(--line);">
                <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--ink-600);">
                    <input type="checkbox" id="select-all-assets" onchange="toggleAllAssets(this.checked)">
                    Select all assets
                </label>
                <p class="text-xs" style="color:var(--ink-400);" id="assets-modal-note"></p>
            </div>

            <div class="px-6 py-4 scroll-area flex-1" id="assets-modal-list">
                <!-- filled by JS -->
            </div>

            <div class="px-6 py-5 flex flex-wrap items-center justify-end gap-3" style="border-top:1px solid var(--line);">
                <button type="button" onclick="closeAssetsModal()" class="px-4 py-2.5 rounded-lg text-sm font-medium" style="background:var(--paper-2); color:var(--ink-600);">
                    Cancel
                </button>
                <button type="button" id="transfer-selected-btn" onclick="startTransfer()" class="px-5 py-2.5 rounded-lg text-sm font-semibold" style="background:var(--gold-500); color:var(--navy-950);">
                    <i class="ri-swap-line mr-1.5"></i><span id="transfer-selected-label">Transfer Assets</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Step 2: pick the receiving employee -->
    <div id="receiverModal" class="fixed inset-0 hidden items-center justify-center z-50 p-4" style="background:rgba(10,24,48,.6);" onclick="closeReceiverModal()">
        <div class="panel modal-panel rounded-xl w-full max-w-2xl flex flex-col max-h-[92vh]" onclick="event.stopPropagation();">
            <div class="modal-head px-6 py-5">
                <div class="flex items-center gap-2.5 mb-3">
                    <span class="step-dot is-on">1</span>
                    <span class="text-xs font-semibold" style="color:var(--ink-400);">of 2 — who takes over</span>
                </div>
                <h3 class="font-display text-lg font-semibold" style="color:var(--navy-900);">Transfer Assets</h3>
            </div>

            <div class="px-6 py-4 scroll-area flex-1">
                <div class="rounded-xl px-4 py-3 mb-5 flex items-center justify-between gap-4" style="background:var(--paper-3); border:1px solid var(--line);">
                    <div class="min-w-0">
                        <p class="eyebrow mb-1">From</p>
                        <p class="text-sm font-semibold truncate" id="receiver-from-name" style="color:var(--navy-900);">—</p>
                        <p class="text-xs truncate" id="receiver-from-meta" style="color:var(--ink-400);">—</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="eyebrow mb-1">Assets</p>
                        <p class="text-xl font-bold" id="receiver-asset-count" style="color:var(--navy-900);">0</p>
                    </div>
                </div>

                <label for="receiver-search" class="eyebrow mb-2 block">Transfer to</label>
                <div class="relative">
                    <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-sm" style="color:var(--ink-400);"></i>
                    <input type="text" id="receiver-search" autocomplete="off"
                           placeholder="Search employee by name, number or department..."
                           class="w-full pl-9 pr-3 py-2.5 rounded-lg text-sm"
                           style="border:1px solid var(--line);" oninput="renderReceiverResults(this.value)">
                </div>

                <div id="receiver-results" class="mt-2 space-y-1 scroll-area" style="max-height:15rem;">
                    <!-- filled by JS -->
                </div>

                <div id="receiver-selected" class="hidden mt-3 rounded-xl px-4 py-3 flex items-center gap-3" style="background:var(--gold-100); border:1px solid #EADFC0;">
                    <i class="ri-user-received-line text-lg" style="color:var(--gold-600);"></i>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold truncate" id="receiver-picked-name" style="color:var(--navy-900);">—</p>
                        <p class="text-xs truncate" id="receiver-picked-meta" style="color:var(--ink-600);">—</p>
                    </div>
                    <button type="button" onclick="clearReceiver()" class="text-sm" style="color:var(--brick);" title="Clear">
                        <i class="ri-close-line"></i>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-5">
                    <div>
                        <label for="reason-select" class="eyebrow mb-2 block">Reason</label>
                        <select id="reason-select" class="w-full px-3 py-2.5 rounded-lg text-sm" style="border:1px solid var(--line);">
                            @foreach($reasons as $reason)
                                <option value="{{ $reason }}">{{ $reason }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="transfer-notes" class="eyebrow mb-2 block">Notes <span class="normal-case" style="color:var(--ink-400);">(optional)</span></label>
                        <input type="text" id="transfer-notes" maxlength="1000" placeholder="e.g., Take-over after June 2026 reshuffling"
                               class="w-full px-3 py-2.5 rounded-lg text-sm" style="border:1px solid var(--line);">
                    </div>
                </div>
            </div>

            <div class="px-6 py-5 flex flex-wrap items-center justify-end gap-3" style="border-top:1px solid var(--line);">
                <button type="button" onclick="closeReceiverModal()" class="px-4 py-2.5 rounded-lg text-sm font-medium" style="background:var(--paper-2); color:var(--ink-600);">
                    Cancel
                </button>
                <button type="button" id="continue-btn" onclick="reviewTransfer()" class="px-5 py-2.5 rounded-lg text-sm font-semibold" style="background:var(--gold-500); color:var(--navy-950); opacity:.5;" disabled>
                    Continue <i class="ri-arrow-right-line ml-1"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Step 3: confirm -->
    <div id="confirmModal" class="fixed inset-0 hidden items-center justify-center z-50 p-4" style="background:rgba(10,24,48,.65);" onclick="closeConfirmModal()">
        <div class="panel modal-panel rounded-xl w-full max-w-xl flex flex-col max-h-[92vh]" onclick="event.stopPropagation();">
            <div class="modal-head px-6 py-5">
                <div class="flex items-center gap-2.5 mb-3">
                    <span class="step-dot is-on">2</span>
                    <span class="text-xs font-semibold" style="color:var(--ink-400);">of 2 — confirm</span>
                </div>
                <h3 class="font-display text-lg font-semibold" style="color:var(--navy-900);">Confirm Asset Transfer</h3>
            </div>

            <div class="px-6 py-5 scroll-area flex-1">
                <div class="grid grid-cols-1 sm:grid-cols-3 items-center gap-3">
                    <div class="rounded-xl px-4 py-3" style="background:var(--paper-3); border:1px solid var(--line);">
                        <p class="eyebrow mb-1">From</p>
                        <p class="text-sm font-semibold" id="confirm-from-name" style="color:var(--navy-900);">—</p>
                        <p class="text-xs mt-0.5" id="confirm-from-meta" style="color:var(--ink-400);">—</p>
                    </div>
                    <div class="flow-arrow flex-row sm:flex-col gap-2 py-1">
                        <i class="ri-arrow-down-line hidden sm:block text-xl"></i>
                        <i class="ri-arrow-right-line sm:hidden text-xl"></i>
                        <span class="text-xs font-bold whitespace-nowrap" style="color:var(--gold-600);"><span id="confirm-asset-count">0</span> ASSETS</span>
                    </div>
                    <div class="rounded-xl px-4 py-3" style="background:var(--gold-100); border:1px solid #EADFC0;">
                        <p class="eyebrow mb-1">To</p>
                        <p class="text-sm font-semibold" id="confirm-to-name" style="color:var(--navy-900);">—</p>
                        <p class="text-xs mt-0.5" id="confirm-to-meta" style="color:var(--ink-600);">—</p>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="eyebrow mb-1">Reason</p>
                        <p class="text-sm font-medium" id="confirm-reason" style="color:var(--navy-900);">—</p>
                    </div>
                    <div>
                        <p class="eyebrow mb-1">Notes</p>
                        <p class="text-sm" id="confirm-notes" style="color:var(--ink-600);">—</p>
                    </div>
                </div>

                <div class="mt-5">
                    <p class="eyebrow mb-2">Assets that will move</p>
                    <div id="confirm-asset-list" class="space-y-1 scroll-area" style="max-height:11rem;"></div>
                </div>

                <div class="mt-5 flex items-start gap-2.5 px-3.5 py-3 rounded-xl" style="background:var(--bronze-tint); border:1px solid #EADFC0;">
                    <i class="ri-alert-line text-lg mt-0.5" style="color:var(--bronze);"></i>
                    <p class="text-xs leading-relaxed" style="color:var(--bronze-dark);">
                        The receiving employee becomes responsible for these assets. Their Asset ID, Asset Code, QR Code and history stay the same — only the accountable employee changes.
                    </p>
                </div>
            </div>

            <div class="px-6 py-5 flex flex-wrap items-center justify-end gap-3" style="border-top:1px solid var(--line);">
                <button type="button" onclick="closeConfirmModal()" class="px-4 py-2.5 rounded-lg text-sm font-medium" style="background:var(--paper-2); color:var(--ink-600);">
                    Cancel
                </button>
                <button type="button" id="confirm-transfer-btn" onclick="submitTransfer()" class="px-5 py-2.5 rounded-lg text-sm font-semibold" style="background:var(--gold-500); color:var(--navy-950);">
                    <i class="ri-check-line mr-1.5"></i>Confirm Transfer
                </button>
            </div>
        </div>
    </div>

    <!-- Transfer details (history) -->
    <div id="historyModal" class="fixed inset-0 hidden items-center justify-center z-50 p-4" style="background:rgba(10,24,48,.6);" onclick="closeHistoryModal()">
        <div class="panel modal-panel rounded-xl w-full max-w-2xl flex flex-col max-h-[92vh]" onclick="event.stopPropagation();">
            <div class="modal-head px-6 py-5 flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="eyebrow mb-1">Transfer</p>
                    <h3 class="font-display text-lg font-semibold" id="history-modal-title" style="color:var(--navy-900);">—</h3>
                </div>
                <button type="button" onclick="closeHistoryModal()" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors flex-shrink-0" style="color:var(--ink-400);">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <div class="px-6 py-5 scroll-area flex-1" id="history-modal-body">
                <!-- filled by JS -->
            </div>
            <div class="px-6 py-4 flex justify-end" style="border-top:1px solid var(--line);">
                <button type="button" onclick="closeHistoryModal()" class="px-4 py-2.5 rounded-lg text-sm font-medium" style="background:var(--paper-2); color:var(--ink-600);">Close</button>
            </div>
        </div>
    </div>

    <form id="transferForm" method="POST" action="{{ route('admin.transfer.store') }}" class="hidden">
        @csrf
        <input type="hidden" name="from_user_id" id="form-from-user">
        <input type="hidden" name="to_user_id" id="form-to-user">
        <input type="hidden" name="reason" id="form-reason">
        <input type="hidden" name="notes" id="form-notes">
        <div id="form-asset-inputs"></div>
    </form>

    <script>
        const TRANSFER_EMPLOYEES = @json($employees);
        const TRANSFER_HISTORY  = @json($transfers);
        const OPEN_TRANSFER_ID  = @json($openTransferId);

        let activeEmployee = null;   // employee object from TRANSFER_EMPLOYEES
        let selectedAssetIds = [];   // asset ids ticked in step 1
        let receiverId = null;       // employee id chosen in step 2

        const byId = (id) => document.getElementById(id);
        const employeeById = (id) => TRANSFER_EMPLOYEES.find((e) => Number(e.id) === Number(id)) || null;

        function escapeHtml(value) {
            return String(value ?? '')
                .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
        }

        function formatDate(value) {
            if (!value) return '—';
            const date = new Date(String(value).replace(' ', 'T'));
            if (isNaN(date.getTime())) return String(value);
            return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
        }

        function metaLine(employee) {
            const bits = [];
            if (employee.employee_number) bits.push('Employee No. ' + employee.employee_number);
            if (employee.role) bits.push(employee.role);
            if (employee.department) bits.push(employee.department);
            return bits.join(' • ') || 'No department on record';
        }

        /* ---------------- tabs ---------------- */
        function selectTab(tab) {
            const isHistory = tab === 'history';
            byId('tab-employees').classList.toggle('hidden', isHistory);
            byId('tab-history').classList.toggle('hidden', !isHistory);
            document.querySelectorAll('.tab-btn').forEach((btn) => {
                const on = btn.dataset.tab === tab;
                btn.classList.toggle('tab-active', on);
                btn.style.color = on ? 'var(--navy-900)' : 'var(--ink-400)';
            });
            applySearch(byId('transferSearch')?.value || '');
        }

        /* ---------------- search + department chips ---------------- */
        function activeDepartment() {
            const chip = document.querySelector('.filter-chip.chip-active');
            return chip ? chip.dataset.dept : '*';
        }

        function applySearch(term) {
            const needle = String(term || '').trim().toLowerCase();
            const department = activeDepartment();

            let employeeHits = 0;
            document.querySelectorAll('#employees-table-body .employee-row').forEach((row) => {
                const matchesSearch = !needle || (row.dataset.search || '').includes(needle);
                const matchesDept = department === '*' || row.dataset.department === department;
                const visible = matchesSearch && matchesDept;
                row.classList.toggle('is-dimmed', !visible);
                if (visible) employeeHits++;
            });
            byId('employees-no-match').classList.toggle('hidden', employeeHits > 0);

            let historyHits = 0;
            document.querySelectorAll('#history-table-body tr').forEach((row) => {
                if (!row.dataset.search) return;
                const visible = !needle || row.dataset.search.includes(needle);
                row.style.display = visible ? '' : 'none';
                if (visible) historyHits++;
            });
            byId('history-no-match').classList.toggle('hidden', historyHits > 0);
        }

        /* ---------------- step 1: the employee's assets ---------------- */
        function openAssetsModal(employeeId) {
            const employee = employeeById(employeeId);
            if (!employee) return;

            activeEmployee = employee;
            receiverId = null;
            selectedAssetIds = employee.assets.map((asset) => Number(asset.id));

            byId('assets-modal-name').textContent = employee.name;
            byId('assets-modal-meta').textContent = metaLine(employee);
            byId('assets-modal-count').textContent = employee.assigned_count;
            byId('assets-modal-status').innerHTML = employee.status === 'reassigned'
                ? '<span style="color:var(--gold-600);">Reassigned</span>'
                : '<span style="color:var(--forest-dark);">Current</span>';

            let note = 'Every assigned asset is ticked. Untick any asset that stays behind.';
            if (employee.not_transferable_count > 0) {
                note += ' ' + employee.not_transferable_count + ' asset(s) already left custody (pulled out or disposed) and are not listed.';
            }
            byId('assets-modal-note').textContent = note;

            renderAssetPicks();
            byId('assetsModal').classList.remove('hidden');
            byId('assetsModal').classList.add('flex');
        }

        function closeAssetsModal() {
            byId('assetsModal').classList.add('hidden');
            byId('assetsModal').classList.remove('flex');
        }

        function renderAssetPicks() {
            const list = byId('assets-modal-list');
            const assets = activeEmployee ? activeEmployee.assets : [];

            if (!assets.length) {
                list.innerHTML = `
                    <div class="py-10 text-center">
                        <i class="ri-inbox-line text-4xl mb-3 block" style="color:var(--gold-500);"></i>
                        <p class="text-sm font-medium" style="color:var(--navy-900);">No assets are assigned to this employee</p>
                        <p class="text-xs mt-1" style="color:var(--ink-400);">Nothing to transfer. Assets appear here once they are assigned to this employee.</p>
                    </div>`;
                updateSelectedCount();
                return;
            }

            list.innerHTML = assets.map((asset) => {
                const checked = selectedAssetIds.includes(Number(asset.id));
                return `
                    <label class="asset-pick mb-2 ${checked ? 'is-checked' : ''}" data-asset-id="${asset.id}">
                        <input type="checkbox" ${checked ? 'checked' : ''} onchange="toggleAsset(${asset.id}, this.checked)">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-mono" style="color:var(--navy-900);">${escapeHtml(asset.code)}</span>
                                <span class="text-sm font-medium" style="color:var(--ink-900);">${escapeHtml(asset.name)}</span>
                            </div>
                            <p class="text-xs mt-1" style="color:var(--ink-400);">
                                ${escapeHtml(asset.category || 'Uncategorised')}
                                ${asset.location ? ' • ' + escapeHtml(asset.location) : ''}
                                ${asset.lifecycle ? ' • ' + escapeHtml(asset.lifecycle) : ''}
                            </p>
                        </div>
                    </label>`;
            }).join('');

            byId('select-all-assets').checked = selectedAssetIds.length === assets.length;
            updateSelectedCount();
        }

        function toggleAsset(assetId, checked) {
            const id = Number(assetId);
            selectedAssetIds = checked
                ? Array.from(new Set([...selectedAssetIds, id]))
                : selectedAssetIds.filter((x) => x !== id);

            const pick = document.querySelector(`.asset-pick[data-asset-id="${id}"]`);
            if (pick) pick.classList.toggle('is-checked', checked);

            const total = activeEmployee ? activeEmployee.assets.length : 0;
            byId('select-all-assets').checked = total > 0 && selectedAssetIds.length === total;
            updateSelectedCount();
        }

        function toggleAllAssets(checked) {
            selectedAssetIds = checked ? activeEmployee.assets.map((a) => Number(a.id)) : [];
            renderAssetPicks();
        }

        function updateSelectedCount() {
            byId('assets-modal-selected').textContent = selectedAssetIds.length;
            const btn = byId('transfer-selected-btn');
            const label = byId('transfer-selected-label');
            const n = selectedAssetIds.length;

            btn.disabled = n === 0;
            btn.style.opacity = n === 0 ? '.5' : '1';
            label.textContent = n === 0
                ? 'Transfer Assets'
                : `Transfer ${n} Asset${n === 1 ? '' : 's'}`;
        }

        /* ---------------- step 2: receiving employee ---------------- */
        function startTransfer() {
            if (!activeEmployee || !selectedAssetIds.length) return;

            byId('receiver-from-name').textContent = activeEmployee.name;
            byId('receiver-from-meta').textContent = metaLine(activeEmployee);
            byId('receiver-asset-count').textContent = selectedAssetIds.length;
            byId('receiver-search').value = '';
            clearReceiver();
            renderReceiverResults('');

            closeAssetsModal();
            byId('receiverModal').classList.remove('hidden');
            byId('receiverModal').classList.add('flex');
            setTimeout(() => byId('receiver-search').focus(), 80);
        }

        function closeReceiverModal() {
            byId('receiverModal').classList.add('hidden');
            byId('receiverModal').classList.remove('flex');
        }

        function renderReceiverResults(term) {
            const needle = String(term || '').trim().toLowerCase();
            const box = byId('receiver-results');

            const matches = TRANSFER_EMPLOYEES.filter((employee) => {
                if (!activeEmployee || Number(employee.id) === Number(activeEmployee.id)) return false;
                if (!needle) return true;
                return metaLine(employee).toLowerCase().includes(needle)
                    || String(employee.name || '').toLowerCase().includes(needle)
                    || String(employee.email || '').toLowerCase().includes(needle);
            });

            if (!matches.length) {
                box.innerHTML = '<p class="text-xs px-3 py-3" style="color:var(--ink-400);">No employee matches that search.</p>';
                return;
            }

            box.innerHTML = matches.map((employee) => `
                <div class="pick-row ${Number(employee.id) === Number(receiverId) ? 'is-selected' : ''}"
                     data-receiver-id="${employee.id}" onclick="pickReceiver(${employee.id})">
                    <span class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                          style="background:var(--navy-900); color:var(--gold-300);">${escapeHtml((employee.name || 'U').substring(0, 1).toUpperCase())}</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium truncate" style="color:var(--navy-900);">${escapeHtml(employee.name)}</p>
                        <p class="text-xs truncate" style="color:var(--ink-400);">${escapeHtml(metaLine(employee))}</p>
                    </div>
                    <span class="text-xs whitespace-nowrap" style="color:var(--ink-400);">${employee.assigned_count} asset(s)</span>
                </div>`).join('');
        }

        function pickReceiver(employeeId) {
            receiverId = Number(employeeId);
            const employee = employeeById(receiverId);
            if (!employee) return;

            byId('receiver-picked-name').textContent = employee.name;
            byId('receiver-picked-meta').textContent = metaLine(employee);
            byId('receiver-selected').classList.remove('hidden');

            document.querySelectorAll('#receiver-results .pick-row').forEach((row) => {
                row.classList.toggle('is-selected', Number(row.dataset.receiverId) === receiverId);
            });

            updateContinueButton();
        }

        function clearReceiver() {
            receiverId = null;
            byId('receiver-selected').classList.add('hidden');
            document.querySelectorAll('#receiver-results .pick-row').forEach((row) => row.classList.remove('is-selected'));
            updateContinueButton();
        }

        function updateContinueButton() {
            const btn = byId('continue-btn');
            const ready = !!receiverId && !!byId('reason-select').value;
            btn.disabled = !ready;
            btn.style.opacity = ready ? '1' : '.5';
        }

        /* ---------------- step 3: confirm ---------------- */
        function reviewedAssets() {
            if (!activeEmployee) return [];
            return activeEmployee.assets.filter((asset) => selectedAssetIds.includes(Number(asset.id)));
        }

        function reviewTransfer() {
            if (!activeEmployee || !receiverId) return;

            const receiver = employeeById(receiverId);
            const assets = reviewedAssets();

            byId('confirm-from-name').textContent = activeEmployee.name;
            byId('confirm-from-meta').textContent = metaLine(activeEmployee);
            byId('confirm-to-name').textContent = receiver.name;
            byId('confirm-to-meta').textContent = metaLine(receiver);
            byId('confirm-asset-count').textContent = assets.length;
            byId('confirm-reason').textContent = byId('reason-select').value;
            byId('confirm-notes').textContent = byId('transfer-notes').value.trim() || '—';

            byId('confirm-asset-list').innerHTML = assets.map((asset) => `
                <div class="flex items-center gap-2 px-3 py-2 rounded-lg" style="background:var(--paper-3);">
                    <i class="ri-arrow-right-s-line" style="color:var(--gold-600);"></i>
                    <span class="text-xs font-mono" style="color:var(--navy-900);">${escapeHtml(asset.code)}</span>
                    <span class="text-xs" style="color:var(--ink-600);">${escapeHtml(asset.name)}</span>
                </div>`).join('');

            closeReceiverModal();
            byId('confirmModal').classList.remove('hidden');
            byId('confirmModal').classList.add('flex');
        }

        function closeConfirmModal() {
            byId('confirmModal').classList.add('hidden');
            byId('confirmModal').classList.remove('flex');
        }

        function submitTransfer() {
            if (!activeEmployee || !receiverId || !selectedAssetIds.length) return;

            const btn = byId('confirm-transfer-btn');
            btn.disabled = true;
            btn.style.opacity = '.6';
            btn.innerHTML = '<i class="ri-loader-4-line mr-1.5 animate-spin inline-block"></i>Transferring...';

            byId('form-from-user').value = activeEmployee.id;
            byId('form-to-user').value = receiverId;
            byId('form-reason').value = byId('reason-select').value;
            byId('form-notes').value = byId('transfer-notes').value.trim();
            byId('form-asset-inputs').innerHTML = selectedAssetIds
                .map((id) => `<input type="hidden" name="asset_ids[]" value="${id}">`).join('');

            byId('transferForm').submit();
        }

        /* ---------------- history details ---------------- */
        function openHistoryModal(transferId) {
            const transfer = TRANSFER_HISTORY.find((t) => Number(t.id) === Number(transferId));
            if (!transfer) return;

            byId('history-modal-title').textContent = 'Transfer ' + transfer.reference + ' — ' + (transfer.status || 'Completed');

            const assetRows = (transfer.assets || []).map((asset) => `
                <div class="flex items-center gap-2 px-3 py-2 rounded-lg" style="background:var(--paper-3);">
                    <span class="text-xs font-mono" style="color:var(--navy-900);">${escapeHtml(asset.code)}</span>
                    <span class="text-xs" style="color:var(--ink-600);">${escapeHtml(asset.name)}</span>
                </div>`).join('') || '<p class="text-xs" style="color:var(--ink-400);">No assets recorded on this transfer.</p>';

            byId('history-modal-body').innerHTML = `
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                    <div class="rounded-xl px-4 py-3" style="background:var(--paper-3);">
                        <p class="eyebrow mb-1">Previous employee</p>
                        <p class="text-sm font-semibold" style="color:var(--navy-900);">${escapeHtml(transfer.from?.name || 'Unknown')}</p>
                    </div>
                    <div class="rounded-xl px-4 py-3" style="background:var(--gold-100);">
                        <p class="eyebrow mb-1">New employee</p>
                        <p class="text-sm font-semibold" style="color:var(--navy-900);">${escapeHtml(transfer.to?.name || 'Unknown')}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
                    <div><p class="eyebrow mb-1">Assets</p><p class="text-sm font-semibold" style="color:var(--navy-900);">${transfer.asset_count}</p></div>
                    <div><p class="eyebrow mb-1">Reason</p><p class="text-sm" style="color:var(--ink-600);">${escapeHtml(transfer.reason || '—')}</p></div>
                    <div><p class="eyebrow mb-1">Date</p><p class="text-sm" style="color:var(--ink-600);">${escapeHtml(formatDate(transfer.date))}</p></div>
                    <div><p class="eyebrow mb-1">Recorded by</p><p class="text-sm" style="color:var(--ink-600);">${escapeHtml(transfer.recorded_by || '—')}</p></div>
                </div>
                ${transfer.notes ? `<div class="mb-5"><p class="eyebrow mb-1">Notes</p><p class="text-sm" style="color:var(--ink-600);">${escapeHtml(transfer.notes)}</p></div>` : ''}
                <p class="eyebrow mb-2">Assets transferred</p>
                <div class="space-y-1">${assetRows}</div>`;

            byId('historyModal').classList.remove('hidden');
            byId('historyModal').classList.add('flex');
        }

        function closeHistoryModal() {
            byId('historyModal').classList.add('hidden');
            byId('historyModal').classList.remove('flex');
        }

        /* ---------------- wiring ---------------- */
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.tab-btn').forEach((btn) => {
                btn.addEventListener('click', () => selectTab(btn.dataset.tab));
            });

            document.querySelectorAll('.filter-chip').forEach((chip) => {
                chip.addEventListener('click', () => {
                    document.querySelectorAll('.filter-chip').forEach((c) => c.classList.remove('chip-active'));
                    chip.classList.add('chip-active');
                    applySearch(byId('transferSearch')?.value || '');
                });
            });

            byId('transferSearch')?.addEventListener('input', (event) => applySearch(event.target.value));
            byId('reason-select')?.addEventListener('change', updateContinueButton);

            // History rows stay searchable while the search box lives in the page header.
            selectTab('employees');

            if (OPEN_TRANSFER_ID) {
                selectTab('history');
                openHistoryModal(OPEN_TRANSFER_ID);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            closeConfirmModal();
            closeReceiverModal();
            closeAssetsModal();
            closeHistoryModal();
        });
    </script>
</body>
</html>
