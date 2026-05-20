<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - StudyHub Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin_reports.css') }}">
</head>
<body>
@php $activeAdmin = 'reports'; @endphp
@include('admin.sidebar')

<main class="adm-main">

    <!-- ── TOOLBAR ── -->
    <div class="adm-toolbar">
        <div class="adm-search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
            </svg>
            <input type="text" id="reportSearch" placeholder="Search reports by reason, type…" oninput="filterReports()">
        </div>
        <select id="reportStatusFilter" class="adm-select" onchange="loadReports()">
            <option value="pending">⏳ Pending</option>
            <option value="reviewed">👁 Reviewed</option>
            <option value="resolved">✅ Resolved</option>
            <option value="">📋 All</option>
        </select>
        <select id="reportTypeFilter" class="adm-select" onchange="filterReports()">
            <option value="">All Types</option>
            <option value="post">Posts</option>
            <option value="resource">Resources</option>
        </select>
    </div>

    <!-- ── REPORTS TABLE ── -->
    <div class="adm-card" style="margin-top:0;">
        <div class="adm-card-header">
            <span class="adm-card-title">User Reports</span>
            <span class="adm-muted" id="reportCount">Loading…</span>
        </div>
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Reported Content</th>
                        <th>Reason</th>
                        <th>Reported By</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="reportsBody">
                    <tr><td colspan="7" class="adm-loading">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- ════════════════════════════════════════════════════════
     CONTENT PREVIEW PANEL (slides in from right)
════════════════════════════════════════════════════════ -->
<div class="adm-preview-overlay" id="previewOverlay" onclick="closePreview(event)">
    <div class="adm-preview-panel" id="previewPanel">
        <div class="adm-preview-topbar">
            <div>
                <div class="adm-preview-label" id="previewLabel">Reported Content</div>
                <div class="adm-preview-type" id="previewTypeTag"></div>
            </div>
            <button class="adm-preview-close" onclick="closePreviewDirect()">✕</button>
        </div>

        <!-- Content body -->
        <div class="adm-preview-body" id="previewBody">
            <div class="adm-loading">Loading content…</div>
        </div>

        <!-- Report details -->
        <div class="adm-preview-report-box" id="previewReportBox"></div>

        <!-- Actions -->
        <div class="adm-preview-actions" id="previewActions"></div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     TAKEDOWN / REVIEW MODAL
════════════════════════════════════════════════════════ -->
<div class="adm-modal-overlay" id="resolveModal" style="display:none;">
    <div class="adm-modal" style="max-width:520px;">
        <div class="adm-modal-header">
            <span class="adm-modal-title" id="resolveModalTitle">Review Report</span>
            <button class="adm-modal-close" onclick="closeResolve()">✕</button>
        </div>

        <!-- Takedown reason (shown only when taking down) -->
        <div id="takedownReasonWrap" style="display:none;margin-bottom:14px;">
            <label class="adm-form-label">Takedown Reason <span style="color:var(--adm-danger)">*</span></label>
            <select id="takedownReason" class="adm-select" style="width:100%;margin-bottom:10px;">
                <option value="">Select a reason…</option>
                <option value="Inappropriate content">Inappropriate content</option>
                <option value="Spam or misleading information">Spam or misleading information</option>
                <option value="Harassment or bullying">Harassment or bullying</option>
                <option value="Hate speech or discrimination">Hate speech or discrimination</option>
                <option value="Copyright violation">Copyright violation</option>
                <option value="Violates community guidelines">Violates community guidelines</option>
                <option value="Other">Other (see admin notes)</option>
            </select>
        </div>

        <label class="adm-form-label">Admin Notes <span class="adm-muted">(optional)</span></label>
        <textarea class="adm-textarea" id="adminNotes" rows="4"
            placeholder="Describe the action taken, add context, or leave a note for other admins…"></textarea>

        <div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end;flex-wrap:wrap;">
            <button class="adm-btn" onclick="closeResolve()">Cancel</button>
            <button class="adm-btn adm-btn-warn" onclick="submitAction('reviewed')" id="btnMarkReviewed">
                👁 Mark Reviewed
            </button>
            <button class="adm-btn adm-btn-danger" onclick="submitAction('resolved')" id="btnTakedown">
                🗑 Take Down &amp; Resolve
            </button>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     VIEW AS USER BANNER (sticky top)
