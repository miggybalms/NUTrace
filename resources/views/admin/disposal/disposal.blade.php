{{-- resources/views/admin/disposal.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Disposal Management - Admin Dashboard</title>
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
        
        .disposal-card {
            transition: all 0.3s ease;
        }
        
        .disposal-card:hover {
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
    </style>
</head>
<body class="bg-gray-50">
    @php
    // Expect controller to supply: $disposalRecords, $totalDisposed, $availableAssets
    $disposalRecords = $disposalRecords ?? collect();
    $availableAssets = $availableAssets ?? collect();
    if (!isset($totalDisposed)) {
        $totalDisposed = is_countable($disposalRecords) ? count($disposalRecords) : 0;
    }
    @endphp<div class="flex h-screen overflow-hidden">
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
                                <h2 class="text-2xl font-bold text-gray-900">Disposal</h2>
                                <p class="text-sm text-gray-500 mt-1">Manage disposed assets</p>
                            </div>
                        </div>
                        <button onclick="openNewDisposalModal()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-all hover:scale-105 flex items-center shadow-md">
                            <i class="ri-add-line mr-2"></i>
                            Record Disposal
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-8">
                <!-- Stats Card -->
                <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-xl shadow-lg p-6 mb-8 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium opacity-90">Total Disposed Assets</p>
                            <p class="text-4xl font-bold mt-2">{{ $totalDisposed ?? 156 }}</p>
                            <p class="text-xs opacity-80 mt-2">Complete log of all disposed institutional assets</p>
                        </div>
                        <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center">
                            <i class="ri-delete-bin-line text-4xl"></i>
                        </div>
                    </div>
                </div><!-- Disposal Records List -->
                @if(isset($disposalRecords) && count($disposalRecords) > 0)
                    <div class="grid grid-cols-1 gap-4">
                        @foreach($disposalRecords as $record)
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 disposal-card">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center mb-3">
                                        <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="ri-delete-bin-line text-red-600 text-xl"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900">{{ $record->asset_name }}</h3>
                                            <p class="text-xs text-gray-500 font-mono">{{ $record->asset_code }}</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                        <div>
                                            <p class="text-xs text-gray-500">Disposal Date</p>
                                            <p class="text-sm font-medium text-gray-900">{{ $record->disposal_date }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Reason</p>
                                            <p class="text-sm font-medium text-gray-900">{{ $record->reason ?? $record->Description ?? $record->notes ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Disposed By</p>
                                            <p class="text-sm font-medium text-gray-900">{{ $record->disposed_by ?? $record->Approve_by ?? $record->Approve_by ?? '-' }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">Original Value</p>
                                            <p class="text-sm font-medium text-gray-900">${{ number_format($record->original_value ?? 0, 2) }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="viewDisposalDetails({{ $record->id }})" class="text-blue-600 hover:text-blue-700">
                                        <i class="ri-eye-line text-xl"></i>
                                    </button>
                                    <button onclick="deleteDisposalRecord({{ $record->id }})" class="text-red-600 hover:text-red-700">
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
                            <i class="ri-inbox-line text-4xl text-gray-400"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No disposal records yet</h3>
                        <p class="text-gray-500">There are currently no disposal reports to show.</p>
                        <button onclick="openNewDisposalModal()" class="mt-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                            <i class="ri-add-line mr-2"></i>
                            Record First Disposal
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

    <!-- New Disposal Modal -->
    <div id="disposalModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center modal">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold text-gray-900">Record Asset Disposal</h3>
                    <button onclick="closeDisposalModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-2xl"></i>
                    </button>
                </div>
            </div>
            <form id="disposalForm" class="p-6">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Asset *</label>
                        <select name="asset_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-red-500 focus:ring focus:ring-red-200">
                            <option value="">Search or select asset...</option>
                            @foreach($availableAssets ?? [] as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->name }} ({{ $asset->asset_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Disposal Date *</label>
                        <input type="date" name="disposal_date" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-red-500 focus:ring focus:ring-red-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Disposal *</label>
                        <select name="reason" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-red-500 focus:ring focus:ring-red-200">
                            <option value="">Select reason...</option>
                            <option value="Damaged">Damaged - Beyond Repair</option>
                            <option value="Obsolete">Obsolete - No longer needed</option>
                            <option value="Lost">Lost / Missing</option>
                            <option value="Stolen">Stolen</option>
                            <option value="Upgraded">Upgraded - Replaced by newer model</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Disposed By *</label>
                        <input type="text" name="disposed_by" required placeholder="Name of person authorizing disposal" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-red-500 focus:ring focus:ring-red-200">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                        <textarea name="notes" rows="3" placeholder="Any additional information about the disposal..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-red-500 focus:ring focus:ring-red-200"></textarea>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeDisposalModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Record Disposal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openNewDisposalModal() {
            document.getElementById('disposalModal').classList.remove('hidden');
            document.getElementById('disposalModal').classList.add('flex');
        }
        
        function closeDisposalModal() {
            document.getElementById('disposalModal').classList.add('hidden');
            document.getElementById('disposalModal').classList.remove('flex');
        }
        
        function viewDisposalDetails(id) {
            // Implement view details logic
            alert('View disposal details for ID: ' + id);
        }
        
        function deleteDisposalRecord(id) {
            if (confirm('Are you sure you want to delete this disposal record?')) {
                // Implement delete logic
                alert('Disposal record ' + id + ' deleted');
            }
        }
        
        // Search functionality
        document.getElementById('search-disposal')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.disposal-card');
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Form submission
        document.getElementById('disposalForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            // Implement form submission logic
            alert('Disposal recorded successfully!');
            closeDisposalModal();
        });
    </script>
</body>
</html>











