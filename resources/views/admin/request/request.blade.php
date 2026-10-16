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
            --gold-500:#C9A227; --gold-600:#A8841E; --gold-ink:#7E5E0E; --gold-100:#F3E7C4;
            --paper:#F3EEE0; --paper-2:#EAE2C9;
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

        .sidebar-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .sidebar-item.active {
            background-color: rgba(201, 162, 39, 0.10);
            color: #E9C766;
            border-left-color: #C9A227;
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

        /* Long unbroken values (notes, codes, emails) must wrap inside the panel
           instead of widening it and giving the whole page a horizontal scroll. */
        .detail-value {
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .detail-note {
            white-space: pre-line;
            overflow-wrap: anywhere;
            word-break: break-word;
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
    @include('partials.ui')
</head>
<body class="nt-ui">
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')

        <!-- Main Content -->
<main class="flex-1 overflow-y-auto" style="background:var(--paper);">
                <!-- Header (shared admin header) -->
                @include('admin.partials.header', [
                    'adminHeaderPage'     => 'requests',
                    'adminHeaderTitle'    => 'Requests',
                    'adminHeaderIcon'     => 'ri-file-list-3-line',
                    'adminHeaderBadge'    => 'Admin',
                ])

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
                        @php
                            $activeTab = strtolower(trim((string) request('tab', 'all')));
                            if (! in_array($activeTab, ['all', 'pending', 'approved', 'rejected'], true)) {
                                $activeTab = 'all';
                            }
                            $requestTabs = [
                                'all'      => 'All Requests',
                                'pending'  => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ];
                        @endphp
                        @foreach($requestTabs as $tabKey => $tabLabel)
                            <a href="{{ request()->fullUrlWithQuery(['tab' => $tabKey, 'page' => 1]) }}"
                               class="tab-btn pb-3 text-sm font-medium transition whitespace-nowrap {{ $activeTab === $tabKey ? 'tab-active' : 'text-[#5C6474]' }}"
                               style="{{ $activeTab === $tabKey ? '' : 'color:var(--ink-400);' }}">
                                {{ $tabLabel }}
                            </a>
                        @endforeach
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
                                        <th class="eyebrow text-left py-3 px-3 whitespace-nowrap hidden md:table-cell">Type</th>
                                        {{-- Submitted By is the first thing to go on a phone: the row opens the
                                             full request details on tap, and dropping one column is
                                             what keeps the table from needing a sideways drag. --}}
                                        <th class="eyebrow text-left py-3 px-3 hidden sm:table-cell">Submitted By</th>
                                        <th class="eyebrow text-left py-3 px-3 whitespace-nowrap hidden sm:table-cell">Date</th>
                                        <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Status</th>
                                        <th class="eyebrow text-left py-3 px-3 whitespace-nowrap">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="requests-table-body">
                                    @forelse($requests as $request)
                                    <tr class="request-row" style="border-bottom:1px solid var(--line);" data-request-id="{{ $request->id }}" onclick="selectRequest({{ $request->id }})">
                                        <td class="py-3 px-3 text-sm font-mono whitespace-nowrap" style="color:var(--navy-900);">#REQ-{{ str_pad($request->id, 4, '0', STR_PAD_LEFT) }}</td>
                                        <td class="py-3 px-3 text-sm" style="color:var(--navy-900);">{{ $request->asset_name }}</td>
                                        <td class="py-3 px-3 hidden md:table-cell">
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
                                        <td class="py-3 px-3 text-sm hidden sm:table-cell" style="color:var(--ink-600);">{{ $request->submitted_by }}</td>
                                        <td class="py-3 px-3 text-sm whitespace-nowrap hidden lg:table-cell" style="color:var(--ink-600);">{{ data_get($request, 'created_at') ? \Carbon\Carbon::parse(data_get($request, 'created_at'))->format('M d, Y') : '—' }}</td>
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
                                                <button onclick="approveRequest({{ $request->id }})" class="p-1.5 rounded transition" style="color:var(--forest);" onmouseover="this.style.background='var(--forest-tint)'" onmouseout="this.style.background='transparent'" title="{{ $request->type === 'disposal' ? 'Approve disposal request' : 'Approve' }}">
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

                        {{-- 15 requests per page, pending ones first --}}
                        <div class="px-4 sm:px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
                             style="border-top:1px solid var(--line);">
                            <p class="text-xs" style="color:var(--ink-400);">
                                Showing {{ $requests->firstItem() ?? 0 }}–{{ $requests->lastItem() ?? 0 }}
                                of {{ $requests->total() }} requests
                            </p>
                            @if($requests->hasPages())
                                <div class="overflow-x-auto scrollbar-hide">{{ $requests->links() }}</div>
                            @endif
                        </div>
                    </div>

                    <!-- Request Details Section -->
                    <div class="w-full lg:w-96 lg:flex-shrink-0 min-w-0 panel rounded-xl overflow-y-auto" id="request-details-panel">
                        <div class="p-6">
                            <h3 class="font-display text-lg font-semibold mb-4 flex items-center" style="color:var(--navy-900);">
                                <i class="ri-file-info-line mr-2" style="color:var(--gold-ink);"></i>
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
                                        <p class="text-sm detail-value" style="color:var(--navy-900);" id="detail-submitter">-</p>
                                        <p class="text-xs mt-1 detail-value" style="color:var(--ink-400);" id="detail-email">-</p>
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
                                        <p class="text-sm font-semibold detail-value" style="color:var(--navy-900);" id="detail-assigned-to">-</p>
                                    </div>
                                    
                                    <!-- The requester's own statement: shown in full, read-only. -->
                                    <div class="pb-3">
                                        <p class="eyebrow mb-1">User's Note</p>
                                        <p class="text-sm mt-1 leading-relaxed detail-note" style="color:var(--ink-600);" id="detail-description">-</p>
                                        <p class="text-xs mt-2 flex items-center" style="color:var(--ink-400);">
                                            <i class="ri-lock-line mr-1"></i>
                                            Submitted by the requester and cannot be edited.
                                        </p>
                                    </div>

                                    <!-- Disposal requests: the reason the Admin confirms
                                     when approving. It is read from the requester's note,
                                     so nothing has to be re-typed. -->
                                <div class="pb-3" style="border-top:1px solid var(--line); padding-top:1rem; display:none;" id="detail-disposal-block">
                                    <p class="eyebrow mb-1">Disposal Reason</p>
                                    <p class="text-sm font-semibold detail-value" style="color:var(--navy-900);" id="detail-disposal-reason">—</p>
                                    <p class="text-xs mt-1" style="color:var(--ink-400);">
                                        Taken from the requester's note. Approving authorises the official disposal form
                                        with this reason — nothing is disposed of until the signed form is filed and the
                                        disposal is finalized.
                                    </p>
                                </div>

                                <!-- Executive Director review (transfer requests):
                                     whether this transfer is waiting on the Executive
                                     Director, what was sent, and the two actions the
                                     office can take on an invitation. -->
                                <div class="pb-3" style="border-top:1px solid var(--line); padding-top:1rem; display:none;" id="detail-review-block">
                                    <p class="eyebrow mb-1">Executive Director Review</p>
                                    <span id="detail-review-status" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background:var(--paper-2); color:var(--ink-600);">—</span>
                                    <p class="text-xs mt-2" style="color:var(--ink-600);" id="detail-review-hint"></p>
                                    <p class="text-xs mt-1" style="color:var(--ink-400);" id="detail-review-meta"></p>
                                    <p class="text-xs mt-2" style="color:var(--brick-dark);" id="detail-review-problem"></p>
                                    <p class="text-xs mt-2" style="color:var(--ink-400);" id="detail-review-history"></p>
                                    <div class="flex gap-2 mt-3">
                                        <button type="button" id="detail-review-resend" onclick="resendReview()"
                                                class="flex-1 text-white px-3 py-2 rounded-lg transition text-sm font-medium flex items-center justify-center"
                                                style="background:var(--navy-900);" onmouseover="this.style.filter='brightness(1.12)'" onmouseout="this.style.filter='none'">
                                            <i class="ri-send-plane-line mr-1.5"></i><span id="detail-review-resend-label">Send invitation</span>
                                        </button>
                                        <button type="button" id="detail-review-revoke" onclick="revokeReview()"
                                                class="flex-1 px-3 py-2 rounded-lg transition text-sm font-medium flex items-center justify-center"
                                                style="background:var(--paper-2); color:var(--brick-dark);" onmouseover="this.style.filter='brightness(.97)'" onmouseout="this.style.filter='none'">
                                            <i class="ri-link-unlink-m mr-1.5"></i>Withdraw link
                                        </button>
                                    </div>
                                </div>

                                <!-- Admin Remarks are recorded only when a request is rejected. -->
                                    <div class="pb-3" style="border-top:1px solid var(--line); padding-top:1rem; display:none;" id="detail-admin-remarks-block">
                                        <p class="eyebrow mb-1">Admin Remarks</p>
                                        <p class="text-sm detail-note" style="color:var(--ink-600);" id="detail-admin-remarks">—</p>
                                        <p class="text-xs mt-2 detail-value" style="color:var(--ink-400);" id="detail-admin-remarks-meta"></p>
                                        <p class="text-xs mt-1" style="color:var(--ink-400);">
                                            Recorded when this request was rejected.
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="mt-6 pt-5 flex gap-3" style="border-top:1px solid var(--line);" id="detail-actions">
                                    <button onclick="approveCurrentRequest()" id="detail-approve-btn" class="flex-1 text-white px-4 py-2 rounded-lg transition flex items-center justify-center" style="background:var(--forest);" onmouseover="this.style.filter='brightness(1.08)'" onmouseout="this.style.filter='none'">
                                        <i class="ri-checkbox-circle-line mr-2"></i>
                                        <span id="detail-approve-label">Approve</span>
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
                <div class="text-center text-sm mt-10 pt-7" style="color:var(--ink-400); border-top:1px solid var(--line);">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </main>
    </div>

    <!-- Reject Request modal: the reason becomes the request's Admin Remarks -->
    <div id="rejectModal" class="fixed inset-0 hidden items-center justify-center z-50 p-4"
         style="background:rgba(10,24,48,.6);" onclick="closeRejectModal()">
        <div class="panel rounded-xl w-full max-w-md p-6" onclick="event.stopPropagation();">
            <h3 class="font-display text-lg font-semibold" style="color:var(--navy-900);">Reject Request</h3>
            <p class="text-sm mt-1 mb-4 detail-value" id="reject-modal-subtitle" style="color:var(--ink-400);">-</p>

            <label for="reject-reason" class="eyebrow mb-1 block">Reason for rejection</label>
            <textarea id="reject-reason" rows="4" maxlength="500" oninput="updateRejectCounter()"
                      class="w-full px-3 py-2 rounded-lg text-sm"
                      style="border:1px solid var(--line); color:var(--ink-900); resize:vertical;"
                      placeholder="e.g., The asset is still under warranty — submit a repair request instead."></textarea>
            <div class="flex items-center justify-between gap-3 mt-2">
                <p class="text-xs" style="color:var(--ink-400);">Shown to the requester as the Admin Remarks.</p>
                <span id="reject-reason-counter" class="text-xs font-medium whitespace-nowrap" style="color:var(--ink-400);">0 / 500 characters</span>
            </div>

            <p class="text-xs mt-2 hidden" id="reject-modal-status"></p>

            <div class="flex justify-end gap-2 mt-5">
                <button type="button" onclick="closeRejectModal()"
                        class="px-4 py-2 rounded-lg text-sm font-medium"
                        style="background:var(--paper-2); color:var(--ink-600);">
                    Cancel
                </button>
                <button type="button" id="reject-modal-confirm" onclick="confirmReject()"
                        class="px-4 py-2 rounded-lg text-sm font-semibold text-white"
                        style="background:var(--brick);">
                    <i class="ri-close-circle-line mr-1.5"></i>Reject Request
                </button>
            </div>
        </div>
    </div>

    <!-- Send (or re-send) an Executive Director review invitation. The address
         is typed here every time, pre-filled with the one used last, so a change
         of director is a change of text rather than a change of deployment. -->
    <div id="reviewSendModal" class="fixed inset-0 hidden items-center justify-center z-50 p-4"
         style="background:rgba(10,24,48,.6);" onclick="closeReviewSendModal()">
        <div class="panel rounded-xl w-full max-w-md p-6" onclick="event.stopPropagation();">
            <h3 class="font-display text-lg font-semibold" style="color:var(--navy-900);" id="review-send-title">Send Review Invitation</h3>
            <p class="text-sm mt-1 mb-4 detail-value" id="review-send-subtitle" style="color:var(--ink-400);">-</p>

            <label for="review-send-recipient" class="eyebrow mb-1 block">Executive Director's email address</label>
            <input type="email" id="review-send-recipient"
                   class="w-full px-3 py-2 rounded-lg text-sm"
                   style="border:1px solid var(--line); color:var(--ink-900);"
                   placeholder="name@nu-lipa.edu.ph" autocomplete="off">
            <p class="text-xs mt-1" style="color:var(--ink-400);">
                The link is emailed here, and the verification code can only be sent to this mailbox.
            </p>

            <p class="text-xs mt-3" style="color:var(--ink-600);" id="review-send-note"></p>
            <p class="text-xs mt-2 hidden" id="review-send-status"></p>

            <div class="flex justify-end gap-2 mt-5">
                <button type="button" onclick="closeReviewSendModal()"
                        class="px-4 py-2 rounded-lg text-sm font-medium"
                        style="background:var(--paper-2); color:var(--ink-600);">
                    Cancel
                </button>
                <button type="button" id="review-send-confirm" onclick="confirmSendReview()"
                        class="px-4 py-2 rounded-lg text-sm font-semibold text-white"
                        style="background:var(--navy-900);">
                    <i class="ri-send-plane-line mr-1.5"></i><span id="review-send-confirm-label">Send invitation</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Approve transfer request modal: approving a transfer either executes it
         here or hands the decision to the Executive Director. Which one happens
         is the checkbox, and the box starts the way the office configured it. -->
    <div id="transferApproveModal" class="fixed inset-0 hidden items-center justify-center z-50 p-4"
         style="background:rgba(10,24,48,.6);" onclick="closeTransferApproveModal()">
        <div class="panel rounded-xl w-full max-w-md p-6" onclick="event.stopPropagation();">
            <h3 class="font-display text-lg font-semibold" style="color:var(--navy-900);">Approve Transfer Request</h3>
            <p class="text-sm mt-1 mb-4 detail-value" id="transfer-approve-subtitle" style="color:var(--ink-400);">-</p>

            <div class="rounded-lg p-3 mb-4" style="background:var(--paper-2);">
                <p class="eyebrow mb-2">Asset(s) in this transfer</p>
                <div id="transfer-approve-assets" class="space-y-1"></div>
            </div>

            <label class="flex items-start gap-2 rounded-lg p-3 mb-3" style="background:var(--paper-2); cursor:pointer;" id="transfer-forward-wrap">
                <input type="checkbox" id="transfer-forward" checked
                       class="mt-0.5" style="accent-color:var(--gold-600); width:auto;">
                <span class="text-sm" style="color:var(--navy-900); font-weight:600;">
                    Send this transfer to the Executive Director
                    <span class="block text-xs mt-1" style="color:var(--ink-600); font-weight:500;" id="transfer-forward-note"></span>
                </span>
            </label>

            {{-- The office chooses who is asked. The Executive Director is a
                 person and people change, so this is typed here rather than
                 fixed in the server's configuration: a new director needs no
                 deployment, and whoever sends the transfer knows the answer. --}}
            <div class="mb-3" id="transfer-forward-address">
                <label for="transfer-recipient" class="eyebrow mb-1 block">Executive Director's email address</label>
                <input type="email" id="transfer-recipient"
                       class="w-full px-3 py-2 rounded-lg text-sm"
                       style="border:1px solid var(--line); color:var(--ink-900);"
                       placeholder="name@nu-lipa.edu.ph" autocomplete="off">
                <p class="text-xs mt-1" style="color:var(--ink-400);">
                    The review link is emailed here, and this is the only mailbox the 6-digit verification code can be sent to.
                </p>
            </div>

            <p class="text-xs mt-1" style="color:var(--ink-600);" id="transfer-approve-explainer"></p>
            <p class="text-xs mt-2 hidden" id="transfer-approve-status"></p>

            <div class="flex justify-end gap-2 mt-5">
                <button type="button" onclick="closeTransferApproveModal()"
                        class="px-4 py-2 rounded-lg text-sm font-medium"
                        style="background:var(--paper-2); color:var(--ink-600);">
                    Cancel
                </button>
                <button type="button" id="transfer-approve-confirm" onclick="confirmTransferApprove()"
                        class="px-4 py-2 rounded-lg text-sm font-semibold text-white"
                        style="background:var(--forest);">
                    <i class="ri-checkbox-circle-line mr-1.5"></i>Approve request
                </button>
            </div>
        </div>
    </div>

    <!-- Approve disposal request modal: approval authorises preparation of the
         official NU Lipa disposal form, so the reason and date travel with it.
         Approving disposes of nothing — the asset keeps its status until the
         signed form is recorded and the disposal is finalized. -->
    <div id="approveModal" class="fixed inset-0 hidden items-center justify-center z-50 p-4"
         style="background:rgba(10,24,48,.6);" onclick="closeApproveModal()">
        <div class="panel rounded-xl w-full max-w-md p-6" onclick="event.stopPropagation();">
            <h3 class="font-display text-lg font-semibold" style="color:var(--navy-900);">Approve Disposal Request</h3>
            <p class="text-sm mt-1 mb-4 detail-value" id="approve-modal-subtitle" style="color:var(--ink-400);">-</p>

            <div class="rounded-lg p-3 mb-4" style="background:var(--paper-2);">
                <p class="eyebrow mb-2">Asset(s) to be disposed</p>
                <div id="approve-modal-assets" class="space-y-1"></div>
            </div>

            <label for="approve-reason" class="eyebrow mb-1 block">Disposal reason</label>
            <select id="approve-reason" class="w-full px-3 py-2 rounded-lg text-sm mb-3"
                    style="border:1px solid var(--line); color:var(--ink-900);">
                @foreach(($disposalReasons ?? []) as $reason)
                    <option value="{{ $reason }}">{{ $reason }}</option>
                @endforeach
            </select>
            <p class="text-xs mb-4" style="color:var(--ink-400);">
                Pre-filled from the requester's note — change it only if it is wrong.
            </p>

            <label for="approve-date" class="eyebrow mb-1 block">Disposal date</label>
            <input type="date" id="approve-date" class="w-full px-3 py-2 rounded-lg text-sm"
                   style="border:1px solid var(--line); color:var(--ink-900);">

            <p class="text-xs mt-3" style="color:var(--ink-600);">
                Approving authorises preparation of the <strong>official NU Lipa Disposal Form</strong> and
                notifies the requester. It does <strong>not</strong> dispose of anything: the asset keeps its
                current status until the signed form has been received and the disposal is finalized
                on the Disposal page.
            </p>

            <p class="text-xs mt-2 hidden" id="approve-modal-status"></p>

            <div class="flex justify-end gap-2 mt-5">
                <button type="button" onclick="closeApproveModal()"
                        class="px-4 py-2 rounded-lg text-sm font-medium"
                        style="background:var(--paper-2); color:var(--ink-600);">
                    Cancel
                </button>
                <button type="button" id="approve-modal-confirm" onclick="confirmApprove()"
                        class="px-4 py-2 rounded-lg text-sm font-semibold text-white"
                        style="background:var(--forest);">
                    <i class="ri-checkbox-circle-line mr-1.5"></i>Approve request
                </button>
            </div>
        </div>
    </div>

    <script>
        // Sample requests data (replace with your actual data from Laravel)
        const requestsData = @json($requests?->items() ?? []);
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

                // Admin remarks — read-only, only ever set by a rejection.
                const remarksBlock = document.getElementById('detail-admin-remarks-block');
                const remarksEl    = document.getElementById('detail-admin-remarks');
                const remarksMeta  = document.getElementById('detail-admin-remarks-meta');
                if (remarksBlock) {
                    if (request.admin_remarks) {
                        remarksEl.textContent = request.admin_remarks;
                        remarksMeta.textContent = `Recorded by ${request.admin_remarks_by || 'Asset Management Office'}${request.admin_remarks_at ? ' on ' + request.admin_remarks_at : ''}`;
                        remarksBlock.style.display = 'block';
                    } else {
                        remarksBlock.style.display = 'none';
                    }
                }
                
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

                // Disposal requests: the reason the approval will record, and the
                // matching label on the action button.
                const isDisposal = request.type === 'disposal';
                const disposalBlock = document.getElementById('detail-disposal-block');
                if (disposalBlock) {
                    if (isDisposal) {
                        document.getElementById('detail-disposal-reason').textContent = request.disposal_reason || '—';
                        disposalBlock.style.display = 'block';
                    } else {
                        disposalBlock.style.display = 'none';
                    }
                }

                const approveLabel = document.getElementById('detail-approve-label');
                if (approveLabel) {
                    approveLabel.textContent = isDisposal ? 'Approve disposal request' : 'Approve';
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
                            <p class="text-sm font-medium detail-value" style="color:var(--navy-900);">${escapeHtml(asset.name || 'Unnamed')}</p>
                            <p class="text-xs font-mono mt-0.5 detail-value" style="color:var(--ink-400);">${escapeHtml(asset.code || '')}</p>
                        `;
                        assetsContainer.appendChild(div);
                    });
                }

                // ─── Executive Director review (transfers only) ─────────
                renderReviewPanel(request);
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
                'pullout': '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background:var(--bronze-tint); color:var(--bronze-dark);"><i class="ri-archive-drawer-line mr-1 text-xs"></i>Pullout</span>',
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

        // ── Executive Director review ────────────────────────────────────────
        // A transfer request the office approves either executes immediately or
        // goes to the Executive Director for the final decision. This panel says
        // which of the two is happening, and offers the only two things that can
        // be done to an invitation: send one (or a replacement), or withdraw the
        // link the office is waiting on.
        function reviewPill(review) {
            const base = 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium';
            const outstanding = review && review.outstanding;

            if (outstanding) {
                return outstanding.status === 'verified'
                    ? `<span class="${base}" style="background:var(--forest-tint); color:var(--forest-dark);"><i class="ri-mail-check-line mr-1 text-xs"></i>Verified — awaiting decision</span>`
                    : `<span class="${base}" style="background:var(--gold-100); color:var(--navy-900);"><i class="ri-mail-send-line mr-1 text-xs"></i>Awaiting verification</span>`;
            }

            const last = (review && review.history && review.history.length) ? review.history[0] : null;

            if (last && last.decided_at) {
                return `<span class="${base}" style="background:var(--forest-tint); color:var(--forest-dark);"><i class="ri-checkbox-circle-line mr-1 text-xs"></i>${escapeHtml(last.decision_label || 'Reviewed')}</span>`;
            }

            if (last) {
                return `<span class="${base}" style="background:var(--paper-2); color:var(--ink-600);"><i class="ri-link-unlink-m mr-1 text-xs"></i>Invitation withdrawn</span>`;
            }

            return `<span class="${base}" style="background:var(--paper-2); color:var(--ink-600);"><i class="ri-mail-line mr-1 text-xs"></i>Not sent yet</span>`;
        }

        function renderReviewPanel(request) {
            const block = document.getElementById('detail-review-block');
            if (!block) return;

            const review = request.review;

            if (request.type !== 'transfer' || !review || !review.tables_ready) {
                block.style.display = 'none';
                return;
            }

            block.style.display = 'block';

            const statusEl  = document.getElementById('detail-review-status');
            const hintEl    = document.getElementById('detail-review-hint');
            const metaEl    = document.getElementById('detail-review-meta');
            const problemEl = document.getElementById('detail-review-problem');
            const historyEl = document.getElementById('detail-review-history');
            const resendEl  = document.getElementById('detail-review-resend');
            const resendLabel = document.getElementById('detail-review-resend-label');
            const revokeEl  = document.getElementById('detail-review-revoke');

            const outstanding = review.outstanding;
            const pending = request.status === 'pending';

            if (statusEl) statusEl.innerHTML = reviewPill(review);

            if (hintEl) {
                hintEl.textContent = outstanding
                    ? outstanding.status_hint
                    : (pending
                        ? 'Approving this transfer can send it to the Executive Director for the final decision.'
                        : 'The office is not waiting on the Executive Director for this transfer right now.');
            }

            if (metaEl) {
                if (outstanding) {
                    metaEl.textContent = `Sent ${outstanding.sent_at || '—'}`
                        + (outstanding.sent_count > 1 ? ` (sent ${outstanding.sent_count} times)` : '')
                        + ` to ${outstanding.recipient_email || '—'}`
                        + ` · the link expires ${outstanding.expires_at || '—'}`
                        + (outstanding.verified_at ? ` · mailbox verified ${outstanding.verified_at}` : '');
                } else if (review.recipient_email) {
                    metaEl.textContent = `Ready to send to ${review.recipient_email} — you can change that address when you send it.`;
                } else {
                    metaEl.textContent = 'Type the Executive Director\u2019s email address when you send the invitation.';
                }
            }

            if (problemEl) {
                // While the request is still Pending, "not approved yet" is not a
                // problem to report — it is the next step, described above.
                problemEl.textContent = (!pending && review.problem) ? review.problem : '';
            }

            if (resendLabel) {
                resendLabel.textContent = outstanding ? 'Send a new link' : 'Send invitation';
            }

            if (resendEl) resendEl.style.display = review.can_send ? 'flex' : 'none';
            if (revokeEl) revokeEl.style.display = review.can_revoke ? 'flex' : 'none';

            if (historyEl) {
                const past = (review.history || []).filter(h => h.id !== (outstanding ? outstanding.id : null));
                historyEl.innerHTML = past.length
                    ? past.map(h => `<span class="block"><i class="ri-history-line mr-1"></i>${escapeHtml(h.sent_at || '—')} to ${escapeHtml(h.recipient_email || '—')} · ${escapeHtml(h.decision_label || h.status_label)}${h.revoked_reason ? ' · ' + escapeHtml(h.revoked_reason) : ''}</span>`).join('')
                    : '';
            }
        }

        // ── Sending the invitation ───────────────────────────────────────────
        // The address is part of the action, not a setting: whoever sends a
        // transfer knows who the Executive Director is right now.
        let pendingReviewSendId = null;

        function openReviewSendModal(requestId) {
            const modal = document.getElementById('reviewSendModal');
            if (!modal) return;

            const request = requestsData.find(r => r.id == requestId);
            const review = (request && request.review) || {};
            const outstanding = review.outstanding;

            pendingReviewSendId = requestId;

            const subtitle = document.getElementById('review-send-subtitle');
            if (subtitle) {
                subtitle.textContent = `#REQ-${String(requestId).padStart(4, '0')} · ${(request && request.asset_name) || ''}`;
            }

            const title = document.getElementById('review-send-title');
            if (title) {
                title.textContent = outstanding ? 'Send a New Review Invitation' : 'Send Review Invitation';
            }

            const label = document.getElementById('review-send-confirm-label');
            if (label) {
                label.textContent = outstanding ? 'Send a new link' : 'Send invitation';
            }

            const recipient = document.getElementById('review-send-recipient');
            if (recipient) {
                recipient.value = review.recipient_email || review.default_recipient || '';
            }

            const note = document.getElementById('review-send-note');
            if (note) {
                note.textContent = outstanding
                    ? 'This withdraws the invitation already sent — that link stops working immediately — and sends a new one to the address above.'
                    : 'The transfer is approved and waits for the Executive Director: no asset changes hands until a decision comes back.';
            }

            const status = document.getElementById('review-send-status');
            if (status) status.classList.add('hidden');

            const confirmBtn = document.getElementById('review-send-confirm');
            if (confirmBtn) confirmBtn.disabled = false;

            modal.classList.remove('hidden');
            modal.classList.add('flex');

            if (recipient) recipient.focus();
        }

        function closeReviewSendModal() {
            const modal = document.getElementById('reviewSendModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            pendingReviewSendId = null;
        }

        async function confirmSendReview() {
            const id = pendingReviewSendId;
            if (!id) return;

            const recipient = document.getElementById('review-send-recipient');
            const address = recipient ? recipient.value.trim() : '';
            const status = document.getElementById('review-send-status');
            const confirmBtn = document.getElementById('review-send-confirm');

            if (!address) {
                if (status) {
                    status.textContent = 'Enter the email address the Executive Director reads.';
                    status.classList.remove('hidden');
                }
                if (recipient) recipient.focus();
                return;
            }

            if (confirmBtn) confirmBtn.disabled = true;
            if (status) status.classList.add('hidden');

            try {
                const result = await sendRequestAction(id, 'review/resend', { recipient_email: address });
                closeReviewSendModal();
                alert(result.message || 'Invitation sent.');
                location.reload();
            } catch (error) {
                if (status) {
                    status.textContent = error.message || 'The invitation could not be sent.';
                    status.classList.remove('hidden');
                }
                if (confirmBtn) confirmBtn.disabled = false;
            }
        }

        function resendReview() {
            if (currentSelectedRequestId) {
                openReviewSendModal(currentSelectedRequestId);
            }
        }

        async function revokeReview() {
            const id = currentSelectedRequestId;
            if (!id) return;

            const reason = prompt(
                'Withdraw the review link? The Executive Director will no longer be able to submit a decision through it.\n\nReason (optional):',
                ''
            );

            if (reason === null) return;

            const button = document.getElementById('detail-review-revoke');
            if (button) button.disabled = true;

            try {
                const result = await sendRequestAction(id, 'review/revoke', { reason: reason });
                alert(result.message || 'The invitation was withdrawn.');
                location.reload();
            } catch (error) {
                alert(error.message || 'The invitation could not be withdrawn.');
                if (button) button.disabled = false;
            }
        }

        async function sendRequestAction(requestId, action, payload = {}) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(`/admin/requests/${requestId}/${action}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf || '',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || 'Failed to process request.');
            }

            return data;
        }
        
        // ── Approval ─────────────────────────────────────────────────────────
        // Approving a Disposal request creates the disposal record and retires the
        // asset, so it goes through its own confirmation step. The reason is read
        // from the requester's note and the date defaults to today — the Admin
        // confirms them, and never re-enters what the requester already submitted.
        async function approveRequest(requestId) {
            const request = requestsData.find(r => r.id == requestId);

            if (request && request.type === 'disposal') {
                openApproveModal(requestId);
                return;
            }

            // A transfer asks a second question — execute it here, or hand the
            // decision to the Executive Director — so it gets a dialog rather
            // than a bare confirm.
            if (request && request.type === 'transfer' && request.review && request.review.tables_ready) {
                openTransferApproveModal(requestId);
                return;
            }

            if (!confirm('Are you sure you want to approve this request?')) return;
            await submitApproval(requestId, {});
        }

        // ── Approving a transfer ─────────────────────────────────────────────
        let pendingTransferApproveId = null;

        function openTransferApproveModal(requestId) {
            const modal = document.getElementById('transferApproveModal');
            if (!modal) return;

            const request = requestsData.find(r => r.id == requestId);
            if (!request) return;

            pendingTransferApproveId = requestId;

            const sub = document.getElementById('transfer-approve-subtitle');
            if (sub) {
                sub.textContent = `#REQ-${String(requestId).padStart(4, '0')} · ${request.asset_name || ''}`;
            }

            const list = document.getElementById('transfer-approve-assets');
            if (list) {
                list.innerHTML = '';
                (request.assets || []).forEach(asset => {
                    const row = document.createElement('div');
                    row.className = 'text-sm detail-value';
                    row.style.color = 'var(--ink-600)';
                    row.innerHTML = `<i class="ri-record-circle-line mr-1.5 text-xs" style="color:var(--gold-ink);"></i>${escapeHtml(asset.name || 'Unnamed')} <span class="font-mono text-xs" style="color:var(--ink-400);">${escapeHtml(asset.code || '')}</span>`;
                    list.appendChild(row);
                });
            }

            const review = request.review || {};
            const box = document.getElementById('transfer-forward');
            const note = document.getElementById('transfer-forward-note');
            const status = document.getElementById('transfer-approve-status');
            const confirmBtn = document.getElementById('transfer-approve-confirm');

            // can_forward, not can_send: this dialog is shown while the request
            // is still Pending, and asking whether it may be forwarded *once
            // approved* is the question that belongs here.
            if (box) {
                box.disabled = !review.can_forward;
                box.checked = !!review.can_forward && review.forward_default !== false;
            }

            const recipientInput = document.getElementById('transfer-recipient');
            if (recipientInput) {
                recipientInput.value = review.recipient_email || review.default_recipient || '';
            }

            if (note) {
                note.textContent = review.can_forward
                    ? 'The review link is emailed to the address below, and nothing moves until the decision comes back.'
                    : (review.forward_problem || 'This transfer can only be approved here.');
            }

            if (status) status.classList.add('hidden');
            if (confirmBtn) confirmBtn.disabled = false;

            updateTransferApproveExplainer();

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeTransferApproveModal() {
            const modal = document.getElementById('transferApproveModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            pendingTransferApproveId = null;
        }

        // The checkbox is the whole difference between the two outcomes, so the
        // sentence under it is rewritten every time it changes.
        function updateTransferApproveExplainer() {
            const box = document.getElementById('transfer-forward');
            const explainer = document.getElementById('transfer-approve-explainer');
            const addressWrap = document.getElementById('transfer-forward-address');
            const recipient = document.getElementById('transfer-recipient');
            const forwarding = !!(box && box.checked && !box.disabled);

            // The address only matters while the box is ticked, so it is shown
            // and asked for only then — and required, because an invitation with
            // nowhere to go is not an invitation.
            if (addressWrap) addressWrap.style.display = forwarding ? 'block' : 'none';
            if (recipient) {
                recipient.disabled = !forwarding;
                recipient.required = forwarding;
            }

            if (!explainer) return;

            if (forwarding) {
                explainer.innerHTML = 'The request is recorded as approved and an invitation is emailed to the address above. '
                    + '<strong>No asset changes hand yet</strong> — the transfer is applied only for the assets the Executive Director approves.';
            } else {
                explainer.innerHTML = 'The transfer is executed now: every asset listed above is reassigned to the employee who requested the transfer.';
            }
        }

        async function confirmTransferApprove() {
            const id = pendingTransferApproveId;
            if (!id) return;

            const box = document.getElementById('transfer-forward');
            const forward = !!(box && box.checked && !box.disabled);
            const recipient = document.getElementById('transfer-recipient');
            const address = recipient ? recipient.value.trim() : '';
            const confirmBtn = document.getElementById('transfer-approve-confirm');
            const status = document.getElementById('transfer-approve-status');

            if (forward && !address) {
                if (status) {
                    status.textContent = 'Enter the email address the Executive Director reads, or untick the box to complete the transfer here.';
                    status.classList.remove('hidden');
                }
                if (recipient) recipient.focus();
                return;
            }

            if (confirmBtn) confirmBtn.disabled = true;
            if (status) status.classList.add('hidden');

            try {
                const result = await sendRequestAction(id, 'approve', {
                    forward_for_review: forward,
                    recipient_email: forward ? address : null,
                });
                closeTransferApproveModal();
                alert(result.message || 'Request approved.');
                location.reload();
            } catch (error) {
                if (status) {
                    status.textContent = error.message || 'Unable to approve request.';
                    status.classList.remove('hidden');
                }
                if (confirmBtn) confirmBtn.disabled = false;
            }
        }

        async function submitApproval(requestId, payload) {
            try {
                const result = await sendRequestAction(requestId, 'approve', payload);
                closeApproveModal();
                alert(result.message || `Request #REQ-${String(requestId).padStart(4, '0')} approved!`);
                location.reload();
            } catch (error) {
                alert(error.message || 'Unable to approve request.');
                throw error;
            }
        }

        let pendingApproveId = null;

        function openApproveModal(requestId) {
            const modal = document.getElementById('approveModal');
            if (!modal) return;

            const request = requestsData.find(r => r.id == requestId);
            pendingApproveId = requestId;

            if (request && request.disposal_recorded) {
                alert('This disposal request has already been recorded as disposed.');
                pendingApproveId = null;
                return;
            }

            const sub = document.getElementById('approve-modal-subtitle');
            if (sub) {
                sub.textContent = request
                    ? `#REQ-${String(requestId).padStart(4, '0')} · ${request.asset_name || ''}`
                    : `#REQ-${String(requestId).padStart(4, '0')}`;
            }

            // Show exactly which assets this approval retires.
            const list = document.getElementById('approve-modal-assets');
            if (list) {
                list.innerHTML = '';
                (request?.assets || []).forEach(asset => {
                    const row = document.createElement('div');
                    row.className = 'text-sm detail-value';
                    row.style.color = 'var(--ink-600)';
                    row.innerHTML = `<i class="ri-record-circle-line mr-1.5 text-xs" style="color:var(--gold-ink);"></i>${escapeHtml(asset.name || 'Unnamed')} <span class="font-mono text-xs" style="color:var(--ink-400);">${escapeHtml(asset.code || '')}</span>`;
                    list.appendChild(row);
                });
            }

            const reason = document.getElementById('approve-reason');
            if (reason) reason.value = request?.disposal_reason || 'Obsolete';

            const date = document.getElementById('approve-date');
            if (date) date.value = request?.disposal_date || new Date().toISOString().slice(0, 10);

            const status = document.getElementById('approve-modal-status');
            if (status) status.classList.add('hidden');

            const confirmBtn = document.getElementById('approve-modal-confirm');
            if (confirmBtn) confirmBtn.disabled = false;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeApproveModal() {
            const modal = document.getElementById('approveModal');
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            pendingApproveId = null;
        }

        async function confirmApprove() {
            if (!pendingApproveId) return;

            const requestId = pendingApproveId;
            const confirmBtn = document.getElementById('approve-modal-confirm');
            const status = document.getElementById('approve-modal-status');

            const payload = {
                disposal_reason: document.getElementById('approve-reason')?.value || '',
                disposal_date: document.getElementById('approve-date')?.value || '',
            };

            if (confirmBtn) confirmBtn.disabled = true;
            if (status) {
                status.textContent = 'Approving and recording the disposal…';
                status.classList.remove('hidden');
                status.style.color = 'var(--ink-400)';
            }

            try {
                await submitApproval(requestId, payload);
            } catch (error) {
                if (status) {
                    status.textContent = error.message || 'Unable to approve this disposal request.';
                    status.classList.remove('hidden');
                    status.style.color = 'var(--brick)';
                }
                if (confirmBtn) confirmBtn.disabled = false;
            }
        }
        
        // ── Rejection ────────────────────────────────────────────────────────
        // Rejecting is the only place the Admin records remarks: the reason is
        // stored as admin_remarks and shown to the requester.
        let pendingRejectId = null;

        function rejectRequest(requestId) {
            openRejectModal(requestId);
        }

        function openRejectModal(requestId) {
            const modal = document.getElementById('rejectModal');
            if (!modal) return;

            pendingRejectId = requestId;

            const request = requestsData.find(r => r.id == requestId);
            const reason  = document.getElementById('reject-reason');
            const sub     = document.getElementById('reject-modal-subtitle');
            const status  = document.getElementById('reject-modal-status');

            if (reason) reason.value = '';
            if (sub) {
                sub.textContent = request
                    ? `#REQ-${String(requestId).padStart(4, '0')} · ${request.asset_name || ''}`
                    : `#REQ-${String(requestId).padStart(4, '0')}`;
            }
            if (status) status.classList.add('hidden');

            updateRejectCounter();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            reason?.focus();
        }

        function closeRejectModal() {
            const modal = document.getElementById('rejectModal');
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            pendingRejectId = null;
        }

        function updateRejectCounter() {
            const input   = document.getElementById('reject-reason');
            const counter = document.getElementById('reject-reason-counter');
            if (!input || !counter) return;
            const length = input.value.length;
            counter.textContent = `${length} / 500 characters`;
            counter.style.color = length >= 500 ? 'var(--brick)' : 'var(--ink-400)';
        }

        async function confirmReject() {
            if (!pendingRejectId) return;

            const reason  = (document.getElementById('reject-reason')?.value || '').trim();
            const status  = document.getElementById('reject-modal-status');
            const confirm = document.getElementById('reject-modal-confirm');

            if (reason.length > 500) {
                if (status) {
                    status.textContent = 'The rejection reason cannot exceed 500 characters.';
                    status.classList.remove('hidden');
                    status.style.color = 'var(--brick)';
                }
                return;
            }

            const requestId = pendingRejectId;
            if (confirm) confirm.disabled = true;
            if (status) {
                status.textContent = 'Rejecting...';
                status.classList.remove('hidden');
                status.style.color = 'var(--ink-400)';
            }

            try {
                const result = await sendRequestAction(requestId, 'reject', { reason });
                closeRejectModal();
                alert(result.message || `Request #REQ-${String(requestId).padStart(4, '0')} rejected!`);
                location.reload();
            } catch (error) {
                if (status) {
                    status.textContent = error.message || 'Unable to reject request.';
                    status.classList.remove('hidden');
                    status.style.color = 'var(--brick)';
                }
                if (confirm) confirm.disabled = false;
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
        
        // The status tabs are ordinary links (?tab=pending&page=1): the server
        // filters and paginates, so a tab always shows real rows instead of
        // hiding the few rows that happen to be on the current page.

        // Escape closes whichever modal is open
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeRejectModal();
                closeApproveModal();
                closeTransferApproveModal();
                closeReviewSendModal();
            }
        });

        // Registered once, not per opening: the sentence under the forward box
        // follows the box, and a listener added on every open would stack up.
        const transferForwardBox = document.getElementById('transfer-forward');
        if (transferForwardBox) {
            transferForwardBox.addEventListener('change', updateTransferApproveExplainer);
        }
    </script>
</body>
</html>