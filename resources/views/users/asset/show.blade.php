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
                </div>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-500">Acquisition Date</p>
                    <p class="text-sm text-gray-900">{{ $asset->accusion_date ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Purchase Price</p>
                    <p class="text-sm text-gray-900">{{ $asset->purchase_Price ? '$' . number_format($asset->purchase_Price, 2) : '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Serial Number</p>
                    <p class="text-sm text-gray-900">{{ $asset->serial_Number ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Location</p>
                    <p class="text-sm text-gray-900">{{ $asset->asset_location ?? '—' }}</p>
                </div>
            </div>

            <div class="mt-6">
                <a href="/users/assets" class="inline-flex items-center px-4 py-2 bg-gray-100 border rounded-lg">&larr; Back to my assets</a>
            </div>
        </div>
    </div>
</body>
</html>
