@php
    $authUser = auth()->user();
    $authRole = $authUser?->role?->value ?? 'buyer';
    $authInitials = strtoupper(substr($authUser?->first_name ?? 'U', 0, 1) . substr($authUser?->last_name ?? '', 0, 1));
    $authFullName = trim(($authUser?->first_name ?? '') . ' ' . ($authUser?->last_name ?? '')) ?: 'User';

    // Real unread notification count + the latest 5 for the bell dropdown.
    // Resolved here so the bell stays in sync regardless of which page renders the topbar.
    $notifUnreadCount = $authUser ? $authUser->unreadNotifications()->count() : 0;
    $notifPreview = [];
    if ($authUser && $notifUnreadCount > 0) {
        $formatter = app(\App\Support\NotificationFormatter::class);
        $notifPreview = $formatter->formatMany(
            $authUser->notifications()->latest()->limit(5)->get()
        );
    }

    $notifColors = [
        'blue'   => ['bg' => 'bg-[#3B82F6]/10', 'text' => 'text-[#3B82F6]'],
        'green'  => ['bg' => 'bg-[#10B981]/10', 'text' => 'text-[#10B981]'],
        'orange' => ['bg' => 'bg-[#F59E0B]/10', 'text' => 'text-[#F59E0B]'],
        'purple' => ['bg' => 'bg-[#8B5CF6]/10', 'text' => 'text-[#8B5CF6]'],
        'red'    => ['bg' => 'bg-[#EF4444]/10', 'text' => 'text-[#EF4444]'],
    ];
@endphp

