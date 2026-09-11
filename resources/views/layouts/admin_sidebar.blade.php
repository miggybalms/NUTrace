<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - University Asset Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: #F3EEE0;
            color: #1A2233;
        }

        .font-display{ font-family: 'Fraunces', Georgia, serif; }

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
            color: #E9C766;
            border-right: 3px solid #C9A227;
        }

        .sidebar-item.active i {
            color: #E9C766;
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

        <div class="flex-1 overflow-y-auto bg-[#F3EEE0]">
            @yield('content')
        </div>
    </div>
</body>
</html>