════════════════════════════════════════════════════════ -->
<div class="adm-view-as-banner" id="viewAsBanner" style="display:none;">
    <span>👁 You are viewing as a regular user. The admin sidebar is still accessible.</span>
    <button onclick="exitViewAsUser()">← Return to Admin Mode</button>
</div>

<script>
const SB_URL = '{{ env("SUPABASE_URL") }}';
const SB_SVC = '{{ env("SUPABASE_SERVICE_KEY") }}';
const SB_KEY = '{{ env("SUPABASE_ANON_KEY") }}';

function svcH()  { return { 'apikey': SB_SVC, 'Authorization': `Bearer ${SB_SVC}`, 'Content-Type': 'application/json' }; }
function anonH() { return { 'apikey': SB_KEY, 'Authorization': `Bearer ${SB_KEY}` }; }

let allReports      = [];
let filteredReports = [];
let pendingReportId = null;
let pendingAction   = null; // 'reviewed' | 'resolved'
let currentReport   = null;

// ── LOAD REPORTS ─────────────────────────────────────────────
async function loadReports() {
    const status = document.getElementById('reportStatusFilter').value;
    document.getElementById('reportsBody').innerHTML =
        '<tr><td colspan="7" class="adm-loading">Loading…</td></tr>';

    const q = status ? `status=eq.${status}&` : '';
    const url = `${SB_URL}/rest/v1/reports?${q}select=id,reason,reported_content_type,reported_content_id,reported_by,reported_user_id,status,created_at,admin_notes,reviewed_at,reviewed_by&order=created_at.desc`;
    const res = await fetch(url, { headers: svcH() });
    allReports = await res.json();

    // Enrich with reporter profile
    const reporterIds = [...new Set(allReports.map(r => r.reported_by).filter(Boolean))];
    let profileMap = {};
    if (reporterIds.length) {
        const pRes = await fetch(
            `${SB_URL}/rest/v1/profiles?id=in.(${reporterIds.join(',')})&select=id,first_name,last_name,username`,
            { headers: svcH() }
        );
        const profiles = await pRes.json();
        profiles.forEach(p => { profileMap[p.id] = p; });
    }
    allReports = allReports.map(r => ({ ...r, _reporter: profileMap[r.reported_by] || null }));

    filterReports();
}

function filterReports() {
    const q    = (document.getElementById('reportSearch').value || '').toLowerCase();
    const type = document.getElementById('reportTypeFilter').value;
    filteredReports = allReports.filter(r => {
        const matchQ    = !q || (r.reason||'').toLowerCase().includes(q) ||
                          (r.reported_content_type||'').toLowerCase().includes(q);
        const matchType = !type || r.reported_content_type === type;
        return matchQ && matchType;
    });
    renderReports(filteredReports);
}