<header class="sticky top-0 z-30 h-[72px] bg-page/90 backdrop-blur-xl border-b border-th-border flex items-center justify-between px-6 lg:px-10">

    {{-- Mobile menu + Search --}}
    <div class="flex items-center gap-4 flex-1">
        <button type="button" onclick="toggleSidebar()" class="lg:hidden w-10 h-10 rounded-lg flex items-center justify-center text-muted hover:text-primary hover:bg-surface-2 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    {{-- Right actions --}}
    <div class="flex items-center gap-3">
        {{-- Search --}}
        <button type="button" class="w-10 h-10 rounded-full flex items-center justify-center text-muted hover:text-primary hover:bg-surface-2 transition-colors" title="{{ __('common.search') }}">
            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </button>

        {{-- Theme toggle --}}
        <button id="theme-toggle" type="button" class="w-10 h-10 rounded-full flex items-center justify-center text-muted hover:text-primary hover:bg-surface-2 transition-colors">
            <svg class="w-[18px] h-[18px] hidden dark:block" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
            <svg class="w-[18px] h-[18px] block dark:hidden" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/></svg>
        </button>

        {{-- Language toggle --}}
        <form method="POST" action="{{ route('locale.switch') }}" class="inline">
            @csrf
            <input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">
            <button type="submit" class="px-3 h-10 rounded-full text-[12px] font-bold text-muted hover:text-primary hover:bg-surface-2 transition-colors uppercase">
                {{ app()->getLocale() === 'ar' ? 'EN' : 'AR' }}
            </button>
        </form>

        {{-- Notifications --}}
        <div class="relative" id="notif-menu-wrapper">
            <button type="button"
                    id="notif-menu-button"
                    aria-haspopup="menu"
                    aria-expanded="false"
                    onclick="toggleNotifMenu(event)"
                    class="relative w-10 h-10 rounded-full flex items-center justify-center text-muted hover:text-primary hover:bg-surface-2 transition-colors">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
                @if($notifUnreadCount > 0)
                    @if($notifUnreadCount > 9)
                    <span class="absolute -top-0.5 -end-0.5 min-w-[18px] h-[18px] px-1 bg-[#EF4444] text-white text-[10px] font-bold rounded-full flex items-center justify-center">{{ $notifUnreadCount > 99 ? '99+' : $notifUnreadCount }}</span>
                    @else
                    <span class="absolute top-2 end-2 w-2 h-2 bg-[#EF4444] rounded-full"></span>
                    @endif
                @endif
            </button>

            {{-- Dropdown --}}
            <div id="notif-menu"
                 role="menu"
                 aria-labelledby="notif-menu-button"
                 class="hidden absolute end-0 mt-2 w-[360px] bg-surface border border-th-border rounded-2xl shadow-2xl overflow-hidden z-50">
                <div class="p-4 border-b border-th-border flex items-center justify-between">
                    <h3 class="text-[14px] font-bold text-primary">{{ __('notifications.title') }}</h3>
                    @if($notifUnreadCount > 0)
                    <span class="text-[11px] font-semibold text-accent">{{ __('notifications.unread_count', ['count' => $notifUnreadCount]) }}</span>
                    @endif
                </div>

                <div class="max-h-[400px] overflow-y-auto divide-y divide-th-border">
                    @forelse($notifPreview as $n)
                    @php $c = $notifColors[$n['color']] ?? $notifColors['blue']; @endphp
                    <form method="POST" action="{{ route('notifications.read', ['id' => $n['id']]) }}">
                        @csrf
                        <button type="submit" class="w-full text-start p-4 flex items-start gap-3 hover:bg-surface-2 transition-colors {{ $n['read'] ? '' : 'bg-accent/5' }}">
                            <div class="w-9 h-9 rounded-lg {{ $c['bg'] }} flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 {{ $c['text'] }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $n['icon'] }}"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2 mb-0.5">
                                    <p class="text-[12px] font-bold text-primary leading-tight">{{ $n['title'] }}</p>
                                    <span class="text-[10px] text-faint flex-shrink-0">{{ $n['time'] }}</span>
                                </div>
                                <p class="text-[11px] text-muted leading-snug truncate">{{ $n['desc'] }}</p>
                            </div>
                        </button>
                    </form>
                    @empty
                    <div class="p-8 text-center text-[12px] text-muted">{{ __('notifications.empty') }}</div>
                    @endforelse
                </div>

                <div class="p-3 border-t border-th-border flex items-center justify-between">
                    <a href="{{ route('notifications.index') }}" class="text-[12px] font-semibold text-accent hover:underline">{{ __('notifications.view_all') }}</a>
                    @if($notifUnreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-[12px] text-muted hover:text-primary">{{ __('notifications.mark_all_read') }}</button>
                    </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- User avatar dropdown --}}
        <div class="relative" id="user-menu-wrapper">
            <button type="button"
                    id="user-menu-button"
                    aria-haspopup="menu"
                    aria-expanded="false"
                    onclick="toggleUserMenu(event)"
                    class="w-10 h-10 rounded-xl bg-accent text-white font-bold text-[13px] flex items-center justify-center hover:opacity-90 transition-opacity overflow-hidden">
                @if($authUser?->company?->logo)
                    <img src="{{ asset('storage/' . $authUser->company->logo) }}" alt="{{ $authUser->company->name }}" class="w-full h-full object-cover">
                @else
                    {{ $authInitials }}
                @endif
            </button>

            <div id="user-menu"
                 role="menu"
                 class="hidden absolute end-0 mt-2 w-64 bg-surface border border-th-border rounded-xl shadow-lg overflow-hidden z-50">
                {{-- Header --}}
                <div class="px-4 py-3 border-b border-th-border flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-accent text-white font-bold text-[13px] flex items-center justify-center flex-shrink-0 overflow-hidden">
                        @if($authUser?->company?->logo)
                            <img src="{{ asset('storage/' . $authUser->company->logo) }}" alt="" class="w-full h-full object-cover">
                        @else
                            {{ $authInitials }}
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-[13px] font-bold text-primary truncate">{{ $authFullName }}</p>
                        <p class="text-[11px] text-muted truncate">{{ $authUser?->email }}</p>
                    </div>
                </div>

                {{-- Links --}}
                <div class="py-1">
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-body hover:bg-surface-2 hover:text-primary transition-colors">
                        <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                        {{ __('user.profile') }}
                    </a>
                    @if(\Illuminate\Support\Facades\Route::has('settings.index'))
                    <a href="{{ route('settings.index') }}" class="flex items-center gap-3 px-4 py-2.5 text-[13px] text-body hover:bg-surface-2 hover:text-primary transition-colors">
                        <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a6.759 6.759 0 010 .255c-.008.378.137.75.43.991l1.004.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.241.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.991l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ __('user.settings') }}
                    </a>
                    @endif
                </div>

                {{-- Logout --}}
                <div class="border-t border-th-border py-1">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 text-[13px] text-[#EF4444] hover:bg-[#EF4444]/5 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
                            {{ __('user.logout') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

@push('scripts')
<script>
document.getElementById('theme-toggle')?.addEventListener('click', function() {
    const html = document.documentElement;
    const isDark = html.classList.toggle('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
});

// Generic dropdown helper used by both user menu and notifications bell.
function toggleMenu(menuId, btnId) {
    const menu = document.getElementById(menuId);
    const btn = document.getElementById(btnId);
    if (!menu || !btn) return;
    const wasHidden = menu.classList.contains('hidden');
    // Close any other open menu first.
    document.querySelectorAll('[role="menu"]').forEach(m => {
        if (m.id !== menuId) m.classList.add('hidden');
    });
    menu.classList.toggle('hidden');
    btn.setAttribute('aria-expanded', wasHidden ? 'true' : 'false');
}

window.toggleUserMenu  = function(e) { e?.stopPropagation(); toggleMenu('user-menu',  'user-menu-button'); };
window.toggleNotifMenu = function(e) { e?.stopPropagation(); toggleMenu('notif-menu', 'notif-menu-button'); };

// Close any open dropdown when clicking outside.
document.addEventListener('click', function(e) {
    [
        ['user-menu-wrapper',  'user-menu',  'user-menu-button'],
        ['notif-menu-wrapper', 'notif-menu', 'notif-menu-button'],
    ].forEach(([wrapId, menuId, btnId]) => {
        const wrapper = document.getElementById(wrapId);
        const menu = document.getElementById(menuId);
        if (wrapper && menu && !wrapper.contains(e.target) && !menu.classList.contains('hidden')) {
            menu.classList.add('hidden');
            document.getElementById(btnId)?.setAttribute('aria-expanded', 'false');
        }
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        ['user-menu', 'notif-menu'].forEach(id => document.getElementById(id)?.classList.add('hidden'));
        ['user-menu-button', 'notif-menu-button'].forEach(id => document.getElementById(id)?.setAttribute('aria-expanded', 'false'));
    }
});
</script>
@endpush
