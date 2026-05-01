@php
    $adminUsername = session('user_username', 'Admin');
    $adminFirstName = session('user_first_name', 'Admin');
    $adminLastName  = session('user_last_name', '');
    $adminInitials  = strtoupper(substr($adminFirstName, 0, 1) . substr($adminLastName, 0, 1));
    $adminFullName  = trim($adminFirstName . ' ' . $adminLastName) ?: $adminUsername;
    $adminPhoto     = session('user_profile_photo', '');
    $activeAdmin    = $activeAdmin ?? '';
@endphp

<style>
:root {
    --adm-primary:    #1a5f7a;
    --adm-dark:       #144d61;
    --adm-accent:     #f59e42;
    --adm-danger:     #ff6b6b;
    --adm-success:    #2a9d8f;
    --adm-warning:    #f4a261;
    --adm-bg:         #f5f6fa;
    --adm-sidebar:    #ffffff;
    --adm-card:       #ffffff;
    --adm-text:       #1a1a1a;
    --adm-muted:      #6b7280;
    --adm-light:      #9ca3af;
    --adm-border:     #e5e7eb;
    --adm-sidebar-w:  240px;
    --adm-topbar-h:   60px;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--adm-bg);color:var(--adm-text);overflow-x:hidden;}

/* ── SIDEBAR ── */
.adm-sidebar {
    position:fixed; left:0; top:0;
    width:var(--adm-sidebar-w); height:100vh;
    background:var(--adm-sidebar);
    border-right:1px solid var(--adm-border);
    display:flex; flex-direction:column;
    z-index:1000;
}

.adm-logo {
    padding:20px 20px 16px;
    border-bottom:1px solid var(--adm-border);
    display:flex; align-items:center; gap:10px;
}
.adm-logo-icon {
    width:36px; height:36px; border-radius:10px;
    background:linear-gradient(135deg,var(--adm-primary),var(--adm-accent));
    display:flex; align-items:center; justify-content:center;
    color:white; font-weight:700; font-size:16px; flex-shrink:0;
}
.adm-logo-text { font-family:'Crimson Pro',serif; font-size:20px; font-weight:700; color:var(--adm-primary); }
.adm-logo-badge {
    margin-left:auto; font-size:10px; font-weight:700;
    background:rgba(26,95,122,0.1); color:var(--adm-primary);
    padding:3px 8px; border-radius:20px; white-space:nowrap;
}

.adm-nav { flex:1; padding:14px 12px; overflow-y:auto; display:flex; flex-direction:column; gap:2px; }

.adm-nav-section {
    font-size:10px; font-weight:700; color:var(--adm-light);
    letter-spacing:0.08em; text-transform:uppercase;
    padding:10px 12px 4px;
}
.adm-nav-section:first-child { padding-top:4px; }

