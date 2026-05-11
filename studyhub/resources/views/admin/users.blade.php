<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - StudyHub Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
@php $activeAdmin = 'users'; @endphp
@include('admin.sidebar')

<main class="adm-main">

    <div class="adm-toolbar">
        <div class="adm-search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" id="userSearch" placeholder="Search by name, username, or email…" oninput="filterUsers()">
        </div>
        <select id="roleFilter" class="adm-select" onchange="filterUsers()">
            <option value="">All roles</option>
            <option value="student">Student</option>
            <option value="moderator">Moderator</option>
            <option value="admin">Admin</option>
        </select>
        <select id="statusFilter" class="adm-select" onchange="filterUsers()">
            <option value="">All status</option>
            <option value="active">Active</option>
            <option value="banned">Banned</option>
        </select>
    </div>

    <div class="adm-card" style="margin-top:0;">
        <div class="adm-card-header">
            <span class="adm-card-title">All Users</span>
            <span class="adm-muted" id="userCount">Loading…</span>
        </div>
        <div class="adm-table-wrap">
            <table class="adm-table" id="usersTable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersBody">
                    <tr><td colspan="7" class="adm-loading">Loading users…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</main>

{{-- CONFIRM MODAL --}}
<div class="adm-modal-overlay" id="confirmModal" style="display:none;">
    <div class="adm-modal">
        <div class="adm-modal-header">
            <span class="adm-modal-title" id="confirmTitle">Confirm Action</span>
            <button class="adm-modal-close" onclick="closeModal()">✕</button>
        </div>
        <p id="confirmMsg" style="font-size:14px;color:var(--adm-muted);margin-bottom:20px;line-height:1.6;"></p>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button class="adm-btn" onclick="closeModal()">Cancel</button>
            <button class="adm-btn adm-btn-danger" id="confirmBtn">Confirm</button>
        </div>
    </div>
</div>

{{-- ROLE MODAL --}}
<div class="adm-modal-overlay" id="roleModal" style="display:none;">
    <div class="adm-modal">
        <div class="adm-modal-header">
            <span class="adm-modal-title">Change Role</span>
            <button class="adm-modal-close" onclick="closeRoleModal()">✕</button>
        </div>
        <p style="font-size:14px;color:var(--adm-muted);margin-bottom:16px;">
            Changing role for: <strong id="roleTargetName"></strong>
        </p>
        <select id="newRoleSelect" class="adm-select" style="width:100%;margin-bottom:20px;">
            <option value="student">Student</option>
            <option value="moderator">Moderator</option>
            <option value="admin">Admin</option>
        </select>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button class="adm-btn" onclick="closeRoleModal()">Cancel</button>
            <button class="adm-btn adm-btn-primary" onclick="saveRole()">Save Role</button>
        </div>
    </div>
</div>

<script>
const SB_URL   = '{{ env("SUPABASE_URL") }}';
const SB_SVC   = '{{ env("SUPABASE_SERVICE_KEY") }}';
const ADMIN_ID = '{{ session("user_id") }}';

function svcHeaders() {
    return { 'apikey': SB_SVC, 'Authorization': `Bearer ${SB_SVC}`, 'Content-Type': 'application/json' };
}

let allUsers = [];
let pendingUserId = null, pendingAction = null;
let pendingRoleUserId = null, pendingRoleUserName = null, pendingOldRole = null;

// ── LOAD ─────────────────────────────────────────────────────
async function loadUsers() {
    try {
        const res = await fetch(
            `${SB_URL}/rest/v1/profiles?select=id,first_name,last_name,username,email,role,is_banned,created_at&order=created_at.desc`,
            { headers: svcHeaders() }
        );
        allUsers = await res.json();
        renderUsers(allUsers);
    } catch(e) {
        document.getElementById('usersBody').innerHTML = '<tr><td colspan="7" class="adm-loading">Failed to load.</td></tr>';
    }
}

function renderUsers(users) {
    const body = document.getElementById('usersBody');
    document.getElementById('userCount').textContent = `${users.length} user${users.length!==1?'s':''}`;
    if (!users.length) { body.innerHTML = '<tr><td colspan="7" class="adm-empty">No users found.</td></tr>'; return; }
    body.innerHTML = users.map(u => `
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="adm-list-avatar" style="width:34px;height:34px;font-size:12px;">${((u.first_name||'?')[0]+(u.last_name||'?')[0]).toUpperCase()}</div>
                    <span>${escH(u.first_name||'')} ${escH(u.last_name||'')}</span>
                </div>
            </td>
            <td><span class="adm-muted">@${escH(u.username||'')}</span></td>
            <td><span class="adm-muted" style="font-size:12px;">${escH(u.email||'')}</span></td>
            <td><span class="adm-badge ${u.role==='admin'?'badge-primary':u.role==='moderator'?'badge-warn':'badge-gray'}">${u.role||'student'}</span></td>
            <td><span class="adm-badge ${u.is_banned?'badge-danger':'badge-success'}">${u.is_banned?'Banned':'Active'}</span></td>
            <td><span class="adm-muted" style="font-size:12px;">${new Date(u.created_at).toLocaleDateString()}</span></td>
            <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <button class="adm-act-btn" onclick="openRoleModal('${u.id}','${escH(u.first_name||'')} ${escH(u.last_name||'')}','${u.role||'student'}')">Edit role</button>
                    ${u.is_banned
                        ? `<button class="adm-act-btn" onclick="openConfirm('${u.id}','unban','${escH(u.first_name||'')}')">Unban</button>`
                        : `<button class="adm-act-btn danger" onclick="openConfirm('${u.id}','ban','${escH(u.first_name||'')}')">Ban</button>`
                    }
                </div>
            </td>
        </tr>`).join('');
}

