<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Approval - StudyHub Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin_resources.css') }}">
</head>
<body>
@php $activeAdmin = 'resources'; @endphp
@include('admin.sidebar')

<main class="adm-main">

    <!-- ── TOOLBAR ── -->
    <div class="adm-toolbar">
        <div class="adm-search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
            </svg>
            <input type="text" id="resSearch" placeholder="Search by title, subject…" oninput="filterRes()">
        </div>
        <select id="resFilter" class="adm-select" onchange="loadResources()">
            <option value="false">⏳ Pending Approval</option>
            <option value="true">✅ Approved</option>
            <option value="">📋 All Resources</option>
        </select>
        <select id="resTypeFilter" class="adm-select" onchange="filterRes()">
            <option value="">All Types</option>
            <option value="image">Image</option>
            <option value="exercise">Exercise</option>
            <option value="slides">Slides</option>
            <option value="reviewer">Reviewer</option>
            <option value="notes">Notes</option>
            <option value="video">Video</option>
            <option value="link">Link</option>
        </select>
    </div>

    <!-- ── RESOURCE CARDS ── -->
    <div id="resourcesList">
        <div class="adm-loading">Loading resources…</div>
    </div>

</main>

<!-- ════════════════════════════════════════════════════════
     DETAIL PANEL (slides from right)
════════════════════════════════════════════════════════ -->
<div class="adm-res-overlay" id="resOverlay" onclick="closePanelOutside(event)">
    <div class="adm-res-panel" id="resPanel">
        <div class="adm-res-panel-topbar">
            <div>
                <div class="adm-res-panel-title" id="panelTitle">Resource Detail</div>
                <div id="panelBadges" style="display:flex;gap:6px;flex-wrap:wrap;"></div>
            </div>
            <button class="adm-res-panel-close" onclick="closePanel()">✕</button>
        </div>
        <div class="adm-res-panel-body" id="panelBody">
            <div class="adm-loading">Loading…</div>
        </div>
        <div class="adm-res-panel-actions" id="panelActions"></div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     REJECT MODAL
════════════════════════════════════════════════════════ -->
<div class="adm-modal-overlay" id="rejectModal" style="display:none;">
    <div class="adm-modal" style="max-width:460px;">
        <div class="adm-modal-header">
            <span class="adm-modal-title">❌ Reject Resource</span>
            <button class="adm-modal-close" onclick="closeRejectModal()">✕</button>
        </div>
        <label class="adm-form-label">Reason <span style="color:var(--adm-danger)">*</span></label>
        <select class="adm-reject-reason" id="rejectReason">
            <option value="">Select a reason…</option>
            <option value="Inappropriate content">Inappropriate content</option>
            <option value="Spam or misleading information">Spam or misleading</option>
            <option value="Copyright violation">Copyright violation</option>
            <option value="Low quality or incomplete">Low quality / incomplete</option>
            <option value="Violates community guidelines">Violates community guidelines</option>
            <option value="Other">Other (see notes)</option>
        </select>
        <label class="adm-form-label" style="margin-top:10px;">Notes <span class="adm-muted">(optional)</span></label>
        <textarea class="adm-textarea" id="rejectNotes" rows="3" placeholder="Additional context for your decision…"></textarea>
        <div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end;">
            <button class="adm-btn" onclick="closeRejectModal()">Cancel</button>
            <button class="adm-btn adm-btn-danger" onclick="confirmReject()">Confirm Reject</button>
        </div>
    </div>
</div>

<script>
const SB_URL = '{{ env("SUPABASE_URL") }}';
const SB_SVC = '{{ env("SUPABASE_SERVICE_KEY") }}';
function svcH() { return { 'apikey': SB_SVC, 'Authorization': `Bearer ${SB_SVC}`, 'Content-Type': 'application/json' }; }

const ICONS = { pdf:'📄', docx:'📝', pptx:'📊', video:'🎬', image:'🖼️', link:'🔗', notes:'📄', exercise:'📝', slides:'📊', reviewer:'📋' };
const ADMIN_ID = '{{ session("user_id") }}';

let allRes = [];
let pendingRejectId = null;
let uploaderCache = {};

