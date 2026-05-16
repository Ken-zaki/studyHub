<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Announcements – StudyHub Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <style>
        .ann-compose {
            background: white;
            border: 1px solid var(--adm-border);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .ann-compose-title {
            font-family: 'Crimson Pro', serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--adm-primary);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ann-form-group { margin-bottom: 14px; }
        .ann-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--adm-muted);
            margin-bottom: 6px;
        }
        .ann-input, .ann-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--adm-border);
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            color: var(--adm-text);
            background: #fafafa;
            transition: border-color 0.2s;
            outline: none;
        }
        .ann-input:focus, .ann-textarea:focus {
            border-color: var(--adm-primary);
            background: white;
        }
        .ann-textarea { resize: vertical; min-height: 110px; }
        .ann-priority-group { display: flex; gap: 10px; flex-wrap: wrap; }
        .ann-priority-btn {
            flex: 1; min-width: 100px;
            padding: 10px 14px;
            border: 2px solid var(--adm-border);
            border-radius: 10px;
            background: white;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px; font-weight: 600;
            cursor: pointer; text-align: center;
            transition: all 0.18s;
            color: var(--adm-muted);
        }
        .ann-priority-btn.active[data-p="normal"]    { border-color: var(--adm-primary); color: var(--adm-primary); background: rgba(26,95,122,0.07); }
        .ann-priority-btn.active[data-p="important"] { border-color: var(--adm-accent);  color: var(--adm-accent);  background: rgba(245,158,66,0.1); }
        .ann-priority-btn.active[data-p="urgent"]    { border-color: var(--adm-danger);  color: var(--adm-danger);  background: rgba(255,107,107,0.1); }
        .ann-priority-btn:hover { border-color: var(--adm-primary); color: var(--adm-primary); }
        .ann-submit-row { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
        .ann-btn {
            padding: 10px 22px; border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px; font-weight: 600;
            cursor: pointer;
            border: 1.5px solid var(--adm-border);
            background: white; color: var(--adm-muted);
            transition: all 0.18s;
        }
        .ann-btn:hover { border-color: var(--adm-primary); color: var(--adm-primary); }
        .ann-btn-primary {
            background: linear-gradient(135deg, var(--adm-primary), #2a9d8f);
            color: white; border-color: transparent;
        }
        .ann-btn-primary:hover { opacity: 0.9; color: white; }
        .ann-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

        .ann-list-card {
            background: white;
            border: 1px solid var(--adm-border);
            border-radius: 14px;
            overflow: hidden;
        }
        .ann-list-header {
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            border-bottom: 1px solid var(--adm-border);
        }
        .ann-list-title { font-family: 'Crimson Pro', serif; font-size: 18px; font-weight: 700; color: var(--adm-text); }
        .ann-item {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 18px 24px;
            border-bottom: 1px solid var(--adm-border);
            transition: background 0.15s;
        }
        .ann-item:last-child { border-bottom: none; }
        .ann-item:hover { background: #fafbfc; }
        .ann-item-icon {
            width: 42px; height: 42px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .icon-normal    { background: rgba(26,95,122,0.1); }
        .icon-important { background: rgba(245,158,66,0.12); }
        .icon-urgent    { background: rgba(255,107,107,0.12); }
        .ann-item-body { flex: 1; min-width: 0; }
        .ann-item-title { font-size: 15px; font-weight: 700; color: var(--adm-text); margin-bottom: 4px; }
        .ann-item-body-text {
            font-size: 13px; color: var(--adm-muted); line-height: 1.5;
            display: -webkit-box; -webkit-line-clamp: 2;
            -webkit-box-orient: vertical; overflow: hidden;
        }
        .ann-item-meta { display: flex; align-items: center; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
        .ann-badge { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 20px; }
        .badge-normal    { background: rgba(26,95,122,0.1);    color: var(--adm-primary); }
        .badge-important { background: rgba(245,158,66,0.15);  color: #d97706; }
        .badge-urgent    { background: rgba(255,107,107,0.15); color: #dc2626; }
        .badge-inactive  { background: #f3f4f6; color: var(--adm-light); }
        .ann-item-time { font-size: 12px; color: var(--adm-light); }
        .ann-item-actions { display: flex; gap: 6px; flex-shrink: 0; align-items: center; }
        .ann-act-btn {
            padding: 6px 12px; border-radius: 8px;
            font-size: 12px; font-weight: 600;
            border: 1.5px solid var(--adm-border);
            background: white; cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: all 0.15s; color: var(--adm-muted);
        }
        .ann-act-btn:hover { border-color: var(--adm-primary); color: var(--adm-primary); }
        .ann-act-btn.danger:hover { border-color: var(--adm-danger); color: var(--adm-danger); }

        .ann-toggle { position: relative; display: inline-block; width: 36px; height: 20px; }
        .ann-toggle input { opacity: 0; width: 0; height: 0; }
        .ann-toggle-slider {
            position: absolute; inset: 0;
            background: #d1d5db; border-radius: 20px;
            cursor: pointer; transition: background 0.25s;
        }
        .ann-toggle-slider::before {
            content: ''; position: absolute;
            width: 14px; height: 14px;
            left: 3px; bottom: 3px;
            background: white; border-radius: 50%;
            transition: transform 0.25s;
        }
        .ann-toggle input:checked + .ann-toggle-slider { background: var(--adm-success); }
        .ann-toggle input:checked + .ann-toggle-slider::before { transform: translateX(16px); }

        .adm-empty { padding: 40px; text-align: center; color: var(--adm-light); font-size: 14px; }
        .adm-loading { padding: 40px; text-align: center; color: var(--adm-light); font-size: 14px; }

        .ann-toast {
            position: fixed; bottom: 28px; right: 28px;
            background: var(--adm-success); color: white;
            padding: 12px 22px; border-radius: 12px;
            font-size: 14px; font-weight: 600;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 9999; animation: slideUp 0.3s ease;
        }
        .ann-toast.error   { background: var(--adm-danger); }
        .ann-toast.warning { background: var(--adm-warning); }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
    </style>
</head>
<body>

@php $activeAdmin = 'announcements'; @endphp
@include('admin.sidebar')

<main class="adm-main">

    {{-- ── COMPOSE ── --}}
    <div class="ann-compose">
        <div class="ann-compose-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 11l19-9-9 19-2-8-8-2z"/>
            </svg>
            New Announcement
        </div>

        <div class="ann-form-group">
            <label class="ann-label">Title <span style="color:var(--adm-danger)">*</span></label>
            <input type="text" class="ann-input" id="annTitle"
                   placeholder="e.g. System maintenance scheduled for Friday…">
        </div>

        <div class="ann-form-group">
            <label class="ann-label">Message <span style="color:var(--adm-danger)">*</span></label>
            <textarea class="ann-textarea" id="annBody"
                      placeholder="Write your announcement here. Users will receive this as a notification."></textarea>
        </div>

        <div class="ann-form-group">
            <label class="ann-label">Priority</label>
            <div class="ann-priority-group">
                <button class="ann-priority-btn active" data-p="normal"    onclick="setPriority('normal',    this)">📢 Normal</button>
                <button class="ann-priority-btn"        data-p="important" onclick="setPriority('important', this)">⚠️ Important</button>
                <button class="ann-priority-btn"        data-p="urgent"    onclick="setPriority('urgent',    this)">🚨 Urgent</button>
            </div>
            <input type="hidden" id="annPriority" value="normal">
        </div>

        <div class="ann-submit-row">
            <button class="ann-btn" onclick="clearForm()">Clear</button>
            <button class="ann-btn ann-btn-primary" id="sendBtn" onclick="sendAnnouncement()">
                📣 Send to All Users
            </button>
        </div>
    </div>

    {{-- ── LIST ── --}}
    <div class="ann-list-card">
        <div class="ann-list-header">
            <span class="ann-list-title">Past Announcements</span>
            <span style="font-size:13px;color:var(--adm-light);" id="annCount">Loading…</span>
        </div>
        <div id="annList">
            <div class="adm-loading">Loading…</div>
        </div>
    </div>

</main>

<script>
// ── All Supabase access goes through Laravel routes — no credentials needed in JS ──
const SB_URL  = '{{ config("services.supabase.url") }}';
const SB_ANON = '{{ config("services.supabase.anon_key") }}';

// CSRF token for Laravel POST/PATCH/DELETE requests
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

function setPriority(p, btn) {
    document.querySelectorAll('.ann-priority-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('annPriority').value = p;
}

function clearForm() {
    document.getElementById('annTitle').value = '';
    document.getElementById('annBody').value  = '';
    setPriority('normal', document.querySelector('[data-p="normal"]'));
}

/* ── SEND — calls the Laravel controller, not Supabase directly ── */
async function sendAnnouncement() {
    const title    = document.getElementById('annTitle').value.trim();
    const body     = document.getElementById('annBody').value.trim();
    const priority = document.getElementById('annPriority').value;

    if (!title) { toast('Please enter a title.',   'error'); return; }
    if (!body)  { toast('Please enter a message.', 'error'); return; }

    const btn = document.getElementById('sendBtn');
    btn.disabled    = true;
    btn.textContent = 'Sending…';

    try {
        const res = await fetch('{{ route("admin.announcements.store") }}', {
            method:  'POST',
            headers: {
                'Content-Type':     'application/json',
                'Accept':           'application/json',
                'X-CSRF-TOKEN':     CSRF,
            },
            body: JSON.stringify({ title, body, priority }),
        });

        const data = await res.json();

        if (!res.ok) {
            throw new Error(data.error || `Server error ${res.status}`);
        }

        if (data.rpc_warning) {
            toast('⚠️ Announcement saved but notifications may not have sent. Check logs.', 'warning');
        } else {
            toast('✅ Announcement sent to all users!');
        }

        clearForm();
        loadAnnouncements();

    } catch (e) {
        toast('Failed: ' + e.message, 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = '📣 Send to All Users';
    }
}

/* ── LOAD LIST — reads directly from Supabase (read-only, anon key is fine) ── */
async function loadAnnouncements() {
    try {
        const res  = await fetch(
            `${SB_URL}/rest/v1/announcements?order=created_at.desc&limit=100`,
            { headers: { 'apikey': SB_ANON, 'Authorization': `Bearer ${SB_ANON}` } }
        );

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const rows = await res.json();
        const el   = document.getElementById('annList');

        document.getElementById('annCount').textContent =
            `${rows.length} announcement${rows.length !== 1 ? 's' : ''}`;

        if (!rows.length) {
            el.innerHTML = '<div class="adm-empty">No announcements yet.</div>';
            return;
        }

        const icons = { normal: '📢', important: '⚠️', urgent: '🚨' };
        el.innerHTML = rows.map(a => `
            <div class="ann-item" id="ann-${escH(a.id)}">
                <div class="ann-item-icon icon-${escH(a.priority)}">
                    ${icons[a.priority] ?? '📢'}
                </div>
                <div class="ann-item-body">
                    <div class="ann-item-title">${escH(a.title)}</div>
                    <div class="ann-item-body-text">${escH(a.body)}</div>
                    <div class="ann-item-meta">
                        <span class="ann-badge badge-${escH(a.priority)}">${escH(a.priority)}</span>
                        ${!a.is_active ? '<span class="ann-badge badge-inactive">Inactive</span>' : ''}
                        <span class="ann-item-time">${timeAgo(a.created_at)}</span>
                    </div>
                </div>
                <div class="ann-item-actions">
                    <label class="ann-toggle" title="${a.is_active ? 'Active — click to deactivate' : 'Inactive — click to activate'}">
                        <input type="checkbox" ${a.is_active ? 'checked' : ''}
                               onchange="toggleActive('${escH(a.id)}', this.checked)">
                        <span class="ann-toggle-slider"></span>
                    </label>
                    <button class="ann-act-btn danger" onclick="deleteAnn('${escH(a.id)}')">🗑</button>
                </div>
            </div>`).join('');

    } catch(e) {
        document.getElementById('annList').innerHTML = '<div class="adm-empty">Failed to load announcements.</div>';
        console.error('loadAnnouncements error:', e);
    }
}

/* ── TOGGLE ACTIVE — calls Laravel controller ── */
async function toggleActive(id, val) {
    try {
        await fetch(`/admin/announcements/${id}`, {
            method:  'PATCH',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify({ is_active: val }),
        });
        loadAnnouncements();
    } catch(e) {
        toast('Failed to update status.', 'error');
    }
}

/* ── DELETE — calls Laravel controller ── */
async function deleteAnn(id) {
    if (!confirm('Delete this announcement?')) return;
    try {
        await fetch(`/admin/announcements/${id}`, {
            method:  'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        });
        toast('Announcement deleted.');
        loadAnnouncements();
    } catch(e) {
        toast('Failed to delete.', 'error');
    }
}

function escH(t) {
    if (t == null) return '';
    const d = document.createElement('div');
    d.textContent = String(t);
    return d.innerHTML;
}

function timeAgo(ts) {
    const s = Math.floor((Date.now() - new Date(ts)) / 1000);
    if (s < 60)     return 'just now';
    if (s < 3600)   return `${Math.floor(s/60)}m ago`;
    if (s < 86400)  return `${Math.floor(s/3600)}h ago`;
    if (s < 604800) return `${Math.floor(s/86400)}d ago`;
    return new Date(ts).toLocaleDateString();
}

function toast(msg, type = '') {
    const t = document.createElement('div');
    t.className   = 'ann-toast' + (type ? ` ${type}` : '');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3200);
}

document.getElementById('adminPageTitle').textContent = 'Announcements';
loadAnnouncements();
</script>
</body>
</html>
