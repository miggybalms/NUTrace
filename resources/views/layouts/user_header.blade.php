{{--
    Reusable Employee / User header
    Usage:
        @include('layouts.user_header', [
            'title'             => 'Welcome, ' . ($user?->full_name ?? 'User'),
            'subtitle'          => null,                    // optional
            'showSearch'        => false,                   // optional
            'searchPlaceholder' => 'Search...',             // optional
            'searchTarget'      => 'auto',                  // assets | requests | auto
            'showAction'        => false,                   // optional
            'actionUrl'         => route('user.request-asset'),
            'actionLabel'       => 'Submit',
            'actionIcon'        => 'ri-add-line',
        ])
--}}

@php
    $user = $user ?? Auth::user();
    $title = $title ?? 'Dashboard';
    $subtitle = $subtitle ?? null;
    $showSearch = $showSearch ?? false;
    $searchPlaceholder = $searchPlaceholder ?? 'Search...';
    $showAction = $showAction ?? false;
    $actionUrl = $actionUrl ?? '#';
    $actionLabel = $actionLabel ?? 'Submit';
    $actionIcon = $actionIcon ?? 'ri-add-line';
    $searchTarget = $searchTarget ?? 'auto';

    $displayName = $user?->full_name
        ?? (optional($user?->employee_numbers)->Full_Name ?? null)
        ?? 'User';

    $deptName = 'N/A';
    if ($user && $user->department_id) {
        $deptName = \Illuminate\Support\Facades\DB::table('departments')
            ->where('id', $user->department_id)
            ->value('Name') ?? 'N/A';
    }

    $initial = strtoupper(substr($displayName, 0, 1));
@endphp

<!-- Header -->
<div class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
    <div class="px-4 sm:px-8 py-4 sm:py-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:justify-between lg:items-center">
            {{-- Left: Title + subtitle --}}
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900">{{ $title }}</h2>
                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-1">
                    @if($subtitle)
                        <span class="text-sm text-blue-600 font-medium">{{ $displayName }}</span>
                        <span class="text-gray-300 hidden xs:inline">•</span>
                        <p class="text-sm text-gray-500">{{ $subtitle }}</p>
                    @else
                        <span class="text-sm font-semibold text-gray-900">
                            {{ $user?->unit_heads_number ?? '' }}
                            @if($user?->unit_heads_number) – @endif
                            {{ $user?->role ?? 'Employee' }}
                        </span>
                        <span class="mx-1 text-gray-300">•</span>
                        <span class="text-sm text-blue-600 font-medium">{{ $deptName }}</span>
                    @endif
                </div>
            </div>

            {{-- Right: search + action + notifications + profile --}}
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                @if($showSearch)
                    <div class="relative flex-1 min-w-[140px] sm:flex-none">
                        <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text"
                               id="header-search-input"
                               placeholder="{{ $searchPlaceholder }}"
                               class="pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-400 w-full sm:w-56"/>
                    </div>
                @endif

                @if($showAction)
                    <a href="{{ $actionUrl }}"
                       class="inline-flex items-center px-3 sm:px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium shadow-sm whitespace-nowrap">
                        <i class="{{ $actionIcon }} mr-1.5"></i>
                        <span>{{ $actionLabel }}</span>
                    </a>
                @endif

                {{-- Notification bell --}}
                <div class="relative" id="notification-wrapper">
                    <button type="button" id="notification-bell"
                            class="relative p-2 rounded-full hover:bg-gray-100 transition focus:outline-none">
                        <i class="ri-notification-3-line text-xl text-gray-600"></i>
                        <span id="notification-badge"
                              class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center px-1 hidden">
                            0
                        </span>
                    </button>

                    <div id="notification-dropdown"
                         class="hidden absolute right-0 mt-2 w-80 sm:w-96 max-h-[420px] overflow-y-auto bg-white rounded-xl shadow-xl border border-gray-200 z-50">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 sticky top-0 bg-white">
                            <h4 class="font-semibold text-gray-900">Notifications</h4>
                            <button type="button" id="mark-all-read"
                                    class="text-xs text-blue-600 hover:text-blue-700 font-medium">
                                Mark all as read
                            </button>
                        </div>
                        <div id="notification-list" class="divide-y divide-gray-50">
                            <div class="px-4 py-8 text-center text-gray-400 text-sm" id="notification-empty">
                                No notifications yet
                            </div>
                        </div>
                    </div>
                </div>