// ── LOAD ─────────────────────────────────────────────────────
async function loadResources() {
    document.getElementById('resourcesList').innerHTML = '<div class="adm-loading">Loading resources…</div>';
    const ap = document.getElementById('resFilter').value;
    const q  = ap === '' ? '' : `is_approved=eq.${ap}&`;

    const res = await fetch(
        `${SB_URL}/rest/v1/resources?${q}` +
        `select=id,title,description,content,subject,file_type,file_url,visibility,is_approved,tags,view_count,created_at,uploaded_by&` +
        `order=created_at.desc`,
        { headers: svcH() }
    );
    allRes = await res.json();

    // Fetch uploaders
    const ids = [...new Set(allRes.map(r => r.uploaded_by).filter(Boolean))];
    if (ids.length) {
        const pRes = await fetch(
            `${SB_URL}/rest/v1/profiles?id=in.(${ids.join(',')})&select=id,first_name,last_name,username`,
            { headers: svcH() }
        );
        (await pRes.json()).forEach(p => { uploaderCache[p.id] = p; });
    }
    filterRes();
}

// ── FILTER ───────────────────────────────────────────────────
function filterRes() {
    const q    = (document.getElementById('resSearch').value || '').toLowerCase();
    const type = document.getElementById('resTypeFilter').value;
    const filtered = allRes.filter(r => {
        const matchQ    = !q || (r.title||'').toLowerCase().includes(q) || (r.subject||'').toLowerCase().includes(q);
        const matchType = !type || r.file_type === type;
        return matchQ && matchType;
    });
    renderRes(filtered);
}

// ── RENDER ───────────────────────────────────────────────────
function renderRes(resources) {
    const el = document.getElementById('resourcesList');
    if (!resources.length) {
        el.innerHTML = `<div class="adm-card"><div class="adm-empty">No resources found. ✅</div></div>`;
        return;
    }
    el.innerHTML = `<div class="adm-res-list">${resources.map(r => {
        const uploader = uploaderCache[r.uploaded_by];
        const uName    = uploader
            ? `${uploader.first_name||''} ${uploader.last_name||''}`.trim() || `@${uploader.username}`
            : '—';
        const uInitial = uploader ? (uploader.first_name||'?')[0].toUpperCase() : '?';

        return `
        <div class="adm-res-card" id="rescard-${r.id}">
            <div class="adm-res-icon">${ICONS[r.file_type||''] || '📎'}</div>
            <div class="adm-res-body">
                <div class="adm-res-title">${escH(r.title)}</div>
                ${r.description ? `<div class="adm-res-desc">${escH(r.description.slice(0,100))}${r.description.length>100?'…':''}</div>` : ''}
                <div class="adm-res-uploader">
                    <div class="adm-res-uploader-avatar">${uInitial}</div>
                    <span>${escH(uName)}</span>
                </div>
                <div class="adm-res-meta">
                    <span>${escH(r.subject||'—')}</span>
                    <span class="adm-res-meta-dot">·</span>
                    <span>${escH(r.file_type||'file')}</span>
                    <span class="adm-res-meta-dot">·</span>
                    <span class="adm-badge ${r.visibility==='public'?'badge-info':'badge-warn'}" style="font-size:10px;">${r.visibility||'public'}</span>
                    <span class="adm-res-meta-dot">·</span>
                    <span>${new Date(r.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})}</span>
                    ${r.is_approved ? '<span class="adm-badge badge-success" style="font-size:10px;">✓ Approved</span>' : '<span class="adm-badge badge-warn" style="font-size:10px;">⏳ Pending</span>'}
                </div>
            </div>
            <div class="adm-res-actions">
                <button class="adm-act-btn" onclick="openPanel('${r.id}')">🔍 View</button>
                ${!r.is_approved
                    ? `<button class="adm-act-btn success" onclick="approveRes('${r.id}', event)">✓ Approve</button>
                       <button class="adm-act-btn danger"  onclick="openRejectModal('${r.id}', event)">✕ Reject</button>`
                    : `<button class="adm-act-btn danger"  onclick="openRejectModal('${r.id}', event)">🗑 Remove</button>`
                }
            </div>
        </div>`;
    }).join('')}</div>`;
}