function renderReports(reports) {
    document.getElementById('reportCount').textContent =
        `${reports.length} report${reports.length !== 1 ? 's' : ''}`;
    const body = document.getElementById('reportsBody');
    if (!reports.length) {
        body.innerHTML = '<tr><td colspan="7" class="adm-empty">No reports found. ✅</td></tr>';
        return;
    }
    body.innerHTML = reports.map(r => {
        const reporter = r._reporter
            ? `${escH(r._reporter.first_name||'')} ${escH(r._reporter.last_name||'')}`.trim()
              || `@${escH(r._reporter.username||'')}`
            : '—';
        const statusClass = r.status === 'pending'  ? 'badge-danger' :
                            r.status === 'reviewed' ? 'badge-warn'   : 'badge-success';
        const typeLabel = r.reported_content_type || 'content';
        const contentSnippet = r.admin_notes
            ? `<div class="adm-muted" style="font-size:11px;margin-top:2px;">📝 ${escH(r.admin_notes.slice(0,40))}${r.admin_notes.length>40?'…':''}</div>`
            : '';
        return `<tr>
            <td><span class="adm-badge badge-info">${escH(typeLabel)}</span></td>
            <td>
                <button class="adm-preview-link" onclick="openPreview('${r.id}')">
                    🔍 View ${escH(typeLabel)}
                </button>
                ${contentSnippet}
            </td>
            <td style="max-width:200px;">
                <span title="${escH(r.reason||'')}">${escH((r.reason||'').slice(0,55))}${(r.reason||'').length>55?'…':''}</span>
            </td>
            <td><span class="adm-muted" style="font-size:12px;">${reporter}</span></td>
            <td><span class="adm-badge ${statusClass}">${r.status}</span></td>
            <td><span class="adm-muted" style="font-size:12px;">${fmtDate(r.created_at)}</span></td>
            <td style="white-space:nowrap;">
                <button class="adm-act-btn" onclick="openPreview('${r.id}')">View</button>
                ${r.status !== 'resolved'
                    ? `<button class="adm-act-btn adm-act-danger" style="margin-left:4px;" onclick="openResolve('${r.id}','resolved')">Take Down</button>`
                    : '<span class="adm-muted" style="font-size:12px;">Resolved</span>'}
            </td>
        </tr>`;
    }).join('');
}

// ── CONTENT PREVIEW PANEL ─────────────────────────────────────
async function openPreview(reportId) {
    currentReport = allReports.find(r => r.id === reportId);
    if (!currentReport) return;

    document.getElementById('previewOverlay').classList.add('open');
    document.getElementById('previewBody').innerHTML = '<div class="adm-loading">Loading content…</div>';
    document.getElementById('previewReportBox').innerHTML = '';
    document.getElementById('previewActions').innerHTML = '';

    const type = currentReport.reported_content_type;
    document.getElementById('previewLabel').textContent =
        type === 'resource' ? 'Reported Resource' : type === 'post' ? 'Reported Post' : 'Reported Content';
    document.getElementById('previewTypeTag').innerHTML =
        `<span class="adm-badge badge-info">${escH(type||'content')}</span>
         <span class="adm-badge ${currentReport.status==='pending'?'badge-danger':currentReport.status==='reviewed'?'badge-warn':'badge-success'}" style="margin-left:6px;">${currentReport.status}</span>`;

    // Load the actual content
    if (type === 'resource') {
        await loadResourcePreview(currentReport.reported_content_id);
    } else if (type === 'post') {
        await loadPostPreview(currentReport.reported_content_id);
    } else {
        document.getElementById('previewBody').innerHTML =
            `<div class="adm-empty">Content type "${escH(type)}" preview not available.</div>`;
    }

    // Report info box
    const reporter = currentReport._reporter;
    const reporterName = reporter
        ? `${reporter.first_name||''} ${reporter.last_name||''}`.trim() || `@${reporter.username}`
        : 'Unknown';
    document.getElementById('previewReportBox').innerHTML = `
        <div class="adm-report-info-box">
            <div class="adm-report-info-title">📋 Report Details</div>
            <div class="adm-report-info-row">
                <span class="adm-report-info-label">Reported by</span>
                <span>${escH(reporterName)}</span>
            </div>
            <div class="adm-report-info-row">
                <span class="adm-report-info-label">Reason</span>
                <span>${escH(currentReport.reason||'—')}</span>
            </div>
            <div class="adm-report-info-row">
                <span class="adm-report-info-label">Submitted</span>
                <span>${fmtDate(currentReport.created_at)}</span>
            </div>
            ${currentReport.admin_notes ? `
            <div class="adm-report-info-row">
                <span class="adm-report-info-label">Admin notes</span>
                <span>${escH(currentReport.admin_notes)}</span>
            </div>` : ''}
            ${currentReport.reviewed_at ? `
            <div class="adm-report-info-row">
                <span class="adm-report-info-label">Reviewed at</span>
                <span>${fmtDate(currentReport.reviewed_at)}</span>
            </div>` : ''}
        </div>`;

    // Action buttons
    if (currentReport.status !== 'resolved') {
        document.getElementById('previewActions').innerHTML = `
            <button class="adm-btn adm-btn-warn" onclick="openResolve('${reportId}','reviewed')">
                👁 Mark as Reviewed
            </button>
            <button class="adm-btn adm-btn-danger" onclick="openResolve('${reportId}','resolved')">
                🗑 Take Down &amp; Resolve
            </button>`;
    } else {
        document.getElementById('previewActions').innerHTML =
            `<span class="adm-badge badge-success" style="font-size:13px;padding:6px 14px;">✅ Already Resolved</span>`;
    }
}

