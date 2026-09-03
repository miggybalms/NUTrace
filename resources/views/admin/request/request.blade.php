<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Request Management - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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

        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: var(--paper);
            color: var(--ink-900);
        }

        .font-display{ font-family:'Fraunces',serif; }
        .font-mono{ font-family:'IBM Plex Mono',monospace; }
        .eyebrow{ font-family:'Inter',sans-serif; font-size:.68rem; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:var(--ink-400); }

        .sidebar-item {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .sidebar-item:hover {
            background-color: #374151;
        }

        .sidebar-item.active {
            background-color: #1f2937;
            color: #3b82f6;
            border-right: 3px solid #3b82f6;
        }

        .topbar{ background:#fff; border-bottom:1px solid var(--line); position:relative; }
        .topbar::after{ content:""; position:absolute; left:0; right:0; bottom:-2px; height:2px; background:linear-gradient(90deg, transparent, var(--gold-500) 20%, var(--gold-500) 80%, transparent); opacity:.7; }

        .badge-role{ background:var(--gold-100); color:var(--navy-900); }

        .btn-gold{ font-family:'Inter',sans-serif; font-weight:600; border-radius:9px; padding:.62rem 1.15rem; background:var(--gold-500); color:var(--navy-950); display:inline-flex; align-items:center; transition:filter .15s ease; }
        .btn-gold:hover{ filter:brightness(1.06); }

        .stat-card{ background:#fff; border:1px solid var(--line); border-radius:14px; padding:1.5rem; box-shadow: 0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28); }
        .stat-icon{ width:2.5rem; height:2.5rem; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }

        .panel{ background:#fff; border:1px solid var(--line); box-shadow: 0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28); }

        .tab-btn{ position:relative; }
        .tab-btn.tab-active{ color:var(--navy-900) !important; font-weight:600; }
        .tab-btn.tab-active::after{ content:""; position:absolute; left:0; right:0; bottom:-1px; height:2px; background:var(--gold-500); }

        .request-row {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .request-row:hover {
            background-color: var(--paper-2);
        }
        
        .request-row.selected {
            background-color: var(--gold-100);
            border-left: 3px solid var(--gold-500);
        }
        
        .status-badge {
            transition: all 0.2s ease;
        }

        .detail-card {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        /* hide horizontal scrollbar while preserving scroll capability */
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body>
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto" style="background:var(--paper);">
                <!-- Header -->
                <div class="topbar sticky top-0 z-10">
                    <div class="px-4 sm:px-8 py-5">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                            <div class="flex items-center">
                                <!-- Hamburger, mobile only -->
                                <button onclick="toggleSidebar()" class="lg:hidden mr-3" style="color:var(--ink-400);">
                                    <i class="ri-menu-line text-2xl"></i>
                                </button>
                                <div>
                                    <h2 class="font-display text-xl sm:text-2xl font-semibold" style="color:var(--navy-900);">Requests</h2>
                                    <div class="flex items-center mt-1.5">
                                        <span class="badge-role inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded-md">Admin</span>
                                        <span class="mx-2" style="color:var(--ink-400);">•</span>
                                        <p class="text-sm hidden sm:block" style="color:var(--ink-600);">Manage and process asset requests</p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                            <button onclick="exportRequests()" class="w-full sm:w-auto justify-center btn-gold flex items-center">
                                    <i class="ri-file-copy-line mr-2"></i>
                                    Export Report
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Content -->
            <div class="p-4 sm:p-8">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mb-6 sm:mb-8">
                    <div class="stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm mb-1" style="color:var(--ink-600);">Total Requests</p>
                                <p class="text-3xl font-bold" style="color:var(--navy-900);">{{ $totalRequests ?? 0 }}</p>
                                <p class="text-xs mt-2" style="color:var(--ink-400);">All time requests</p>
                            </div>
                            <div class="stat-icon" style="background:var(--steel-tint);">
                                <i class="ri-mail-line text-xl" style="color:var(--steel);"></i>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm mb-1" style="color:var(--ink-600);">Pending</p>
                                <p class="text-3xl font-bold" style="color:var(--bronze-dark);">{{ $pendingRequests ?? 0 }}</p>
                                <p class="text-xs mt-2" style="color:var(--bronze-dark);">Awaiting approval</p>
                            </div>
                            <div class="stat-icon" style="background:var(--bronze-tint);">
                                <i class="ri-time-line text-xl" style="color:var(--bronze);"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="panel rounded-t-xl mb-0">
                    <div class="flex space-x-6 sm:space-x-8 px-4 sm:px-6 pt-4 overflow-x-auto scrollbar-hide">
                        <button class="tab-btn pb-3 text-sm font-medium transition whitespace-nowrap" style="color:var(--ink-400);" data-tab="all">
                            All Requests
                        </button>
                        <button class="tab-btn pb-3 text-sm font-medium transition whitespace-nowrap" style="color:var(--ink-400);" data-tab="pending">
                            Pending
                        </button>
                        <button class="tab-btn pb-3 text-sm font-medium transition whitespace-nowrap" style="color:var(--ink-400);" data-tab="approved">
                            Approved
                        </button>
                        <button class="tab-btn pb-3 text-sm font-medium transition whitespace-nowrap" style="color:var(--ink-400);" data-tab="rejected">
                            Rejected
                        </button>
                    </div>
                </div>

                <!-- Main Content Area: Request List + Details Side by Side -->
                <div class="flex flex-col lg:flex-row gap-6">
                    <!-- Request List Section -->
                    <div class="flex-1 min-w-0 panel rounded-b-xl lg:rounded-b-xl overflow-hidden" style="border-top:0;">
                        <div class="overflow-x-auto scrollbar-hide">
                            <table class="w-full">
                                <thead class="sticky top-0" style="background:var(--paper-2); border-bottom:1px solid var(--line);">
                                    <tr>
                                        <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Request ID</th>
                                        <th class="eyebrow text-left py-3 px-3">Asset Name</th>
                                        <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Type</th>
                                        <th class="eyebrow text-left py-3 px-3">Submitted By</th>
                                        <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Date</th>
                                        <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Status</th>
                                        <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="requests-table-body">
                                    @forelse($requests as $request)
                                    <tr class="request-row" style="border-bottom:1px solid var(--line);" data-request-id="{{ $request->id }}" onclick="selectRequest({{ $request->id }})">
                                        <td class="py-3 px-3 text-sm font-mono whitespace-nowrap" style="color:var(--navy-900);">#REQ-{{ str_pad($request->id, 4, '0', STR_PAD_LEFT) }}</td>
                                        <td class="py-3 px-3 text-sm" style="color:var(--navy-900);">{{ $request->asset_name }}</td>
                                        <td class="py-3 px-3">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap"
                                                style="
                                                @if($request->type == 'new_asset') background:var(--steel-tint); color:var(--steel-dark);
                                                @elseif($request->type == 'repair') background:var(--brick-tint); color:var(--brick-dark);
                                                @elseif($request->type == 'pullout') background:var(--bronze-tint); color:var(--bronze-dark);
                                                @elseif($request->type == 'transfer') background:var(--gold-100); color:var(--navy-900);
                                                @elseif($request->type == 'replacement') background:var(--plum-tint); color:var(--plum-dark);
                                                @else background:var(--paper-2); color:var(--ink-600);
                                                @endif">
                                                {{ ucfirst(str_replace('_', ' ', $request->type)) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 text-sm" style="color:var(--ink-600);">{{ $request->submitted_by }}</td>
                                        <td class="py-3 px-3 text-sm whitespace-nowrap" style="color:var(--ink-600);">{{ data_get($request, 'created_at') ? \Carbon\Carbon::parse(data_get($request, 'created_at'))->format('M d, Y') : '—' }}</td>
                                        <td class="py-3 px-3 whitespace-nowrap">
                                            <span class="status-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                                style="
                                                @if($request->status == 'pending') background:var(--bronze-tint); color:var(--bronze-dark);
                                                @elseif($request->status == 'approved') background:var(--forest-tint); color:var(--forest-dark);
                                                @else background:var(--brick-tint); color:var(--brick-dark);
                                                @endif">
                                                @if($request->status == 'pending')
                                                    <i class="ri-time-line mr-1 text-xs"></i>
                                                @elseif($request->status == 'approved')
                                                    <i class="ri-checkbox-circle-line mr-1 text-xs"></i>
                                                @else
                                                    <i class="ri-close-circle-line mr-1 text-xs"></i>
                                                @endif
                                                {{ ucfirst($request->status) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 whitespace-nowrap">
                                            <div class="flex items-center space-x-2" onclick="event.stopPropagation()">
                                                @if($request->status == 'pending')
                                                <button onclick="approveRequest({{ $request->id }})" class="p-1.5 rounded transition" style="color:var(--forest);" onmouseover="this.style.background='var(--forest-tint)'" onmouseout="this.style.background='transparent'" title="Approve">
                                                    <i class="ri-checkbox-circle-line text-lg"></i>
                                                </button>
                                                <button onclick="rejectRequest({{ $request->id }})" class="p-1.5 rounded transition" style="color:var(--brick);" onmouseover="this.style.background='var(--brick-tint)'" onmouseout="this.style.background='transparent'" title="Reject">
                                                    <i class="ri-close-circle-line text-lg"></i>
                                                </button>
                                                @else
                                                <span class="text-xs" style="color:var(--ink-400);">No actions</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="py-12 text-center">
                                            <i class="ri-inbox-line text-5xl mb-3 block" style="color:var(--line);"></i>
                                            <p style="color:var(--ink-400);">No requests found.</p>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Request Details Section -->
                    <div class="w-full lg:w-96 lg:flex-shrink-0 panel rounded-xl overflow-y-auto" id="request-details-panel">
                        <div class="p-6">
                            <h3 class="font-display text-lg font-semibold mb-4 flex items-center" style="color:var(--navy-900);">
                                <i class="ri-file-info-line mr-2" style="color:var(--gold-600);"></i>
                                Request Details
                            </h3>
                            
                            <div id="no-selection-message" class="text-center py-12">
                                <i class="ri-inbox-line text-5xl mb-3 block" style="color:var(--line);"></i>
                                <p style="color:var(--ink-400);">No request selected.</p>
                                <p class="text-xs mt-2" style="color:var(--ink-400);">Click on any request to view details</p>
                            </div>
                            
                            <div id="request-detail-content" style="display: none;">
                                <!-- Asset Photo -->
                                <div class="mb-6">
                                    <div class="rounded-lg h-48 flex items-center justify-center mb-3 overflow-hidden" style="background:var(--paper-2);">
                                        <img id="detail-photo" src="" alt="Request Photo" class="w-full h-full object-cover hidden">
                                        <i id="detail-photo-placeholder" class="ri-image-line text-4xl" style="color:var(--ink-400);"></i>
                                    </div>
                                    <p class="text-xs text-center" style="color:var(--ink-400);">Request Photo</p>
                                </div>
                                
                                <!-- Request Information -->
                                <div class="space-y-4">
                                    <div class="pb-3" style="border-bottom:1px solid var(--line);">
                                        <p class="field-label eyebrow mb-1">Request ID</p>
                                        <p class="text-sm font-semibold font-mono" style="color:var(--navy-900);" id="detail-id">-</p>
                                    </div>
                                    
                                    <!-- Assets list (supports bulk) -->
                                    <div class="pb-3" style="border-bottom:1px solid var(--line);">
                                        <p class="eyebrow mb-2">Asset(s)</p>
                                        <div id="detail-assets-list" class="space-y-2">
                                            <!-- filled by JS -->
                                        </div>
                                    </div>
                                    
                                    <div class="pb-3" style="border-bottom:1px solid var(--line);">
                                        <p class="eyebrow mb-1">Request Type</p>
                                        <p class="text-sm font-semibold" style="color:var(--navy-900);" id="detail-type">-</p>
                                    </div>
                                    
                                    <div class="pb-3" style="border-bottom:1px solid var(--line);">
                                        <p class="eyebrow mb-1">Submitted By</p>
                                        <p class="text-sm" style="color:var(--navy-900);" id="detail-submitter">-</p>
                                        <p class="text-xs mt-1" style="color:var(--ink-400);" id="detail-email">-</p>
                                    </div>
                                    
                                    <div class="pb-3" style="border-bottom:1px solid var(--line);">
                                        <p class="eyebrow mb-1">Date Submitted</p>
                                        <p class="text-sm" style="color:var(--navy-900);" id="detail-date">-</p>
                                    </div>
                                    
                                    <div class="pb-3" style="border-bottom:1px solid var(--line);">
                                        <p class="eyebrow mb-1">Status</p>
                                        <span id="detail-status-badge" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium">-</span>
                                    </div>

                                    <div class="pb-3" style="border-bottom:1px solid var(--line); display:none;" id="detail-assigned-block">
                                        <p class="eyebrow mb-1">Transferring To</p>
                                        <p class="text-sm font-semibold" style="color:var(--navy-900);" id="detail-assigned-to">-</p>
                                    </div>
                                    
                                    <div class="pb-3">
                                        <p class="eyebrow mb-1">Description / Reason</p>
                                        <p class="text-sm mt-1 leading-relaxed" style="color:var(--ink-600);" id="detail-description">-</p>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="mt-6 pt-4 flex gap-3" style="border-top:1px solid var(--line);" id="detail-actions">
                                    <button onclick="approveCurrentRequest()" class="flex-1 text-white px-4 py-2 rounded-lg transition flex items-center justify-center" style="background:var(--forest);" onmouseover="this.style.filter='brightness(1.08)'" onmouseout="this.style.filter='none'">
                                        <i class="ri-checkbox-circle-line mr-2"></i>
                                        Approve
                                    </button>
                                    <button onclick="rejectCurrentRequest()" class="flex-1 text-white px-4 py-2 rounded-lg transition flex items-center justify-center" style="background:var(--brick);" onmouseover="this.style.filter='brightness(1.08)'" onmouseout="this.style.filter='none'">
                                        <i class="ri-close-circle-line mr-2"></i>
                                        Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center text-sm mt-8 pt-6" style="color:var(--ink-400); border-top:1px solid var(--line);">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <script>
        
    function exportRequests() {
        const rows = [['Request ID', 'Asset Name', 'Type', 'Submitted By', 'Date', 'Status']];

        // Prefer the full data source (has the assets array for bulk requests)
        const data = (typeof requestsData !== 'undefined' && Array.isArray(requestsData))
            ? requestsData
            : [];

        data.forEach(request => {
            // Respect the current tab filter (only export visible rows)
            const row = document.querySelector(`.request-row[data-request-id="${request.id}"]`);
            if (!row || row.style.display === 'none') return;

            // Build full asset name list for bulk requests
            let assetName = request.asset_name || '';
            if (request.assets && Array.isArray(request.assets) && request.assets.length > 0) {
                assetName = request.assets
                    .map(a => (a.name || 'Unnamed').trim())
                    .filter(Boolean)
                    .join(', ');
            }

            // Format type the same way the table does
            const typeLabel = String(request.type || '')
                .replace(/_/g, ' ')
                .replace(/\b\w/g, c => c.toUpperCase());

            // Format date
            let dateStr = '';
            if (request.created_at) {
                const d = new Date(request.created_at);
                dateStr = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            }

            // Status
            const statusLabel = String(request.status || '').replace(/\b\w/g, c => c.toUpperCase());

            rows.push([
                `#REQ-${String(request.id).padStart(4, '0')}`,
                assetName,
                typeLabel,
                request.submitted_by || '',
                dateStr,
                statusLabel,
            ]);
        });

        if (rows.length <= 1) {
            alert('No requests to export.');
            return;
        }

        // Build CSV (escape quotes)
        const csv = rows.map(r =>
            r.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')
        ).join('\n');

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'requests-report-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }
        // Sample requests data (replace with your actual data from Laravel)
        const requestsData = @json($requests ?? []);
        let currentSelectedRequestId = null;
        
        function selectRequest(requestId) {
            // Remove selected class from all rows
            document.querySelectorAll('.request-row').forEach(row => {
                row.classList.remove('selected');
            });
            
            // Add selected class to clicked row
            const selectedRow = document.querySelector(`.request-row[data-request-id="${requestId}"]`);
            if (selectedRow) {
                selectedRow.classList.add('selected');
            }
            
            // Find request data
            const request = requestsData.find(r => r.id == requestId);
            if (request) {
                currentSelectedRequestId = requestId;
                displayRequestDetails(request);
            }
        }
        
            function displayRequestDetails(request) {
                // Hide no selection message and show details
                document.getElementById('no-selection-message').style.display = 'none';
                document.getElementById('request-detail-content').style.display = 'block';
                
                // Populate photo
                const photoImg = document.getElementById('detail-photo');
                const photoPlaceholder = document.getElementById('detail-photo-placeholder');
                if (request.image) {
                    photoImg.src = request.image;
                    photoImg.classList.remove('hidden');
                    photoPlaceholder.classList.add('hidden');
                } else {
                    photoImg.classList.add('hidden');
                    photoPlaceholder.classList.remove('hidden');
                }
                
                // Basic fields
                document.getElementById('detail-id').textContent = `#REQ-${String(request.id).padStart(4, '0')}`;
                document.getElementById('detail-type').innerHTML = getTypeBadge(request.type);
                document.getElementById('detail-submitter').textContent = request.submitted_by;
                document.getElementById('detail-email').textContent = request.email || '—';
                document.getElementById('detail-date').textContent = new Date(request.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                document.getElementById('detail-description').textContent = request.description || 'No description provided.';
                
                // Status badge
                const statusBadge = document.getElementById('detail-status-badge');
                statusBadge.innerHTML = getStatusBadge(request.status);
                
                // Show/hide action buttons
                const actionButtons = document.getElementById('detail-actions');
                actionButtons.style.display = (request.status === 'pending') ? 'flex' : 'none';

                // Assigned-to (Transfer)
                const assignedBlock = document.getElementById('detail-assigned-block');
                const assignedToEl = document.getElementById('detail-assigned-to');
                if (request.type === 'transfer' && request.assigned_to) {
                    assignedToEl.textContent = request.assigned_to;
                    assignedBlock.style.display = 'block';
                } else {
                    assignedBlock.style.display = 'none';
                }

                // ─── Assets list (supports bulk) ───────────────────────
                const assetsContainer = document.getElementById('detail-assets-list');
                assetsContainer.innerHTML = '';

                const assets = request.assets || [];
                if (assets.length === 0) {
                    assetsContainer.innerHTML = `<p class="text-sm" style="color:var(--ink-400);">No assets linked to this request.</p>`;
                } else {
                    assets.forEach(asset => {
                        const div = document.createElement('div');
                        div.className = 'rounded-lg px-3 py-2';
                        div.style.background = 'var(--paper-2)';
                        div.innerHTML = `
                            <p class="text-sm font-medium" style="color:var(--navy-900);">${escapeHtml(asset.name || 'Unnamed')}</p>
                            <p class="text-xs font-mono mt-0.5" style="color:var(--ink-400);">${escapeHtml(asset.code || '')}</p>
                        `;
                        assetsContainer.appendChild(div);
                    });
                }
            }

            function escapeHtml(str) {
                if (!str) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }
        
        function getTypeBadge(type) {
            const badges = {
                'repair': '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background:var(--brick-tint); color:var(--brick-dark);"><i class="ri-tools-line mr-1 text-xs"></i>Repair</span>',
                'disposal': '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background:var(--paper-2); color:var(--ink-600);"><i class="ri-delete-bin-line mr-1 text-xs"></i>Disposal</span>',
                'transfer': '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background:var(--gold-100); color:var(--navy-900);"><i class="ri-arrow-left-right-line mr-1 text-xs"></i>Transfer</span>',
                'replacement': '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background:var(--plum-tint); color:var(--plum-dark);"><i class="ri-refresh-line mr-1 text-xs"></i>Replacement</span>',
                'pullout': '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background:var(--bronze-tint); color:var(--bronze-dark);"><i class="ri-logout-box-line mr-1 text-xs"></i>Pullout</span>',
                'other': '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background:var(--paper-2); color:var(--ink-600);"><i class="ri-file-list-3-line mr-1 text-xs"></i>Other</span>'
            };
            if (badges[type]) {
                return badges[type];
            }

            const label = String(type || 'Other');
            return `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background:var(--paper-2); color:var(--ink-600);">${label.charAt(0).toUpperCase()}${label.slice(1)}</span>`;
        }
        
        function getStatusBadge(status) {
            if (status === 'pending') {
                return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background:var(--bronze-tint); color:var(--bronze-dark);"><i class="ri-time-line mr-1 text-xs"></i>Pending</span>';
            } else if (status === 'approved') {
                return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background:var(--forest-tint); color:var(--forest-dark);"><i class="ri-checkbox-circle-line mr-1 text-xs"></i>Approved</span>';
            } else {
                return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background:var(--brick-tint); color:var(--brick-dark);"><i class="ri-close-circle-line mr-1 text-xs"></i>Rejected</span>';
            }
        }

        async function sendRequestAction(requestId, action) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(`/admin/requests/${requestId}/${action}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf || '',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({})
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || 'Failed to process request.');
            }

            return data;
        }
        
        async function approveRequest(requestId) {
            if (confirm('Are you sure you want to approve this request?')) {
                try {
                    const result = await sendRequestAction(requestId, 'approve');
                    alert(result.message || `Request #REQ-${String(requestId).padStart(4, '0')} approved!`);
                    location.reload();
                } catch (error) {
                    alert(error.message || 'Unable to approve request.');
                }
            }
        }
        
        async function rejectRequest(requestId) {
            if (confirm('Are you sure you want to reject this request?')) {
                try {
                    const result = await sendRequestAction(requestId, 'reject');
                    alert(result.message || `Request #REQ-${String(requestId).padStart(4, '0')} rejected!`);
                    location.reload();
                } catch (error) {
                    alert(error.message || 'Unable to reject request.');
                }
            }
        }
        
        function approveCurrentRequest() {
            if (currentSelectedRequestId) {
                approveRequest(currentSelectedRequestId);
            }
        }
        
        function rejectCurrentRequest() {
            if (currentSelectedRequestId) {
                rejectRequest(currentSelectedRequestId);
            }
        }
        
        // Tab functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tab = this.dataset.tab;
                
                // Update active tab styling
                document.querySelectorAll('.tab-btn').forEach(b => {
                    b.classList.remove('tab-active');
                    b.style.color = 'var(--ink-400)';
                });
                this.classList.add('tab-active');
                this.style.color = '';
                
                // Filter table rows
                filterRequests(tab);
            });
        });
        
 function filterRequests(status) {
    const rows = document.querySelectorAll('#requests-table-body tr');
    
    rows.forEach(row => {
        // Skip the empty-state row
        if (!row.querySelector('td')) return;

        // Status is in the 6th column
        const statusCell = row.querySelector('td:nth-child(6) .status-badge');
        
        if (statusCell) {
            const rowStatus = statusCell.textContent.trim().toLowerCase();
            
            if (status === 'all' || rowStatus === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}
        
        // Read `tab` from query parameters and set initial active tab (defaults to 'all')
        (function() {
            const params = new URLSearchParams(window.location.search);
            const initialTab = params.get('tab') || 'all';
            const btn = document.querySelector(`.tab-btn[data-tab="${initialTab}"]`) || document.querySelector('.tab-btn[data-tab="all"]') || document.querySelector('.tab-btn');
            if (btn) {
                document.querySelectorAll('.tab-btn').forEach(b => { b.classList.remove('tab-active'); b.style.color = 'var(--ink-400)'; });
                btn.classList.add('tab-active');
                btn.style.color = '';
            }
            filterRequests(initialTab);
        })();
    </script>
</body>
</html>