{{-- Profile chip + dropdown --}}
<div class="relative" id="profile-wrapper">
    <button type="button"
            id="profile-btn"
            class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 rounded-lg px-2 py-1 focus:outline-none">
        
        {{-- Small avatar --}}
        <div class="w-8 h-8 rounded-full overflow-hidden flex items-center justify-center flex-shrink-0
                    {{ $user?->profile_photo_url ? '' : 'bg-gradient-to-br from-blue-500 to-blue-600' }}">
            @if($user && $user->profile_photo_url)
                <img src="{{ $user->profile_photo_url }}"
                     class="w-8 h-8 object-cover"
                     alt="Profile">
            @else
                <span class="text-white text-xs font-semibold">{{ $initial }}</span>
            @endif
        </div>

        <i class="ri-arrow-down-s-line text-gray-500 hidden sm:inline"></i>
    </button>

            {{-- Profile dropdown --}}
            <div id="profile-dropdown"
                class="hidden absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl border border-gray-200 z-50 overflow-hidden">
                
                {{-- Header with larger photo --}}
                <div class="px-4 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full overflow-hidden flex items-center justify-center flex-shrink-0
                                    {{ $user?->profile_photo_url ? '' : 'bg-gradient-to-br from-blue-500 to-blue-600' }}">
                            @if($user && $user->profile_photo_url)
                                <img src="{{ $user->profile_photo_url }}"
                                    class="w-12 h-12 object-cover"
                                    alt="Profile">
                            @else
                                <span class="text-white text-lg font-semibold">{{ $initial }}</span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 truncate">{{ $displayName }}</p>
                            <p class="text-xs text-gray-500 truncate">
                                {{ $user?->email ?? 'No email' }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Info rows --}}
                <div class="px-4 py-3 space-y-2.5 text-sm">
                    <div class="flex items-center gap-2.5">
                        <i class="ri-user-3-line text-gray-400 text-base"></i>
                        <div>
                            <p class="text-xs text-gray-400">Role</p>
                            <p class="font-medium text-gray-800">{{ $user?->role ?? 'Employee' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <i class="ri-building-2-line text-gray-400 text-base"></i>
                        <div>
                            <p class="text-xs text-gray-400">Department</p>
                            <p class="font-medium text-gray-800">{{ $deptName }}</p>
                        </div>
                    </div>
                    @if($user?->unit_heads_number)
                    <div class="flex items-center gap-2.5">
                        <i class="ri-hashtag text-gray-400 text-base"></i>
                        <div>
                            <p class="text-xs text-gray-400">Unit Head No.</p>
                            <p class="font-medium text-gray-800">{{ $user->unit_heads_number }}</p>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Footer actions --}}
                <div class="border-t border-gray-100 px-2 py-2">
                    <a href="{{ route('logout') }}"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                    class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-red-600 hover:bg-red-50 transition font-medium">
                        <i class="ri-logout-box-r-line text-base"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>

        {{-- Hidden logout form --}}
        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
            @csrf
        </form>
            </div>
        </div>
    </div>
</div>