async function loadResourcePreview(resourceId) {
    if (!resourceId) {
        document.getElementById('previewBody').innerHTML = '<div class="adm-empty">Resource ID not found.</div>';
        return;
    }
    try {
        const res = await fetch(
            `${SB_URL}/rest/v1/resources?id=eq.${resourceId}` +
            `&select=*,profiles(first_name,last_name,username)`,
            { headers: svcH() }
        );
        const data = await res.json();
        const r = data?.[0];
        if (!r) {
            document.getElementById('previewBody').innerHTML =
                '<div class="adm-empty">Resource has been deleted or not found.</div>';
            return;
        }
        const uploader = r.profiles
            ? `${r.profiles.first_name||''} ${r.profiles.last_name||''}`.trim() || `@${r.profiles.username}`
            : '—';

        // Also load resource files
        const filesRes = await fetch(
            `${SB_URL}/rest/v1/resource_files?resource_id=eq.${resourceId}&select=file_name,file_url,file_size`,
            { headers: svcH() }
        );
        const files = await filesRes.json();

        let filesHTML = '';
        if (files?.length) {
            filesHTML = `<div class="adm-preview-files">
                <div class="adm-preview-section-title">📎 Attached Files</div>
                ${files.map(f => `
                <a class="adm-preview-file-row" href="${escH(f.file_url)}" target="_blank" download>
                    <span>📄</span>
                    <span class="adm-preview-file-name">${escH(f.file_name||'File')}</span>
                    ${f.file_size ? `<span class="adm-muted" style="font-size:11px;">${fmtBytes(f.file_size)}</span>` : ''}
                </a>`).join('')}
            </div>`;
        } else if (r.file_url) {
            filesHTML = `<div class="adm-preview-files">
                <div class="adm-preview-section-title">📎 File</div>
                <a class="adm-preview-file-row" href="${escH(r.file_url)}" target="_blank" download>
                    <span>📄</span>
                    <span class="adm-preview-file-name">${escH(r.original_filename||r.file_url.split('/').pop()||'Download')}</span>
                </a>
            </div>`;
        }

        document.getElementById('previewBody').innerHTML = `
            <div class="adm-preview-resource">
                <div class="adm-preview-resource-header">
                    <div class="adm-preview-resource-icon">${fileTypeEmoji(r.file_type)}</div>
                    <div>
                        <div class="adm-preview-resource-title">${escH(r.title||'Untitled')}</div>
                        <div class="adm-muted" style="font-size:12px;margin-top:4px;">
                            Uploaded by <strong>${escH(uploader)}</strong> ·
                            ${fmtDate(r.created_at)} ·
                            <span class="adm-badge ${r.is_approved?'badge-success':'badge-warn'}">${r.is_approved?'Approved':'Pending'}</span>
                        </div>
                    </div>
                </div>

                <div class="adm-preview-meta-grid">
                    <div class="adm-preview-meta-item"><span class="adm-preview-meta-label">Subject</span><span>${escH(r.subject||'—')}</span></div>
                    <div class="adm-preview-meta-item"><span class="adm-preview-meta-label">Type</span><span>${escH(r.file_type||'—')}</span></div>
                    <div class="adm-preview-meta-item"><span class="adm-preview-meta-label">Visibility</span><span>${escH(r.visibility||'public')}</span></div>
                    <div class="adm-preview-meta-item"><span class="adm-preview-meta-label">Views</span><span>${r.view_count||0}</span></div>
                </div>

                ${r.description ? `
                <div class="adm-preview-section">
                    <div class="adm-preview-section-title">📝 Description</div>
                    <div class="adm-preview-text">${escH(r.description)}</div>
                </div>` : ''}

                ${r.content ? `
                <div class="adm-preview-section">
                    <div class="adm-preview-section-title">📄 Content / Notes</div>
                    <div class="adm-preview-text adm-preview-content-box">${escH(r.content)}</div>
                </div>` : ''}

                ${filesHTML}

                ${r.tags?.length ? `
                <div class="adm-preview-section">
                    <div class="adm-preview-section-title">🏷 Tags</div>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        ${r.tags.map(t=>`<span class="adm-badge badge-gray">${escH(t)}</span>`).join('')}
                    </div>
                </div>` : ''}
            </div>`;
    } catch(e) {
        document.getElementById('previewBody').innerHTML = `<div class="adm-empty">Failed to load resource: ${e.message}</div>`;
    }
}

