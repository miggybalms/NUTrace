<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Asset Details - {{ $asset->Asset_code ?? 'Asset' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        @include('admin.partials.sidebar')

        <div class="flex-1 overflow-y-auto bg-gray-50">
            <div class="max-w-4xl mx-auto p-8">
                <div class="bg-white border border-gray-200 rounded-xl p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h1 class="text-2xl font-bold">{{ $asset->Asset_name ?? 'Asset' }}</h1>
                            <p class="text-sm text-gray-500 mt-1">Code: <span class="font-mono">{{ $asset->Asset_code }}</span></p>
                            <p class="text-sm text-gray-500 mt-1">Status: {{ $asset->Lifecycle_Status }}</p>
                            <p class="text-sm text-gray-500 mt-1">Assigned to: {{ $asset->user?->full_name ?? 'Unassigned' }}</p>
                        </div>
                        <div class="text-right">
                            @if($asset->url)
                                <img src="{{ $asset->url }}" alt="Asset photo" class="h-28 w-auto rounded-lg border" />
                            @else
                                <div class="h-28 w-28 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400">No Photo</div>
                            @endif
                            @if(!empty($asset->qr_code_url) || !empty($asset->qr_code_path))
                                <div class="mt-3">
                                    <p class="text-xs text-gray-500">Asset QR</p>
                                    <img src="{{ $asset->qr_code_url ?? (\Illuminate\Support\Facades\Storage::url($asset->qr_code_path)) }}" alt="Asset QR" class="h-28 w-28 rounded-lg border mt-1" />
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500">Acquisition Date</p>
                            <p class="text-sm text-gray-900">{{ $asset->accusion_date ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Purchase Price</p>
                            <p class="text-sm text-gray-900">{{ $asset->purchase_Price ? '₱' . number_format($asset->purchase_Price, 2) : '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Serial Number</p>
                            <p class="text-sm text-gray-900">{{ $asset->serial_Number ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Location</p>
                            <p class="text-sm text-gray-900">{{ $asset->asset_location ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Condition</p>
                            <p class="text-sm text-gray-900">{{ $asset->Condition ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Category</p>
                            <p class="text-sm text-gray-900">{{ $asset->Category ?? '—' }}</p>
                        </div>
                    </div>

                    <!-- Lifespan Information -->
                    @if($asset->lifespan_months || $asset->expiration_date)
                    <div class="mt-8 border-t pt-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Asset Lifespan</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">Lifespan Duration</p>
                                <p class="text-sm text-gray-900">{{ $asset->lifespan_months ? $asset->lifespan_months . ' months' : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Expiration Date</p>
                                @if($asset->expiration_date)
                                    @php
                                        $expirationDate = \Carbon\Carbon::parse($asset->expiration_date);
                                        $isExpired = $expirationDate->isPast();
                                        $daysRemaining = now()->diffInDays($expirationDate, false);
                                    @endphp
                                    <p class="text-sm {{ $isExpired ? 'text-red-600 font-semibold' : ($daysRemaining < 90 ? 'text-amber-600' : 'text-gray-900') }}">
                                        {{ $expirationDate->format('M d, Y') }}
                                        @if($isExpired)
                                            <span class="text-xs ml-1">(Expired {{ abs($daysRemaining) }} days ago)</span>
                                        @elseif($daysRemaining < 90)
                                            <span class="text-xs ml-1">({{ $daysRemaining }} days remaining)</span>
                                        @endif
                                    </p>
                                @else
                                    <p class="text-sm text-gray-900">—</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Maintenance Information -->
                    @if($asset->maintenance_interval || $asset->next_maintenance_date)
                    <div class="mt-8 border-t pt-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Maintenance Schedule</h3>
                            @if($asset->next_maintenance_date && \Carbon\Carbon::parse($asset->next_maintenance_date)->isPast())
                                <span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded-full">OVERDUE</span>
                            @elseif($asset->next_maintenance_date && \Carbon\Carbon::parse($asset->next_maintenance_date)->diffInDays(now()) <= 14)
                                <span class="px-3 py-1 bg-amber-100 text-amber-700 text-xs font-semibold rounded-full">DUE SOON</span>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-xs text-gray-500">Maintenance Interval</p>
                                <p class="text-sm text-gray-900">{{ $asset->maintenance_interval ? $asset->maintenance_interval . ' months' : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Last Maintenance Date</p>
                                <p class="text-sm text-gray-900">{{ $asset->last_maintenance_date ? \Carbon\Carbon::parse($asset->last_maintenance_date)->format('M d, Y') : 'Never' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Next Maintenance Due</p>
                                @if($asset->next_maintenance_date)
                                    @php
                                        $nextMaintDate = \Carbon\Carbon::parse($asset->next_maintenance_date);
                                        $isOverdue = $nextMaintDate->isPast();
                                        $daysUntilDue = now()->diffInDays($nextMaintDate, false);
                                    @endphp
                                    <p class="text-sm {{ $isOverdue ? 'text-red-600 font-semibold' : ($daysUntilDue < 14 ? 'text-amber-600' : 'text-gray-900') }}">
                                        {{ $nextMaintDate->format('M d, Y') }}
                                        @if($isOverdue)
                                            <span class="text-xs ml-1">({{ abs($daysUntilDue) }} days overdue)</span>
                                        @elseif($daysUntilDue < 14)
                                            <span class="text-xs ml-1">({{ $daysUntilDue }} days)</span>
                                        @endif
                                    </p>
                                @else
                                    <p class="text-sm text-gray-900">—</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Repair History</p>
                                <p class="text-sm text-gray-900">{{ $asset->repair_counts ?? 0 }} repair(s)</p>
                            </div>
                        </div>
                        
                        <!-- Mark Maintenance Complete Button -->
                        @if($asset->next_maintenance_date)
                        <button onclick="openMaintenanceCompleteModal()" class="mt-4 inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                            <i class="ri-checkbox-circle-line mr-2"></i>
                            Mark Maintenance Complete
                        </button>
                        @endif
                    </div>
                    @endif

                    <div class="mt-6">
                        <a href="/admin/assets" class="inline-flex items-center px-4 py-2 bg-gray-100 border rounded-lg">&larr; Back to assets</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mark Maintenance Complete Modal -->
    <div id="maintenanceCompleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center" onclick="closeMaintenanceCompleteModal(event)">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4" onclick="event.stopPropagation()">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-900">Mark Maintenance Complete</h3>
                <button onclick="closeMaintenanceCompleteModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="ri-close-line text-2xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <form id="maintenanceCompleteForm">
                    @csrf
                    <div class="mb-4">
                        <label for="maintenanceDate" class="block text-sm font-medium text-gray-700 mb-2">Completion Date</label>
                        <input 
                            type="date" 
                            id="maintenanceDate" 
                            name="completion_date"
                            value="{{ date('Y-m-d') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition"
                        />
                    </div>
                    
                    <div class="mb-4">
                        <label for="maintenanceNotes" class="block text-sm font-medium text-gray-700 mb-2">Maintenance Notes (Optional)</label>
                        <textarea 
                            id="maintenanceNotes" 
                            name="notes"
                            placeholder="e.g., Replaced filters, lubricated joints, all systems operational"
                            rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition resize-none"
                        ></textarea>
                    </div>
                    
                    <div id="maintenanceError" class="text-red-600 text-sm mb-4" style="display: none;"></div>
                    <div id="maintenanceSuccess" class="text-green-600 text-sm mb-4" style="display: none;"></div>
                    
                    <div class="flex gap-3 justify-end">
                        <button 
                            type="button" 
                            onclick="closeMaintenanceCompleteModal()"
                            class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                        >
                            Cancel
                        </button>
                        <button 
                            type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center"
                        >
                            <i class="ri-checkbox-circle-line mr-2"></i>
                            Mark Complete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openMaintenanceCompleteModal() {
            document.getElementById('maintenanceCompleteModal').classList.remove('hidden');
        }
        
        function closeMaintenanceCompleteModal(event) {
            if (event && event.target.id !== 'maintenanceCompleteModal') return;
            document.getElementById('maintenanceCompleteModal').classList.add('hidden');
            document.getElementById('maintenanceCompleteForm').reset();
            document.getElementById('maintenanceDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('maintenanceError').style.display = 'none';
            document.getElementById('maintenanceSuccess').style.display = 'none';
        }
        
        document.getElementById('maintenanceCompleteForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const completionDate = document.getElementById('maintenanceDate').value;
            const notes = document.getElementById('maintenanceNotes').value.trim();
            const errorEl = document.getElementById('maintenanceError');
            const successEl = document.getElementById('maintenanceSuccess');
            
            errorEl.style.display = 'none';
            successEl.style.display = 'none';
            
            if (!completionDate) {
                errorEl.textContent = 'Completion date is required';
                errorEl.style.display = 'block';
                return;
            }
            
            try {
                const assetId = '{{ $asset->id }}';
                const response = await fetch(`/admin/api/assets/${assetId}/maintenance-complete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({
                        completion_date: completionDate,
                        notes: notes || 'Maintenance completed'
                    })
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    successEl.textContent = '✓ ' + data.message;
                    successEl.style.display = 'block';
                    
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    errorEl.textContent = data.message || 'Failed to mark maintenance complete';
                    errorEl.style.display = 'block';
                }
            } catch (error) {
                errorEl.textContent = 'Error: ' + error.message;
                errorEl.style.display = 'block';
            }
        });
        
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast-notification fixed bottom-4 right-4 px-6 py-3 rounded-lg text-white ${type === 'success' ? 'bg-green-600' : 'bg-red-600'} shadow-lg z-50`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>
