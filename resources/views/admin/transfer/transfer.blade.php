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
            --gold-500:#C9A227; --gold-600:#A8841E; --gold-ink:#7E5E0E; --gold-300:#E9C766; --gold-100:#F3E7C4;
            --paper:#F3EEE0; --paper-2:#EAE2C9; --paper-3:#F5F0E2;
            --ink-900:#1A2233; --ink-600:#4B5468; --ink-400:#5C6474;
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

        .flow-arrow{ display:flex; align-items:center; justify-content:center; color:var(--gold-ink); }
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

<main class="flex-1 overflow-y-auto" style="background:var(--paper);">
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

                @if(session('notice'))
                    <div class="mb-6 px-4 py-3 rounded-xl flex items-start gap-3" style="background:var(--steel-tint); border:1px solid #CFDCE8;">
                        <i class="ri-information-line text-lg mt-0.5" style="color:var(--steel);"></i>
                        <div class="min-w-0">
                            <p class="text-sm font-medium" style="color:var(--steel);">{{ session('notice') }}</p>
                            @if(session('transfer_receipt'))
                                <button type="button" class="text-xs font-semibold mt-1 underline" style="color:var(--steel);"
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
                                <i class="ri-computer-line text-xl" style="color:var(--gold-ink);"></i>
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
                        <button class="tab-btn pb-3 text-sm font-medium transition whitespace-nowrap" style="color:var(--ink-400);" data-tab="executive">
                            Executive Review
                            @if($awaitingReviews > 0)
                                <span class="ml-1 inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[.65rem] font-bold align-middle"
                                      style="background:var(--gold-500); color:var(--navy-950);"
                                      title="Waiting on the {{ $reviewDefaults['recipient'] }}">{{ $awaitingReviews }}</span>
                            @elseif(count($executiveReviews) > 0)
                                <span class="ml-1 inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[.65rem] font-bold align-middle"
                                      style="background:var(--paper-2); color:var(--ink-600);">{{ count($executiveReviews) }}</span>
                            @endif
                        </button>
                    </div>
                </div>

                <!-- Employees -->
                <div id="tab-employees" class="panel rounded-b-xl overflow-hidden" style="border-top:0;">
                    <div class="px-4 sm:px-6 py-4 flex flex-wrap items-center gap-2" style="border-bottom:1px solid var(--line);">
                        <span class="eyebrow mr-1">Department</span>
                        {{-- Links, not buttons: the filter has to reach every employee,
                             including the ones on the pages that are not on screen. --}}
                        <a href="{{ url('/admin/transfer?' . http_build_query(array_filter(['tab' => 'employees', 'q' => $search ?: null]))) }}"
                           class="filter-chip {{ $department === '' ? 'chip-active' : '' }}" data-dept="*">All</a>
                        @foreach($departments as $dept)
                            <a href="{{ url('/admin/transfer?' . http_build_query(array_filter(['tab' => 'employees', 'q' => $search ?: null, 'department' => $dept]))) }}"
                               class="filter-chip {{ $department === $dept ? 'chip-active' : '' }}" data-dept="{{ $dept }}">{{ $dept }}</a>
                        @endforeach
                    </div>

                    <div class="overflow-x-auto scrollbar-hide">
                        <table class="w-full">
                            <thead class="sticky top-0" style="background:var(--paper-2); border-bottom:1px solid var(--line);">
                                <tr>
                                    <th class="eyebrow text-left py-3 px-3">Employee</th>
                                    {{-- The employee number and the department column move out of the way on a
                                     phone: the name is right there, the search box finds people by
                                     number, and the assets modal repeats both. --}}
                                    <th class="eyebrow text-left py-3 px-3 whitespace-nowrap hidden lg:table-cell">Employee No.</th>
                                    <th class="eyebrow text-left py-3 px-3 hidden xl:table-cell">Position / Department</th>
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
                                                    {{-- Wraps on a phone, ellipsised from sm up: a truncating cell is what
                                                        forces this table wider than the screen. --}}
                                                    <p class="text-sm font-medium break-words sm:truncate" style="color:var(--navy-900);">{{ $employee['name'] }}</p>
                                                    <p class="text-xs break-words sm:truncate" style="color:var(--ink-400);">{{ $employee['email'] }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-sm font-mono whitespace-nowrap hidden lg:table-cell" style="color:var(--ink-600);">{{ $employee['employee_number'] ?? '—' }}</td>
                                        <td class="py-3 px-3 text-sm hidden xl:table-cell" style="color:var(--ink-600);">
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
                                                      style="background:var(--gold-100); color:var(--gold-ink);"
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

                    @if($employees->hasPages())
                        <div class="px-4 sm:px-6 py-4 flex flex-wrap items-center justify-between gap-3" style="border-top:1px solid var(--line);">
                            <p class="text-xs" style="color:var(--ink-400);">
                                Showing {{ $employees->firstItem() }}–{{ $employees->lastItem() }} of {{ $employees->total() }} employees
                            </p>
                            <div>{{ $employees->appends(['tab' => 'employees'])->links() }}</div>
                        </div>
                    @endif
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
                                            <i class="ri-arrow-right-line mx-1 text-xs" style="color:var(--gold-ink);"></i>
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

                    @if($transfers->hasPages())
                        <div class="px-4 sm:px-6 py-4 flex flex-wrap items-center justify-between gap-3" style="border-top:1px solid var(--line);">
                            <p class="text-xs" style="color:var(--ink-400);">
                                Showing {{ $transfers->firstItem() }}–{{ $transfers->lastItem() }} of {{ $transfers->total() }} transfers
                            </p>
                            <div>{{ $transfers->appends(['tab' => 'history'])->links() }}</div>
                        </div>
                    @endif
                </div>

                <!-- Executive Review — every transfer the Executive Director was emailed about -->
                <div id="tab-executive" class="panel rounded-b-xl overflow-hidden hidden" style="border-top:0;">
                    <div class="px-4 sm:px-6 py-4" style="border-bottom:1px solid var(--line); background:var(--paper-3);">
                        <div class="flex items-start gap-3">
                            <i class="ri-shield-star-line text-lg mt-0.5 flex-shrink-0" style="color:var(--gold-ink);"></i>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold" style="color:var(--navy-900);">Transfers sent to the {{ $reviewDefaults['recipient'] }}</p>
                                <p class="text-xs mt-1 leading-relaxed" style="color:var(--ink-600);">
                                    Everything the office approved and emailed for a final decision — the transfers sent from this page
                                    and the ones forwarded from Requests. The assets stay with the employee holding them until a decision
                                    is recorded, and the review link exists only in the email the {{ $reviewDefaults['recipient'] }} received.
                                </p>
                            </div>
                        </div>
                    </div>

                    @if(! $reviewDefaults['tables_ready'])
                        <div class="px-6 py-10 text-center">
                            <i class="ri-database-2-line text-4xl mb-3 block" style="color:var(--bronze);"></i>
                            <p class="text-sm font-medium" style="color:var(--navy-900);">The transfer review tables have not been created on this server yet</p>
                            <p class="text-xs mt-1" style="color:var(--ink-400);">Run the database migration, then reload this page — until then a transfer cannot be sent to the {{ $reviewDefaults['recipient'] }}.</p>
                        </div>
                    @else
                    <div class="overflow-x-auto scrollbar-hide">
                        <table class="w-full">
                            <thead class="sticky top-0" style="background:var(--paper-2); border-bottom:1px solid var(--line);">
                                <tr>
                                    <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Transfer</th>
                                    <th class="eyebrow text-left py-3 px-3">From → To</th>
                                    <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Assets</th>
                                    <th class="eyebrow text-left py-3 px-3 hidden lg:table-cell">Sent to</th>
                                    <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Status</th>
                                    <th class="eyebrow text-left py-3 px-3 whitespace-nowrap hidden xl:table-cell">Sent</th>
                                    <th class="eyebrow text-right py-3 px-3 whitespace-nowrap">Action</th>
                                </tr>
                            </thead>
                            <tbody id="executive-table-body">
                                @forelse($executiveReviews as $review)
                                    @php
                                        $reviewTone = match (true) {
                                            $review['awaiting'] => ['bg' => 'var(--gold-100)', 'ink' => 'var(--gold-ink)', 'label' => 'Awaiting decision'],
                                            $review['decided'] && $review['decision'] === 'rejected' => ['bg' => 'var(--brick-tint)', 'ink' => 'var(--brick-dark)', 'label' => 'Rejected'],
                                            $review['decided'] && $review['decision'] === 'partially_approved' => ['bg' => 'var(--plum-tint)', 'ink' => 'var(--plum-dark)', 'label' => 'Partially approved'],
                                            $review['decided'] => ['bg' => 'var(--forest-tint)', 'ink' => 'var(--forest-dark)', 'label' => 'Approved'],
                                            $review['status'] === 'revoked' => ['bg' => 'var(--paper-2)', 'ink' => 'var(--ink-600)', 'label' => 'Withdrawn'],
                                            default => ['bg' => 'var(--bronze-tint)', 'ink' => 'var(--bronze-dark)', 'label' => 'Link expired'],
                                        };
                                    @endphp
                                    <tr class="request-row" style="border-bottom:1px solid var(--line);"
                                        data-search="{{ strtolower(trim($review['reference'] . ' ' . ($review['from']['name'] ?? '') . ' ' . ($review['to']['name'] ?? '') . ' ' . ($review['recipient_email'] ?? '') . ' ' . ($review['reason'] ?? '') . ' ' . $review['decision_label'] . ' ' . implode(' ', array_column($review['assets'], 'code')))) }}">
                                        <td class="py-3 px-3 whitespace-nowrap">
                                            <p class="text-sm font-mono" style="color:var(--navy-900);">{{ $review['reference'] }}</p>
                                            <p class="text-[.65rem] mt-0.5 uppercase tracking-wider" style="color:var(--ink-400);">
                                                {{ $review['origin'] === 'transfer_page' ? 'Prepared here' : 'Employee request' }}
                                            </p>
                                        </td>
                                        <td class="py-3 px-3 text-sm" style="color:var(--ink-600);">
                                            <span style="color:var(--navy-900);">{{ $review['from']['name'] ?? 'Unknown' }}</span>
                                            <i class="ri-arrow-right-line mx-1 text-xs" style="color:var(--gold-ink);"></i>
                                            <span style="color:var(--navy-900);">{{ $review['to']['name'] ?? 'Unknown' }}</span>
                                        </td>
                                        <td class="py-3 px-3 whitespace-nowrap">
                                            <span class="status-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold"
                                                  style="background:var(--steel-tint); color:var(--steel-dark);">
                                                {{ $review['asset_count'] }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 text-sm hidden lg:table-cell" style="color:var(--ink-600);">
                                            <span class="truncate inline-block max-w-[14rem] align-bottom">{{ $review['recipient_email'] ?: '—' }}</span>
                                        </td>
                                        <td class="py-3 px-3 whitespace-nowrap">
                                            <span class="status-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold"
                                                  style="background:{{ $reviewTone['bg'] }}; color:{{ $reviewTone['ink'] }};"
                                                  title="{{ $review['status_label'] }} — {{ $review['status_hint'] }}">
                                                <span class="w-1.5 h-1.5 rounded-full mr-1.5" style="background:currentColor;"></span>{{ $reviewTone['label'] }}
                                            </span>
                                            @if($review['outcome'])
                                                <p class="text-xs mt-1 max-w-[18rem]" style="color:var(--ink-400);">{{ $review['outcome'] }}</p>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-sm whitespace-nowrap hidden xl:table-cell" style="color:var(--ink-600);">{{ $review['sent_at'] ?: '—' }}</td>
                                        <td class="py-3 px-3 text-right whitespace-nowrap">
                                            <button type="button" onclick="openExecutiveModal({{ $review['id'] }})"
                                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                                                    style="background:var(--paper-2); color:var(--navy-900);">
                                                <i class="ri-eye-line mr-1"></i>View Details
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-12 text-center">
                                            <i class="ri-shield-star-line text-5xl mb-3 block" style="color:var(--line);"></i>
                                            <p style="color:var(--ink-400);">No transfer has been sent to the {{ $reviewDefaults['recipient'] }} yet.</p>
                                            <p class="text-xs mt-1" style="color:var(--ink-400);">On the Employees tab, pick the assets, choose the receiving employee, and send the confirmation for approval instead of completing it.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div id="executive-no-match" class="hidden py-12 text-center">
                        <i class="ri-search-line text-4xl mb-3 block" style="color:var(--gold-500);"></i>
                        <p class="text-sm font-medium" style="color:var(--navy-900);">No matching reviews</p>
                        <p class="text-xs mt-1" style="color:var(--ink-400);">Try another employee name, transfer number or email address.</p>
                    </div>
                    @endif
                </div>

                <div class="text-center text-sm mt-10 pt-7" style="color:var(--ink-400); border-top:1px solid var(--line);">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </main>
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
                <button type="button" onclick="closeAssetsModal()" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors flex-shrink-0" style="color:var(--ink-400);" aria-label="Close">
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
                    <i class="ri-user-received-line text-lg" style="color:var(--gold-ink);"></i>
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
                        <span class="text-xs font-bold whitespace-nowrap" style="color:var(--gold-ink);"><span id="confirm-asset-count">0</span> ASSETS</span>
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
                    <p class="eyebrow mb-2">Who completes this transfer?</p>
                    <div class="space-y-2">
                        <label class="asset-pick" id="mode-direct-label">
                            <input type="radio" name="transfer_mode" value="direct" checked onchange="updateTransferMode()">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium" style="color:var(--navy-900);">Complete it now</p>
                                <p class="text-xs mt-0.5" style="color:var(--ink-400);">The assets change custodian the moment you confirm. No further approval is needed.</p>
                            </div>
                        </label>
                        <label class="asset-pick {{ $reviewDefaults['tables_ready'] ? '' : 'opacity-60' }}" id="mode-review-label">
                            <input type="radio" name="transfer_mode" value="review" onchange="updateTransferMode()"
                                   {{ $reviewDefaults['tables_ready'] ? '' : 'disabled' }}>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium" style="color:var(--navy-900);">Send to the {{ $reviewDefaults['recipient'] }} for approval</p>
                                <p class="text-xs mt-0.5" style="color:var(--ink-400);">
                                    @if($reviewDefaults['tables_ready'])
                                        The transfer is held: nothing changes hands until the decision comes back on the emailed review page.
                                    @else
                                        Unavailable until the transfer review tables are created on this server — run the database migration first.
                                    @endif
                                </p>
                            </div>
                        </label>
                    </div>
                </div>

                <div id="review-options" class="hidden mt-4 rounded-xl px-4 py-3" style="background:var(--gold-100); border:1px solid #EADFC0;">
                    <label for="review-recipient" class="eyebrow mb-1 block">Executive Director's email address</label>
                    <input type="email" id="review-recipient" autocomplete="off"
                           placeholder="executive.director@nu-lipa.edu.ph"
                           value="{{ $reviewDefaults['default_recipient'] }}"
                           class="w-full px-3 py-2.5 rounded-lg text-sm" style="border:1px solid var(--line);">
                    <p class="text-xs mt-2" style="color:var(--gold-ink);">
                        The review link and its 6-digit verification code are emailed here, and the only mailbox that can submit a decision.
                        The link stops working after {{ $reviewDefaults['link_lifetime'] }} — or the moment you withdraw it on the Executive Review tab.
                    </p>
                    <p class="text-xs mt-2 font-medium" id="review-mode-warning" style="color:var(--bronze-dark);"></p>
                </div>

                <div class="mt-5">
                    <p class="eyebrow mb-2">Assets named on this transfer</p>
                    <div id="confirm-asset-list" class="space-y-1 scroll-area" style="max-height:11rem;"></div>
                </div>

                <div class="mt-5 flex items-start gap-2.5 px-3.5 py-3 rounded-xl" style="background:var(--bronze-tint); border:1px solid #EADFC0;">
                    <i class="ri-alert-line text-lg mt-0.5 flex-shrink-0" style="color:var(--bronze);"></i>
                    <p class="text-xs leading-relaxed" id="confirm-warning" style="color:var(--bronze-dark);">
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

    <!-- Executive Director review details — the same facts the email link carries -->
    <div id="executiveModal" class="fixed inset-0 hidden items-center justify-center z-50 p-4" style="background:rgba(10,24,48,.6);" onclick="closeExecutiveModal()">
        <div class="panel modal-panel rounded-xl w-full max-w-3xl flex flex-col max-h-[92vh]" onclick="event.stopPropagation();">
            <div class="modal-head px-6 py-5 flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="eyebrow mb-1">Executive Director review</p>
                    <h3 class="font-display text-lg font-semibold truncate" id="executive-modal-title" style="color:var(--navy-900);">—</h3>
                    <p class="text-sm mt-0.5" id="executive-modal-sub" style="color:var(--ink-400);">—</p>
                </div>
                <button type="button" onclick="closeExecutiveModal()" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors flex-shrink-0" style="color:var(--ink-400);" aria-label="Close">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <div class="px-6 py-5 scroll-area flex-1" id="executive-modal-body">
                <!-- filled by JS -->
            </div>
            <div class="px-6 py-4 flex flex-wrap items-center justify-end gap-3" style="border-top:1px solid var(--line);" id="executive-modal-actions">
                <!-- filled by JS -->
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
                <button type="button" onclick="closeHistoryModal()" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors flex-shrink-0" style="color:var(--ink-400);" aria-label="Close">
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
        <input type="hidden" name="send_for_review" id="form-send-for-review" value="0">
        <input type="hidden" name="review_recipient_email" id="form-review-email">
        <div id="form-asset-inputs"></div>
    </form>

    <script>
        // The whole lists, not the current page: a modal has to be able to open
        // an employee or a transfer that is on another page.
        const TRANSFER_EMPLOYEES = @json($allEmployees);
        const TRANSFER_HISTORY  = @json($allTransfers);
        const ACTIVE_TAB        = @json($activeTab);
        const TRANSFER_REVIEWS  = @json($executiveReviews);
        const REVIEW_TABLES_READY = @json($reviewDefaults['tables_ready']);
        const REVIEW_RECIPIENT  = @json($reviewDefaults['recipient']);
        const OPEN_TRANSFER_ID  = @json($openTransferId);
        const OPEN_REVIEW_ID    = @json($openReviewId);
        const CSRF_TOKEN        = document.querySelector('meta[name="csrf-token"]')?.content || '';

        let activeEmployee = null;   // employee object from TRANSFER_EMPLOYEES
        let selectedAssetIds = [];   // asset ids ticked in step 1
        let receiverId = null;       // employee id chosen in step 2
        let submitted = false;       // the confirmation has already been sent once

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
            ['employees', 'history', 'executive'].forEach((name) => {
                byId('tab-' + name)?.classList.toggle('hidden', name !== tab);
            });
            document.querySelectorAll('.tab-btn').forEach((btn) => {
                const on = btn.dataset.tab === tab;
                btn.classList.toggle('tab-active', on);
                btn.style.color = on ? 'var(--navy-900)' : 'var(--ink-400)';
            });
            applySearch(byId('transferSearch')?.value || '');

            // The tab lives in the URL, so a search, a department filter or a
            // page link comes back to the tab it was used on instead of
            // silently returning to the first one.
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tab);
                window.history.replaceState({}, '', url);
            } catch (e) {
                /* an opaque origin: the tab still works, only the URL lacks it */
            }

            // The search box posts the tab it was used on, which is not
            // necessarily the one the page was loaded with.
            const tabField = document.querySelector('input[name="tab"]');
            if (tabField) tabField.value = tab;
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

            let reviewHits = 0;
            document.querySelectorAll('#executive-table-body tr').forEach((row) => {
                if (!row.dataset.search) return;
                const visible = !needle || row.dataset.search.includes(needle);
                row.style.display = visible ? '' : 'none';
                if (visible) reviewHits++;
            });
            byId('executive-no-match')?.classList.toggle('hidden', reviewHits > 0);
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
                ? '<span style="color:var(--gold-ink);">Reassigned</span>'
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
                    <i class="ri-arrow-right-s-line" style="color:var(--gold-ink);"></i>
                    <span class="text-xs font-mono" style="color:var(--navy-900);">${escapeHtml(asset.code)}</span>
                    <span class="text-xs" style="color:var(--ink-600);">${escapeHtml(asset.name)}</span>
                </div>`).join('');

            closeReceiverModal();
            updateTransferMode();
            byId('confirmModal').classList.remove('hidden');
            byId('confirmModal').classList.add('flex');
        }

        function closeConfirmModal() {
            byId('confirmModal').classList.add('hidden');
            byId('confirmModal').classList.remove('flex');
        }

        /* ---------------- complete now, or ask the Executive Director ---------------- */
        function transferMode() {
            const chosen = document.querySelector('input[name="transfer_mode"]:checked');
            return chosen ? chosen.value : 'direct';
        }

        function updateTransferMode() {
            const review = transferMode() === 'review';

            byId('review-options').classList.toggle('hidden', !review);
            byId('mode-direct-label').classList.toggle('is-checked', !review);
            byId('mode-review-label').classList.toggle('is-checked', review);

            const from = activeEmployee ? activeEmployee.name : 'the employee';

            if (review) {
                byId('review-mode-warning').textContent =
                    'Nothing changes hands when you send this: ' + from + ' stays accountable for these assets until the ' + REVIEW_RECIPIENT + ' records a decision.';
                byId('confirm-warning').textContent =
                    'The assets stay with ' + from + ' and keep their Asset ID, Asset Code, QR Code and history. They move to the receiving employee only after the ' + REVIEW_RECIPIENT + ' approves the transfer.';
                byId('confirm-transfer-btn').innerHTML = '<i class="ri-send-plane-line mr-1.5"></i>Send for Approval';
            } else {
                byId('confirm-warning').textContent =
                    'The receiving employee becomes responsible for these assets. Their Asset ID, Asset Code, QR Code and history stay the same — only the accountable employee changes.';
                byId('confirm-transfer-btn').innerHTML = '<i class="ri-check-line mr-1.5"></i>Confirm Transfer';
            }
        }

        function submitTransfer() {
            if (submitted) return;   // one confirmation per page — no double sends
            if (!activeEmployee || !receiverId || !selectedAssetIds.length) return;

            const review = transferMode() === 'review';
            const recipient = byId('review-recipient').value.trim();

            // The address is where the link and its code go, so a send without a
            // usable one is stopped here rather than at the server. The browser's
            // own validation does not run for a form this script submits.
            if (review && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(recipient)) {
                alert('Enter the email address ' + REVIEW_RECIPIENT + ' actually reads — the review link and its verification code are sent there.');
                byId('review-recipient').focus();
                return;
            }

            submitted = true;

            const btn = byId('confirm-transfer-btn');
            btn.disabled = true;
            btn.style.opacity = '.6';
            btn.innerHTML = review
                ? '<i class="ri-loader-4-line mr-1.5 animate-spin inline-block"></i>Sending...'
                : '<i class="ri-loader-4-line mr-1.5 animate-spin inline-block"></i>Transferring...';

            byId('form-from-user').value = activeEmployee.id;
            byId('form-to-user').value = receiverId;
            byId('form-reason').value = byId('reason-select').value;
            byId('form-notes').value = byId('transfer-notes').value.trim();
            byId('form-send-for-review').value = review ? '1' : '0';
            byId('form-review-email').value = review ? recipient : '';
            byId('form-asset-inputs').innerHTML = selectedAssetIds
                .map((id) => `<input type="hidden" name="asset_ids[]" value="${id}">`).join('');

            byId('transferForm').submit();
        }

        /* ---------------- the Executive Director review tab ---------------- */
        function reviewById(reviewId) {
            return TRANSFER_REVIEWS.find((r) => Number(r.id) === Number(reviewId)) || null;
        }

        function reviewTone(review) {
            if (review.awaiting) return { bg: 'var(--gold-100)', ink: 'var(--gold-ink)', label: 'Awaiting ' + REVIEW_RECIPIENT + '’s decision' };
            if (review.decided && review.decision === 'rejected') return { bg: 'var(--brick-tint)', ink: 'var(--brick-dark)', label: 'Rejected' };
            if (review.decided && review.decision === 'partially_approved') return { bg: 'var(--plum-tint)', ink: 'var(--plum-dark)', label: 'Approved in part' };
            if (review.decided) return { bg: 'var(--forest-tint)', ink: 'var(--forest-dark)', label: 'Approved' };
            if (review.status === 'revoked') return { bg: 'var(--paper-2)', ink: 'var(--ink-600)', label: 'Invitation withdrawn' };
            return { bg: 'var(--bronze-tint)', ink: 'var(--bronze-dark)', label: 'Link expired' };
        }

        function fact(label, value) {
            return `<div class="rounded-xl px-4 py-3" style="background:var(--paper-3);">
                <p class="eyebrow mb-1">${escapeHtml(label)}</p>
                <p class="text-sm" style="color:var(--navy-900);">${escapeHtml(value || '—')}</p>
            </div>`;
        }

        function openExecutiveModal(reviewId) {
            const review = reviewById(reviewId);
            if (!review) return;

            const tone = reviewTone(review);

            byId('executive-modal-title').textContent = 'Transfer ' + review.reference + ' — ' + tone.label;
            byId('executive-modal-sub').textContent = review.from?.name
                ? `${review.from.name} → ${review.to?.name || 'unknown employee'} • ${review.asset_count} asset(s)`
                : `${review.asset_count} asset(s)`;

            const assetRows = (review.assets || []).map((asset) => `
                <div class="flex flex-wrap items-center gap-2 px-3 py-2 rounded-lg" style="background:var(--paper-3);">
                    <span class="text-xs font-mono" style="color:var(--navy-900);">${escapeHtml(asset.code || ('asset #' + asset.id))}</span>
                    <span class="text-xs flex-1 min-w-0" style="color:var(--ink-600);">${escapeHtml(asset.name || '')}</span>
                    <span class="text-[.68rem] font-semibold whitespace-nowrap" style="color:${asset.decision === 'approved' ? 'var(--forest-dark)' : (asset.decision === 'declined' ? 'var(--brick-dark)' : 'var(--ink-400)')};">${escapeHtml(asset.decision_label)}</span>
                </div>`).join('') || '<p class="text-xs" style="color:var(--ink-400);">The assets on this transfer are no longer on record.</p>';

            const sentLine = review.can_resend
                ? `<div class="px-4 py-3 rounded-xl" style="background:var(--gold-100); border:1px solid #EADFC0;">
                        <label for="executive-resend-email" class="eyebrow mb-1 block">Send the review link to</label>
                        <input type="email" id="executive-resend-email" autocomplete="off"
                               value="${escapeHtml(review.recipient_email || '')}"
                               class="w-full px-3 py-2.5 rounded-lg text-sm" style="border:1px solid var(--line);">
                        <p class="text-xs mt-2" style="color:var(--gold-ink);">
                            Sending again replaces the previous link: whatever was emailed before stops working, and this address is the only mailbox that can submit the decision.
                        </p>
                   </div>`
                : '';

            byId('executive-modal-body').innerHTML = `
                <div class="rounded-xl px-4 py-3 mb-4" style="background:${tone.bg};">
                    <p class="text-sm font-semibold" style="color:${tone.ink};">${escapeHtml(tone.label)}</p>
                    <p class="text-xs mt-1" style="color:${tone.ink};">${escapeHtml(review.status_hint)}</p>
                    ${review.outcome ? `<p class="text-xs mt-2 font-medium" style="color:${tone.ink};">${escapeHtml(review.outcome)}</p>` : ''}
                    ${review.revoked_reason ? `<p class="text-xs mt-2" style="color:${tone.ink};">Withdrawn: ${escapeHtml(review.revoked_reason)}</p>` : ''}
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    ${fact('From', review.from?.name || 'Unknown')}
                    ${fact('To', review.to?.name || 'Unknown')}
                    ${fact('Reason', review.reason || '—')}
                    ${fact('Notes', review.notes || '—')}
                </div>

                <p class="eyebrow mb-2">Email and decision</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    ${fact('Sent to', review.recipient_email || '—')}
                    ${fact('Sent', review.sent_at ? (review.sent_at + (review.sent_count > 1 ? ' (sent ' + review.sent_count + ' times)' : '')) : '—')}
                    ${fact('Link expires', review.expires_at || '—')}
                    ${fact('Mailbox verified', review.verified_at || 'Not yet')}
                    ${fact('Decision recorded', review.decided_at || '—')}
                    ${fact('Released for review by', (review.released_by || '—') + (review.released_at ? ' on ' + review.released_at : ''))}
                </div>

                ${sentLine ? `<div class="mb-4">${sentLine}</div>` : ''}

                <p class="eyebrow mb-2">Assets named on this transfer</p>
                <div class="space-y-1">${assetRows}</div>`;

            const actions = [];

            if (review.can_resend) {
                actions.push(`<button type="button" onclick="sendReviewAgain(${review.id})" class="px-4 py-2.5 rounded-lg text-sm font-semibold" style="background:var(--gold-500); color:var(--navy-950);">
                    <i class="ri-send-plane-line mr-1.5"></i>${review.status === 'revoked' ? 'Send a new invitation' : 'Send again'}</button>`);
            }

            if (review.can_revoke) {
                actions.push(`<button type="button" onclick="withdrawReview(${review.id})" class="px-4 py-2.5 rounded-lg text-sm font-medium" style="background:var(--brick-tint); color:var(--brick-dark);">
                    <i class="ri-close-circle-line mr-1.5"></i>Withdraw invitation</button>`);
            }

            actions.push('<button type="button" onclick="closeExecutiveModal()" class="px-4 py-2.5 rounded-lg text-sm font-medium" style="background:var(--paper-2); color:var(--ink-600);">Close</button>');

            byId('executive-modal-actions').innerHTML = actions.join('');

            byId('executiveModal').classList.remove('hidden');
            byId('executiveModal').classList.add('flex');
        }

        function closeExecutiveModal() {
            byId('executiveModal').classList.add('hidden');
            byId('executiveModal').classList.remove('flex');
        }

        async function reviewAction(url, body) {
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                    },
                    body: JSON.stringify(body || {}),
                });

                let data = {};
                try { data = await response.json(); } catch (e) { data = {}; }

                return { ok: response.ok, message: data.message || 'The server did not say what happened.' };
            } catch (e) {
                return { ok: false, message: 'The request could not be sent. Check the connection and try again.' };
            }
        }

        async function sendReviewAgain(reviewId) {
            const review = reviewById(reviewId);
            if (!review) return;

            const email = byId('executive-resend-email')?.value.trim() || '';

            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Enter the email address ' + REVIEW_RECIPIENT + ' actually reads — the review link and its verification code are sent there.');
                byId('executive-resend-email')?.focus();
                return;
            }

            const result = await reviewAction(`/admin/requests/${review.request_id}/review/resend`, { recipient_email: email });

            alert(result.message);

            if (result.ok) window.location.reload();
        }

        async function withdrawReview(reviewId) {
            const review = reviewById(reviewId);
            if (!review) return;

            if (!confirm('Withdraw this invitation?\n\nThe link emailed to the ' + REVIEW_RECIPIENT + ' stops working immediately and cannot be used to submit a decision. The assets stay where they are, and nothing is transferred.')) {
                return;
            }

            const reason = prompt('Why is this invitation being withdrawn? This is recorded on the trail.', 'Withdrawn by the Asset Management Office.') || '';

            const result = await reviewAction(`/admin/requests/${review.request_id}/review/revoke`, { reason });

            alert(result.message);

            if (result.ok) window.location.reload();
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

            byId('reason-select')?.addEventListener('change', updateContinueButton);

            // The header search is an ordinary GET form now: it filters the whole
            // list on the server, so typing must not hide rows on this page only.
            selectTab(ACTIVE_TAB || 'employees');

            if (OPEN_TRANSFER_ID) {
                selectTab('history');
                openHistoryModal(OPEN_TRANSFER_ID);
            }

            // The office just sent a transfer for review (or asked for one again):
            // land on the tab that holds it, with its details open, rather than
            // making them search for what they just did.
            if (OPEN_REVIEW_ID) {
                selectTab('executive');
                openExecutiveModal(OPEN_REVIEW_ID);
            }
        });

        // Coming back to this page from the history (browser Back / Forward) restores
        // the old screen, its open confirmation included. Put the button back in
        // working order so the Admin is never stuck on a dead "Transferring..." —
        // a repeat confirmation is harmless, the server answers it with the transfer
        // that already did the work instead of an error.
        window.addEventListener('pageshow', (event) => {
            if (!event.persisted) return;

            submitted = false;

            const btn = byId('confirm-transfer-btn');
            if (btn) {
                btn.disabled = false;
                btn.style.opacity = '1';
            }

            updateTransferMode();
            closeConfirmModal();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            closeConfirmModal();
            closeReceiverModal();
            closeAssetsModal();
            closeHistoryModal();
            closeExecutiveModal();
        });
    </script>
</body>
</html>
