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
            box-sizing: border-box;
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

        /* ── FILE UPLOAD ZONE ── */
        .ann-file-zone {
            border: 2px dashed var(--adm-border);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            background: #fafafa;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            position: relative;
        }
        .ann-file-zone:hover,
        .ann-file-zone.dragover {
            border-color: var(--adm-primary);
            background: rgba(26,95,122,0.04);
        }
        .ann-file-zone input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .ann-file-zone-icon { font-size: 28px; margin-bottom: 6px; }
        .ann-file-zone-label {
            font-size: 13px; font-weight: 600;
            color: var(--adm-primary);
        }
        .ann-file-zone-sub {
            font-size: 12px; color: var(--adm-light); margin-top: 3px;
        }
        .ann-file-list { margin-top: 12px; display: flex; flex-direction: column; gap: 6px; }
        .ann-file-chip {
            display: flex; align-items: center; gap: 10px;
            background: white;
            border: 1.5px solid var(--adm-border);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
        }
        .ann-file-chip-icon { font-size: 18px; flex-shrink: 0; }
        .ann-file-chip-name { flex: 1; font-weight: 600; color: var(--adm-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ann-file-chip-size { font-size: 11px; color: var(--adm-light); flex-shrink: 0; }
        .ann-file-chip-remove {
            background: none; border: none; cursor: pointer;
            color: var(--adm-light); font-size: 16px; line-height: 1;
            padding: 0 2px; transition: color 0.15s;
            flex-shrink: 0;
        }
        .ann-file-chip-remove:hover { color: var(--adm-danger); }

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

        /* ── LIST ── */
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

        /* Attached files in list */
        .ann-item-files { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px; }
        .ann-item-file-chip {
            display: inline-flex; align-items: center; gap: 5px;
            background: #f3f4f6; border-radius: 6px;
            padding: 4px 9px; font-size: 11px; font-weight: 600;
            color: var(--adm-primary); text-decoration: none;
            transition: background 0.15s;
        }
        .ann-item-file-chip:hover { background: rgba(26,95,122,0.12); }

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

        .adm-empty   { padding: 40px; text-align: center; color: var(--adm-light); font-size: 14px; }
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

        /* Upload progress bar */
        .ann-upload-progress {
            display: none;
            margin-top: 10px;
            background: #f3f4f6;
            border-radius: 99px;
            height: 6px;
            overflow: hidden;
        }
        .ann-upload-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--adm-primary), #2a9d8f);
            border-radius: 99px;
            width: 0%;
            transition: width 0.3s ease;
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

        {{-- ── FILE ATTACHMENTS ── --}}
        <div class="ann-form-group">
            <label class="ann-label">Attachments <span style="color:var(--adm-light);font-weight:400;">(optional — PDF, Word, images, etc. Max 20 MB each)</span></label>
            <div class="ann-file-zone" id="fileZone">
                <input type="file" id="annFiles" multiple
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.png,.jpg,.jpeg,.gif,.zip,.rar"
                       onchange="handleFileSelect(this.files)">
                <div class="ann-file-zone-icon">📎</div>
                <div class="ann-file-zone-label">Click to attach files or drag & drop here</div>
                <div class="ann-file-zone-sub">PDF, Word, Excel, PowerPoint, images, ZIP — up to 20 MB each</div>
            </div>
            <div class="ann-file-list" id="fileList"></div>
            <div class="ann-upload-progress" id="uploadProgress">
                <div class="ann-upload-progress-bar" id="uploadProgressBar"></div>
            </div>
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
const SB_URL  = '{{ config("services.supabase.url") }}';
const SB_ANON = '{{ config("services.supabase.anon_key") }}';
const CSRF    = document.querySelector('meta[name="csrf-token"]').content;

// ── Chosen files (kept in JS so we can show previews + remove them) ──
let _selectedFiles = [];

function setPriority(p, btn) {
    document.querySelectorAll('.ann-priority-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('annPriority').value = p;
}

function clearForm() {
    document.getElementById('annTitle').value = '';
    document.getElementById('annBody').value  = '';
    setPriority('normal', document.querySelector('[data-p="normal"]'));
    _selectedFiles = [];
    renderFileList();
    // Reset the file input so the same file can be re-selected after clear
    document.getElementById('annFiles').value = '';
}

/* ── FILE SELECTION ── */
function handleFileSelect(fileList) {
    const MAX_MB   = 20;
    const MAX_BYTES = MAX_MB * 1024 * 1024;
    Array.from(fileList).forEach(f => {
        if (f.size > MAX_BYTES) {
            toast(`"${f.name}" exceeds the ${MAX_MB} MB limit and was skipped.`, 'error');
            return;
        }
        // Avoid duplicates by name+size
        const exists = _selectedFiles.some(x => x.name === f.name && x.size === f.size);
        if (!exists) _selectedFiles.push(f);
    });
    renderFileList();
}

function removeFile(index) {
    _selectedFiles.splice(index, 1);
    renderFileList();
}

function fileIcon(name) {
    const ext = name.split('.').pop().toLowerCase();
    const map = {
        pdf: '📄', doc: '📝', docx: '📝',
        xls: '📊', xlsx: '📊',
        ppt: '📋', pptx: '📋',
        png: '🖼', jpg: '🖼', jpeg: '🖼', gif: '🖼',
        zip: '🗜', rar: '🗜', txt: '📃',
    };
    return map[ext] ?? '📎';
}

function formatSize(bytes) {
    if (bytes < 1024)        return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function renderFileList() {
    const el = document.getElementById('fileList');
    if (!_selectedFiles.length) { el.innerHTML = ''; return; }
    el.innerHTML = _selectedFiles.map((f, i) => `
        <div class="ann-file-chip">
            <span class="ann-file-chip-icon">${fileIcon(f.name)}</span>
            <span class="ann-file-chip-name" title="${escH(f.name)}">${escH(f.name)}</span>
            <span class="ann-file-chip-size">${formatSize(f.size)}</span>
            <button class="ann-file-chip-remove" onclick="removeFile(${i})" title="Remove">×</button>
        </div>`).join('');
}

/* ── DRAG & DROP ── */
const fileZone = document.getElementById('fileZone');
fileZone.addEventListener('dragover',  e => { e.preventDefault(); fileZone.classList.add('dragover'); });
fileZone.addEventListener('dragleave', () => fileZone.classList.remove('dragover'));
fileZone.addEventListener('drop', e => {
    e.preventDefault();
    fileZone.classList.remove('dragover');
    if (e.dataTransfer.files.length) handleFileSelect(e.dataTransfer.files);
});

/* ── SEND — uses FormData so files travel as multipart ── */
async function sendAnnouncement() {
    const title    = document.getElementById('annTitle').value.trim();
    const body     = document.getElementById('annBody').value.trim();
    const priority = document.getElementById('annPriority').value;

    if (!title) { toast('Please enter a title.',   'error'); return; }
    if (!body)  { toast('Please enter a message.', 'error'); return; }

    const btn = document.getElementById('sendBtn');
    btn.disabled    = true;
    btn.textContent = _selectedFiles.length ? 'Uploading…' : 'Sending…';

    // Show progress bar if files are attached
    const progressWrap = document.getElementById('uploadProgress');
    const progressBar  = document.getElementById('uploadProgressBar');
    if (_selectedFiles.length) {
        progressWrap.style.display = 'block';
        progressBar.style.width    = '30%';
    }

    try {
        const fd = new FormData();
        fd.append('title',    title);
        fd.append('body',     body);
        fd.append('priority', priority);
        fd.append('_token',   CSRF);
        _selectedFiles.forEach(f => fd.append('files[]', f));

        if (_selectedFiles.length) progressBar.style.width = '60%';

        const res  = await fetch('{{ route("admin.announcements.store") }}', {
            method:  'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    fd,
            // Note: do NOT set Content-Type — browser sets it with the boundary for multipart
        });

        if (_selectedFiles.length) progressBar.style.width = '90%';

        const data = await res.json();

        if (!res.ok) throw new Error(data.error || `Server error ${res.status}`);

        progressBar.style.width = '100%';
        setTimeout(() => { progressWrap.style.display = 'none'; progressBar.style.width = '0%'; }, 600);

        if (data.file_warning) {
            toast('⚠️ ' + data.file_warning, 'warning');
        } else if (data.rpc_warning) {
            toast('⚠️ Announcement saved but notifications may not have sent.', 'warning');
        } else {
            const fileCount = (data.uploaded_files ?? []).length;
            toast('✅ Announcement sent!' + (fileCount ? ` (${fileCount} file${fileCount !== 1 ? 's' : ''} attached)` : ''));
        }

        clearForm();
        loadAnnouncements();

    } catch (e) {
        progressWrap.style.display = 'none';
        progressBar.style.width    = '0%';
        toast('Failed: ' + e.message, 'error');
    } finally {
        btn.disabled    = false;
        btn.textContent = '📣 Send to All Users';
    }
}

/* ── LOAD LIST — reads Supabase + joins files ── */
async function loadAnnouncements() {
    try {
        // Fetch announcements
        const annRes  = await fetch(
            `${SB_URL}/rest/v1/announcements?order=created_at.desc&limit=100`,
            { headers: { 'apikey': SB_ANON, 'Authorization': `Bearer ${SB_ANON}` } }
        );
        if (!annRes.ok) throw new Error(`HTTP ${annRes.status}`);
        const rows = await annRes.json();

        // Fetch all files in one request (no individual per-announcement calls)
        const filesRes = await fetch(
            `${SB_URL}/rest/v1/announcement_files?order=created_at.asc`,
            { headers: { 'apikey': SB_ANON, 'Authorization': `Bearer ${SB_ANON}` } }
        );
        const allFiles = filesRes.ok ? await filesRes.json() : [];

        // Group files by announcement_id
        const filesByAnn = {};
        (Array.isArray(allFiles) ? allFiles : []).forEach(f => {
            (filesByAnn[f.announcement_id] = filesByAnn[f.announcement_id] ?? []).push(f);
        });

        const el = document.getElementById('annList');
        document.getElementById('annCount').textContent =
            `${rows.length} announcement${rows.length !== 1 ? 's' : ''}`;

        if (!rows.length) {
            el.innerHTML = '<div class="adm-empty">No announcements yet.</div>';
            return;
        }

        const icons = { normal: '📢', important: '⚠️', urgent: '🚨' };
        el.innerHTML = rows.map(a => {
            const annFiles = filesByAnn[a.id] ?? [];
            const filesHtml = annFiles.length
                ? `<div class="ann-item-files">
                    ${annFiles.map(f => `
                        <a class="ann-item-file-chip" href="${escH(f.file_url)}" target="_blank" rel="noopener">
                            ${fileIcon(f.file_name)} ${escH(f.file_name)}
                        </a>`).join('')}
                   </div>`
                : '';

            return `
            <div class="ann-item" id="ann-${escH(a.id)}">
                <div class="ann-item-icon icon-${escH(a.priority)}">
                    ${icons[a.priority] ?? '📢'}
                </div>
                <div class="ann-item-body">
                    <div class="ann-item-title">${escH(a.title)}</div>
                    <div class="ann-item-body-text">${escH(a.body)}</div>
                    ${filesHtml}
                    <div class="ann-item-meta">
                        <span class="ann-badge badge-${escH(a.priority)}">${escH(a.priority)}</span>
                        ${!a.is_active ? '<span class="ann-badge badge-inactive">Inactive</span>' : ''}
                        ${annFiles.length ? `<span style="font-size:11px;color:var(--adm-light);">📎 ${annFiles.length} file${annFiles.length !== 1 ? 's' : ''}</span>` : ''}
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
            </div>`;
        }).join('');

    } catch(e) {
        document.getElementById('annList').innerHTML = '<div class="adm-empty">Failed to load announcements.</div>';
        console.error('loadAnnouncements error:', e);
    }
}

/* ── TOGGLE ACTIVE ── */
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

/* ── DELETE ── */
async function deleteAnn(id) {
    if (!confirm('Delete this announcement and all its attached files?')) return;
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

/* ── HELPERS ── */
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

function fileIcon(name) {
    const ext = (name || '').split('.').pop().toLowerCase();
    const map = {
        pdf:'📄', doc:'📝', docx:'📝', xls:'📊', xlsx:'📊',
        ppt:'📋', pptx:'📋', png:'🖼', jpg:'🖼', jpeg:'🖼',
        gif:'🖼', zip:'🗜', rar:'🗜', txt:'📃',
    };
    return map[ext] ?? '📎';
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
