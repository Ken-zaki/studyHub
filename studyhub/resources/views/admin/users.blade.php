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

{{-- CONFIRM MODAL (ban / unban) --}}
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

{{-- DELETE MODAL --}}
<div class="adm-modal-overlay" id="deleteModal" style="display:none;">
    <div class="adm-modal">
        <div class="adm-modal-header">
            <span class="adm-modal-title" style="color:var(--adm-danger);">Delete User</span>
            <button class="adm-modal-close" onclick="closeDeleteModal()">✕</button>
        </div>

        {{-- Admin-protected error state --}}
        <div id="deleteBlockedMsg" style="display:none;">
            <div style="background:#fff5f5;border:1px solid rgba(255,107,107,0.4);border-radius:10px;
                        padding:16px;margin-bottom:20px;display:flex;gap:12px;align-items:flex-start;">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none"
                     viewBox="0 0 24 24" stroke="#c0392b" stroke-width="2" style="flex-shrink:0;margin-top:1px;">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0
                             2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898
                             0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
                <div>
                    <p style="font-size:14px;font-weight:700;color:#c0392b;margin-bottom:4px;">
                        Cannot Delete Admin Account
                    </p>
                    <p style="font-size:13px;color:#c0392b;line-height:1.6;margin:0;">
                        You cannot delete your own admin account while you are logged in.
                        To remove this account, log in as a different admin first.
                    </p>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;">
                <button class="adm-btn" onclick="closeDeleteModal()">Close</button>
            </div>
        </div>

        {{-- Normal delete confirmation state --}}
        <div id="deleteConfirmMsg">
            <p style="font-size:14px;color:var(--adm-muted);margin-bottom:8px;line-height:1.6;">
                You are about to permanently delete
                <strong id="deleteTargetName"></strong>.
            </p>
            <div style="background:#fff5f5;border:1px solid rgba(255,107,107,0.3);
                        border-radius:10px;padding:12px 14px;margin-bottom:20px;">
                <p style="font-size:13px;color:#c0392b;line-height:1.6;margin:0;">
                    <strong>This cannot be undone.</strong>
                    All their tasks, notes, resources, messages, and profile data
                    will be permanently deleted.
                </p>
            </div>
            <p style="font-size:13px;color:var(--adm-muted);margin-bottom:8px;">
                Type <code id="deleteConfirmWord"
                    style="background:#f3f4f6;padding:2px 7px;border-radius:5px;font-size:13px;"></code>
                to confirm:
            </p>
            <input type="text" id="deleteConfirmInput"
                placeholder="Type username here…"
                oninput="checkDeleteInput()"
                style="width:100%;padding:10px 14px;
                       border:1px solid var(--adm-border);border-radius:10px;
                       font-family:'DM Sans',sans-serif;font-size:14px;
                       margin-bottom:6px;outline:none;">
            <div id="deleteInputError"
                 style="display:none;font-size:12px;color:var(--adm-danger);margin-bottom:14px;"></div>
            <div style="height:14px;"></div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button class="adm-btn" onclick="closeDeleteModal()">Cancel</button>
                <button class="adm-btn adm-btn-danger" id="deleteConfirmBtn"
                        disabled onclick="executeDelete()">
                    Delete Permanently
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// SB_URL, SB_SVC, SB_ANON, ADMIN_ID are set by the sidebar partial as window.*

function svcHeaders() {
    return {
        'apikey':        window.SB_SVC,
        'Authorization': `Bearer ${window.SB_SVC}`,
        'Content-Type':  'application/json'
    };
}

let allUsers = [];
let pendingUserId = null, pendingAction = null;
let pendingRoleUserId = null, pendingRoleUserName = null, pendingOldRole = null;
let deleteTargetId = null, deleteTargetUsername = null;

// ── LOAD ─────────────────────────────────────────────────────
async function loadUsers() {
    try {
        const res = await fetch(
            `${window.SB_URL}/rest/v1/profiles?select=id,first_name,last_name,username,email,role,is_banned,created_at&order=created_at.desc`,
            { headers: svcHeaders() }
        );
        allUsers = await res.json();
        renderUsers(allUsers);
    } catch(e) {
        document.getElementById('usersBody').innerHTML =
            '<tr><td colspan="7" class="adm-loading">Failed to load.</td></tr>';
    }
}

