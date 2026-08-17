<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Posts Feed - StudyHub Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin_reports.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin_posts.css') }}">
</head>
<body>
@php $activeAdmin = 'posts'; @endphp
@include('admin.sidebar')

<main class="adm-main">

    <!-- ── TOOLBAR ── -->
    <div class="adm-toolbar">
        <div class="adm-search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
            </svg>
            <input type="text" id="postSearch" placeholder="Search by content or username…" oninput="filterPosts()">
        </div>
        <select id="visFilter" class="adm-select" onchange="filterPosts()">
            <option value="">All Visibility</option>
            <option value="public">🌐 Public</option>
            <option value="friends">👥 Friends</option>
            <option value="only_me">🔒 Only Me</option>
        </select>
        <select id="flagFilter" class="adm-select" onchange="filterPosts()">
            <option value="">All Posts</option>
            <option value="reported">⚠ Reported Only</option>
        </select>
        <select id="sortFilter" class="adm-select" onchange="loadPosts()">
            <option value="newest">Newest First</option>
            <option value="oldest">Oldest First</option>
        </select>
    </div>

    <!-- ── POSTS LIST ── -->
    <div class="adm-card" style="margin-top:0;">
        <div class="adm-card-header">
            <span class="adm-card-title">All User Posts</span>
            <span class="adm-muted" id="postCount">Loading…</span>
        </div>
        <div id="postsList">
            <div class="adm-loading">Loading posts…</div>
        </div>
    </div>

</main>

<!-- ════════════════════════════════════════════════════════════
     POST DETAIL PANEL (slides from right)
════════════════════════════════════════════════════════════ -->
<div class="adm-preview-overlay" id="postOverlay" onclick="closePanelOutside(event)">
    <div class="adm-preview-panel" id="postPanel">
        <div class="adm-preview-topbar">
            <div>
                <div class="adm-preview-label" id="panelAuthorName">Post Detail</div>
                <div id="panelMeta" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:4px;"></div>
            </div>
            <button class="adm-preview-close" onclick="closePanel()">✕</button>
        </div>
        <div class="adm-preview-body" id="panelBody">
            <div class="adm-loading">Loading…</div>
        </div>
        <div id="panelReportBox" style="display:none;" class="adm-preview-report-box">
            <div id="panelReportInner"></div>
        </div>
        <div class="adm-preview-actions" id="panelActions"></div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     TAKEDOWN MODAL
════════════════════════════════════════════════════════════ -->
<div class="adm-modal-overlay" id="takedownModal" style="display:none;">
    <div class="adm-modal" style="max-width:480px;">
        <div class="adm-modal-header">
            <span class="adm-modal-title">🗑 Take Down Post</span>
            <button class="adm-modal-close" onclick="closeTakedown()">✕</button>
        </div>
        <label class="adm-form-label">Reason <span style="color:var(--adm-danger)">*</span></label>
        <select class="adm-select" id="takedownReason" style="width:100%;margin-bottom:12px;">
            <option value="">Select a reason…</option>
            <option value="Inappropriate content">Inappropriate content</option>
            <option value="Spam or misleading information">Spam or misleading</option>
            <option value="Harassment or bullying">Harassment or bullying</option>
            <option value="Hate speech or discrimination">Hate speech or discrimination</option>
            <option value="Copyright violation">Copyright violation</option>
            <option value="Violates community guidelines">Violates community guidelines</option>
            <option value="Other">Other (see notes)</option>
        </select>
        <label class="adm-form-label">Notes <span class="adm-muted">(optional)</span></label>
        <textarea class="adm-textarea" id="takedownNotes" rows="3"
            placeholder="Additional context…"></textarea>
        <div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end;">
            <button class="adm-btn" onclick="closeTakedown()">Cancel</button>
            <button class="adm-btn adm-btn-danger" onclick="confirmTakedown()">🗑 Take Down</button>
        </div>
    </div>
</div>

