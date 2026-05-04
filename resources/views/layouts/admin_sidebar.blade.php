<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - University Asset Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        /* Sidebar item styles so the active nav highlights correctly */
        .sidebar-item {
            transition: all 0.15s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .sidebar-item i { margin-right: 0.75rem; color: #9ca3af; }

        .sidebar-item:hover {
            background-color: #0f1724; /* slightly lighter than bg */
            color: #ffffff;
        }

        .sidebar-item.active {
            background-color: #0b1220;
            color: #3b82f6;
            border-right: 3px solid #3b82f6;
        }

        .sidebar-item.active i {
            color: #3b82f6;
        }
    </style>
</head>
<body>
    <div class="flex min-h-screen">
        @if(View::hasSection('sidebar'))
            @yield('sidebar')
        @else
            @include('admin.partials.sidebar')
        @endif

        <div class="flex-1 overflow-y-auto bg-gray-50">
            @yield('content')
        </div>
    </div>
</body>
</html>