async function loadPostPreview(postId) {
    if (!postId) {
        document.getElementById('previewBody').innerHTML = '<div class="adm-empty">Post ID not found.</div>';
        return;
    }
    try {
        const res = await fetch(
            `${SB_URL}/rest/v1/posts?id=eq.${postId}` +
            `&select=*,profiles(first_name,last_name,username,profile_photo_url)`,
            { headers: svcH() }
        );
        const data = await res.json();
        const p = data?.[0];
        if (!p) {
            document.getElementById('previewBody').innerHTML =
                '<div class="adm-empty">Post has been deleted or not found.</div>';
            return;
        }
        const author = p.profiles
            ? `${p.profiles.first_name||''} ${p.profiles.last_name||''}`.trim() || `@${p.profiles.username}`
            : '—';
        const media  = safeJSON(p.media_urls, []);
        const files  = safeJSON(p.file_urls,  []);
        const link   = safeJSON(p.link_meta,  null);
        const visIcon = { public:'🌐', friends:'👥', only_me:'🔒' }[p.visibility] || '🌐';

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

        document.getElementById('previewBody').innerHTML = `
            <div class="adm-preview-post">
                <div class="adm-preview-post-header">
                    <div class="adm-preview-post-avatar">${(p.profiles?.first_name||'?')[0].toUpperCase()}</div>
                    <div>
                        <div class="adm-preview-post-author">${escH(author)}</div>
                        <div class="adm-muted" style="font-size:12px;">
                            ${fmtDate(p.created_at)} · ${visIcon} ${escH(p.visibility||'public')}
                        </div>
                    </div>
                </div>

                ${p.content ? `<div class="adm-preview-post-text">${escH(p.content)}</div>` : ''}
                ${mediaHTML}

                ${files.length ? `<div class="adm-preview-files">
                    ${files.map(f=>`<a class="adm-preview-file-row" href="${escH(f.url)}" target="_blank" download>
                        📎 <span>${escH(f.name||'File')}</span>
                    </a>`).join('')}</div>` : ''}

                ${link?.url ? `<a class="adm-preview-link-card" href="${escH(link.url)}" target="_blank" rel="noopener">
                    <div class="adm-preview-link-title">${escH(link.title||link.url)}</div>
                    <div class="adm-muted" style="font-size:11px;">${escH(link.url)}</div>
                </a>` : ''}
            </div>`;
    } catch(e) {
        document.getElementById('previewBody').innerHTML = `<div class="adm-empty">Failed to load post: ${e.message}</div>`;
    }
}

function closePreview(e) {
    if (e && e.target !== document.getElementById('previewOverlay')) return;
    closePreviewDirect();
}
function closePreviewDirect() {
    document.getElementById('previewOverlay').classList.remove('open');
    currentReport = null;
}