<!-- FLAG REPORT MODAL -->
<div class="adm-modal-overlay" id="flagModal" style="display:none;">
    <div class="adm-modal" style="max-width:440px;">
        <div class="adm-modal-header">
            <span class="adm-modal-title">⚠ Flag Post for Review</span>
            <button class="adm-modal-close" onclick="closeFlagModal()">✕</button>
        </div>
        <label class="adm-form-label">Reason <span style="color:var(--adm-danger)">*</span></label>
        <select class="adm-select" id="flagReason" style="width:100%;margin-bottom:12px;">
            <option value="">Select a reason…</option>
            <option value="Needs further review">Needs further review</option>
            <option value="Potentially inappropriate content">Potentially inappropriate</option>
            <option value="Spam or misleading">Spam or misleading</option>
            <option value="Copyright concern">Copyright concern</option>
        </select>
        <label class="adm-form-label">Notes <span class="adm-muted">(optional)</span></label>
        <textarea class="adm-textarea" id="flagNotes" rows="2" placeholder="Add context…"></textarea>
        <div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end;">
            <button class="adm-btn" onclick="closeFlagModal()">Cancel</button>
            <button class="adm-btn adm-btn-warn" onclick="confirmFlag()" style="background:var(--adm-warning);color:white;border-color:var(--adm-warning);">⚠ Create Report</button>
        </div>
    </div>
</div>

<script>
const SB_URL   = '{{ env("SUPABASE_URL") }}';
const SB_SVC   = '{{ env("SUPABASE_SERVICE_KEY") }}';
const SB_KEY   = '{{ env("SUPABASE_ANON_KEY") }}';
const ADMIN_ID = '{{ session("user_id") }}';

function svcH() { return { 'apikey': SB_SVC, 'Authorization': `Bearer ${SB_SVC}`, 'Content-Type': 'application/json' }; }

let allPosts    = [];
let reportedIds = new Set();   // post IDs that have pending reports
let authorCache = {};
let pendingTakedownId = null;
let pendingFlagId     = null;

// ── LOAD ──────────────────────────────────────────────────────
async function loadPosts() {
    document.getElementById('postsList').innerHTML = '<div class="adm-loading">Loading posts…</div>';
    const sort = document.getElementById('sortFilter').value;

    const res = await fetch(
        `${SB_URL}/rest/v1/posts` +
        `?select=*,profiles(id,first_name,last_name,username,profile_photo_url)` +
        `&order=created_at.${sort === 'oldest' ? 'asc' : 'desc'}` +
        `&limit=200`,
        { headers: svcH() }
    );
    allPosts = await res.json();

    // Cache authors
    allPosts.forEach(p => { if (p.profiles) authorCache[p.user_id] = p.profiles; });

    // Load reported post IDs
    const rRes = await fetch(
        `${SB_URL}/rest/v1/reports?reported_content_type=eq.post&status=eq.pending&select=reported_content_id`,
        { headers: svcH() }
    );
    const rData = await rRes.json();
    reportedIds = new Set((rData||[]).map(r => r.reported_content_id));

    filterPosts();
}

function filterPosts() {
    const q    = (document.getElementById('postSearch').value || '').toLowerCase();
    const vis  = document.getElementById('visFilter').value;
    const flag = document.getElementById('flagFilter').value;

    const filtered = allPosts.filter(p => {
        const author = p.profiles || {};
        const name   = `${author.first_name||''} ${author.last_name||''}`.trim();
        const matchQ    = !q || (p.content||'').toLowerCase().includes(q) || name.toLowerCase().includes(q) || (author.username||'').toLowerCase().includes(q);
        const matchVis  = !vis  || p.visibility === vis;
        const matchFlag = !flag || (flag === 'reported' && reportedIds.has(p.id));
        return matchQ && matchVis && matchFlag;
    });
    renderPosts(filtered);
}

