<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $asset->Asset_name ?? 'Asset' }} — My Assets</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        @include('users.partials.sidebar')

        <div class="flex-1 overflow-y-auto bg-gray-50">
    <div class="max-w-4xl mx-auto p-8">

            {{-- Back Button --}}
            <a href="{{ url()->previous() }}"
            class="inline-flex items-center gap-1.5 text-sm text-gray-500 px-3 py-1.5 rounded-lg border border-gray-200 bg-gray-100 hover:bg-gray-200 transition mb-5">
                <i class="ri-arrow-left-line"></i> Back to my assets
            </a>

        {{-- Main Card --}}
        <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">

            {{-- Header --}}
            <div class="flex justify-between items-start p-6 pb-0">
                <div>
                    <span class="inline-block text-xs font-medium px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 mb-2">
                        {{ $asset->Lifecycle_Status ?? 'Unknown' }}
                    </span>
                    <h1 class="text-2xl font-semibold text-gray-900 capitalize">{{ $asset->Asset_name ?? 'Asset' }}</h1>
                    <p class="text-xs font-mono text-gray-400 mt-1">{{ $asset->Asset_code }}</p>
                </div>
                <div class="w-24 h-24 rounded-xl border border-gray-100 bg-gray-50 flex items-center justify-center overflow-hidden flex-shrink-0">
                    @if($asset->image_url)
                        <img src="{{ $asset->image_url }}" alt="Asset photo" class="w-full h-full object-contain" />
                    @else
                        <i class="ri-image-off-line text-2xl text-gray-300"></i>
                    @endif
                </div>
            </div>

            <hr class="border-t border-gray-100 mx-6 mt-5">

            {{-- Assigned To --}}
            <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
                <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                    <i class="ri-user-follow-line text-base"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-1">Assigned to</p>
                    <div class="inline-flex items-center gap-1.5 bg-gray-100 rounded-full pl-1 pr-3 py-0.5">
                        <div class="w-5 h-5 rounded-full bg-violet-100 text-violet-700 text-[10px] font-semibold flex items-center justify-center">
                            {{ strtoupper(substr($asset->full_name ?? 'U', 0, 1)) }}{{ strtoupper(substr(strstr($asset->full_name ?? ' U', ' '), 1, 1)) }}
                        </div>
                        <span class="text-sm font-medium text-gray-800">{{ $asset->full_name ?? 'Unassigned' }}</span>
                    </div>
                </div>
            </div>

            {{-- Detail Grid --}}
            <div class="grid grid-cols-2">

                {{-- Acquisition Date --}}
                <div class="flex items-center gap-3 px-6 py-4 border-b border-r border-gray-100">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                        <i class="ri-calendar-event-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Acquisition date</p>
                        <p class="text-sm font-medium text-gray-900">{{ $asset->accusion_date ?? '—' }}</p>
                    </div>
                </div>

                {{-- Purchase Price --}}
                <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                        <i class="ri-money-peso-circle-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Purchase price</p>
                        <p class="text-sm font-medium text-gray-900">
                            {{ $asset->purchase_Price ? '₱' . number_format($asset->purchase_Price, 2) : '—' }}
                        </p>
                    </div>
                </div>

                {{-- Serial Number --}}
                <div class="flex items-center gap-3 px-6 py-4 border-r border-gray-100">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                        <i class="ri-barcode-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Serial number</p>
                        <p class="text-sm font-medium font-mono text-gray-900">{{ $asset->serial_Number ?? '—' }}</p>
                    </div>
                </div>

                {{-- Location --}}
                <div class="flex items-center gap-3 px-6 py-4 border-b border-gray-100">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                        <i class="ri-map-pin-2-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Location</p>
                        <p class="text-sm font-medium text-gray-900">{{ $asset->asset_location ?? '—' }}</p>
                    </div>
                </div>

                {{-- Next Maintenance Date --}}
                <div class="flex items-center gap-3 px-6 py-4 border-r border-gray-100">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                        <i class="ri-tools-line text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Next maintenance</p>
                        <p class="text-sm font-medium text-gray-900">
                            @if($asset->next_maintenance_date)
                                {{ date('M d, Y', strtotime($asset->next_maintenance_date)) }}
                            @else
                                —
                            @endif
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
    </div>
</body>
</html>
