<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - StudyHub</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
@php $activeAdmin = 'dashboard'; @endphp
@include('admin.sidebar')

<main class="adm-main">

    {{-- STAT CARDS --}}
    <div class="adm-stats-grid">
        <div class="adm-stat-card">
            <div class="adm-stat-icon" style="background:rgba(26,95,122,0.1);color:#1a5f7a;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75"/></svg>
            </div>
            <div class="adm-stat-body">
                <div class="adm-stat-label">Total Users</div>
                <div class="adm-stat-num" id="statTotalUsers">—</div>
                <div class="adm-stat-sub" id="statNewUsers">Loading…</div>
            </div>
        </div>
        <div class="adm-stat-card">
            <div class="adm-stat-icon" style="background:rgba(255,107,107,0.1);color:#ff6b6b;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div class="adm-stat-body">
                <div class="adm-stat-label">Pending Reports</div>
                <div class="adm-stat-num" id="statReports">—</div>
                <div class="adm-stat-sub">Awaiting review</div>
            </div>
        </div>
        <div class="adm-stat-card">
            <div class="adm-stat-icon" style="background:rgba(245,158,66,0.1);color:#f59e42;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
            </div>
            <div class="adm-stat-body">
                <div class="adm-stat-label">Pending Resources</div>
                <div class="adm-stat-num" id="statResources">—</div>
                <div class="adm-stat-sub">Awaiting approval</div>
            </div>
        </div>
        <div class="adm-stat-card">
            <div class="adm-stat-icon" style="background:rgba(42,157,143,0.1);color:#2a9d8f;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="adm-stat-body">
                <div class="adm-stat-label">Total Posts</div>
                <div class="adm-stat-num" id="statPosts">—</div>
                <div class="adm-stat-sub">Newsfeed posts</div>
            </div>
        </div>
        <div class="adm-stat-card">
            <div class="adm-stat-icon" style="background:rgba(124,77,202,0.1);color:#7c4dca;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </div>
            <div class="adm-stat-body">
                <div class="adm-stat-label">Banned Users</div>
                <div class="adm-stat-num" id="statBanned">—</div>
                <div class="adm-stat-sub">Currently suspended</div>
            </div>
        </div>
        <div class="adm-stat-card">
            <div class="adm-stat-icon" style="background:rgba(26,95,122,0.1);color:#1a5f7a;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
            </div>
            <div class="adm-stat-body">
                <div class="adm-stat-label">Study Groups</div>
                <div class="adm-stat-num" id="statGroups">—</div>
                <div class="adm-stat-sub">Active groups</div>
            </div>
        </div>
    </div>

    {{-- BOTTOM GRID --}}
    <div class="adm-bottom-grid">

        {{-- Recent Users --}}
        <div class="adm-card">
            <div class="adm-card-header">
                <span class="adm-card-title">Recent Registrations</span>
                <a href="{{ route('admin.users') }}" class="adm-link">View all →</a>
            </div>
            <div id="recentUsersList">
                <div class="adm-loading">Loading…</div>
            </div>
        </div>

        {{-- Pending Reports --}}
        <div class="adm-card">
            <div class="adm-card-header">
                <span class="adm-card-title">Pending Reports</span>
                <a href="{{ route('admin.reports') }}" class="adm-link">View all →</a>
            </div>
            <div id="pendingReportsList">
                <div class="adm-loading">Loading…</div>
            </div>
        </div>

    </div>

</main>

<script>
const SB_URL = '{{ env("SUPABASE_URL") }}';
const SB_KEY = '{{ env("SUPABASE_ANON_KEY") }}';
const SB_SVC = '{{ env("SUPABASE_SERVICE_KEY") }}';

function sbHeaders(svc=false) {
    const k = svc ? SB_SVC : SB_KEY;
    return { 'apikey': k, 'Authorization': `Bearer ${k}` };
}