function renderPosts(posts) {
    document.getElementById('postCount').textContent = `${posts.length} post${posts.length !== 1 ? 's' : ''}`;
    const el = document.getElementById('postsList');
    if (!posts.length) { el.innerHTML = '<div class="adm-empty">No posts found.</div>'; return; }

    el.innerHTML = `<div class="adm-posts-list">${posts.map(p => {
        const author   = p.profiles || {};
        const name     = `${author.first_name||''} ${author.last_name||''}`.trim() || author.username || 'Unknown';
        const initials = ((author.first_name||'?')[0] + (author.last_name||'?')[0]).toUpperCase();
        const isReported = reportedIds.has(p.id);
        const visIcon  = { public:'🌐', friends:'👥', only_me:'🔒' }[p.visibility] || '🌐';
        const media    = safeJSON(p.media_urls, []);
        const files    = safeJSON(p.file_urls,  []);
        const excerpt  = (p.content||'').slice(0, 120) + ((p.content||'').length > 120 ? '…' : '');

        return `
        <div class="adm-post-card ${isReported ? 'reported' : ''}" id="apc-${p.id}">
            <div class="adm-post-card-left">
                <div class="adm-post-avatar">${author.profile_photo_url
                    ? `<img src="${escH(author.profile_photo_url)}" alt="">`
                    : initials}</div>
                <div class="adm-post-body">
                    <div class="adm-post-meta">
                        <span class="adm-post-author">${escH(name)}</span>
                        <span class="adm-post-username">@${escH(author.username||'')}</span>
                        <span class="adm-post-time">${fmtDate(p.created_at)}</span>
                        <span class="adm-post-vis">${visIcon} ${p.visibility}</span>
                        ${isReported ? `<span class="adm-badge badge-danger" style="font-size:10px;">⚠ Reported</span>` : ''}
                    </div>
                    ${excerpt ? `<div class="adm-post-excerpt">${escH(excerpt)}</div>` : '<div class="adm-post-excerpt adm-muted">[No text content]</div>'}
                    <div class="adm-post-attachments">
                        ${media.length ? `<span class="adm-post-attach-chip">🖼 ${media.length} media</span>` : ''}
                        ${files.length ? `<span class="adm-post-attach-chip">📎 ${files.length} file${files.length>1?'s':''}</span>` : ''}
                    </div>
                </div>
            </div>
            <div class="adm-post-card-actions">
                <button class="adm-act-btn" onclick="openPanel('${p.id}')">🔍 View</button>
                <button class="adm-act-btn" style="color:var(--adm-warning);border-color:rgba(244,162,97,0.4);background:rgba(244,162,97,0.06);"
                    onclick="openFlagModal('${p.id}')">⚠ Flag</button>
                <button class="adm-act-btn danger" onclick="openTakedown('${p.id}')">🗑 Take Down</button>
            </div>
        </div>`;
    }).join('')}</div>`;
}