.adm-nav-item {
    display:flex; align-items:center; gap:10px;
    padding:11px 12px; border-radius:10px;
    color:var(--adm-muted); text-decoration:none;
    font-size:14px; font-weight:500;
    transition:all 0.2s ease; cursor:pointer;
    position:relative;
}
.adm-nav-item:hover { background:#f7f8fa; color:var(--adm-primary); }
.adm-nav-item.active {
    background:linear-gradient(135deg,rgba(26,95,122,0.08),rgba(245,158,66,0.08));
    color:var(--adm-primary); font-weight:600;
}
.adm-nav-item.active::before {
    content:''; position:absolute; left:0; top:50%;
    transform:translateY(-50%);
    width:3px; height:22px;
    background:linear-gradient(var(--adm-primary),var(--adm-accent));
    border-radius:0 3px 3px 0;
}
.adm-nav-icon {
    width:18px; height:18px; flex-shrink:0;
    color:inherit; opacity:0.7;
}
.adm-nav-item.active .adm-nav-icon { opacity:1; }
.adm-nav-badge {
    margin-left:auto; font-size:10px; font-weight:700;
    background:var(--adm-danger); color:white;
    padding:2px 7px; border-radius:10px; min-width:20px; text-align:center;
}
.adm-nav-badge.warn { background:var(--adm-warning); }

.adm-sidebar-footer { padding:12px; border-top:1px solid var(--adm-border); }
.adm-user-card {
    display:flex; align-items:center; gap:10px;
    padding:10px 12px; border-radius:10px;
    cursor:pointer; transition:background 0.2s; text-decoration:none;
}
.adm-user-card:hover { background:#f7f8fa; }
.adm-avatar {
    width:36px; height:36px; border-radius:10px;
    background:linear-gradient(135deg,var(--adm-primary),var(--adm-accent));
    display:flex; align-items:center; justify-content:center;
    color:white; font-weight:700; font-size:13px; flex-shrink:0; overflow:hidden;
}
.adm-avatar img { width:100%; height:100%; object-fit:cover; }
.adm-user-info { flex:1; min-width:0; }
.adm-user-name { font-size:13px; font-weight:600; color:var(--adm-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.adm-user-role { font-size:11px; color:var(--adm-light); margin-top:1px; }

.adm-logout {
    display:flex; align-items:center; gap:10px;
    padding:10px 12px; border-radius:10px;
    color:var(--adm-danger); font-size:14px; font-weight:500;
    cursor:pointer; transition:background 0.2s; margin-top:2px;
    background:none; border:none; width:100%; text-align:left;
    font-family:'DM Sans',sans-serif;
}
.adm-logout:hover { background:rgba(255,107,107,0.08); }
.adm-logout svg { width:18px; height:18px; flex-shrink:0; }

/* ── TOP BAR ── */
.adm-topbar {
    position:fixed; top:0; right:0;
    left:var(--adm-sidebar-w);
    height:var(--adm-topbar-h);
    background:white; border-bottom:1px solid var(--adm-border);
    display:flex; align-items:center;
    padding:0 24px; gap:12px; z-index:900;
}
.adm-topbar-title { font-family:'Crimson Pro',serif; font-size:22px; font-weight:700; color:var(--adm-text); }
.adm-topbar-badge {
    font-size:11px; font-weight:700;
    background:rgba(26,95,122,0.1); color:var(--adm-primary);
    padding:3px 10px; border-radius:20px;
}
.adm-topbar-right { margin-left:auto; display:flex; align-items:center; gap:10px; }

/* ── MAIN CONTENT ── */
.adm-main {
    margin-left:var(--adm-sidebar-w);
    margin-top:var(--adm-topbar-h);
    min-height:calc(100vh - var(--adm-topbar-h));
    padding:28px 24px;
}
</style>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<aside class="adm-sidebar">
    <div class="adm-logo">
        <div class="adm-logo-icon">S</div>
        <span class="adm-logo-text">StudyHub</span>
        <span class="adm-logo-badge">Admin</span>
    </div>

    <nav class="adm-nav">
        <div class="adm-nav-section">Overview</div>

        <a href="{{ route('admin.dashboard') }}" class="adm-nav-item {{ $activeAdmin === 'dashboard' ? 'active' : '' }}">
            <svg class="adm-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
            Dashboard
        </a>

        <div class="adm-nav-section">Management</div>

        <a href="{{ route('admin.users') }}" class="adm-nav-item {{ $activeAdmin === 'users' ? 'active' : '' }}">
            <svg class="adm-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75"/>
            </svg>
            User Management
        </a>

        <a href="{{ route('admin.reports') }}" class="adm-nav-item {{ $activeAdmin === 'reports' ? 'active' : '' }}">
            <svg class="adm-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            Reports
            <span class="adm-nav-badge" id="sidebarReportBadge">—</span>
        </a>

        <a href="{{ route('admin.resources') }}" class="adm-nav-item {{ $activeAdmin === 'resources' ? 'active' : '' }}">
            <svg class="adm-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/>
                <path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>
            </svg>
            Resource Approval
            <span class="adm-nav-badge warn" id="sidebarResourceBadge">—</span>
        </a>

        <a href="{{ route('admin.logs') }}" class="adm-nav-item {{ $activeAdmin === 'logs' ? 'active' : '' }}">
            <svg class="adm-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                <polyline points="10 9 9 9 8 9"/>
            </svg>
            Admin Logs
        </a>

        <div class="adm-nav-section">Platform</div>

        <a href="{{ route('admin.settings') }}" class="adm-nav-item {{ $activeAdmin === 'settings' ? 'active' : '' }}">
            <svg class="adm-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.6 9a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9"/>
            </svg>
            Settings
        </a>

        <a href="{{ route('newsfeed') }}" class="adm-nav-item">
            <svg class="adm-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
            </svg>
            View as User
        </a>
    </nav>

    <div class="adm-sidebar-footer">
        <div class="adm-user-card">
            <div class="adm-avatar">
                @if($adminPhoto)
                    <img src="{{ $adminPhoto }}" alt="{{ $adminFullName }}">
                @else
                    {{ $adminInitials }}
                @endif
            </div>
            <div class="adm-user-info">
                <div class="adm-user-name">{{ $adminFullName }}</div>
                <div class="adm-user-role">Platform Admin</div>
            </div>
        </div>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="adm-logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                Log Out
            </button>
        </form>
    </div>
</aside>

<div class="adm-topbar">
    <span class="adm-topbar-title" id="adminPageTitle">Admin Panel</span>
    <span class="adm-topbar-badge">StudyHub Admin</span>
    <div class="adm-topbar-right" id="adminTopbarActions"></div>
</div>

<script>
// Update sidebar report/resource badges from Supabase
const SUPABASE_URL      = '{{ env("SUPABASE_URL") }}';
const SUPABASE_ANON_KEY = '{{ env("SUPABASE_ANON_KEY") }}';

async function loadSidebarBadges() {
    try {
        const [rRes, resRes] = await Promise.all([
            fetch(`${SUPABASE_URL}/rest/v1/reports?status=eq.pending&select=id`,
                { headers:{ 'apikey': SUPABASE_ANON_KEY, 'Prefer':'count=exact' }}),
            fetch(`${SUPABASE_URL}/rest/v1/resources?is_approved=eq.false&select=id`,
                { headers:{ 'apikey': SUPABASE_ANON_KEY, 'Prefer':'count=exact' }})
        ]);
        const rCount   = parseInt(rRes.headers.get('content-range')?.split('/')[1] || '0');
        const resCount = parseInt(resRes.headers.get('content-range')?.split('/')[1] || '0');

        const rb = document.getElementById('sidebarReportBadge');
        const ab = document.getElementById('sidebarResourceBadge');
        if (rb)   { rb.textContent   = rCount;   rb.style.display   = rCount   ? '' : 'none'; }
        if (ab)   { ab.textContent   = resCount; ab.style.display   = resCount ? '' : 'none'; }
    } catch(e) {}
}
loadSidebarBadges();
</script>