async function loadStats() {
    try {
        const [users, reports, resources, posts, banned, groups] = await Promise.all([
            fetch(`${SB_URL}/rest/v1/profiles?select=id`, { headers:{ ...sbHeaders(), 'Prefer':'count=exact' }}),
            fetch(`${SB_URL}/rest/v1/reports?status=eq.pending&select=id`, { headers:{ ...sbHeaders(), 'Prefer':'count=exact' }}),
            fetch(`${SB_URL}/rest/v1/resources?is_approved=eq.false&select=id`, { headers:{ ...sbHeaders(), 'Prefer':'count=exact' }}),
            fetch(`${SB_URL}/rest/v1/newsfeed_posts?is_archived=eq.false&select=id`, { headers:{ ...sbHeaders(), 'Prefer':'count=exact' }}),
            fetch(`${SB_URL}/rest/v1/profiles?is_banned=eq.true&select=id`, { headers:{ ...sbHeaders(true), 'Prefer':'count=exact' }}),
            fetch(`${SB_URL}/rest/v1/study_groups?select=id`, { headers:{ ...sbHeaders(), 'Prefer':'count=exact' }}),
        ]);

        const c = async r => parseInt((await r).headers?.get('content-range')?.split('/')[1] ?? '0');
        document.getElementById('statTotalUsers').textContent  = await c(Promise.resolve(users));
        document.getElementById('statReports').textContent     = await c(Promise.resolve(reports));
        document.getElementById('statResources').textContent   = await c(Promise.resolve(resources));
        document.getElementById('statPosts').textContent       = await c(Promise.resolve(posts));
        document.getElementById('statBanned').textContent      = await c(Promise.resolve(banned));
        document.getElementById('statGroups').textContent      = await c(Promise.resolve(groups));

        // new users this week
        const week = new Date(Date.now() - 7*24*60*60*1000).toISOString();
        const wRes = await fetch(`${SB_URL}/rest/v1/profiles?created_at=gte.${week}&select=id`,
            { headers:{ ...sbHeaders(), 'Prefer':'count=exact' }});
        const wCount = parseInt(wRes.headers.get('content-range')?.split('/')[1] ?? '0');
        document.getElementById('statNewUsers').textContent = `+${wCount} this week`;
    } catch(e) { console.error(e); }
}

async function loadRecentUsers() {
    try {
        const res = await fetch(
            `${SB_URL}/rest/v1/profiles?select=id,first_name,last_name,username,email,role,is_banned,created_at&order=created_at.desc&limit=5`,
            { headers: sbHeaders(true) }
        );
        const users = await res.json();
        const el = document.getElementById('recentUsersList');
        if (!users.length) { el.innerHTML = '<div class="adm-empty">No users yet.</div>'; return; }
        el.innerHTML = users.map(u => `
            <div class="adm-list-row">
                <div class="adm-list-avatar">${avatarInitials(u.first_name, u.last_name)}</div>
                <div class="adm-list-info">
                    <div class="adm-list-name">${escH(u.first_name||'')} ${escH(u.last_name||'')}</div>
                    <div class="adm-list-sub">@${escH(u.username||'')} · ${timeAgo(u.created_at)}</div>
                </div>
                <span class="adm-badge ${u.role==='admin'?'badge-primary':u.role==='moderator'?'badge-warn':'badge-gray'}">${u.role||'student'}</span>
                ${u.is_banned ? '<span class="adm-badge badge-danger">Banned</span>' : ''}
            </div>`).join('');
    } catch(e) {}
}

async function loadPendingReports() {
    try {
        const res = await fetch(
            `${SB_URL}/rest/v1/reports?status=eq.pending&select=id,reason,reported_content_type,created_at&order=created_at.desc&limit=5`,
            { headers: sbHeaders(true) }
        );
        const reports = await res.json();
        const el = document.getElementById('pendingReportsList');
        if (!reports.length) { el.innerHTML = '<div class="adm-empty">No pending reports. ✅</div>'; return; }
        el.innerHTML = reports.map(r => `
            <div class="adm-list-row">
                <div class="adm-list-avatar" style="background:rgba(255,107,107,0.12);color:#ff6b6b;">⚠</div>
                <div class="adm-list-info">
                    <div class="adm-list-name">${escH(r.reason||'').slice(0,60)}${(r.reason||'').length>60?'…':''}</div>
                    <div class="adm-list-sub">${escH(r.reported_content_type||'content')} · ${timeAgo(r.created_at)}</div>
                </div>
                <a href="{{ route('admin.reports') }}" class="adm-act-btn">Review</a>
            </div>`).join('');
    } catch(e) {}
}

function avatarInitials(f,l) { return ((f||'?')[0]+(l||'?')[0]).toUpperCase(); }
function escH(t) { const d=document.createElement('div'); d.textContent=t; return d.innerHTML; }
function timeAgo(ts) {
    const s=Math.floor((Date.now()-new Date(ts))/1000);
    if(s<60) return 'just now';
    if(s<3600) return `${Math.floor(s/60)}m ago`;
    if(s<86400) return `${Math.floor(s/3600)}h ago`;
    if(s<604800) return `${Math.floor(s/86400)}d ago`;
    return new Date(ts).toLocaleDateString();
}

document.getElementById('adminPageTitle').textContent = 'Dashboard';
loadStats();
loadRecentUsers();
loadPendingReports();
</script>
</body>
</html>