// ── DETAIL PANEL ─────────────────────────────────────────────
async function openPanel(postId) {
    const post = allPosts.find(p => p.id === postId);
    if (!post) return;

    document.getElementById('postOverlay').classList.add('open');
    document.getElementById('panelBody').innerHTML    = '<div class="adm-loading">Loading…</div>';
    document.getElementById('panelReportBox').style.display = 'none';
    document.getElementById('panelActions').innerHTML = '';

    const author   = post.profiles || {};
    const name     = `${author.first_name||''} ${author.last_name||''}`.trim() || author.username || 'Unknown';
    const initials = ((author.first_name||'?')[0] + (author.last_name||'?')[0]).toUpperCase();
    const visIcon  = { public:'🌐', friends:'👥', only_me:'🔒' }[post.visibility] || '🌐';
    const isReported = reportedIds.has(postId);

    document.getElementById('panelAuthorName').textContent = name;
    document.getElementById('panelMeta').innerHTML = `
        <span class="adm-badge badge-gray">${visIcon} ${post.visibility}</span>
        <span class="adm-muted" style="font-size:12px;">${fmtDate(post.created_at)}</span>
        ${isReported ? `<span class="adm-badge badge-danger">⚠ Reported</span>` : ''}`;

    const media = safeJSON(post.media_urls, []);
    const files = safeJSON(post.file_urls,  []);
    const link  = safeJSON(post.link_meta,  null);

    let mediaHTML = '';
    if (media.length) {
        mediaHTML = `<div class="adm-preview-media-grid count-${Math.min(media.length,4)}">` +
            media.slice(0,4).map(url => {
                const isVid = /\.(mp4|mov|webm)(\?|$)/i.test(url);
                return `<div class="adm-preview-media-item">${isVid
                    ? `<video src="${escH(url)}" controls preload="none"></video>`
                    : `<img src="${escH(url)}" alt="" loading="lazy">`}</div>`;
            }).join('') + `</div>`;
    }
    if (files.length) {
        mediaHTML += `<div class="adm-preview-files">` +
            files.map(f=>`<a class="adm-preview-file-row" href="${escH(f.url)}" target="_blank" download>
                📎 <span class="adm-preview-file-name">${escH(f.name||'File')}</span>
            </a>`).join('') + `</div>`;
    }
    if (link?.url) {
        mediaHTML += `<a class="adm-preview-link-card" href="${escH(link.url)}" target="_blank" rel="noopener">
            <div class="adm-preview-link-title">${escH(link.title||link.url)}</div>
            <div class="adm-muted" style="font-size:11px;">${escH(link.url)}</div></a>`;
    }

    // Author card
    const authorCard = `
        <div class="adm-post-panel-author">
            <div class="adm-preview-post-avatar">${author.profile_photo_url
                ? `<img src="${escH(author.profile_photo_url)}" alt="">` : initials}</div>
            <div>
                <div class="adm-preview-post-author">${escH(name)}</div>
                <div class="adm-muted" style="font-size:12px;">@${escH(author.username||'')} · ${fmtDate(post.created_at)}</div>
            </div>
        </div>`;

    document.getElementById('panelBody').innerHTML = `
        ${authorCard}
        ${post.content ? `<div class="adm-preview-post-text" style="margin:14px 0;">${escH(post.content)}</div>` : ''}
        ${mediaHTML}`;

    // Show report info if reported
    if (isReported) {
        try {
            const rRes = await fetch(
                `${SB_URL}/rest/v1/reports?reported_content_id=eq.${postId}&reported_content_type=eq.post&status=eq.pending&select=reason,created_at,reported_by`,
                { headers: svcH() }
            );
            const reports = await rRes.json();
            if (reports?.length) {
                const rBox = document.getElementById('panelReportBox');
                rBox.style.display = '';
                document.getElementById('panelReportInner').innerHTML = `
                    <div class="adm-report-info-title">📋 Pending Report${reports.length > 1 ? 's' : ''}</div>
                    ${reports.map(r => `
                    <div class="adm-report-info-row">
                        <span class="adm-report-info-label">Reason</span>
                        <span>${escH(r.reason||'—')}</span>
                    </div>
                    <div class="adm-report-info-row">
                        <span class="adm-report-info-label">Reported</span>
                        <span>${fmtDate(r.created_at)}</span>
                    </div>`).join('<hr style="border:none;border-top:1px solid var(--adm-border);margin:8px 0;">')}`;
            }
        } catch(e) {}
    }

    document.getElementById('panelActions').innerHTML = `
        <button class="adm-btn" style="color:var(--adm-warning);border-color:rgba(244,162,97,0.4);"
            onclick="openFlagModal('${postId}')">⚠ Flag</button>
        <button class="adm-btn adm-btn-danger" onclick="openTakedown('${postId}')">🗑 Take Down</button>`;
}

function closePanel() { document.getElementById('postOverlay').classList.remove('open'); }
function closePanelOutside(e) { if (e.target === document.getElementById('postOverlay')) closePanel(); }

// ── TAKEDOWN ──────────────────────────────────────────────────
function openTakedown(postId) {
    pendingTakedownId = postId;
    document.getElementById('takedownReason').value = '';
    document.getElementById('takedownNotes').value  = '';
    document.getElementById('takedownModal').style.display = 'flex';
}
function closeTakedown() { document.getElementById('takedownModal').style.display = 'none'; }

