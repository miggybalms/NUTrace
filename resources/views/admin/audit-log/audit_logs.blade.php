@extends('layouts.admin_sidebar')

@section('title', 'Audit Logs')

@section('content')

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
    :root{
        --navy-950:#0A1830; --navy-900:#0F2143; --navy-800:#15305B; --navy-700:#1D3F73;
        --gold-500:#C9A227; --gold-600:#A8841E; --gold-100:#F3E7C4;
        --paper:#F3EEE0; --paper-2:#EAE2C9;
        --ink-900:#1A2233; --ink-600:#4B5468; --ink-400:#8991A0;
        --line:#DED2AE;
        --forest:#2F7A4D; --forest-dark:#245C3B; --forest-tint:#EAF4EE;
        --bronze:#B4791E; --bronze-dark:#8F5F16; --bronze-tint:#FBF1DE;
        --steel:#2E5C8A; --steel-dark:#234869; --steel-tint:#E9F0F7;
        --brick:#A23B32; --brick-dark:#7E2E27; --brick-tint:#F7E9E6;
        --plum:#6B4C82; --plum-dark:#523A64; --plum-tint:#EFE7F3;
    }
    body{ background:var(--paper) !important; font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif !important; color:var(--ink-900); }
    .font-display{ font-family:'Fraunces',Georgia,serif; }
    .font-mono{ font-family:'IBM Plex Mono',monospace; }
    .eyebrow{ font-size:.68rem; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:var(--ink-400); }
    .topbar{ background:#fff; border-bottom:1px solid var(--line); position:relative; }
    .topbar::after{ content:""; position:absolute; left:0; right:0; bottom:-2px; height:2px; background:linear-gradient(90deg, transparent, var(--gold-500) 20%, var(--gold-500) 80%, transparent); opacity:.7; }
    .search-input{ border:1px solid var(--line); background:#fff; transition:border-color .15s, box-shadow .15s; }
    .search-input:focus{ outline:none; border-color:var(--gold-500); box-shadow:0 0 0 3px rgba(201,162,39,.18); }
    .card{ background:#fff; border:1px solid var(--line); border-radius:14px; box-shadow:0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28); }
    .avatar-badge{ background:var(--navy-950); color:var(--gold-500); border:1px solid var(--gold-500); }
</style>

    <!-- Header (shared admin header) -->
    @include('admin.partials.header', [
        'adminHeaderPage'     => 'audit_logs',
        'adminHeaderTitle'    => 'Audit Logs',
        'adminHeaderIcon'     => 'ri-history-line',
        'adminHeaderBadge'    => 'Admin',
    ])

    <!-- Content -->
    <div class="p-8">

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="card p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-[#5B6678]">Total Logs</p>
                    <div class="w-9 h-9 bg-[#F3E7C4] rounded-lg flex items-center justify-center">
                        <i class="ri-file-list-line text-[#A8841E]"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-[#0F2143]">{{ $totalLogs ?? 0 }}</p>
            </div>
            <div class="card p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-[#5B6678]">Today's Activity</p>
                    <div class="w-9 h-9 bg-[#EAF4EE] rounded-lg flex items-center justify-center">
                        <i class="ri-calendar-check-line text-[#2F7A4D]"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-[#2F7A4D]">{{ $todayLogs ?? 0 }}</p>
            </div>
            <div class="card p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-[#5B6678]">This Week</p>
                    <div class="w-9 h-9 bg-[#EFE7F3] rounded-lg flex items-center justify-center">
                        <i class="ri-bar-chart-line text-[#6B4C82]"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-[#6B4C82]">{{ $weekLogs ?? 0 }}</p>
            </div>
            <div class="card p-5">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-[#5B6678]">Active Users</p>
                    <div class="w-9 h-9 bg-[#FBF1DE] rounded-lg flex items-center justify-center">
                        <i class="ri-user-line text-[#B4791E]"></i>
                    </div>
                </div>
                <p class="text-3xl font-bold text-[#B4791E]">{{ $activeUsers ?? 0 }}</p>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="bg-white rounded-xl border border-[#DED2AE] shadow-sm">

            <!-- Tabs -->
            <div class="flex items-center justify-between px-6 pt-5 pb-0 border-b border-[#EFE9D8]">
                <div class="flex space-x-1">
                    @php
                        $tabs = [
                            'all'     => 'All',
                            'asset'   => 'Assets',
                            'request' => 'Requests',
                            'auth'    => 'Auth',
                        ];
                        $currentFilter = $filter ?? 'all';
                    @endphp
                    @foreach($tabs as $key => $label)
                        <a href="{{ url('/admin/audit-logs') }}?{{ http_build_query(array_filter(['filter' => $key, 'q' => $search ?? '', 'date' => $date ?? ''])) }}"
                        class="filter-tab px-4 py-2.5 text-sm font-medium {{ $currentFilter === $key ? 'text-[#0F2143] border-b-2 border-[#C9A227]' : 'text-[#5B6678] hover:text-[#33425C]' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                <p class="text-sm text-[#8991A0] pb-3">{{ $logs->total() ?? 0 }} records</p>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="logsTable">
                    <thead>
                        <tr class="bg-[#F5F0E2] text-[#5B6678] uppercase text-xs tracking-wider">
                            <th class="px-6 py-4 text-left font-semibold">User</th>
                            <th class="px-6 py-4 text-left font-semibold">Action</th>
                            <th class="px-6 py-4 text-left font-semibold">Asset</th>
                            <th class="px-6 py-4 text-left font-semibold">Request</th>
                            <th class="px-6 py-4 text-left font-semibold">Date & Time</th>
                            <th class="px-6 py-4 text-left font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#EFE9D8]" id="logsTableBody">

                        @forelse($logs ?? [] as $log)
                        <tr class="log-row hover:bg-[#F5F0E2] transition"
                            data-type="{{ str_contains(strtolower($log->notes ?? ''), 'asset') ? 'asset' : (str_contains(strtolower($log->notes ?? ''), 'request') ? 'request' : (str_contains(strtolower($log->notes ?? ''), 'login') ? 'auth' : 'all')) }}"
                            data-date="{{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d') }}">

                            {{-- User --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-[#0F2143] rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-[#E9C766] text-xs font-semibold">
                                            {{ strtoupper(substr($log->user_name ?? 'U', 0, 1)) }}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-[#0F2143]">{{ $log->user_name ?? '—' }}</p>
                                        <p class="text-xs text-[#8991A0]">{{ $log->user_role ?? ($log->user->role ?? '—') }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Action / Notes --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    {{-- Action icon based on note content --}}
                                    @php
                                        $note = strtolower($log->notes ?? '');
                                        $icon = 'ri-information-line text-[#5B6678]';
                                        $bg = 'bg-[#EFE9D8]';
                                        if (str_contains($note, 'approved')) { $icon = 'ri-checkbox-circle-line text-[#2F7A4D]'; $bg = 'bg-[#EAF4EE]'; }
                                        elseif (str_contains($note, 'rejected')) { $icon = 'ri-close-circle-line text-[#A23B32]'; $bg = 'bg-[#F7E9E6]'; }
                                        elseif (str_contains($note, 'created') || str_contains($note, 'registered')) { $icon = 'ri-add-circle-line text-[#A8841E]'; $bg = 'bg-[#F3E7C4]'; }
                                        elseif (str_contains($note, 'deleted') || str_contains($note, 'disposed')) { $icon = 'ri-delete-bin-line text-[#A23B32]'; $bg = 'bg-[#F7E9E6]'; }
                                        elseif (str_contains($note, 'updated') || str_contains($note, 'edited')) { $icon = 'ri-edit-line text-[#B4791E]'; $bg = 'bg-[#FBF1DE]'; }
                                        elseif (str_contains($note, 'login')) { $icon = 'ri-login-circle-line text-[#6B4C82]'; $bg = 'bg-[#EFE7F3]'; }
                                        elseif (str_contains($note, 'transfer')) { $icon = 'ri-swap-line text-[#A8841E]'; $bg = 'bg-[#F3E7C4]'; }
                                    @endphp
                                    <div class="w-8 h-8 {{ $bg }} rounded-lg flex items-center justify-center flex-shrink-0">
                                        <i class="{{ $icon }} text-sm"></i>
                                    </div>
                                    <p class="text-[#33425C] max-w-xs">{{ $log->notes ?? '—' }}</p>
                                </div>
                            </td>

                            {{-- Asset --}}
                            <td class="px-6 py-4">
                                @if(!empty($log->asset_name) || !empty($log->asset_code))
                                <div>
                                    <p class="font-medium text-[#0F2143]">{{ $log->asset_name ?? '—' }}</p>
                                    <p class="text-xs text-[#8991A0] font-mono">{{ $log->asset_code ?? '—' }}</p>
                                </div>
                                @else
                                <span class="text-[#8991A0]">—</span>
                                @endif
                            </td>

                            {{-- Request --}}
                            <td class="px-6 py-4">
                                @if(!empty($log->request_type) || !empty($log->request_id))
                                <div>
                                    <p class="font-medium text-[#0F2143]">{{ $log->request_type ?? '—' }}</p>
                                    <p class="text-xs text-[#8991A0] font-mono">REQ-{{ str_pad($log->request_id ?? 0, 5, '0', STR_PAD_LEFT) }}</p>
                                </div>
                                @else
                                <span class="text-[#8991A0]">—</span>
                                @endif
                            
                            </td>

                            {{-- Date & Time --}}
                            <td class="px-6 py-4">
                                <p class="text-[#33425C]">{{ \Carbon\Carbon::parse($log->created_at)->format('M d, Y') }}</p>
                                <p class="text-xs text-[#8991A0]">{{ \Carbon\Carbon::parse($log->created_at)->format('h:i A') }}</p>
                                <p class="text-xs text-[#8991A0] mt-0.5">{{ \Carbon\Carbon::parse($log->created_at)->diffForHumans() }}</p>
                            </td>

                            {{-- Actions --}}
                            <td class="px-6 py-4">
                                <button onclick="openViewModal({{ $log->id }})"
                                    class="p-1.5 bg-[#F3E7C4] text-[#A8841E] rounded-lg hover:bg-[#C9A227] hover:text-[#0A1830] transition"
                                    title="View Details">
                                    <i class="ri-eye-line text-sm"></i>
                                </button>
                            </td>

                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 bg-[#EFE9D8] rounded-full flex items-center justify-center mb-3">
                                        <i class="ri-shield-check-line text-2xl text-[#8991A0]"></i>
                                    </div>
                                    <p class="text-[#5B6678] font-medium">No audit logs found</p>
                                    <p class="text-[#8991A0] text-xs mt-1">System activities will appear here</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse

                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if(isset($logs) && $logs->hasPages())
            <div class="px-6 py-5 border-t border-[#EFE9D8]">
                {{ $logs->links() }}
            </div>
            @endif

        </div>

        <!-- Footer -->
        <div class="text-center text-sm text-[#8991A0] mt-8 pt-6 border-t border-[#DED2AE]">
            © {{ date('Y') }} University Asset Management. All rights reserved.
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full mx-4">
            <div class="p-6 border-b border-[#DED2AE] flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#0F2143]">Log Details</h3>
                <button onclick="closeViewModal()" class="text-[#8991A0] hover:text-[#46536B]">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                @foreach($logs ?? [] as $log)
                        <div id="modal-{{ $log->id }}" class="modal-content hidden space-y-3">
                    {{-- User Info --}}
                    <div class="flex items-center space-x-3 p-3 bg-[#F5F0E2] rounded-lg">
                        <div class="w-10 h-10 bg-[#0A1830] border border-[#C9A227] rounded-full flex items-center justify-center">
                            <span class="text-[#E9C766] text-sm font-semibold">
                                {{ strtoupper(substr($log->user_name ?? 'U', 0, 1)) }}
                            </span>
                        </div>
                        <div>
                            <p class="font-semibold text-[#0F2143]">{{ $log->user_name ?? '—' }}</p>
                            <p class="text-xs text-[#5B6678]">{{ $log->user_role ?? ($log->user->role ?? '—') }} • {{ $log->user->department ?? '—' }}</p>
                        </div>
                    </div>
                    {{-- Details Grid --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-[#F5F0E2] rounded-lg p-3">
                            <p class="text-xs text-[#8991A0] mb-1">Action</p>
                            <p class="text-sm font-medium text-[#0F2143]">{{ $log->notes ?? '—' }}</p>
                        </div>
                        <div class="bg-[#F5F0E2] rounded-lg p-3">
                            <p class="text-xs text-[#8991A0] mb-1">Date & Time</p>
                            <p class="text-sm font-medium text-[#0F2143]">{{ \Carbon\Carbon::parse($log->created_at)->format('M d, Y h:i A') }}</p>
                        </div>
                        @if(!empty($log->asset_name) || !empty($log->asset_code))
                        <div class="bg-[#F5F0E2] rounded-lg p-3">
                            <p class="text-xs text-[#8991A0] mb-1">Asset</p>
                            <p class="text-sm font-medium text-[#0F2143]">{{ $log->asset_name ?? '—' }}</p>
                            <p class="text-xs text-[#8991A0] font-mono">{{ $log->asset_code ?? '' }}</p>
                        </div>
                        @endif
                        @if(!empty($log->request_type) || !empty($log->request_id))
                        <div class="bg-[#F5F0E2] rounded-lg p-3">
                            <p class="text-xs text-[#8991A0] mb-1">Request</p>
                            <p class="text-sm font-medium text-[#0F2143]">{{ $log->request_type ?? '—' }}</p>
                            <p class="text-xs text-[#8991A0] font-mono">REQ-{{ str_pad($log->request_id ?? 0, 5, '0', STR_PAD_LEFT) }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            <div class="p-6 border-t border-[#EFE9D8] flex justify-end">
                <button onclick="closeViewModal()"
                    class="px-4 py-2.5 bg-white text-[#33425C] border border-[#DED2AE] rounded-lg hover:bg-[#EFE9D8] transition text-sm font-medium">
                    Close
                </button>
            </div>
        </div>
    </div>

    <style>
        .filter-tab { transition: all 0.2s ease; }
        .log-row { transition: all 0.2s ease; }
    </style>

    <script>
        // Filter tabs
            function applyFilters() {
                const params = new URLSearchParams();
                const filter = '{{ $filter ?? "all" }}';
                const q = document.getElementById('searchInput').value.trim();
                const date = document.getElementById('dateFilter').value;

                if (filter && filter !== 'all') params.set('filter', filter);
                if (q) params.set('q', q);
                if (date) params.set('date', date);

                const qs = params.toString();
                window.location.href = '{{ url("/admin/audit-logs") }}' + (qs ? '?' + qs : '');
            }

            document.getElementById('searchInput').addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyFilters();
                }
            });

            document.getElementById('dateFilter').addEventListener('change', applyFilters);

        // View Modal
        function openViewModal(id) {
            document.querySelectorAll('.modal-content').forEach(c => c.classList.add('hidden'));
            const content = document.getElementById('modal-' + id);
            if (content) content.classList.remove('hidden');
            document.getElementById('viewModal').classList.remove('hidden');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }

        // Export (basic CSV export)
        function exportLogs() {
            const rows = [['User', 'Action', 'Asset', 'Request', 'Date']];
            document.querySelectorAll('.log-row').forEach(row => {
                const cells = row.querySelectorAll('td');
                rows.push([
                    cells[0]?.innerText.trim().replace(/\n/g, ' '),
                    cells[1]?.innerText.trim().replace(/\n/g, ' '),
                    cells[2]?.innerText.trim().replace(/\n/g, ' '),
                    cells[3]?.innerText.trim().replace(/\n/g, ' '),
                    cells[4]?.innerText.trim().replace(/\n/g, ' '),
                ]);
            });
            const csv = rows.map(r => r.join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'audit-logs.csv';
            a.click();
        }
    </script>

@endsection