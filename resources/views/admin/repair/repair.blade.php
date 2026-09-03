<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Repair Management - Admin Dashboard</title>
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
            -webkit-font-smoothing: antialiased;
            background: var(--paper);
            color: var(--ink-900);
        }

        .font-display{ font-family:'Fraunces',serif; }
        .font-mono{ font-family:'IBM Plex Mono',monospace; }
        .eyebrow{ font-size:.68rem; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--ink-400); }

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
        .btn-ghost{ font-family:'Inter',sans-serif; font-weight:500; border-radius:9px; padding:.6rem 1.1rem; color:var(--navy-800); border:1px solid var(--line); background:#fff; transition:background .15s; }
        .btn-ghost:hover{ background:var(--paper-2); }

        .stat-card{ background:#fff; border:1px solid var(--line); border-radius:14px; box-shadow: 0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28); }

        .repair-card {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            box-shadow: 0 1px 2px rgba(10,24,48,.05), 0 10px 26px -18px rgba(10,24,48,.28);
            transition: all 0.2s ease;
        }

        .repair-card:hover {
            border-color: var(--gold-500);
            box-shadow: 0 2px 4px rgba(10,24,48,.06), 0 14px 30px -14px rgba(10, 24, 48, 0.3);
        }

        .status-badge {
            transition: all 0.2s ease;
        }

        .filter-btn {
            transition: all 0.15s ease;
            color: var(--ink-400);
            position: relative;
        }

        .filter-btn.active {
            color: var(--navy-900) !important;
            font-weight: 600;
        }
        .filter-btn.active::after{ content:""; position:absolute; left:0; right:0; bottom:-1px; height:2px; background:var(--gold-500); }

        .modal {
            transition: all 0.2s ease;
        }

        .modal.show {
            display: flex;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(12px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-panel {
            animation: slideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .modal-head{ background:linear-gradient(135deg,var(--navy-950),var(--navy-800)); position:relative; }
        .modal-head::after{ content:""; position:absolute; left:0; right:0; bottom:0; height:2px; background:var(--gold-500); }
        .form-input{ width:100%; border:1px solid var(--line); border-radius:9px; padding:.6rem .9rem; font-size:.9rem; outline:none; transition:border-color .15s, box-shadow .15s; }
        .form-input:focus{ border-color:var(--gold-500); box-shadow:0 0 0 3px rgba(201,162,39,.18); }

        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

        .detail-field dt {
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: var(--ink-400);
            margin-bottom: 0.25rem;
        }

        .detail-field dd {
            font-size: 0.9375rem;
            color: var(--navy-900);
            font-weight: 500;
        }
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
                                <h2 class="font-display text-xl sm:text-2xl font-semibold tracking-tight" style="color:var(--navy-900);">Repair Management</h2>
                                <div class="flex items-center mt-1.5">
                                    <span class="badge-role inline-flex items-center gap-1.5 text-xs font-semibold px-2 py-0.5 rounded-md">
                                        <i class="ri-tools-line"></i> Asset Officer
                                    </span>
                                    <p class="text-sm hidden sm:block ml-3" style="color:var(--ink-600);">Manage and track all repair requests</p>
                                </div>
                            </div>
                        </div>
                        <button onclick="openNewRepairModal()" class="btn-gold w-full sm:w-auto justify-center">
                            <i class="ri-add-line mr-1.5 text-base"></i>
                            New Repair Request
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-4 sm:p-8">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 sm:gap-5 mb-6 sm:mb-8">
                    <div class="stat-card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="eyebrow">Total Repairs</p>
                                <p class="text-3xl font-bold mt-2" style="color:var(--navy-900);" id="totalRepairs">0</p>
                            </div>
                            <div class="w-11 h-11 rounded-lg flex items-center justify-center" style="background:var(--steel-tint);">
                                <i class="ri-tools-line text-xl" style="color:var(--steel);"></i>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="eyebrow">Pending</p>
                                <p class="text-3xl font-bold mt-2" style="color:var(--navy-900);" id="pendingRepairs">0</p>
                            </div>
                            <div class="w-11 h-11 rounded-lg flex items-center justify-center" style="background:var(--bronze-tint);">
                                <i class="ri-time-line text-xl" style="color:var(--bronze);"></i>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="eyebrow">In Progress</p>
                                <p class="text-3xl font-bold mt-2" style="color:var(--navy-900);" id="inProgressRepairs">0</p>
                            </div>
                            <div class="w-11 h-11 rounded-lg flex items-center justify-center" style="background:var(--steel-tint);">
                                <i class="ri-refresh-line text-xl" style="color:var(--steel);"></i>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card p-5">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="eyebrow">Completed</p>
                                <p class="text-3xl font-bold mt-2" style="color:var(--navy-900);" id="completedRepairs">0</p>
                            </div>
                            <div class="w-11 h-11 rounded-lg flex items-center justify-center" style="background:var(--forest-tint);">
                                <i class="ri-checkbox-circle-line text-xl" style="color:var(--forest);"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Tabs -->
                <div class="mb-6 overflow-x-auto scrollbar-hide">
                    <div class="flex space-x-1 min-w-max" style="border-bottom:1px solid var(--line);">
                        <button class="filter-btn active px-4 py-2.5 text-sm font-medium whitespace-nowrap" data-filter="all">
                            All Requests
                        </button>
                        <button class="filter-btn px-4 py-2.5 text-sm font-medium whitespace-nowrap" data-filter="pending">
                            Pending
                        </button>
                        <button class="filter-btn px-4 py-2.5 text-sm font-medium whitespace-nowrap" data-filter="in_progress">
                            In Progress
                        </button>
                        <button class="filter-btn px-4 py-2.5 text-sm font-medium whitespace-nowrap" data-filter="completed">
                            Completed
                        </button>
                        <button class="filter-btn px-4 py-2.5 text-sm font-medium whitespace-nowrap" data-filter="cancelled">
                            Cancelled
                        </button>
                    </div>
                </div>

                <!-- Repair Requests List -->
                <div id="repairsList" class="space-y-3">
                    <!-- Repair cards will be dynamically inserted here -->
                </div>

                <!-- Empty State -->
                <div id="emptyState" class="hidden p-12 text-center" style="background:#fff; border-radius:14px; border:1px solid var(--line);">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4" style="background:var(--paper-2);">
                        <i class="ri-tools-line text-3xl" style="color:var(--ink-400);"></i>
                    </div>
                    <h3 class="text-base font-semibold mb-1.5" style="color:var(--navy-900);">No repair requests</h3>
                    <p class="text-sm" style="color:var(--ink-400);">There are currently no repair requests to show.</p>
                    <button onclick="openNewRepairModal()" class="btn-gold mt-5">
                        <i class="ri-add-line mr-1.5"></i>
                        Create New Repair Request
                    </button>
                </div>

                <!-- Footer -->
                <div class="text-center text-xs mt-10 pt-6" style="color:var(--ink-400); border-top:1px solid var(--line);">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- New Repair Request Modal -->
    <div id="newRepairModal" class="hidden fixed inset-0 backdrop-blur-sm z-50 items-center justify-center modal p-4" style="background:rgba(10,24,48,.55);">
        <div class="modal-panel rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto" style="background:#fff;">
            <div class="modal-head px-6 py-5 flex justify-between items-center">
                <div>
                    <h3 class="font-display text-lg font-semibold text-white">New Repair Request</h3>
                    <p class="text-xs mt-0.5" style="color:var(--gold-100);">Log an issue for an asset that needs attention</p>
                </div>
                <button onclick="closeNewRepairModal()" class="text-white/60 hover:text-white w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <form id="repairForm" class="p-6">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color:var(--ink-600);">Select Asset <span style="color:var(--brick);">*</span></label>
                        <select name="asset_id" required class="form-input">
                        <option value="">Select asset...</option>
                        @foreach($availableAssets ?? [] as $asset)
                        <option value="{{ $asset->id }}">
                        {{ $asset->Asset_name }} ({{ $asset->Asset_code }})
                        </option>
                        @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color:var(--ink-600);">Issue Description <span style="color:var(--brick);">*</span></label>
                        <textarea name="issue_description" rows="4" required placeholder="Describe the issue in detail..." class="form-input resize-none"></textarea>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:var(--ink-600);">Priority <span style="color:var(--brick);">*</span></label>
                            <select name="priority" required class="form-input">
                                <option value="low">Low - Can wait</option>
                                <option value="medium">Medium - Needs attention soon</option>
                                <option value="high">High - Urgent</option>
                                <option value="critical">Critical - Emergency</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5" style="color:var(--ink-600);">Requested By</label>
                            <input type="text" name="requested_by" placeholder="Name of person requesting repair" class="form-input">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color:var(--ink-600);">Attach Photo (Optional)</label>
                        <div class="rounded-lg p-5 text-center cursor-pointer transition-colors" style="border:2px dashed var(--line);" onmouseover="this.style.borderColor='var(--gold-500)'" onmouseout="this.style.borderColor='var(--line)'" onclick="document.getElementById('repair-photo').click()">
                            <i class="ri-image-add-line text-2xl mb-1 block" style="color:var(--ink-400);"></i>
                            <p class="text-sm font-medium" style="color:var(--ink-600);">Click to upload photo</p>
                            <p class="text-xs mt-0.5" style="color:var(--ink-400);">PNG, JPG up to 10MB</p>
                            <input type="file" id="repair-photo" name="attachment" class="hidden" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6 pt-5" style="border-top:1px solid var(--line);">
                    <button type="button" onclick="closeNewRepairModal()" class="btn-ghost text-sm">Cancel</button>
                    <button type="submit" class="btn-gold text-sm">Create Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View/Edit Repair Modal -->
    <div id="viewRepairModal" class="hidden fixed inset-0 backdrop-blur-sm z-50 items-center justify-center modal p-4" style="background:rgba(10,24,48,.55);">
        <div class="modal-panel rounded-2xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto" style="background:#fff;">
            <div class="modal-head px-6 py-5 flex justify-between items-center sticky top-0 z-10">
                <h3 class="font-display text-lg font-semibold text-white">Repair Request Details</h3>
                <button onclick="closeViewRepairModal()" class="text-white/60 hover:text-white w-8 h-8 rounded-lg flex items-center justify-center transition-colors">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>
            <div class="p-6" id="viewRepairContent">
                <!-- Dynamic content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Use server-provided repairs collection (passed from controller)
        let repairs = @json($repairs ?? []);

        let currentFilter = "all";

        function priorityBadgeClasses(priority) {
            switch (priority) {
                case 'critical': return 'background:var(--brick-tint); color:var(--brick-dark);';
                case 'high': return 'background:var(--bronze-tint); color:var(--bronze-dark);';
                case 'medium': return 'background:var(--gold-100); color:var(--gold-600);';
                case 'low': return 'background:var(--forest-tint); color:var(--forest-dark);';
                default: return 'background:var(--paper-2); color:var(--ink-600);';
            }
        }

        function statusBadgeClasses(status) {
            switch (status) {
                case 'pending': return 'background:var(--bronze-tint); color:var(--bronze-dark);';
                case 'in_progress': return 'background:var(--steel-tint); color:var(--steel-dark);';
                case 'completed': return 'background:var(--forest-tint); color:var(--forest-dark);';
                case 'cancelled': return 'background:var(--paper-2); color:var(--ink-600);';
                default: return 'background:var(--paper-2); color:var(--ink-600);';
            }
        }

        function statusDotColor(status) {
            switch (status) {
                case 'pending': return 'color:var(--bronze);';
                case 'in_progress': return 'color:var(--steel);';
                case 'completed': return 'color:var(--forest);';
                case 'cancelled': return 'color:var(--ink-400);';
                default: return 'color:var(--ink-400);';
            }
        }

        function renderRepairs() {
            const repairsList = document.getElementById('repairsList');
            const emptyState = document.getElementById('emptyState');

            let filteredRepairs = repairs;
            if (currentFilter !== "all") {
                filteredRepairs = repairs.filter(r => r.status === currentFilter);
            }

            if (filteredRepairs.length === 0) {
                repairsList.classList.add('hidden');
                emptyState.classList.remove('hidden');
            } else {
                repairsList.classList.remove('hidden');
                emptyState.classList.add('hidden');

                repairsList.innerHTML = filteredRepairs.map(repair => `
                    <div class="repair-card p-4 sm:p-5" data-id="${repair.id}">
                        <div class="flex justify-between items-start gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center mb-4">
                                    <div class="w-11 h-11 rounded-lg flex items-center justify-center mr-3.5 shrink-0" style="background:var(--brick-tint);">
                                        <i class="ri-tools-line text-lg" style="color:var(--brick);"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="font-semibold text-base truncate" style="color:var(--navy-900);">${repair.asset_name}</h3>
                                        <p class="text-xs font-mono" style="color:var(--ink-400);">${repair.asset_code}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                    <div>
                                        <p class="text-xs mb-0.5" style="color:var(--ink-400);">Issue</p>
                                        <p class="text-sm font-medium" style="color:var(--ink-900);">${repair.issue.substring(0, 50)}${repair.issue.length > 50 ? '...' : ''}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs mb-0.5" style="color:var(--ink-400);">Requested By</p>
                                        <p class="text-sm font-medium" style="color:var(--ink-900);">${repair.requested_by}</p>
                                        <p class="text-xs" style="color:var(--ink-400);">${repair.department}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs mb-0.5" style="color:var(--ink-400);">Date Requested</p>
                                        <p class="text-sm font-medium" style="color:var(--ink-900);">${new Date(repair.date_requested).toLocaleDateString()}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs mb-0.5" style="color:var(--ink-400);">Priority</p>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" style="${priorityBadgeClasses(repair.priority)}">
                                            ${repair.priority.charAt(0).toUpperCase() + repair.priority.slice(1)}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-3">
                                    <span class="status-badge inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium" style="${statusBadgeClasses(repair.status)}"
                                        id="status-badge-${repair.id}">
                                        <i class="ri-circle-fill mr-1.5" style="font-size:8px; ${statusDotColor(repair.status)}"></i>
                                        ${displayStatusLabel(repair.status)}
                                    </span>
                                    ${repair.status === 'completed' && repair.completion_date ? `
                                        <span class="text-xs" style="color:var(--ink-400);">Completed ${new Date(repair.completion_date).toLocaleDateString()}</span>
                                    ` : ''}
                                </div>
                            </div>

                            <div class="flex sm:flex-col gap-1 shrink-0">
                                <button onclick="viewRepairDetails(${repair.id})" title="View" class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors" style="color:var(--ink-600);" onmouseover="this.style.background='var(--steel-tint)'; this.style.color='var(--steel)'" onmouseout="this.style.background='transparent'; this.style.color='var(--ink-600)'">
                                    <i class="ri-eye-line text-lg"></i>
                                </button>
                                ${repair.status !== 'completed' && repair.status !== 'cancelled' ? `
                                    <button onclick="viewRepairDetails(${repair.id})" title="Edit" class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors" style="color:var(--ink-600);" onmouseover="this.style.background='var(--forest-tint)'; this.style.color='var(--forest)'" onmouseout="this.style.background='transparent'; this.style.color='var(--ink-600)'">
                                        <i class="ri-edit-line text-lg"></i>
                                    </button>
                                ` : ''}
                                ${repair.status === 'completed' || repair.status === 'cancelled' ? `
                                    <button onclick="deleteRepair(${repair.id})" title="Delete" class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors" style="color:var(--ink-600);" onmouseover="this.style.background='var(--brick-tint)'; this.style.color='var(--brick)'" onmouseout="this.style.background='transparent'; this.style.color='var(--ink-600)'">
                                        <i class="ri-delete-bin-line text-lg"></i>
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `).join('');
            }

            // Update statistics
            document.getElementById('totalRepairs').textContent = repairs.length;
            document.getElementById('pendingRepairs').textContent = repairs.filter(r => r.status === 'pending').length;
            document.getElementById('inProgressRepairs').textContent = repairs.filter(r => r.status === 'in_progress').length;
            document.getElementById('completedRepairs').textContent = repairs.filter(r => r.status === 'completed').length;
        }

        function viewRepairDetails(id) {
            const repair = repairs.find(r => r.id === id);
            if (repair) {
                const content = `
                    <div class="space-y-6">
                        <div class="flex items-start justify-between pb-5" style="border-bottom:1px solid var(--line);">
                            <div class="flex items-center">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center mr-4 shrink-0" style="background:var(--brick-tint);">
                                    <i class="ri-tools-line text-xl" style="color:var(--brick);"></i>
                                </div>
                                <div>
                                    <h4 class="text-lg font-bold" style="color:var(--navy-900);">${repair.asset_name}</h4>
                                    <p class="text-xs font-mono mt-0.5" style="color:var(--ink-400);">${repair.asset_code}</p>
                                </div>
                            </div>
                            <span class="status-badge inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold shrink-0" style="${statusBadgeClasses(repair.status)}">
                                <i class="ri-circle-fill mr-1.5" style="font-size:8px; ${statusDotColor(repair.status)}"></i>
                                ${displayStatusLabel(repair.status)}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-x-6 gap-y-5">
                            <div class="detail-field col-span-2">
                                <dt>Issue Description</dt>
                                <dd class="font-normal leading-relaxed" style="color:var(--ink-600);">${repair.issue}</dd>
                            </div>
                            <div class="detail-field">
                                <dt>Priority</dt>
                                <dd>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" style="${priorityBadgeClasses(repair.priority)}">
                                        ${repair.priority.charAt(0).toUpperCase() + repair.priority.slice(1)}
                                    </span>
                                </dd>
                            </div>
                            <div class="detail-field">
                                <dt>Date Requested</dt>
                                <dd>${new Date(repair.date_requested).toLocaleDateString()}</dd>
                            </div>
                            <div class="detail-field">
                                <dt>Requested By</dt>
                                <dd>${repair.requested_by}</dd>
                                <p class="text-xs mt-0.5" style="color:var(--ink-400);">${repair.department}</p>
                            </div>
                            ${repair.technician ? `
                            <div class="detail-field">
                                <dt>Assigned Technician</dt>
                                <dd>${repair.technician}</dd>
                            </div>
                            ` : ''}
                            ${repair.estimated_cost ? `
                            <div class="detail-field">
                                <dt>Estimated Cost</dt>
                                <dd>₱${repair.estimated_cost.toFixed(2)}</dd>
                            </div>
                            ` : ''}
                            ${repair.completion_date ? `
                            <div class="detail-field">
                                <dt>Completion Date</dt>
                                <dd>${new Date(repair.completion_date).toLocaleDateString()}</dd>
                            </div>
                            ` : ''}
                        </div>

                        ${repair.notes ? `
                        <div class="pt-5 detail-field" style="border-top:1px solid var(--line);">
                            <dt>Notes</dt>
                            <dd class="font-normal" style="color:var(--ink-600);">${repair.notes}</dd>
                        </div>
                        ` : ''}

                        <div class="pt-5" style="border-top:1px solid var(--line);">
                            <h4 class="eyebrow mb-3">Asset Details</h4>
                            <div class="rounded-xl p-4 grid grid-cols-2 gap-x-6 gap-y-4" style="background:var(--paper-2);">
                                <div class="detail-field"><dt>Serial</dt><dd class="font-normal">${repair.serial_number ?? '-'}</dd></div>
                                <div class="detail-field"><dt>Condition</dt><dd class="font-normal">${repair.condition ?? '-'}</dd></div>
                                <div class="detail-field"><dt>Purchase Price</dt><dd class="font-normal">${repair.purchase_price ? ('₱' + repair.purchase_price.toFixed(2)) : '-'}</dd></div>
                                <div class="detail-field"><dt>Warranty (months)</dt><dd class="font-normal">${repair.warranty_months ?? '-'}</dd></div>
                                <div class="detail-field"><dt>Location</dt><dd class="font-normal">${repair.asset_location ?? '-'}</dd></div>
                                <div class="detail-field"><dt>Supplier / Model</dt><dd class="font-normal">${repair.supplier ?? '-'}${repair.model ? (' / ' + repair.model) : ''}</dd></div>
                            </div>
                        </div>

                        <div class="pt-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3" style="border-top:1px solid var(--line);">
                            <button onclick="closeViewRepairModal()" class="btn-ghost order-2 sm:order-1 text-sm">Close</button>
                                ${(repair.status !== 'completed' && repair.status !== 'cancelled') ? `
                                    <div class="order-1 sm:order-2 flex flex-wrap items-center gap-2">
                                        <span class="text-xs mr-1 hidden sm:inline" style="color:var(--ink-400);">Set status:</span>
                                        <button onclick="changeRepairStatus(${repair.id}, 'pending')" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors" style="background:var(--bronze-tint); color:var(--bronze-dark);">Pending</button>
                                        <button onclick="changeRepairStatus(${repair.id}, 'in_progress')" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors" style="background:var(--steel-tint); color:var(--steel-dark);">In Progress</button>
                                        <button onclick="changeRepairStatus(${repair.id}, 'completed')" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors" style="background:var(--forest-tint); color:var(--forest-dark);">Completed</button>
                                        <button onclick="changeRepairStatus(${repair.id}, 'cancelled')" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors" style="background:var(--paper-2); color:var(--ink-600);">Cancelled</button>
                                    </div>
                                ` : ''}
                        </div>

                        ${(repair.status !== 'completed' && repair.status !== 'cancelled') ? `
                        <div class="pt-5 flex flex-col sm:flex-row gap-3" style="border-top:1px solid var(--line);">
                            <button onclick="sendAssetToReplacement(${repair.id}, ${repair.asset_id}, ${repair.request_id})" class="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors flex items-center justify-center" style="background:var(--plum-tint); color:var(--plum-dark);">
                                <i class="ri-refresh-line mr-2"></i>
                                Send to Replacement
                            </button>
                            <button onclick="sendAssetToDisposal(${repair.id}, ${repair.asset_id}, ${repair.request_id})" class="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors flex items-center justify-center" style="background:var(--brick-tint); color:var(--brick-dark);">
                                <i class="ri-delete-bin-line mr-2"></i>
                                Send to Disposal
                            </button>
                        </div>
                    ` : ''}
                    </div>
                `;
                document.getElementById('viewRepairContent').innerHTML = content;
                document.getElementById('viewRepairModal').classList.remove('hidden');
                document.getElementById('viewRepairModal').classList.add('flex');
            }
        }

            function changeRepairStatus(id, newStatus) {
                const valid = ['pending', 'in_progress', 'completed', 'cancelled'];
                if (!valid.includes(newStatus)) return;
                const repair = repairs.find(r => r.id === id);
                if (!repair) return;

                let cancellationReason = '';
                if (newStatus === 'cancelled') {
                    cancellationReason = prompt('Please enter the reason for cancelling this repair:');
                    if (cancellationReason === null) return; // user pressed Cancel
                    cancellationReason = cancellationReason.trim();
                    if (!cancellationReason) {
                        alert('A cancellation reason is required.');
                        return;
                    }
                }

                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                fetch(`/admin/repairs/${id}/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf || '',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        status: newStatus,
                        cancellation_reason: cancellationReason || undefined
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        repair.status = newStatus;
                        if (newStatus === 'completed') {
                            repair.completion_date = new Date().toISOString().split('T')[0];
                        } else {
                            repair.completion_date = null;
                        }
                        if (newStatus === 'cancelled' && cancellationReason) {
                            repair.notes = (repair.notes ? repair.notes + ' | ' : '') + 'Cancelled: ' + cancellationReason;
                        }
                        renderRepairs();
                        viewRepairDetails(id);
                        alert('Repair status updated to ' + displayStatusLabel(newStatus));
                    } else {
                        alert('Error: ' + (data.message || 'Failed to update repair status'));
                    }
                })
                .catch(err => {
                    alert('Error updating repair status: ' + err.message);
                });
            }

        function deleteRepair(id) {
            if (confirm('Are you sure you want to delete this repair request?')) {
                repairs = repairs.filter(r => r.id !== id);
                renderRepairs();
                alert('Repair request deleted successfully');
            }
        }

        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('active');
                });
                this.classList.add('active');

                currentFilter = this.getAttribute('data-filter');
                renderRepairs();
            });
        });

        // Search functionality
        document.getElementById('searchRepairs')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const filtered = repairs.filter(r =>
                r.asset_name.toLowerCase().includes(searchTerm) ||
                r.asset_code.toLowerCase().includes(searchTerm) ||
                r.requested_by.toLowerCase().includes(searchTerm) ||
                r.issue.toLowerCase().includes(searchTerm)
            );

            const repairsList = document.getElementById('repairsList');
            if (filtered.length === 0) {
                repairsList.innerHTML = '<div class="text-center py-12" style="background:#fff; border-radius:14px; border:1px solid var(--line);"><p class="text-sm" style="color:var(--ink-400);">No matching repair requests found</p></div>';
            } else {
                repairsList.innerHTML = filtered.map(repair => `
                    <div class="repair-card p-5">
                        <!-- Same card structure as above -->
                    </div>
                `).join('');
            }
        });

        // Modal functions
        function displayStatusLabel(status) {
            if (!status) return '';
            switch(status) {
                case 'in_progress': return 'In Progress';
                case 'pending': return 'Pending';
                case 'completed': return 'Completed';
                case 'cancelled': return 'Cancelled';
                default: return status.charAt(0).toUpperCase() + status.slice(1);
            }
        }

        function openNewRepairModal() {
            document.getElementById('newRepairModal').classList.remove('hidden');
            document.getElementById('newRepairModal').classList.add('flex');
        }

        function closeNewRepairModal() {
            document.getElementById('newRepairModal').classList.add('hidden');
            document.getElementById('newRepairModal').classList.remove('flex');
            document.getElementById('repairForm').reset();
        }

        function closeViewRepairModal() {
            document.getElementById('viewRepairModal').classList.add('hidden');
            document.getElementById('viewRepairModal').classList.remove('flex');
        }

        // Send asset to replacement — requires a reason note
        function sendAssetToReplacement(repairId, assetId, requestId) {
            const reason = prompt(
                'Please enter the reason why this asset needs to be replaced (this note will be shown to the user):'
            );

            if (reason === null) return; // user cancelled
            const trimmed = reason.trim();
            if (!trimmed) {
                alert('A reason is required so the user knows why their repair request is being converted to a replacement.');
                return;
            }

            if (!confirm('Create a replacement request for this asset?\n\nReason that will be sent to the user:\n' + trimmed)) {
                return;
            }

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            fetch('/admin/replacements/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    request_id: requestId,
                    asset_id: assetId,
                    repair_id: repairId,
                    reason: trimmed,
                    replacement_reason: 'Beyond Repair'
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Replacement request created. The user has been notified.');
                    closeViewRepairModal();
                    // Optionally mark the repair as completed/cancelled on the client
                    const repair = repairs.find(r => r.id === repairId);
                    if (repair) {
                        repair.status = 'cancelled';
                        repair.notes = (repair.notes ? repair.notes + ' | ' : '') + 'Sent to replacement: ' + trimmed;
                        renderRepairs();
                    }
                } else {
                    alert('Error: ' + (data.message || 'Failed to create replacement request'));
                }
            })
            .catch(err => {
                alert('Error creating replacement: ' + err.message);
            });
        }

        // Send asset to disposal — requires a reason note + notifies user
        function sendAssetToDisposal(repairId, assetId, requestId) {
            const reason = prompt(
                'Please enter the reason why this asset is being disposed (this note will be shown to the user):'
            );

            if (reason === null) return; // user cancelled
            const trimmed = reason.trim();
            if (!trimmed) {
                alert('A reason is required so the user knows why their asset is being disposed.');
                return;
            }

            if (!confirm('Dispose this asset?\n\nReason that will be sent to the user:\n' + trimmed)) {
                return;
            }

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            fetch('/admin/disposals/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    request_id: requestId,
                    asset_id: assetId,
                    repair_id: repairId,
                    reason: trimmed,
                    disposal_reason: 'Beyond Repair'
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Asset sent to disposal. The user has been notified.');
                    closeViewRepairModal();
                    const repair = repairs.find(r => r.id === repairId);
                    if (repair) {
                        repair.status = 'cancelled';
                        repair.notes = (repair.notes ? repair.notes + ' | ' : '') + 'Sent to disposal: ' + trimmed;
                        renderRepairs();
                    }
                } else {
                    alert('Error: ' + (data.message || 'Failed to create disposal'));
                }
            })
            .catch(err => {
                alert('Error creating disposal: ' + err.message);
            });
        }

        // Form submission — save to database
        document.getElementById('repairForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const payload = {
                asset_id: formData.get('asset_id'),
                issue_description: formData.get('issue_description'),
                priority: formData.get('priority'),
                requested_by: formData.get('requested_by') || '',
            };

            if (!payload.asset_id || !payload.issue_description) {
                alert('Please select an asset and describe the issue.');
                return;
            }

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating...';
            }

            try {
                const res = await fetch('/admin/repairs/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await res.json();

                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Failed to create repair request');
                }

                // Reload so the new repair comes from the database
                alert('Repair request created successfully!');
                location.reload();
            } catch (err) {
                alert('Error: ' + err.message);
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Create Request';
                }
            }
        });

        // Initial render
        renderRepairs();
    </script>
</body>
</html>