async function confirmTakedown() {
    const reason = document.getElementById('takedownReason').value;
    const notes  = document.getElementById('takedownNotes').value.trim();
    if (!reason) { alert('Please select a reason.'); return; }

    const fullNotes = `[Takedown: ${reason}]${notes ? ' — ' + notes : ''}`;

    try {
        // 1. Hard-delete the post
        await fetch(`${SB_URL}/rest/v1/posts?id=eq.${pendingTakedownId}`,
            { method: 'DELETE', headers: svcH() });

        // 2. Resolve any pending reports for this post
        await fetch(
            `${SB_URL}/rest/v1/reports?reported_content_id=eq.${pendingTakedownId}&reported_content_type=eq.post`,
            { method: 'PATCH', headers: svcH(),
              body: JSON.stringify({ status: 'resolved', admin_notes: fullNotes, reviewed_at: new Date().toISOString() }) }
        );

        // 3. Log the admin action
        await logAction('takedown_content', 'post', pendingTakedownId, fullNotes);

        closeTakedown();
        closePanel();
        showToast('Post taken down and removed.');

        // Remove from local state and re-render
        allPosts = allPosts.filter(p => p.id !== pendingTakedownId);
        reportedIds.delete(pendingTakedownId);
        filterPosts();

    } catch(e) { alert('Take down failed: ' + e.message); }
}

// ── FLAG (CREATE REPORT) ──────────────────────────────────────
function openFlagModal(postId) {
    pendingFlagId = postId;
    document.getElementById('flagReason').value = '';
    document.getElementById('flagNotes').value  = '';
    document.getElementById('flagModal').style.display = 'flex';
}
function closeFlagModal() { document.getElementById('flagModal').style.display = 'none'; }

async function confirmFlag() {
    const reason = document.getElementById('flagReason').value;
    const notes  = document.getElementById('flagNotes').value.trim();
    if (!reason) { alert('Please select a reason.'); return; }

    try {
        // Create a report entry
        await fetch(`${SB_URL}/rest/v1/reports`, {
            method: 'POST', headers: svcH(),
            body: JSON.stringify({
                reported_by:           ADMIN_ID,
                reported_content_type: 'post',
                reported_content_id:   pendingFlagId,
                reason:                notes ? `${reason}: ${notes}` : reason,
                status:                'pending'
            })
        });
        await logAction('flag_post', 'post', pendingFlagId, `Flagged by admin: ${reason}`);

        reportedIds.add(pendingFlagId);
        closeFlagModal();
        filterPosts();
        showToast('Post flagged and added to reports.');
    } catch(e) { alert('Flag failed: ' + e.message); }
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

// ── TOAST ─────────────────────────────────────────────────────
function showToast(msg, isError = false) {
    const t = document.createElement('div');
    t.textContent = msg;
    Object.assign(t.style, {
        position: 'fixed', bottom: '24px', right: '24px', zIndex: '9999',
        padding: '12px 20px', borderRadius: '10px',
        background: isError ? 'var(--adm-danger)' : 'var(--adm-success)',
        color: 'white', fontFamily: "'DM Sans',sans-serif",
        fontSize: '14px', fontWeight: '600',
        boxShadow: '0 4px 16px rgba(0,0,0,0.15)', transition: 'opacity 0.4s',
    });
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 2800);
}

// ── HELPERS ───────────────────────────────────────────────────
function fmtDate(ts) {
    if (!ts) return '—';
    const d = new Date(ts);
    const diff = (Date.now() - d) / 1000;
    if (diff < 60)    return 'Just now';
    if (diff < 3600)  return `${Math.floor(diff/60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;
    return d.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
}
function safeJSON(val, fallback) {
    if (!val) return fallback;
    if (typeof val === 'object') return val;
    try { return JSON.parse(val); } catch { return fallback; }
}
function escH(t) {
    if (t == null) return '';
    const d = document.createElement('div'); d.textContent = String(t); return d.innerHTML;
}

document.getElementById('adminPageTitle').textContent = 'Posts Feed';
loadPosts();
</script>
</body>
</html>
