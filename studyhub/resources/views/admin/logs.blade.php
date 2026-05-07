<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Logs - StudyHub Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin_logs.css') }}">
</head>
<body>
@php $activeAdmin = 'logs'; @endphp
@include('admin.sidebar')

<main class="adm-main">

    <!-- ── TOOLBAR ── -->
    <div class="adm-toolbar">
        <div class="adm-search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
            </svg>
            <input type="text" id="logSearch" placeholder="Search by action, notes, admin…" oninput="filterLogs()">
        </div>
        <select id="logActionFilter" class="adm-select" onchange="filterLogs()">
            <option value="">All Actions</option>
            <option value="ban_user">Ban User</option>
            <option value="unban_user">Unban User</option>
            <option value="change_role">Change Role</option>
            <option value="approve_resource">Approve Resource</option>
            <option value="reject_resource">Reject Resource</option>
            <option value="resolve_report">Resolve Report</option>
            <option value="takedown_content">Takedown Content</option>
            <option value="delete_post">Delete Post</option>
        </select>
        <select id="logDateFilter" class="adm-select" onchange="filterLogs()">
            <option value="">All Time</option>
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
        </select>
        <button class="adm-btn adm-btn-primary" onclick="loadLogs()" style="white-space:nowrap;">
            ↻ Refresh
        </button>
    </div>

    <!-- ── SUMMARY STRIP ── -->
    <div class="adm-log-summary" id="logSummary" style="display:none;">
        <div class="adm-log-summary-item" id="summaryTotal"></div>
        <div class="adm-log-summary-item" id="summaryToday"></div>
        <div class="adm-log-summary-item" id="summaryMostCommon"></div>
    </div>

    <!-- ── LOGS LIST ── -->
    <div class="adm-card" style="margin-top:0;">
        <div class="adm-card-header">
            <span class="adm-card-title">Admin Action Logs</span>
            <span class="adm-muted" id="logCount">Loading…</span>
        </div>
        <div id="logsList">
            <div class="adm-loading">Loading logs…</div>
        </div>
    </div>
</main>

<script>
const SB_URL = '{{ env("SUPABASE_URL") }}';
const SB_SVC = '{{ env("SUPABASE_SERVICE_KEY") }}';

function svcH() {
    return { 'apikey': SB_SVC, 'Authorization': `Bearer ${SB_SVC}` };
}

const ACTION_META = {
    ban_user:          { label: 'Ban User',          color: 'badge-danger',  icon: '🚫' },
    unban_user:        { label: 'Unban User',         color: 'badge-success', icon: '✅' },
    change_role:       { label: 'Change Role',        color: 'badge-warn',    icon: '🎭' },
    approve_resource:  { label: 'Approve Resource',   color: 'badge-success', icon: '✅' },
    reject_resource:   { label: 'Reject Resource',    color: 'badge-danger',  icon: '❌' },
    resolve_report:    { label: 'Resolve Report',     color: 'badge-info',    icon: '📋' },
    takedown_content:  { label: 'Takedown Content',   color: 'badge-danger',  icon: '🗑' },
    delete_post:       { label: 'Delete Post',        color: 'badge-danger',  icon: '🗑' },
    view_as_user:      { label: 'View as User',       color: 'badge-gray',    icon: '👁' },
};

let allLogs     = [];
let adminMap    = {};

async function loadLogs() {
    document.getElementById('logsList').innerHTML = '<div class="adm-loading">Loading logs…</div>';

    // Fetch all logs
    const res = await fetch(
        `${SB_URL}/rest/v1/admin_logs` +
        `?select=id,action,target_type,target_id,notes,created_at,admin_id` +
        `&order=created_at.desc&limit=500`,
        { headers: svcH() }
    );
    allLogs = await res.json();

    // Fetch admin profiles for all unique admin_ids
    const adminIds = [...new Set(allLogs.map(l => l.admin_id).filter(Boolean))];
    if (adminIds.length) {
        const pRes = await fetch(
            `${SB_URL}/rest/v1/profiles?id=in.(${adminIds.join(',')})&select=id,first_name,last_name,username,profile_photo_url`,
            { headers: svcH() }
        );
        const profiles = await pRes.json();
        adminMap = {};
        profiles.forEach(p => { adminMap[p.id] = p; });
    }

    renderSummary();
    filterLogs();
}

function renderSummary() {
    const today = new Date(); today.setHours(0,0,0,0);
    const todayCount = allLogs.filter(l => new Date(l.created_at) >= today).length;

    // Most common action
    const actionCounts = {};
    allLogs.forEach(l => { if (l.action) actionCounts[l.action] = (actionCounts[l.action]||0)+1; });
    const topAction = Object.entries(actionCounts).sort((a,b)=>b[1]-a[1])[0];

    document.getElementById('summaryTotal').textContent     = `${allLogs.length} total actions`;
    document.getElementById('summaryToday').textContent     = `${todayCount} actions today`;
    document.getElementById('summaryMostCommon').textContent =
        topAction ? `Most common: ${ACTION_META[topAction[0]]?.label || topAction[0]} (${topAction[1]}×)` : '';
    document.getElementById('logSummary').style.display = 'flex';
}

