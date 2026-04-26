{{-- resources/views/admin/pullout.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pullout Management - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
        }
        
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
        
        .pullout-card {
            transition: all 0.3s ease;
        }
        
        .pullout-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .modal {
            transition: all 0.3s ease;
        }
        
        .modal.show {
            display: flex;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .status-badge {
            transition: all 0.2s ease;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto bg-gray-50">
            <!-- Header -->
            <div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                <div class="px-8 py-5">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <a href="#" onclick="window.history.back(); return false;" class="text-gray-500 hover:text-gray-700 mr-4 transition-transform hover:translate-x-[-2px]">
                                <i class="ri-arrow-left-line text-xl"></i>
                            </a>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900">Record Pullout</h2>
                                <p class="text-sm text-gray-500 mt-1">Manage pulled out assets</p>
                            </div>
                        </div>
                        <button onclick="openNewPulloutModal()" class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition-all hover:scale-105 flex items-center shadow-md">
                            <i class="ri-add-line mr-2"></i>
                            Record Pullout
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <!-- Stats Card -->
                <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl shadow-lg p-6 mb-8 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium opacity-90">Total Pulled Out</p>
                            <p class="text-4xl font-bold mt-2">{{ $totalPulledOut ?? 0 }}</p>
                            <p class="text-xs opacity-80 mt-2">Complete log of pulled out institutional assets</p>
                        </div>
                        <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center">
                            <i class="ri-logout-box-r-line text-4xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Pullout Records List -->
                @if(isset($pulloutRecords) && count($pulloutRecords) > 0)
                    <div class="grid grid-cols-1 gap-4">
                        @foreach($pulloutRecords as $record)
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 pullout-card">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center mb-3">
                                        <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="ri-logout-box-r-line text-orange-600 text-xl"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900">{{ $record->asset_name }}</h3>
                                            <p class="text-xs text-gray-500 font-mono">{{ $record->asset_code }}</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                        <div>
                                            <p class="text-xs text-gray-500">Pullout Date</p>
                                            <p class="text-sm font-medium text-gray-900">{{ $record->pullout_date }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Reason</p>
                                            <p class="text-sm font-medium text-gray-900">{{ $record->reason }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Pulled By</p>
                                            <p class="text-sm font-medium text-gray-900">{{ $record->pulled_by }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Status</p>
                                            <span class="status-badge inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                @if($record->status == 'pending') bg-yellow-100 text-yellow-700
                                                @elseif($record->status == 'approved') bg-green-100 text-green-700
                                                @else bg-red-100 text-red-700
                                                @endif">
                                                {{ ucfirst($record->status) }}
                                            </span>
                                        </div>
                                    </div>
                                    @if($record->destination)
                                    <div class="mt-3 pt-3 border-t border-gray-100">
                                        <p class="text-xs text-gray-500">Destination / New Location</p>
                                        <p class="text-sm text-gray-700">{{ $record->destination }}</p>
                                    </div>
                                    @endif
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="viewPulloutDetails({{ $record->id }})" class="text-blue-600 hover:text-blue-700">
                                        <i class="ri-eye-line text-xl"></i>
                                    </button>
                                    @if($record->status == 'pending')
                                    <button onclick="approvePullout({{ $record->id }})" class="text-green-600 hover:text-green-700">
                                        <i class="ri-checkbox-circle-line text-xl"></i>
                                    </button>
                                    @endif
                                    <button onclick="deletePulloutRecord({{ $record->id }})" class="text-red-600 hover:text-red-700">
                                        <i class="ri-delete-bin-line text-xl"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <!-- Empty State -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="ri-logout-box-r-line text-4xl text-gray-400"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No pullout records yet</h3>
                        <p class="text-gray-500">There are currently no pullout reports to show.</p>
                        <button onclick="openNewPulloutModal()" class="mt-4 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                            <i class="ri-add-line mr-2"></i>
                            Record First Pullout
                        </button>
                    </div>
                @endif

                <!-- Footer -->
                <div class="text-center text-sm text-gray-500 mt-8 pt-6 border-t border-gray-200">
                    © 2026 University Asset Management. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <!-- New Pullout Modal -->
    <div id="pulloutModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center modal">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-900">Record Asset Pullout</h3>
                    <button onclick="closePulloutModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-2xl"></i>
                    </button>
                </div>
            </div>
            <form id="pulloutForm" class="p-6">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Asset *</label>
                        <select name="asset_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring focus:ring-orange-200">
                            <option value="">Search or select asset...</option>
                            @foreach($availableAssets ?? [] as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->name }} ({{ $asset->asset_code }}) - Assigned to: {{ $asset->assignedUser->name ?? 'Unassigned' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pullout Date *</label>
                        <input type="date" name="pullout_date" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring focus:ring-orange-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Pullout *</label>
                        <select name="reason" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring focus:ring-orange-200">
                            <option value="">Select reason...</option>
                            <option value="Transfer">Transfer to another department</option>
                            <option value="Repair">Needs repair/maintenance</option>
                            <option value="Reassignment">Reassignment to different user</option>
                            <option value="Upgrade">Upgrade to newer equipment</option>
                            <option value="Temporary">Temporary removal</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pulled By *</label>
                        <input type="text" name="pulled_by" required placeholder="Name of person authorizing pullout" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring focus:ring-orange-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Destination / New Location</label>
                        <input type="text" name="destination" placeholder="e.g., IT Department, Room 302, Storage Room" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring focus:ring-orange-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Expected Return Date (if applicable)</label>
                        <input type="date" name="expected_return_date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring focus:ring-orange-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                        <textarea name="notes" rows="3" placeholder="Any additional information about the pullout..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-orange-500 focus:ring focus:ring-orange-200"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closePulloutModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">Record Pullout</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center modal">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="ri-delete-bin-line text-2xl text-red-600"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Delete Pullout Record</h3>
                    <p class="text-gray-500 mb-4">Are you sure you want to delete this pullout record? This action cannot be undone.</p>
                    <div class="flex space-x-3">
                        <button onclick="closeDeleteModal()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button onclick="confirmDelete()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentDeleteId = null;
        
        function openNewPulloutModal() {
            document.getElementById('pulloutModal').classList.remove('hidden');
            document.getElementById('pulloutModal').classList.add('flex');
        }
        
        function closePulloutModal() {
            document.getElementById('pulloutModal').classList.add('hidden');
            document.getElementById('pulloutModal').classList.remove('flex');
        }
        
        function viewPulloutDetails(id) {
            // Implement view details logic
            alert('View pullout details for ID: ' + id);
        }
        
        function approvePullout(id) {
            if (confirm('Approve this pullout request?')) {
                // Implement approve logic
                alert('Pullout request ' + id + ' approved');
            }
        }
        
        function deletePulloutRecord(id) {
            currentDeleteId = id;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('flex');
            currentDeleteId = null;
        }
        
        function confirmDelete() {
            if (currentDeleteId) {
                // Implement delete logic
                alert('Pullout record ' + currentDeleteId + ' deleted');
                closeDeleteModal();
            }
        }
        