// ── DETAIL PANEL ─────────────────────────────────────────────
async function openPanel(resourceId) {
    document.getElementById('resOverlay').classList.add('open');
    document.getElementById('panelBody').innerHTML = '<div class="adm-loading">Loading…</div>';
    document.getElementById('panelActions').innerHTML = '';
    document.getElementById('panelBadges').innerHTML = '';

    try {
        const res  = await fetch(
            `${SB_URL}/rest/v1/resources?id=eq.${resourceId}&select=*,profiles(first_name,last_name,username)`,
            { headers: svcH() }
        );
        const data = await res.json();
        const r    = data?.[0];
        if (!r) { document.getElementById('panelBody').innerHTML = '<div class="adm-empty">Resource not found.</div>'; return; }

        const uploader = r.profiles
            ? `${r.profiles.first_name||''} ${r.profiles.last_name||''}`.trim() || `@${r.profiles.username}`
            : '—';

        // Also load files
        const fRes  = await fetch(`${SB_URL}/rest/v1/resource_files?resource_id=eq.${resourceId}&select=file_name,file_url,file_size`, { headers: svcH() });
        const files = await fRes.json();

        document.getElementById('panelTitle').textContent = r.title || 'Untitled';
        document.getElementById('panelBadges').innerHTML =
            `<span class="adm-badge ${r.is_approved?'badge-success':'badge-warn'}">${r.is_approved?'✓ Approved':'⏳ Pending'}</span>
             <span class="adm-badge badge-info">${escH(r.file_type||'file')}</span>
             <span class="adm-badge badge-gray">${escH(r.visibility||'public')}</span>`;

        let filesHTML = '';
        if (files?.length) {
            filesHTML = `<div class="adm-res-panel-section">
                <div class="adm-res-panel-section-title">📎 Attached Files</div>
                ${files.map(f=>`
                <a class="adm-res-panel-file" href="${escH(f.file_url)}" target="_blank" download>
                    <span>📄</span>
                    <span class="adm-res-panel-file-name">${escH(f.file_name||'File')}</span>
                    ${f.file_size?`<span class="adm-res-panel-file-size">${fmtBytes(f.file_size)}</span>`:''}
                </a>`).join('')}
            </div>`;
        } else if (r.file_url) {
            filesHTML = `<div class="adm-res-panel-section">
                <div class="adm-res-panel-section-title">📎 File</div>
                <a class="adm-res-panel-file" href="${escH(r.file_url)}" target="_blank" download>
                    <span>📄</span>
                    <span class="adm-res-panel-file-name">${escH(r.original_filename||'Download')}</span>
                </a>
            </div>`;
        }

        document.getElementById('panelBody').innerHTML = `
            <div class="adm-res-panel-meta-grid">
                <div class="adm-res-panel-meta-item"><span class="adm-res-panel-meta-label">Uploaded by</span><span class="adm-res-panel-meta-val">${escH(uploader)}</span></div>
                <div class="adm-res-panel-meta-item"><span class="adm-res-panel-meta-label">Subject</span><span class="adm-res-panel-meta-val">${escH(r.subject||'—')}</span></div>
                <div class="adm-res-panel-meta-item"><span class="adm-res-panel-meta-label">Uploaded</span><span class="adm-res-panel-meta-val">${fmtDate(r.created_at)}</span></div>
                <div class="adm-res-panel-meta-item"><span class="adm-res-panel-meta-label">Views</span><span class="adm-res-panel-meta-val">${r.view_count||0}</span></div>
            </div>

            ${r.description ? `<div class="adm-res-panel-section">
                <div class="adm-res-panel-section-title">📝 Description</div>
                <div class="adm-res-panel-text">${escH(r.description)}</div>
            </div>` : ''}

            ${r.content ? `<div class="adm-res-panel-section">
                <div class="adm-res-panel-section-title">📄 Content / Notes</div>
                <div class="adm-res-panel-content-box">${escH(r.content)}</div>
            </div>` : ''}

            ${filesHTML}

            ${r.tags?.length ? `<div class="adm-res-panel-section">
                <div class="adm-res-panel-section-title">🏷 Tags</div>
                <div class="adm-res-panel-tags">${r.tags.map(t=>`<span class="adm-res-panel-tag">${escH(t)}</span>`).join('')}</div>
            </div>` : ''}`;

        document.getElementById('panelActions').innerHTML = !r.is_approved
            ? `<button class="adm-btn adm-btn-success" onclick="approveRes('${r.id}')">✓ Approve</button>
               <button class="adm-btn adm-btn-danger" onclick="openRejectModal('${r.id}')">✕ Reject</button>`
            : `<span class="adm-badge badge-success" style="font-size:13px;padding:6px 14px;">✓ Already Approved</span>
               <button class="adm-btn adm-btn-danger" style="margin-left:auto;" onclick="openRejectModal('${r.id}')">Remove</button>`;

    } catch(e) {
        document.getElementById('panelBody').innerHTML = `<div class="adm-empty">Error: ${escH(e.message)}</div>`;
    }
}