function filterLogs() {
    const q          = (document.getElementById('logSearch').value || '').toLowerCase();
    const actionF    = document.getElementById('logActionFilter').value;
    const dateF      = document.getElementById('logDateFilter').value;
    const now        = new Date();
    const todayStart = new Date(now); todayStart.setHours(0,0,0,0);
    const weekStart  = new Date(now - 7*24*60*60*1000);
    const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);

    const filtered = allLogs.filter(l => {
        const admin = adminMap[l.admin_id];
        const adminName = admin
            ? `${admin.first_name||''} ${admin.last_name||''}`.trim() || admin.username || ''
            : '';
        const matchQ = !q ||
            (l.action||'').toLowerCase().includes(q) ||
            (l.notes||'').toLowerCase().includes(q) ||
            (l.target_type||'').toLowerCase().includes(q) ||
            adminName.toLowerCase().includes(q);
        const matchAction = !actionF || l.action === actionF;
        let matchDate = true;
        if (dateF) {
            const d = new Date(l.created_at);
            if (dateF === 'today')  matchDate = d >= todayStart;
            if (dateF === 'week')   matchDate = d >= weekStart;
            if (dateF === 'month')  matchDate = d >= monthStart;
        }
        return matchQ && matchAction && matchDate;
    });

    renderLogs(filtered);
}

function renderLogs(logs) {
    document.getElementById('logCount').textContent =
        `${logs.length} entr${logs.length !== 1 ? 'ies' : 'y'}`;
    const el = document.getElementById('logsList');

    if (!logs.length) {
        el.innerHTML = '<div class="adm-empty">No log entries match your filters.</div>';
        return;
    }

    // Group by date
    const groups = {};
    logs.forEach(l => {
        const dateKey = new Date(l.created_at).toLocaleDateString('en-US',
            { weekday:'long', year:'numeric', month:'long', day:'numeric' });
        if (!groups[dateKey]) groups[dateKey] = [];
        groups[dateKey].push(l);
    });

    el.innerHTML = Object.entries(groups).map(([date, entries]) => `
        <div class="adm-log-date-group">
            <div class="adm-log-date-header">${escH(date)}</div>
            <div class="adm-log-entries">
                ${entries.map(l => renderLogEntry(l)).join('')}
            </div>
        </div>`).join('');
}

function renderLogEntry(l) {
    const meta      = ACTION_META[l.action] || { label: l.action||'action', color:'badge-gray', icon:'🔧' };
    const admin     = adminMap[l.admin_id];
    const adminName = admin
        ? `${admin.first_name||''} ${admin.last_name||''}`.trim() || `@${admin.username}`
        : 'Unknown Admin';
    const adminInitials = admin
        ? ((admin.first_name||'?')[0] + (admin.last_name||'?')[0]).toUpperCase()
        : '??';

    const timeStr = new Date(l.created_at).toLocaleTimeString('en-US',
        { hour:'2-digit', minute:'2-digit', second:'2-digit' });

    const dotColor = meta.color === 'badge-danger'  ? 'var(--adm-danger)' :
                     meta.color === 'badge-success' ? 'var(--adm-success)' :
                     meta.color === 'badge-warn'    ? 'var(--adm-warning)' :
                     meta.color === 'badge-info'    ? 'var(--adm-primary)' : 'var(--adm-light)';

    return `
    <div class="adm-log-entry">
        <div class="adm-log-timeline">
            <div class="adm-log-dot" style="background:${dotColor};"></div>
            <div class="adm-log-line"></div>
        </div>
        <div class="adm-log-card">
            <div class="adm-log-card-header">
                <div class="adm-log-card-left">
                    <span class="adm-log-icon">${meta.icon}</span>
                    <span class="adm-badge ${meta.color}">${escH(meta.label)}</span>
                    ${l.target_type
                        ? `<span class="adm-muted" style="font-size:12px;">on ${escH(l.target_type)}</span>`
                        : ''}
                    ${l.target_id
                        ? `<span class="adm-log-id" title="${escH(l.target_id)}">#${escH(l.target_id.slice(0,8))}…</span>`
                        : ''}
                </div>
                <span class="adm-log-time">${timeStr}</span>
            </div>
            ${l.notes ? `<div class="adm-log-notes">${escH(l.notes)}</div>` : ''}
            <div class="adm-log-admin-row">
                <div class="adm-log-admin-avatar">${admin?.profile_photo_url
                    ? `<img src="${escH(admin.profile_photo_url)}" alt="">`
                    : adminInitials}</div>
                <span class="adm-log-admin-name">${escH(adminName)}</span>
                <span class="adm-muted" style="font-size:11px;margin-left:4px;">· Platform Admin</span>
            </div>
        </div>
    </div>`;
}

function escH(t) {
    if (t == null) return '';
    const d = document.createElement('div'); d.textContent = String(t); return d.innerHTML;
}

document.getElementById('adminPageTitle').textContent = 'Admin Logs';
loadLogs();
</script>
</body>
</html>
