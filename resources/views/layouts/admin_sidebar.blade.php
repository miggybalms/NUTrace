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

        .sidebar-item {
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .sidebar-item:hover {
            background-color: #1f2937;
            color: #ffffff;
        }

        .sidebar-item.active {
            background-color: #1f2937;
            color: #3b82f6;
            border-right: 3px solid #3b82f6;
        }

        .sidebar-item.active i {
            color: #3b82f6;
        }

        /* Search input */
        .search-input {
            background: #1f2937;
            border: 1px solid #374151;
            color: #9ca3af;
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            width: 100%;
            font-size: 0.85rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-input:focus {
            border-color: #3b82f6;
            color: #ffffff;
        }

        .search-input::placeholder {
            color: #6b7280;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">

        <!-- SIDEBAR -->
        <div class="w-64 bg-gray-900 text-white flex flex-col overflow-y-auto flex-shrink-0">

            <!-- Logo / Title -->
            <div class="p-6 pb-4">
                <h1 class="text-2xl font-bold flex items-center text-white">
                    <i class="ri-dashboard-line mr-2 text-blue-400"></i>
                    Dashboard
                </h1>
            </div>

            <!-- Search -->
            <div class="px-4 mb-4">
                <div class="relative">
                    <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm"></i>
                    <input
                        type="text"
                        placeholder="Search departments..."
                        class="search-input pl-8"
                    />
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 px-4">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3 px-3">Main</p>

                <a href="/admin/dashboard"
                   class="sidebar-item {{ Request::is('admin/dashboard') ? 'active' : '' }} flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1">
                    <i class="ri-dashboard-line mr-3 text-lg"></i>
                    <span>Dashboard</span>
                </a>

                <a href="/admin/pages/assets"
                   class="sidebar-item {{ Request::is('admin/pages/assets*') ? 'active' : '' }} flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1">
                    <i class="ri-building-line mr-3 text-lg"></i>
                    <span>Assets</span>
                </a>

                <a href="/admin/requests"
                   class="sidebar-item {{ Request::is('admin/requests*') ? 'active' : '' }} flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1">
                    <i class="ri-mail-line mr-3 text-lg"></i>
                    <span>Requests</span>
                </a>

                <a href="/admin/disposal"
                   class="sidebar-item {{ Request::is('admin/disposal*') ? 'active' : '' }} flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1">
                    <i class="ri-delete-bin-line mr-3 text-lg"></i>
                    <span>Disposal</span>
                </a>

                <a href="/admin/pullout"
                   class="sidebar-item {{ Request::is('admin/pullout*') ? 'active' : '' }} flex items-center px-3 py-2.5 text-sm text-gray-300 rounded-lg mb-1">
                    <i class="ri-logout-box-r-line mr-3 text-lg"></i>
                    <span>Pullout</span>
                </a>
            </nav>

            <!-- User Info + Logout -->
            <div class="border-t border-gray-800 p-4 mt-auto">
                <div class="flex items-center mb-3 p-2 rounded-lg bg-gray-800">
                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                        <span class="text-white font-semibold text-sm">
                            {{ strtoupper(substr(Auth::user()->full_name ?? 'A', 0, 1)) }}{{ strtoupper(substr(explode(' ', Auth::user()->full_name ?? 'AO')[1] ?? 'O', 0, 1)) }}
                        </span>
                    </div>
                    <div class="ml-3 flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ Auth::user()->full_name ?? 'Asset Officer' }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ Auth::user()->email ?? 'admin@university.edu' }}</p>
                    </div>
                    <a href="/admin/settings">
                        <i class="ri-settings-3-line text-gray-400 cursor-pointer hover:text-white text-sm"></i>
                    </a>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center px-3 py-2 text-sm text-gray-300 rounded-lg hover:bg-gray-800 transition">
                        <i class="ri-logout-box-line mr-3 text-lg"></i>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="flex-1 overflow-y-auto bg-gray-50">
            @yield('content')
        </div>

    </div>
</body>
</html>