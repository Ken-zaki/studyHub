{{-- resources/views/layouts/guest-sidebar.blade.php --}}
@php $guestNav = $guestNav ?? ''; @endphp

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon">S</div>
            <span class="logo-text">StudyHub</span>
        </div>
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

        {{--
            Settings — OPEN
            Icon copied VERBATIM from layouts/sidebar.blade.php (the logged-in sidebar).
        --}}
        <a href="{{ route('guest.settings') }}"
           class="nav-item {{ $guestNav === 'settings' ? 'active' : '' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3" />
                <path
                    d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z" />
            </svg>
            <span class="nav-text">Settings</span>
        </a>

    </nav>

    {{-- Sidebar footer --}}
    <div class="sidebar-footer">
        <div style="display:flex;align-items:center;gap:10px;
                    padding:10px 12px;border-radius:12px;
                    background:var(--bg-hover,rgba(0,0,0,0.04));
                    margin-bottom:10px;">
            <div style="width:36px;height:36px;border-radius:10px;flex-shrink:0;
                        background:var(--bg-main,#f0f0f0);
                        border:1px solid var(--border,#e5e7eb);
                        display:flex;align-items:center;justify-content:center;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     style="width:18px;height:18px;color:var(--text-light,#9ca3af);">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:600;color:var(--text-primary,#1a1a1a);line-height:1.3;">Guest</div>
                <div style="font-size:11px;color:var(--text-light,#9ca3af);line-height:1.3;">Not signed in</div>
            </div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('login') }}"
               style="flex:1;text-align:center;padding:9px 0;border-radius:10px;
                      border:1.5px solid var(--border,#e5e7eb);background:var(--bg-card,white);
                      color:var(--text-primary,#1a1a1a);font-size:13px;font-weight:600;
                      text-decoration:none;transition:all 0.18s;"
               onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)';"
               onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-primary)';">
                Log In
            </a>
            <a href="{{ route('signup') }}"
               style="flex:1;text-align:center;padding:9px 0;border-radius:10px;
                      border:none;background:var(--primary,#1a5f7a);color:white;
                      font-size:13px;font-weight:600;text-decoration:none;transition:opacity 0.18s;"
               onmouseover="this.style.opacity='.85';" onmouseout="this.style.opacity='1';">
                Sign Up
            </a>
        </div>
    </div>
</aside>

{{-- Top bar --}}
<div class="top-bar">
    <div style="flex:1;"></div>
    <a href="{{ route('login') }}"
       style="padding:7px 16px;border-radius:9px;border:1.5px solid var(--border,#e5e7eb);
              background:var(--bg-card,white);color:var(--text-primary,#1a1a1a);
              font-size:13px;font-weight:600;text-decoration:none;transition:all 0.18s;"
       onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)';"
       onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-primary)';">
        Log In
    </a>
    <a href="{{ route('signup') }}"
       style="padding:7px 16px;border-radius:9px;border:none;
              background:var(--primary,#1a5f7a);color:white;
              font-size:13px;font-weight:600;text-decoration:none;transition:opacity 0.18s;"
       onmouseover="this.style.opacity='.85';" onmouseout="this.style.opacity='1';">
        Sign Up Free
    </a>
</div>

{{-- Lock modal --}}
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
.nav-item--locked {
    display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;
    font-size:14px;font-weight:500;color:var(--text-secondary,#6b7280);
    opacity:0.45;cursor:pointer;transition:opacity 0.18s,background 0.18s;user-select:none;
}
.nav-item--locked:hover { opacity:0.65;background:var(--bg-hover,rgba(0,0,0,0.04)); }
.nav-lock-icon { width:13px;height:13px;margin-left:auto;flex-shrink:0;color:var(--text-light,#9ca3af); }
</style>

<script>
(function(){
    var L={
        'dashboard':      {icon:'📊',title:'Access your Dashboard',  body:'Log in or sign up to view your calendar, tasks, and study overview.'},
        'study-groups':   {icon:'👥',title:'Join Study Groups',       body:'Log in or sign up to create and join study groups with other students.'},
        'focus-mode':     {icon:'🎯',title:'Enter Focus Mode',        body:'Log in or sign up to use the Pomodoro timer, flashcards, and focus sessions.'},
        'messages':       {icon:'💬',title:'Send Messages',           body:'Log in or sign up to chat with your friends and classmates.'},
        'friends':        {icon:'🤝',title:'View your Friends',       body:'Log in or sign up to manage your friends list and see who is online.'},
        'friend-requests':{icon:'📨',title:'Friend Requests',         body:'Log in or sign up to send and receive friend requests.'},
    };
    document.querySelectorAll('.nav-item--locked').forEach(function(el){
        el.addEventListener('click',function(){
            var d=L[el.dataset.lock]||{icon:'🔒',title:'Sign in to continue',body:'Create a free account or log in.'};
            document.getElementById('glmIcon').textContent =d.icon;
            document.getElementById('glmTitle').textContent=d.title;
            document.getElementById('glmBody').textContent =d.body;
            var m=document.getElementById('guestLockModal');
            m.style.opacity='1';m.style.pointerEvents='all';
            document.getElementById('guestLockModalBox').style.transform='scale(1)';
        });
    });
    window.closeGuestLockModal=function(){
        var m=document.getElementById('guestLockModal');
        m.style.opacity='0';m.style.pointerEvents='none';
        document.getElementById('guestLockModalBox').style.transform='scale(0.95)';
    };
    document.addEventListener('keydown',function(e){if(e.key==='Escape')window.closeGuestLockModal();});
})();
</script>
