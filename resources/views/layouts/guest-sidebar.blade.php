{{-- resources/views/layouts/guest-sidebar.blade.php --}}
@php $guestNav = $guestNav ?? ''; @endphp

{{-- Theme init — identical to layouts/theme-init.blade.php --}}
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

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon">S</div>
            <span class="logo-text">StudyHub</span>
        </div>
    </div>

    {{-- Global search — same as registered sidebar, but searches public profiles only --}}
    <div class="global-search-wrap">
        <div class="global-search-input-wrap">
            <svg class="global-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="text" id="globalUserSearch" placeholder="Search people…" autocomplete="off">
            <button class="global-search-clear" id="globalSearchClear" title="Clear" style="display:none;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="global-search-results" id="globalSearchResults"></div>
    </div>

    <nav class="sidebar-nav">

        {{-- Dashboard — LOCKED --}}
        <span class="nav-item nav-item--locked" data-lock="dashboard">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/>
                <rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
            <span class="nav-text">Dashboard</span>
            <svg class="nav-lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2"/>
                <path d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
        </span>

        {{-- Calendar — LOCKED --}}
        <span class="nav-item nav-item--locked" data-lock="calendar">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <span class="nav-text">Calendar</span>
            <svg class="nav-lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2"/>
                <path d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
        </span>

        {{-- Tasks — LOCKED --}}
        <span class="nav-item nav-item--locked" data-lock="tasks">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4"/>
                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
            </svg>
            <span class="nav-text">Tasks</span>
            <svg class="nav-lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2"/>
                <path d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
        </span>

        {{-- Newsfeed — OPEN --}}
        <a href="{{ route('guest.newsfeed') }}"
           class="nav-item {{ $guestNav === 'newsfeed' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
            </svg>
            <span class="nav-text">Newsfeed</span>
        </a>

        {{-- Study Groups — LOCKED --}}
        <span class="nav-item nav-item--locked" data-lock="study-groups">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75"/>
            </svg>
            <span class="nav-text">Study Groups</span>
            <svg class="nav-lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2"/>
                <path d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
        </span>

        {{-- Resources — OPEN --}}
        <a href="{{ route('guest.resources') }}"
           class="nav-item {{ $guestNav === 'resources' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/>
                <path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>
            </svg>
            <span class="nav-text">Resources</span>
        </a>

        {{-- Focus Mode — LOCKED --}}
        <span class="nav-item nav-item--locked" data-lock="focus-mode">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <circle cx="12" cy="12" r="6"/>
                <circle cx="12" cy="12" r="2"/>
            </svg>
            <span class="nav-text">Focus Mode</span>
            <svg class="nav-lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2"/>
                <path d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
        </span>

        {{-- Messages — LOCKED --}}
        <span class="nav-item nav-item--locked" data-lock="messages">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
            </svg>
            <span class="nav-text">Messages</span>
            <svg class="nav-lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2"/>
                <path d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
        </span>

        {{-- Friends — LOCKED --}}
        <span class="nav-item nav-item--locked" data-lock="friends">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75"/>
            </svg>
            <span class="nav-text">Friends</span>
            <svg class="nav-lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2"/>
                <path d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
        </span>

        {{-- Friend Requests — LOCKED --}}
        <span class="nav-item nav-item--locked" data-lock="friend-requests">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
                <line x1="19" y1="8" x2="19" y2="14"/>
                <line x1="22" y1="11" x2="16" y2="11"/>
            </svg>
            <span class="nav-text">Friend Requests</span>
            <svg class="nav-lock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2"/>
                <path d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
        </span>

        {{-- Settings — OPEN (icon verbatim from registered sidebar) --}}
        <a href="{{ route('guest.settings') }}"
           class="nav-item {{ $guestNav === 'settings' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
            </svg>
            <span class="nav-text">Settings</span>
        </a>

    </nav>

    {{-- Footer — mirrors registered .sidebar-footer exactly, but shows Guest identity + leave button --}}
    <div class="sidebar-footer">
        <div class="user-profile" style="cursor:default;pointer-events:none;">
            <div class="user-avatar" style="background:var(--bg-main);border:1.5px solid var(--border);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     style="width:18px;height:18px;color:var(--text-light);">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </div>
            <div class="user-info">
                <div class="user-name">Guest</div>
                <div class="user-status" style="color:var(--text-light);">Not signed in</div>
            </div>
        </div>

        {{-- "Leave Guest Mode" mirrors the registered sidebar-logout-btn style --}}
        <a href="{{ route('login') }}" class="sidebar-logout-btn" title="Leave Guest Mode"
           style="text-decoration:none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            <span class="logout-text">Leave Guest Mode</span>
        </a>
    </div>
