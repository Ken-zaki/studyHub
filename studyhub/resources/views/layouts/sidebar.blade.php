<script>
    (function() {
        var theme = localStorage.getItem('sh_theme') || 'light';
        var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        var resolved = theme === 'auto' ? (prefersDark ? 'dark' : 'light') : theme;
        document.documentElement.setAttribute('data-theme', resolved);
        var accent = localStorage.getItem('sh_accent');
        if (accent) document.documentElement.style.setProperty('--primary', accent);
        var fontSize = localStorage.getItem('sh_font_size');
        if (fontSize) document.documentElement.style.setProperty('font-size', fontSize + 'px');
    })();
</script>

@php
    $sessionFirstName = session('user_first_name', 'User');
    $sessionLastName = session('user_last_name', '');
    $sessionUsername = session('user_username', '');
    $sessionProfilePhoto = session('user_profile_photo', '');
    $sessionUserId = session('user_id', '');
    $sessionInitials = strtoupper(substr($sessionFirstName, 0, 1) . substr($sessionLastName, 0, 1));
    $sessionFullName = trim($sessionFirstName . ' ' . $sessionLastName) ?: $sessionUsername ?: 'You';

    // $activeNav is passed from each page controller
    $activeNav = $activeNav ?? '';

    $currentRoute = \Illuminate\Support\Facades\Route::currentRouteName() ?? '';
    $showFriendRail = in_array(
        $currentRoute,
        [],
        true,
    );

    $sidebarFriends = [];
    if ($showFriendRail && $sessionUserId !== '') {
        try {
            $provider = new \App\Providers\SupabaseServiceProvider();
            $friendIds = [];

            $friendRows = \App\Models\Friendship::query()
                ->where('user_id', $sessionUserId)
                ->orWhere('friend_id', $sessionUserId)
                ->get(['user_id', 'friend_id']);

            foreach ($friendRows as $row) {
                $candidate = (string) ($row->user_id === $sessionUserId ? $row->friend_id : $row->user_id);
                if ($candidate !== '' && $candidate !== $sessionUserId) {
                    $friendIds[$candidate] = true;
                }
            }

            if (!empty($friendIds)) {
                foreach (array_keys($friendIds) as $friendId) {
                    $sidebarFriends[] = resolveFriendProfileEntry($provider, $friendId);
                }
            }
        } catch (\Throwable $e) {
            $sidebarFriends = [];
        }
    }