// ── RESOLVE / TAKEDOWN MODAL ──────────────────────────────────
function openResolve(reportId, action) {
    pendingReportId = reportId;
    pendingAction   = action;

    const isTakedown = action === 'resolved';
    document.getElementById('resolveModalTitle').textContent =
        isTakedown ? '🗑 Take Down Content' : '👁 Mark as Reviewed';
    document.getElementById('takedownReasonWrap').style.display = isTakedown ? '' : 'none';
    document.getElementById('btnMarkReviewed').style.display  = isTakedown ? 'none' : '';
    document.getElementById('btnTakedown').style.display      = isTakedown ? '' : 'none';
    document.getElementById('adminNotes').value = '';
    document.getElementById('takedownReason').value = '';
    document.getElementById('resolveModal').style.display = 'flex';
}
function closeResolve() {
    document.getElementById('resolveModal').style.display = 'none';
}

async function submitAction(status) {
    const notes         = document.getElementById('adminNotes').value.trim();
    const takedownReason = document.getElementById('takedownReason').value;

    if (status === 'resolved' && !takedownReason) {
        alert('Please select a takedown reason.');
        return;
    }

    const fullNotes = takedownReason
        ? `[Takedown: ${takedownReason}]${notes ? ' — ' + notes : ''}`
        : notes;

    try {
        // 1. Update the report
        await fetch(`${SB_URL}/rest/v1/reports?id=eq.${pendingReportId}`, {
            method: 'PATCH',
            headers: svcH(),
            body: JSON.stringify({
                status,
                admin_notes: fullNotes || null,
                reviewed_at: new Date().toISOString()
            })
        });

        // 2. Act on the reported content
        if (status === 'resolved') {
            const report = allReports.find(r => r.id === pendingReportId);

            if (report?.reported_content_type === 'resource' && report.reported_content_id) {
                // Delete resource files first (FK constraint), then the resource
                await fetch(`${SB_URL}/rest/v1/resource_files?resource_id=eq.${report.reported_content_id}`,
                    { method: 'DELETE', headers: svcH() });
                await fetch(`${SB_URL}/rest/v1/resources?id=eq.${report.reported_content_id}`,
                    { method: 'DELETE', headers: svcH() });
            }

            if (report?.reported_content_type === 'post' && report.reported_content_id) {
                // Hard-delete the post (comments cascade via FK)
                await fetch(`${SB_URL}/rest/v1/posts?id=eq.${report.reported_content_id}`,
                    { method: 'DELETE', headers: svcH() });
            }

            // 3. Log the admin action
            await logAdminAction('takedown_content', 'post_or_resource', report?.reported_content_id, fullNotes);
            await logAdminAction('resolve_report', 'report', pendingReportId, fullNotes);
        }

        closeResolve();
        closePreviewDirect();
        loadReports();
    } catch(e) {
        alert('Action failed: ' + e.message);
    }
}

// ── ADMIN ACTION LOGGER ───────────────────────────────────────
async function logAdminAction(action, targetType, targetId, notes) {
    try {
        const adminId = '{{ session("user_id") }}';
        await fetch(`${SB_URL}/rest/v1/admin_logs`, {
            method: 'POST',
            headers: svcH(),
            body: JSON.stringify({
                admin_id:    adminId || null,
                action,
                target_type: targetType,
                target_id:   targetId || null,
                notes:       notes || null
            })
        });
    } catch(e) { console.warn('Log failed:', e); }
}

// ── VIEW AS USER ──────────────────────────────────────────────
function exitViewAsUser() {
    document.getElementById('viewAsBanner').style.display = 'none';
    sessionStorage.removeItem('admViewAsUser');
}

// Check if already in "view as user" mode
if (sessionStorage.getItem('admViewAsUser')) {
    document.getElementById('viewAsBanner').style.display = 'flex';
}

// ── HELPERS ───────────────────────────────────────────────────
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
function fileTypeEmoji(type) {
    return { notes:'📄', exercise:'📝', slides:'📊', video:'🎬', image:'🖼️',
             link:'🔗', reviewer:'📋', text:'✍️' }[type] || '📎';
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

document.getElementById('adminPageTitle').textContent = 'Reports';
loadReports();
document.getElementById('reportStatusFilter').addEventListener('change', loadReports);
</script>
</body>
</html>