function filterUsers() {
    const q      = document.getElementById('userSearch').value.toLowerCase();
    const role   = document.getElementById('roleFilter').value;
    const status = document.getElementById('statusFilter').value;
    const filtered = allUsers.filter(u => {
        const matchQ = !q ||
            (u.first_name||'').toLowerCase().includes(q) ||
            (u.last_name||'').toLowerCase().includes(q)  ||
            (u.username||'').toLowerCase().includes(q)   ||
            (u.email||'').toLowerCase().includes(q);
        const matchRole   = !role   || (u.role||'student') === role;
        const matchStatus = !status ||
            (status==='banned' && u.is_banned) ||
            (status==='active' && !u.is_banned);
        return matchQ && matchRole && matchStatus;
    });
    renderUsers(filtered);
}

// ── BAN / UNBAN ───────────────────────────────────────────────
function openConfirm(userId, action, name) {
    pendingUserId = userId; pendingAction = action;
    document.getElementById('confirmTitle').textContent = action === 'ban' ? 'Ban User' : 'Unban User';
    document.getElementById('confirmMsg').textContent   =
        action === 'ban'
            ? `Are you sure you want to ban "${name}"? They will be unable to log in.`
            : `Are you sure you want to unban "${name}"? They will regain access.`;
    document.getElementById('confirmBtn').textContent   = action === 'ban' ? 'Ban User' : 'Unban User';
    document.getElementById('confirmBtn').className     = 'adm-btn ' + (action==='ban'?'adm-btn-danger':'adm-btn-primary');
    document.getElementById('confirmModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('confirmModal').style.display = 'none';
    pendingUserId = null; pendingAction = null;
}

document.getElementById('confirmBtn').addEventListener('click', async () => {
    if (!pendingUserId) return;
    const isBan = pendingAction === 'ban';
    try {
        await fetch(`${SB_URL}/rest/v1/profiles?id=eq.${pendingUserId}`, {
            method: 'PATCH', headers: svcHeaders(),
            body: JSON.stringify({ is_banned: isBan })
        });
        // ── LOG TO ADMIN_LOGS ──────────────────────────────────
        await logAction(
            isBan ? 'ban_user' : 'unban_user',
            'user',
            pendingUserId,
            isBan ? 'User banned by admin' : 'User unbanned by admin'
        );
        closeModal();
        loadUsers();
    } catch(e) { alert('Action failed: ' + e.message); }
});

// ── CHANGE ROLE ───────────────────────────────────────────────
function openRoleModal(userId, name, currentRole) {
    pendingRoleUserId  = userId;
    pendingRoleUserName = name;
    pendingOldRole     = currentRole;
    document.getElementById('roleTargetName').textContent = name;
    document.getElementById('newRoleSelect').value = currentRole;
    document.getElementById('roleModal').style.display = 'flex';
}
function closeRoleModal() {
    document.getElementById('roleModal').style.display = 'none';
}

async function saveRole() {
    const newRole = document.getElementById('newRoleSelect').value;
    if (newRole === pendingOldRole) { closeRoleModal(); return; }
    try {
        await fetch(`${SB_URL}/rest/v1/profiles?id=eq.${pendingRoleUserId}`, {
            method: 'PATCH', headers: svcHeaders(),
            body: JSON.stringify({ role: newRole })
        });
        // ── LOG TO ADMIN_LOGS ──────────────────────────────────
        await logAction(
            'change_role',
            'user',
            pendingRoleUserId,
            `Role changed from ${pendingOldRole} to ${newRole} for ${pendingRoleUserName}`
        );
        closeRoleModal();
        loadUsers();
    } catch(e) { alert('Failed: ' + e.message); }
}

// ── ADMIN LOG HELPER ──────────────────────────────────────────
async function logAction(action, targetType, targetId, notes) {
    try {
        await fetch(`${SB_URL}/rest/v1/admin_logs`, {
            method: 'POST',
            headers: svcHeaders(),
            body: JSON.stringify({
                admin_id:    ADMIN_ID || null,
                action,
                target_type: targetType,
                target_id:   targetId || null,
                notes:       notes || null
            })
        });
    } catch(e) { console.warn('Log failed:', e); }
}

function escH(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
document.getElementById('adminPageTitle').textContent = 'User Management';
loadUsers();
</script>
</body>
</html>