{{-- Notification + Search JS --}}
<script>
(function () {
    if (window.__userNotificationsInit) return;
    window.__userNotificationsInit = true;

    const bellBtn    = document.getElementById('notification-bell');
    const dropdown   = document.getElementById('notification-dropdown');
    const listEl     = document.getElementById('notification-list');
    const badgeEl    = document.getElementById('notification-badge');
    const markAllBtn = document.getElementById('mark-all-read');
    const csrfToken  = document.querySelector('meta[name="csrf-token"]')?.content || '';

    if (!bellBtn || !dropdown || !listEl || !badgeEl) return;

    const typeIcon = {
        REQUEST:     { icon: 'ri-file-list-3-line', bg: 'bg-blue-100',   color: 'text-blue-600',   label: 'Request' },
        REPAIR:      { icon: 'ri-tools-line',       bg: 'bg-red-100',    color: 'text-red-600',    label: 'Repair' },
        REPLACEMENT: { icon: 'ri-exchange-line',    bg: 'bg-orange-100', color: 'text-orange-600', label: 'Replacement' },
    };

    // Detail modal
    let modalOverlay = document.getElementById('notification-detail-modal');
    if (!modalOverlay) {
        modalOverlay = document.createElement('div');
        modalOverlay.id = 'notification-detail-modal';
        modalOverlay.className = 'fixed inset-0 z-[100] hidden items-center justify-center bg-black/40 p-4';
        modalOverlay.innerHTML = `
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                <div class="flex items-start gap-3 p-5 border-b border-gray-100">
                    <div id="nd-icon" class="w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0"></div>
                    <div class="flex-1 min-w-0">
                        <p id="nd-type" class="text-xs font-semibold uppercase tracking-wide text-gray-400"></p>
                        <h3 id="nd-title" class="text-lg font-bold text-gray-900 mt-0.5"></h3>
                    </div>
                    <button type="button" id="nd-close" class="p-1 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>
                <div class="p-5">
                    <p id="nd-message" class="text-sm text-gray-700 leading-relaxed"></p>
                    <p id="nd-time" class="text-xs text-gray-400 mt-4"></p>
                </div>
                <div class="flex gap-2 px-5 pb-5">
                    <button type="button" id="nd-view-related"
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2.5 rounded-lg transition">
                        View related
                    </button>
                    <button type="button" id="nd-dismiss"
                            class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg transition">
                        Close
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modalOverlay);

        document.getElementById('nd-close').addEventListener('click', closeModal);
        document.getElementById('nd-dismiss').addEventListener('click', closeModal);
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) closeModal();
        });
    }

    function openModal(n) {
        const cfg = typeIcon[n.type] || typeIcon.REQUEST;
        const iconEl = document.getElementById('nd-icon');
        iconEl.className = `w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 ${cfg.bg}`;
        iconEl.innerHTML = `<i class="${cfg.icon} ${cfg.color} text-xl"></i>`;

        document.getElementById('nd-type').textContent = cfg.label;
        document.getElementById('nd-title').textContent = n.title || 'Notification';
        document.getElementById('nd-message').textContent = n.message || '';
        document.getElementById('nd-time').textContent = n.time_ago || '';

        document.getElementById('nd-view-related').onclick = () => {
            closeModal();
            window.location.href = '/user/requests';
        };

        modalOverlay.classList.remove('hidden');
        modalOverlay.classList.add('flex');
        dropdown.classList.add('hidden');
    }

    function closeModal() {
        modalOverlay.classList.add('hidden');
        modalOverlay.classList.remove('flex');
    }

    function updateBadge(count) {
        if (count > 0) {
            badgeEl.textContent = count > 99 ? '99+' : count;
            badgeEl.classList.remove('hidden');
        } else {
            badgeEl.classList.add('hidden');
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderNotifications(items) {
        listEl.innerHTML = '';
        if (!items || items.length === 0) {
            listEl.innerHTML = `<div class="px-4 py-8 text-center text-gray-400 text-sm">No notifications yet</div>`;
            return;
        }

        items.forEach(n => {
            const cfg = typeIcon[n.type] || typeIcon.REQUEST;
            const unreadClass = n.is_read ? '' : 'bg-blue-50/60';
            const item = document.createElement('div');
            item.className = `flex gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer transition ${unreadClass}`;
            item.innerHTML = `
                <div class="w-9 h-9 rounded-full ${cfg.bg} flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="${cfg.icon} ${cfg.color}"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">${escapeHtml(n.title)}</p>
                    <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">${escapeHtml(n.message)}</p>
                    <p class="text-[11px] text-gray-400 mt-1">${escapeHtml(n.time_ago)}</p>
                </div>
                ${!n.is_read ? '<span class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0 mt-2"></span>' : ''}
            `;
            item.addEventListener('click', () => onNotificationClick(n));
            listEl.appendChild(item);
        });
    }

    async function onNotificationClick(n) {
        try {
            await fetch(`/api/notifications/${n.id}/read`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
            });
            fetchNotifications();
        } catch (e) {}
        openModal(n);
    }

    async function fetchNotifications() {
        try {
            const res = await fetch('/api/notifications?limit=20', { credentials: 'same-origin' });
            if (!res.ok) return;
            const data = await res.json();
            updateBadge(data.unread_count || 0);
            renderNotifications(data.notifications || []);
        } catch (e) {}
    }

    bellBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden')) fetchNotifications();
    });

    document.addEventListener('click', (e) => {
        if (!document.getElementById('notification-wrapper')?.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    markAllBtn?.addEventListener('click', async (e) => {
        e.stopPropagation();
        try {
            await fetch('/api/notifications/read-all', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            fetchNotifications();
        } catch (e) {}
    });

    // ===== Profile dropdown =====
(function () {
    if (window.__userProfileInit) return;
    window.__userProfileInit = true;

    const profileBtn  = document.getElementById('profile-btn');
    const profileDrop = document.getElementById('profile-dropdown');
    const profileWrap = document.getElementById('profile-wrapper');

    if (!profileBtn || !profileDrop) return;

    profileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        profileDrop.classList.toggle('hidden');
        // Close notification dropdown if open
        document.getElementById('notification-dropdown')?.classList.add('hidden');
    });

    document.addEventListener('click', (e) => {
        if (!profileWrap?.contains(e.target)) {
            profileDrop.classList.add('hidden');
        }
    });
})();



    // Search (assets / requests)
    (function () {
        if (window.__userHeaderSearchInit) return;
        window.__userHeaderSearchInit = true;

        const searchInput = document.getElementById('header-search-input');
        if (!searchInput) return;

        const mode = @json($searchTarget ?? 'auto');

        function getCards() {
            if (mode === 'assets') return document.querySelectorAll('.asset-card');
            if (mode === 'requests') return document.querySelectorAll('.request-row');
            const assets = document.querySelectorAll('.asset-card');
            if (assets.length) return assets;
            return document.querySelectorAll('.request-row');
        }

        function getCardText(card) {
            const parts = [
                card.dataset.name,
                card.dataset.code,
                card.dataset.category,
                card.dataset.location,
                card.dataset.status,
                card.dataset.type,
                card.dataset.note,
            ].filter(Boolean);
            if (parts.length) return parts.join(' ').toLowerCase();
            return (card.textContent || '').toLowerCase();
        }

        function getActiveFilter() {
            const active =
                document.querySelector('.filter-btn.active') ||
                document.querySelector('.filter-tab.active');
            return (active?.dataset?.filter || 'all').trim();
        }

        function passesTabFilter(card, filter) {
            if (filter === 'all' || filter === 'recent') return true;
            return (card.dataset.status || '').trim() === filter;
        }

        function filterList() {
            const q = (searchInput.value || '').trim().toLowerCase();
            const filter = getActiveFilter();
            const cards = getCards();
            let visible = 0;

            cards.forEach(card => {
                const searchOk = !q || getCardText(card).includes(q);
                const tabOk = passesTabFilter(card, filter);
                const show = searchOk && tabOk;
                card.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            const countLabel = document.querySelector('[data-list-count]');
            if (countLabel) {
                const isRequests = mode === 'requests' || document.querySelectorAll('.request-row').length > 0;
                countLabel.textContent = isRequests ? `${visible} requests` : `Showing ${visible} assets`;
            }
        }

        window.__userRerunHeaderSearch = filterList;
        searchInput.addEventListener('input', filterList);
        document.querySelectorAll('.filter-btn, .filter-tab').forEach(btn => {
            btn.addEventListener('click', () => setTimeout(filterList, 0));
        });
    })();

    fetchNotifications();
    setInterval(fetchNotifications, 30000);
})();
</script>