</aside>

{{-- Top bar — mirrors registered top bar exactly, with Log In + Sign Up instead of notifications/avatar --}}
<div class="top-bar">
    <div style="flex:1;"></div>
    <a href="{{ route('login') }}"
       class="top-bar-btn"
       style="display:flex;align-items:center;padding:7px 16px;border-radius:9px;
              border:1.5px solid var(--border);background:var(--bg-card);
              color:var(--text-primary);font-size:13px;font-weight:600;
              text-decoration:none;transition:all 0.18s;gap:6px;"
       onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)';"
       onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-primary)';">
        Log In
    </a>
    <a href="{{ route('signup') }}"
       style="display:flex;align-items:center;padding:7px 16px;border-radius:9px;border:none;
              background:var(--primary);color:white;font-size:13px;font-weight:600;
              text-decoration:none;transition:opacity 0.18s;"
       onmouseover="this.style.opacity='.85';" onmouseout="this.style.opacity='1';">
        Sign Up Free
    </a>
</div>

{{-- Lock modal — identical markup to the registered sidebar's equivalent --}}
<div id="guestLockModal"
     style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;
            display:flex;align-items:center;justify-content:center;
            opacity:0;pointer-events:none;transition:opacity 0.2s;"
     onclick="if(event.target===this)closeGuestLockModal();">
    <div id="guestLockModalBox"
         style="background:var(--bg-card,white);border-radius:20px;padding:32px;
                width:90%;max-width:400px;text-align:center;
                transform:scale(0.95);transition:transform 0.2s;
                box-shadow:0 20px 60px rgba(0,0,0,0.18);">
        <div id="glmIcon"  style="font-size:40px;margin-bottom:14px;"></div>
        <h3 id="glmTitle"  style="font-family:'Crimson Pro',serif;font-size:22px;font-weight:700;
                                   margin-bottom:8px;color:var(--text-primary,#1a1a1a);"></h3>
        <p  id="glmBody"   style="font-size:14px;color:var(--text-secondary,#6b7280);
                                   line-height:1.6;margin-bottom:24px;"></p>
        <a href="{{ route('signup') }}"
           style="display:block;padding:12px;border-radius:12px;margin-bottom:10px;
                  background:var(--primary,#1a5f7a);color:white;font-size:14px;font-weight:700;
                  text-decoration:none;"
           onmouseover="this.style.opacity='.88';" onmouseout="this.style.opacity='1';">
            Create Free Account
        </a>
        <a href="{{ route('login') }}"
           style="display:block;padding:12px;border-radius:12px;
                  border:1.5px solid var(--border,#e5e7eb);background:var(--bg-card,white);
                  font-size:14px;font-weight:600;color:var(--text-primary,#1a1a1a);text-decoration:none;"
           onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)';"
           onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-primary)';">
            I already have an account
        </a>
        <button onclick="closeGuestLockModal()"
                style="margin-top:12px;font-size:13px;color:var(--text-light,#9ca3af);
                       cursor:pointer;background:none;border:none;font-family:inherit;">
            Maybe later
        </button>
    </div>
</div>

<style>
/* ── Locked nav items ── */
.nav-item--locked {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 10px;
    font-size: 14px; font-weight: 500;
    color: var(--text-secondary, #6b7280);
    opacity: 0.45; cursor: pointer;
    transition: opacity 0.18s, background 0.18s;
    user-select: none;
}
.nav-item--locked:hover { opacity: 0.65; background: var(--bg-hover, rgba(0,0,0,0.04)); }
.nav-lock-icon {
    width: 13px; height: 13px; margin-left: auto;
    flex-shrink: 0; color: var(--text-light, #9ca3af);
}

/* ── Global search — copied verbatim from registered sidebar styles ── */
.global-search-wrap {
    padding: 8px 12px 4px;
    position: relative;
}
.global-search-input-wrap {
    display: flex; align-items: center; gap: 8px;
    background: var(--bg-hover, rgba(0,0,0,0.05));
    border: 1.5px solid transparent;
    border-radius: 12px; padding: 8px 10px;
    transition: border-color 0.18s, background 0.18s, box-shadow 0.18s;
}
.global-search-input-wrap:focus-within {
    border-color: var(--primary, #1a5f7a);
    background: var(--bg-card, #fff);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary, #1a5f7a) 12%, transparent);
}
.global-search-icon {
    width: 15px; height: 15px; flex-shrink: 0;
    color: var(--text-light, #9ca3af); transition: color 0.18s;
}
.global-search-input-wrap:focus-within .global-search-icon {
    color: var(--primary, #1a5f7a);
}
#globalUserSearch {
    flex: 1; border: none; outline: none; background: transparent;
    font-size: 13px; color: var(--text-primary, #1a1a1a);
    font-family: inherit; min-width: 0;
}
#globalUserSearch::placeholder { color: var(--text-light, #9ca3af); }
.global-search-clear {
    display: flex; align-items: center; justify-content: center;
    width: 16px; height: 16px; flex-shrink: 0;
    background: var(--text-light, #9ca3af);
    border: none; border-radius: 50%; cursor: pointer;
    padding: 0; transition: background 0.15s;
}
.global-search-clear:hover { background: var(--primary, #1a5f7a); }
.global-search-clear svg { width: 9px; height: 9px; stroke: white; }
.global-search-results {
    display: none; position: absolute;
    top: calc(100% - 2px); left: 12px; right: 12px;
    background: var(--bg-card, #fff);
    border: 1.5px solid var(--border, #e5e7eb);
    border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    z-index: 999; overflow: hidden;
    max-height: 280px; overflow-y: auto;
}
/* Result rows — same classes as registered sidebar JS output */
.global-search-result {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; text-decoration: none;
    transition: background 0.15s; border-bottom: 1px solid var(--border);
}
.global-search-result:last-child { border-bottom: none; }
.global-search-result:hover { background: var(--bg-main); }
.global-search-result img {
    width: 34px; height: 34px; border-radius: 10px;
    object-fit: cover; flex-shrink: 0;
}
.global-search-avatar {
    width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
    background: linear-gradient(135deg, var(--primary), #144d61);
    color: white; font-size: 12px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
}
.global-search-name {
    font-size: 13px; font-weight: 600; color: var(--text-primary);
}
.global-search-username {
    font-size: 11px; color: var(--text-light);
}
.global-search-badge {
    margin-left: auto; flex-shrink: 0; font-size: 10px; font-weight: 700;
    background: rgba(26,95,122,0.1); color: var(--primary);
    padding: 2px 8px; border-radius: 20px;
}
</style>

<script>
/* ── Lock modal ── */
(function() {
    var L = {
        'dashboard':      { icon:'📊', title:'Access your Dashboard',  body:'Log in or sign up to view your calendar, tasks, and study overview.' },
        'calendar':       { icon:'📅', title:'Open your Calendar',     body:'Log in or sign up to manage your schedule and track deadlines.' },
        'tasks':          { icon:'✅', title:'Manage Tasks',           body:'Log in or sign up to create and track your study tasks.' },
        'study-groups':   { icon:'👥', title:'Join Study Groups',      body:'Log in or sign up to create and join study groups with other students.' },
        'focus-mode':     { icon:'🎯', title:'Enter Focus Mode',       body:'Log in or sign up to use the Pomodoro timer, flashcards, and focus sessions.' },
        'messages':       { icon:'💬', title:'Send Messages',          body:'Log in or sign up to chat with your friends and classmates.' },
        'friends':        { icon:'🤝', title:'View your Friends',      body:'Log in or sign up to manage your friends list and see who is online.' },
        'friend-requests':{ icon:'📨', title:'Friend Requests',        body:'Log in or sign up to send and receive friend requests.' },
    };
    document.querySelectorAll('.nav-item--locked').forEach(function(el) {
        el.addEventListener('click', function() {
            var d = L[el.dataset.lock] || { icon:'🔒', title:'Sign in to continue', body:'Create a free account or log in.' };
            document.getElementById('glmIcon').textContent  = d.icon;
            document.getElementById('glmTitle').textContent = d.title;
            document.getElementById('glmBody').textContent  = d.body;
            var m = document.getElementById('guestLockModal');
            m.style.opacity = '1'; m.style.pointerEvents = 'all';
            document.getElementById('guestLockModalBox').style.transform = 'scale(1)';
        });
    });
    window.closeGuestLockModal = function() {
        var m = document.getElementById('guestLockModal');
        m.style.opacity = '0'; m.style.pointerEvents = 'none';
        document.getElementById('guestLockModalBox').style.transform = 'scale(0.95)';
    };
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') window.closeGuestLockModal(); });
})();

/* ── Global search — identical logic to registered sidebar ── */
document.addEventListener('DOMContentLoaded', function() {
    var input  = document.getElementById('globalUserSearch');
    var box    = document.getElementById('globalSearchResults');
    var clear  = document.getElementById('globalSearchClear');
    if (!input || !box) return;

    var timer = null;

    function toggleClear() {
        if (clear) clear.style.display = input.value.length ? 'flex' : 'none';
    }

    if (clear) {
        clear.addEventListener('click', function() {
            input.value = '';
            box.style.display = 'none';
            box.innerHTML = '';
            toggleClear();
            input.focus();
        });
    }

    input.addEventListener('input', function() {
        toggleClear();
        var q = this.value.trim();
        clearTimeout(timer);
        if (q.length < 2) {
            box.style.display = 'none';
            box.innerHTML = '';
            return;
        }
        timer = setTimeout(async function() {
            try {
                var res = await fetch('/api/search/users?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' }
                });
                var data = await res.json();
                var users = data.users || [];
                if (!users.length) {
                    box.innerHTML = '<div style="padding:12px;color:#999;font-size:13px;">No users found.</div>';
                    box.style.display = 'block';
                    return;
                }
                box.innerHTML = users.map(function(user) {
                    var avatar = user.photo
                        ? '<img src="' + user.photo + '" alt="">'
                        : '<div class="global-search-avatar">' + user.name.substring(0, 2).toUpperCase() + '</div>';
                    return '<a href="' + user.url + '" class="global-search-result">'
                        + avatar
                        + '<div>'
                        + '<div class="global-search-name">' + user.name + '</div>'
                        + '<div class="global-search-username">@' + (user.username || 'user') + '</div>'
                        + '</div>'
                        + (user.is_friend ? '<span class="global-search-badge">Friend</span>' : '')
                        + '</a>';
                }).join('');
                box.style.display = 'block';
            } catch(e) {
                box.innerHTML = '<div style="padding:12px;color:#d33;font-size:13px;">Search failed.</div>';
                box.style.display = 'block';
            }
        }, 250);
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.global-search-wrap')) {
            box.style.display = 'none';
        }
    });
});
</script>