@endphp

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon">S</div>
            <span class="logo-text">StudyHub</span>
        </div>
    </div>

    <div class="global-search-wrap">
        <div class="global-search-input-wrap">
            <svg class="global-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" />
                <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <input type="text" id="globalUserSearch" placeholder="Search people..." autocomplete="off">
            <button class="global-search-clear" id="globalSearchClear" title="Clear" style="display:none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>
        <div class="global-search-results" id="globalSearchResults"></div>
    </div>

    <style>
    .global-search-wrap {
        padding: 8px 12px 4px;
        position: relative;
    }
    .global-search-input-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
        background: var(--bg-hover, rgba(0,0,0,0.05));
        border: 1.5px solid transparent;
        border-radius: 12px;
        padding: 8px 10px;
        transition: border-color 0.18s, background 0.18s, box-shadow 0.18s;
    }
    .global-search-input-wrap:focus-within {
        border-color: var(--primary, #1a5f7a);
        background: var(--bg-card, #fff);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary, #1a5f7a) 12%, transparent);
    }
    .global-search-icon {
        width: 15px;
        height: 15px;
        flex-shrink: 0;
        color: var(--text-light, #9ca3af);
        transition: color 0.18s;
    }
    .global-search-input-wrap:focus-within .global-search-icon {
        color: var(--primary, #1a5f7a);
    }
    #globalUserSearch {
        flex: 1;
        border: none;
        outline: none;
        background: transparent;
        font-size: 13px;
        color: var(--text-primary, #1a1a1a);
        font-family: inherit;
        min-width: 0;
    }
    #globalUserSearch::placeholder {
        color: var(--text-light, #9ca3af);
    }
    .global-search-clear {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        flex-shrink: 0;
        background: var(--text-light, #9ca3af);
        border: none;
        border-radius: 50%;
        cursor: pointer;
        padding: 0;
        transition: background 0.15s;
    }
    .global-search-clear:hover {
        background: var(--primary, #1a5f7a);
    }
    .global-search-clear svg {
        width: 9px;
        height: 9px;
        stroke: white;
    }
    .global-search-results {
        display: none;
        position: absolute;
        top: calc(100% - 2px);
        left: 12px;
        right: 12px;
        background: var(--bg-card, #fff);
        border: 1.5px solid var(--border, #e5e7eb);
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        z-index: 999;
        overflow: hidden;
        max-height: 280px;
        overflow-y: auto;
    }
    .global-search-result {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        color: inherit;
        text-decoration: none;
        border-bottom: 1px solid var(--border, #e5e7eb);
        transition: background 0.18s ease;
    }
    .global-search-result:hover {
        background: var(--bg-main, #fafbfc);
    }
    .global-search-result:last-child {
        border-bottom: none;
    }
    .global-search-result img,
    .global-search-avatar {
        width: 42px;
        height: 42px;
        min-width: 42px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
        overflow: hidden;
        background: linear-gradient(135deg, var(--primary, #1a5f7a), var(--secondary, #f59e42));
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
    }
    .global-search-result > div {
        flex: 1;
        min-width: 0;
    }
    .global-search-name {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary, #1a1a1a);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .global-search-username {
        font-size: 12px;
        color: var(--text-light, #9ca3af);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .global-search-badge {
        margin-left: auto;
        font-size: 11px;
        font-weight: 700;
        padding: 4px 8px;
        border-radius: 999px;
        background: rgba(26, 95, 122, 0.1);
        color: var(--primary, #1a5f7a);
        flex-shrink: 0;
    }
    </style>

    <nav class="sidebar-nav">

        {{-- 1. Dashboard --}}
        <a href="{{ route('dashboard') }}" class="nav-item {{ $activeNav === 'dashboard' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7" rx="1" />
                <rect x="14" y="3" width="7" height="7" rx="1" />
                <rect x="3" y="14" width="7" height="7" rx="1" />
                <rect x="14" y="14" width="7" height="7" rx="1" />
            </svg>
            <span class="nav-text">Dashboard</span>
        </a>

        {{-- 2. Calendar --}}
        <a href="{{ route('calendar') }}" class="nav-item {{ $activeNav === 'calendar' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                <line x1="16" y1="2" x2="16" y2="6" />
                <line x1="8" y1="2" x2="8" y2="6" />
                <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
            <span class="nav-text">Calendar</span>
        </a>

        {{-- 3. Tasks --}}
        <a href="{{ route('tasks') }}" class="nav-item {{ $activeNav === 'tasks' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4" />
                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
            </svg>
            <span class="nav-text">Tasks</span>
        </a>

        {{-- 4. Newsfeed --}}
        <a href="{{ route('newsfeed') }}" class="nav-item {{ $activeNav === 'newsfeed' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path
                    d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
            </svg>
            <span class="nav-text">Newsfeed</span>
        </a>

        {{-- 5. Announcements --}}
        <a href="{{ route('announcements') }}" class="nav-item {{ $activeNav === 'announcements' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 11l19-9-9 19-2-8-8-2z"/>
            </svg>
            <span class="nav-text">Announcements</span>
        </a>

        {{-- 6. Study Groups --}}
        <a href="{{ route('study-groups') }}" class="nav-item {{ $activeNav === 'study-groups' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75" />
            </svg>
            <span class="nav-text">Study Groups</span>
        </a>

        {{-- 7. Resources --}}
        <a href="{{ route('resources') }}" class="nav-item {{ $activeNav === 'resources' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z" />
                <path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z" />
            </svg>
            <span class="nav-text">Resources</span>
        </a>

        {{-- 8. Focus Mode --}}
        <a href="{{ route('focus-mode') }}" class="nav-item {{ $activeNav === 'focus-mode' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <circle cx="12" cy="12" r="6" />
                <circle cx="12" cy="12" r="2" />
            </svg>
            <span class="nav-text">Focus Mode</span>
        </a>

        {{-- 9. Messages --}}
        <a href="{{ route('messages') }}" class="nav-item {{ $activeNav === 'messages' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
            </svg>
            <span class="nav-text">Messages</span>
        </a>

        {{-- 10. Friends --}}
        <a href="{{ route('friends') }}" class="nav-item {{ $activeNav === 'friends' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75" />
            </svg>
            <span class="nav-text">Friends</span>
        </a>

        {{-- 11. Friend Requests --}}
        <a href="{{ route('friend-requests') }}"
            class="nav-item {{ $activeNav === 'friend-requests' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" />
                <circle cx="12" cy="7" r="4" />
                <path d="M22 16s-1-1-2-1" />
                <path d="M22 19s-1 1-2 1" />
            </svg>
            <span class="nav-text">Friend Requests</span>
        </a>

        {{-- 12. Settings --}}
        <a href="{{ route('settings') }}" class="nav-item {{ $activeNav === 'settings' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3" />
                <path
                    d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z" />
            </svg>
            <span class="nav-text">Settings</span>
        </a>

    </nav>

    <div class="sidebar-footer">
        <a href="{{ route('profile') }}" class="user-profile">
            <div class="user-avatar">
                @if ($sessionProfilePhoto)
                    <img src="{{ $sessionProfilePhoto }}" alt="{{ $sessionFullName }}">
                @else
                    {{ $sessionInitials }}
                @endif
            </div>
            <div class="user-info">
                <div class="user-name">{{ $sessionFullName }}</div>
                <div class="user-status">Online</div>
            </div>
        </a>
        <form method="POST" action="{{ route('logout') }}" style="margin:0;">
            @csrf
            <button type="submit" class="sidebar-logout-btn" title="Logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24"
                    height="24">
                    <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
                <span class="logout-text">Logout</span>
            </button>
        </form>
    </div>
</aside>

<div class="top-bar">
    <a href="{{ route('notifications') }}" class="top-bar-btn" title="Notifications">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 01-3.46 0" />
        </svg>
        <span class="notif-dot"></span>
    </a>
    <a href="{{ route('profile') }}" class="top-bar-avatar" title="Your Profile">
        @if ($sessionProfilePhoto)
            <img src="{{ $sessionProfilePhoto }}" alt="{{ $sessionFullName }}">
        @else
            {{ $sessionInitials }}
        @endif
    </a>
</div>

@if ($showFriendRail)
    <aside class="right-sidebar">
        <div class="widget-card friends-widget-card">
            <div class="widget-title">👥 Friends</div>

            @if (empty($sidebarFriends))
                <div class="friends-empty">No friends available.</div>
            @else
                <div class="friends-list">
                    @foreach ($sidebarFriends as $friend)
                        <a href="{{ route('profile.view', ['userId' => $friend['id'], 'name' => $friend['name'], 'photo' => $friend['photo']]) }}"
                            class="friend-item">
                            <div class="friend-avatar">
                                @if ($friend['photo'])
                                    <img src="{{ $friend['photo'] }}" alt="{{ $friend['name'] }}">
                                @else
                                    {{ $friend['initials'] }}
                                @endif
                            </div>
                            <div class="friend-meta">
                                <div class="friend-name">{{ $friend['name'] }}</div>
                                <div class="friend-status-row">
                                    <span
                                        class="friend-status-dot {{ $friend['is_active'] ? 'online' : 'offline' }}"></span>
                                    <span
                                        class="friend-status-text">{{ $friend['is_active'] ? 'Online' : 'Offline' }}</span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </aside>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('globalUserSearch');
    const box   = document.getElementById('globalSearchResults');
    const clear = document.getElementById('globalSearchClear');

    if (!input || !box) return;

    let timer = null;

    function toggleClear() {
        if (clear) clear.style.display = input.value.length ? 'flex' : 'none';
    }

    if (clear) {
        clear.addEventListener('click', function () {
            input.value = '';
            box.style.display = 'none';
            box.innerHTML = '';
            toggleClear();
            input.focus();
        });
    }

    input.addEventListener('input', function () {
        toggleClear();
        const q = this.value.trim();

        clearTimeout(timer);

        if (q.length < 2) {
            box.style.display = 'none';
            box.innerHTML = '';
            return;
        }

        timer = setTimeout(async function () {
            try {
                const res = await fetch(`/search/users?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json' }
                });

                const data  = await res.json();
                const users = data.users || [];

                if (!users.length) {
                    box.innerHTML = `<div style="padding:12px;color:#999;font-size:13px;">No users found.</div>`;
                    box.style.display = 'block';
                    return;
                }

                box.innerHTML = users.map(user => {
                    const avatar = user.photo
                        ? `<img src="${user.photo}" alt="" class="global-search-avatar-image">`
                        : `<div class="global-search-avatar">${user.name.substring(0, 2).toUpperCase()}</div>`;

                    return `
                        <a href="${user.url}" class="global-search-result">
                            ${avatar}
                            <div>
                                <div class="global-search-name">${user.name}</div>
                                <div class="global-search-username">@${user.username || 'user'}</div>
                            </div>
                            ${user.is_friend ? `<span class="global-search-badge">Friend</span>` : ''}
                        </a>
                    `;
                }).join('');

                box.style.display = 'block';
            } catch (e) {
                box.innerHTML = `<div style="padding:12px;color:#d33;font-size:13px;">Search failed.</div>`;
                box.style.display = 'block';
            }
        }, 250);
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.global-search-wrap')) {
            box.style.display = 'none';
        }
    });
});
</script>