function renderUsers(users) {
    const body = document.getElementById('usersBody');
    document.getElementById('userCount').textContent =
        `${users.length} user${users.length !== 1 ? 's' : ''}`;

    if (!users.length) {
        body.innerHTML = '<tr><td colspan="7" class="adm-empty">No users found.</td></tr>';
        return;
    }

    body.innerHTML = users.map(u => `
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div class="adm-list-avatar" style="width:34px;height:34px;font-size:12px;">
                        ${((u.first_name||'?')[0]+(u.last_name||'?')[0]).toUpperCase()}
                    </div>
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
                    <button class="adm-act-btn"
                        onclick="openRoleModal('${u.id}','${escH(u.first_name||'')} ${escH(u.last_name||'')}','${u.role||'student'}')">
                        Edit role
                    </button>
                    ${u.is_banned
                        ? `<button class="adm-act-btn" onclick="openConfirm('${u.id}','unban','${escH(u.first_name||'')}')">Unban</button>`
                        : `<button class="adm-act-btn danger" onclick="openConfirm('${u.id}','ban','${escH(u.first_name||'')}')">Ban</button>`
                    }
                    <button class="adm-act-btn danger"
                        onclick="openDeleteModal('${u.id}','${escH(u.username||'')}','${escH(u.first_name||'')} ${escH(u.last_name||'')}')">
                        Delete
                    </button>
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
    document.getElementById('confirmMsg').textContent =
        action === 'ban'
            ? `Are you sure you want to ban "${name}"? They will be unable to log in.`
            : `Are you sure you want to unban "${name}"? They will regain access.`;
    document.getElementById('confirmBtn').textContent = action === 'ban' ? 'Ban User' : 'Unban User';
    document.getElementById('confirmBtn').className   = 'adm-btn ' + (action==='ban' ? 'adm-btn-danger' : 'adm-btn-primary');
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
        await fetch(`${window.SB_URL}/rest/v1/profiles?id=eq.${pendingUserId}`, {
            method: 'PATCH', headers: svcHeaders(),
            body: JSON.stringify({ is_banned: isBan })
        });
        await logAction(
            isBan ? 'ban_user' : 'unban_user',
            'user', pendingUserId,
            isBan ? 'User banned by admin' : 'User unbanned by admin'
        );
        closeModal();
        loadUsers();
    } catch(e) { alert('Action failed: ' + e.message); }
});

// ── CHANGE ROLE ───────────────────────────────────────────────
function openRoleModal(userId, name, currentRole) {
    pendingRoleUserId   = userId;
    pendingRoleUserName = name;
    pendingOldRole      = currentRole;
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
        await fetch(`${window.SB_URL}/rest/v1/profiles?id=eq.${pendingRoleUserId}`, {
            method: 'PATCH', headers: svcHeaders(),
            body: JSON.stringify({ role: newRole })
        });
        await logAction(
            'change_role', 'user', pendingRoleUserId,
            `Role changed from ${pendingOldRole} to ${newRole} for ${pendingRoleUserName}`
        );
        closeRoleModal();
        loadUsers();
    } catch(e) { alert('Failed: ' + e.message); }
}

// ── DELETE ────────────────────────────────────────────────────
function openDeleteModal(userId, username, fullName) {
    deleteTargetId       = userId;
    deleteTargetUsername = username;

    // Reset modal state
    document.getElementById('deleteBlockedMsg').style.display  = 'none';
    document.getElementById('deleteConfirmMsg').style.display  = 'block';
    document.getElementById('deleteConfirmInput').value        = '';
    document.getElementById('deleteConfirmBtn').disabled       = true;
    document.getElementById('deleteInputError').style.display  = 'none';
    document.getElementById('deleteTargetName').textContent    = fullName;
    document.getElementById('deleteConfirmWord').textContent   = username;
    document.getElementById('deleteModal').style.display       = 'flex';

    // If this is the logged-in admin's own account, show the blocked message
    if (userId === window.ADMIN_ID) {
        document.getElementById('deleteConfirmMsg').style.display = 'none';
        document.getElementById('deleteBlockedMsg').style.display = 'block';
    }
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    deleteTargetId = null; deleteTargetUsername = null;
}

function checkDeleteInput() {
    const val  = document.getElementById('deleteConfirmInput').value.trim();
    const btn  = document.getElementById('deleteConfirmBtn');
    const errEl = document.getElementById('deleteInputError');

    if (val && val !== deleteTargetUsername) {
        errEl.textContent   = 'Username does not match.';
        errEl.style.display = 'block';
    } else {
        errEl.style.display = 'none';
    }

    btn.disabled = (val !== deleteTargetUsername);
}

async function executeDelete() {
    if (!deleteTargetId) return;

    // Hard guard — never allow deleting own account
    if (deleteTargetId === window.ADMIN_ID) {
        document.getElementById('deleteConfirmMsg').style.display = 'none';
        document.getElementById('deleteBlockedMsg').style.display = 'block';
        return;
    }

    const btn = document.getElementById('deleteConfirmBtn');
    btn.disabled    = true;
    btn.textContent = 'Deleting…';

    try {
        // Step 1 — delete profile row.
        // Your schema has ON DELETE CASCADE on all child tables
        // (tasks, notes, calendar_events, resources, messages, etc.)
        // so one DELETE on profiles removes everything.
        const profileRes = await fetch(
            `${window.SB_URL}/rest/v1/profiles?id=eq.${deleteTargetId}`,
            { method: 'DELETE', headers: svcHeaders() }
        );
        if (!profileRes.ok) throw new Error('Profile deletion failed.');

        // Step 2 — delete from auth.users via Supabase Admin API.
        // This requires the service key and removes login credentials.
        const authRes = await fetch(
            `${window.SB_URL}/auth/v1/admin/users/${deleteTargetId}`,
            {
                method:  'DELETE',
                headers: {
                    'apikey':        window.SB_SVC,
                    'Authorization': `Bearer ${window.SB_SVC}`,
                }
            }
        );
        // auth deletion failing is non-fatal (profile is already gone)
        if (!authRes.ok) {
            console.warn('Auth user deletion warning:', await authRes.text());
        }

        // Step 3 — log the action
        await logAction(
            'delete_user', 'user', deleteTargetId,
            `User @${deleteTargetUsername} permanently deleted by admin`
        );

        closeDeleteModal();
        loadUsers();

    } catch(e) {
        btn.disabled    = false;
        btn.textContent = 'Delete Permanently';
        const errEl = document.getElementById('deleteInputError');
        errEl.textContent   = 'Deletion failed: ' + e.message;
        errEl.style.display = 'block';
    }
}

// ── ADMIN LOG HELPER ──────────────────────────────────────────
async function logAction(action, targetType, targetId, notes) {
    try {
        await fetch(`${window.SB_URL}/rest/v1/admin_logs`, {
            method: 'POST',
            headers: svcHeaders(),
            body: JSON.stringify({
                admin_id:    window.ADMIN_ID || null,
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