function closePanel() { document.getElementById('resOverlay').classList.remove('open'); }
function closePanelOutside(e) { if (e.target === document.getElementById('resOverlay')) closePanel(); }

// ── APPROVE ──────────────────────────────────────────────────
async function approveRes(id, e) {
    if (e) e.stopPropagation();
    await fetch(`${SB_URL}/rest/v1/resources?id=eq.${id}`, {
        method: 'PATCH', headers: svcH(), body: JSON.stringify({ is_approved: true })
    });
    await logAction('approve_resource', 'resource', id, 'Resource approved');
    closePanel();
    loadResources();
}

// ── REJECT ───────────────────────────────────────────────────
function openRejectModal(id, e) {
    if (e) e.stopPropagation();
    pendingRejectId = id;
    // Check if this resource is approved or pending so we can label the modal
    const card = document.getElementById(`rescard-${id}`);
    const isApproved = card?.querySelector('.badge-success') !== null;
    document.querySelector('#rejectModal .adm-modal-title').textContent =
        isApproved ? '🗑 Remove Resource' : '❌ Reject Resource';
    document.getElementById('rejectReason').value = '';
    document.getElementById('rejectNotes').value  = '';
    document.getElementById('rejectModal').style.display = 'flex';
}
function closeRejectModal() { document.getElementById('rejectModal').style.display = 'none'; }

async function confirmReject() {
    const reason = document.getElementById('rejectReason').value;
    const notes  = document.getElementById('rejectNotes').value.trim();
    if (!reason) { alert('Please select a reason.'); return; }

    const logNote = `[Rejected: ${reason}]${notes?' — '+notes:''}`;

    try {
        // 1. Delete associated files first (foreign key constraint)
        await fetch(`${SB_URL}/rest/v1/resource_files?resource_id=eq.${pendingRejectId}`,
            { method: 'DELETE', headers: svcH() });

        // 2. Delete the resource itself — removes it from all views
        const res = await fetch(`${SB_URL}/rest/v1/resources?id=eq.${pendingRejectId}`,
            { method: 'DELETE', headers: svcH() });

        if (!res.ok) throw new Error('Delete failed');

        // 3. Log the action
        await logAction('reject_resource', 'resource', pendingRejectId, logNote);

        closeRejectModal();
        closePanel();
        loadResources();

        // Show brief success toast
        showToast('Resource rejected and removed.');

    } catch(e) {
        alert('Failed to reject resource: ' + e.message);
    }
}

// ── ADMIN LOG ─────────────────────────────────────────────────
async function logAction(action, targetType, targetId, notes) {
    try {
        await fetch(`${SB_URL}/rest/v1/admin_logs`, {
            method: 'POST', headers: svcH(),
            body: JSON.stringify({ admin_id: ADMIN_ID||null, action, target_type: targetType, target_id: targetId||null, notes: notes||null })
        });
    } catch(e) { console.warn('Log failed:', e); }
}

// ── HELPERS ──────────────────────────────────────────────────
function showToast(msg, isError = false) {
    const t = document.createElement('div');
    t.textContent = msg;
    Object.assign(t.style, {
        position: 'fixed', bottom: '24px', right: '24px', zIndex: '9999',
        padding: '12px 20px', borderRadius: '10px',
        background: isError ? 'var(--adm-danger)' : 'var(--adm-success)',
        color: 'white', fontFamily: "'DM Sans', sans-serif",
        fontSize: '14px', fontWeight: '600',
        boxShadow: '0 4px 16px rgba(0,0,0,0.15)',
        transition: 'opacity 0.4s',
    });
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 2800);
}
function fmtDate(ts) {
    if (!ts) return '—';
    return new Date(ts).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' });
}
function fmtBytes(b) {
    if (!b) return '';
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(1) + ' MB';
}
function escH(t) {
    if (t == null) return '';
    const d = document.createElement('div'); d.textContent = String(t); return d.innerHTML;
}

document.getElementById('adminPageTitle').textContent = 'Resource Approval';
loadResources();
</script>
</body>
</html>
