<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources - StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
    <link rel="stylesheet" href="{{ asset('css/resources.css') }}">
</head>
<body>

@include('layouts.sidebar')

<main class="main-content">

    <!-- ══ CENTER COLUMN ══ -->
    <div class="feed-column">

        <header class="page-header">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                <div>
                    <h1 class="page-title">Resources</h1>
                    <p class="page-subtitle">Study materials, notes, and files shared by the community</p>
                </div>
                <button class="res-upload-trigger" onclick="openUploadModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Upload Resource
                </button>
            </div>
        </header>

        <!-- Search + Filters row -->
        <div class="res-toolbar">
            <div class="res-search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" id="searchInput" placeholder="Search resources by title, subject, description…" oninput="filterResources()">
                <button class="res-search-clear" id="searchClear" onclick="clearSearch()" style="display:none;">✕</button>
            </div>
            <div class="res-filter-group">
                <button class="res-filter-btn active" id="filterPublic" onclick="setVisibility('public', this)">🌐 Public</button>
                <button class="res-filter-btn" id="filterPrivate" onclick="setVisibility('private', this)">🔒 Friends</button>
            </div>
        </div>

        <!-- Category search -->
        <div class="res-cat-row">
            <div class="res-cat-search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" id="catSearch" placeholder="Search categories…" oninput="filterCategories()">
            </div>
            <div class="res-pills" id="categoryPills">
                <!-- populated by JS -->
            </div>
        </div>

        <!-- Active filters display -->
        <div class="res-active-filters" id="activeFilters" style="display:none;">
            <span class="res-filter-tag" id="activeFilterTag"></span>
            <button onclick="clearCategory()" class="res-clear-filters">Clear filter</button>
        </div>

        <!-- Feed -->
        <div id="resourceFeed">
            <div class="loading-state">Loading resources…</div>
        </div>
    </div>

    <!-- ══ RIGHT SIDEBAR ══ -->
    <aside class="right-sidebar">

        <!-- Recently Viewed -->
        <div class="widget-card">
            <div class="widget-title">🕐 Recently Viewed</div>
            <div id="recentlyViewed">
                <div class="res-empty-small">Nothing viewed yet</div>
            </div>
        </div>

        <!-- My Uploads -->
        <div class="widget-card">
            <div class="widget-title">📤 My Uploads</div>
            <button class="res-upload-btn-sm" onclick="openUploadModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                + Upload File
            </button>
            <div id="myUploads" style="margin-top:12px;">
                <div class="res-empty-small">No uploads yet</div>
            </div>
        </div>

        <!-- Stats -->
        <div class="widget-card">
            <div class="widget-title">📊 Stats</div>
            <div class="res-stats-grid">
                <div class="res-stat-box">
                    <span class="res-stat-num" id="totalCount">—</span>
                    <span class="res-stat-lbl">Resources</span>
                </div>
                <div class="res-stat-box">
                    <span class="res-stat-num" id="subjectCount">—</span>
                    <span class="res-stat-lbl">Subjects</span>
                </div>
            </div>
        </div>

    </aside>
</main>

<!-- ══════════════════════════════════════════
     UPLOAD MODAL
══════════════════════════════════════════ -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <span class="modal-title">Upload Resource</span>
            <button class="modal-close" onclick="closeUploadModal()">✕</button>
        </div>

        <!-- Step indicator -->
        <div class="upload-steps">
            <div class="upload-step active" id="ustep1">1 · Files</div>
            <div class="upload-step-divider"></div>
            <div class="upload-step" id="ustep2">2 · Details</div>
            <div class="upload-step-divider"></div>
            <div class="upload-step" id="ustep3">3 · Settings</div>
        </div>

        <!-- Step 1: Files -->
        <div class="upload-section active" id="usec1">
            <div class="upload-dropzone" id="dropzone" onclick="document.getElementById('fileInputMain').click()"
                 ondragover="dragOver(event)" ondragleave="dragLeave(event)" ondrop="dropFiles(event)">
                <div class="upload-dropzone-icon">📁</div>
                <p class="upload-dropzone-title">Drop files here or click to browse</p>
                <p class="upload-dropzone-sub">Images, videos, PDFs, DOCX, PPTX — multiple files allowed</p>
                <input type="file" id="fileInputMain" multiple accept="image/*,video/*,.pdf,.doc,.docx,.ppt,.pptx,.txt,.zip" style="display:none;" onchange="handleFiles(this.files)">
            </div>
            <div id="filePreviewList" class="file-preview-list"></div>
            <div class="upload-divider"><span>or paste a link instead</span></div>
            <input type="url" id="uploadLink" class="res-input" placeholder="https://…">

            <div class="upload-divider"><span>or write text content</span></div>
            <label class="res-label">Text Content
                <span style="color:var(--text-light);font-weight:400;">(optional — notes, summaries, study material)</span>
            </label>
            <textarea id="uploadContent" class="res-input" rows="5"
                style="resize:vertical;"
                placeholder="Write your study notes, summaries, or any text content directly here…"></textarea>

            <div class="modal-actions" style="margin-top:16px;">
                <button class="btn-secondary" onclick="closeUploadModal()">Cancel</button>
                <button class="btn-primary" onclick="goStep(2)">Next →</button>
            </div>
        </div>

        <!-- Step 2: Details -->
        <div class="upload-section" id="usec2">
            <div style="display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label class="res-label">Title <span class="req">*</span></label>
                    <input type="text" id="uploadTitle" class="res-input" placeholder="e.g. Chapter 4 – Calculus Notes">
                    <div class="field-error" id="errTitle"></div>
                </div>
                <div>
                    <label class="res-label">Description <span style="color:var(--text-light);font-weight:400;">(optional)</span></label>
                    <textarea id="uploadDesc" class="res-input" rows="3" placeholder="Brief description of this resource…" style="resize:vertical;"></textarea>
                </div>
                <div>
                    <label class="res-label">Text Content <span style="color:var(--text-light);font-weight:400;">(optional — write notes directly, no file needed)</span></label>
                    <textarea id="uploadContent" class="res-input" rows="5" placeholder="Write your study notes, summaries, or any text content here…" style="resize:vertical;"></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label class="res-label">Subject Category <span class="req">*</span></label>
                        <select id="uploadSubject" class="res-input" onchange="checkOtherSubject()">
                            <option value="">Select subject…</option>
                            <option>Mathematics</option>
                            <option>Science</option>
                            <option>Filipino</option>
                            <option>English</option>
                            <option>PE</option>
                            <option>Health</option>
                            <option>Music</option>
                            <option>Arts</option>
                            <option>Social Studies</option>
                            <option>Computer Science</option>
                            <option>Values Education</option>
                            <option>MAPEH</option>
                            <option value="others">Others…</option>
                        </select>
                        <input type="text" id="uploadSubjectOther" class="res-input" placeholder="Enter subject name…" style="display:none;margin-top:8px;">
                        <div class="field-error" id="errSubject"></div>
                    </div>
                    <div>
                        <label class="res-label">Material Type <span class="req">*</span></label>
                        <select id="uploadType" class="res-input" onchange="checkOtherType()">
                            <option value="">Select type…</option>
                            <option value="notes">📄 Notes</option>
                            <option value="exercise">📝 Exercise / Activity</option>
                            <option value="slides">📊 Slides / Presentation</option>
                            <option value="video">🎬 Video</option>
                            <option value="image">🖼️ Image</option>
                            <option value="link">🔗 Link / Reference</option>
                            <option value="reviewer">📋 Reviewer</option>
                            <option value="text">✍️ Text Only</option>
                            <option value="others">✏️ Others…</option>
                        </select>
                        <input type="text" id="uploadTypeOther" class="res-input" placeholder="Describe the material type…" style="display:none;margin-top:8px;">
                        <div class="field-error" id="errType"></div>
                    </div>
                </div>
            </div>
            <div class="modal-actions" style="margin-top:16px;">
                <button class="btn-secondary" onclick="goStep(1)">← Back</button>
                <button class="btn-primary" onclick="goStep(3)">Next →</button>
            </div>
        </div>

        <!-- Step 3: Settings -->
        <div class="upload-section" id="usec3">
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div>
                    <label class="res-label">Visibility <span class="req">*</span></label>
                    <div class="vis-options">
                        <label class="vis-option" id="visPublic">
                            <input type="radio" name="visibility" value="public" checked onchange="setVis('public')">
                            <div class="vis-option-body">
                                <span class="vis-icon">🌐</span>
                                <div>
                                    <div class="vis-title">Public</div>
                                    <div class="vis-sub">Visible to all StudyHub users</div>
                                </div>
                            </div>
                        </label>
                        <label class="vis-option" id="visPrivate">
                            <input type="radio" name="visibility" value="private" onchange="setVis('private')">
                            <div class="vis-option-body">
                                <span class="vis-icon">🔒</span>
                                <div>
                                    <div class="vis-title">Friends Only</div>
                                    <div class="vis-sub">Only your connections can see this</div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="upload-summary" id="uploadSummary">
                    <!-- filled by JS -->
                </div>
            </div>
            <div class="modal-actions" style="margin-top:16px;">
                <button class="btn-secondary" onclick="goStep(2)">← Back</button>
                <button class="btn-primary" id="submitUploadBtn" onclick="submitUpload()">📤 Upload</button>
            </div>
        </div>
    </div>
</div>

<!-- ══ CONFIG ══ -->
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script>
    const SUPABASE_URL         = '{{ env("SUPABASE_URL") }}';
    const SUPABASE_ANON_KEY    = '{{ env("SUPABASE_ANON_KEY") }}';
    const SUPABASE_SERVICE_KEY = '{{ env("SUPABASE_SERVICE_KEY") }}';
    const CURRENT_USER = {
        id:       '{{ session("user_id") }}',
        name:     '{{ trim(session("user_first_name","") . " " . session("user_last_name","")) }}',
        initials: '{{ strtoupper(substr(session("user_first_name","U"),0,1).substr(session("user_last_name","U"),0,1)) }}'
    };
</script>
<script src="{{ asset('js/resources.js') }}"></script>

{{-- Appended to resources.blade.php BEFORE </body> --}}

<!-- ══════════════════════════════════════════
     RESOURCE DETAIL PAGE (full-screen overlay)
══════════════════════════════════════════ -->
<div class="res-detail-overlay" id="resDetailOverlay" style="display:none;">
    <div class="res-detail-page">

        <!-- Top bar -->
        <div class="res-detail-topbar">
            <button class="res-detail-back" onclick="closeDetail()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                Back to Resources
            </button>
            <div class="res-detail-actions" id="detailActions"></div>
        </div>

        <div class="res-detail-body">

            <!-- LEFT: main content -->
            <div class="res-detail-main">

                <!-- Header -->
                <div class="res-detail-header">
                    <div class="res-detail-icon" id="detailIcon">📄</div>
                    <div class="res-detail-meta">
                        <h1 class="res-detail-title" id="detailTitle">Loading…</h1>
                        <div class="res-detail-submeta" id="detailSubmeta"></div>
                        <!-- Star rating -->
                        <div class="res-stars-row">
                            <div class="res-stars-display" id="detailStarsDisplay"></div>
                            <span class="res-rating-count" id="detailRatingCount"></span>
                        </div>
                    </div>
                </div>

                <!-- Description / Content -->
                <div class="res-detail-section" id="detailDescSection" style="display:none;">
                    <div class="res-detail-section-title">Description</div>
                    <div class="res-detail-desc" id="detailDesc"></div>
                </div>

                <!-- Text content (for text-only resources) -->
                <div class="res-detail-section" id="detailContentSection" style="display:none;">
                    <div class="res-detail-section-title">Content</div>
                    <div class="res-detail-content-body" id="detailContent"></div>
                </div>

                <!-- File -->
                <div class="res-detail-section" id="detailFileSection" style="display:none;">
                    <div class="res-detail-section-title">File</div>
                    <a class="res-detail-file-btn" id="detailFileBtn" href="#" target="_blank" download>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Download File
                    </a>
                </div>

                <!-- Link -->
                <div class="res-detail-section" id="detailLinkSection" style="display:none;">
                    <div class="res-detail-section-title">Reference Link</div>
                    <a class="res-detail-link-btn" id="detailLinkBtn" href="#" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        Open Link
                    </a>
                </div>

                <!-- Rating + Comment combined -->
                <div class="res-detail-section" id="reviewSection">
                    <div class="res-detail-section-title">
                        Rate &amp; Comment
                        <span class="res-comment-count" id="commentCount">0</span>
                    </div>

                    <!-- Star rating row -->
                    <div class="res-rate-row" style="margin-bottom:14px;">
                        <div class="res-rate-stars" id="rateStars">
                            <span class="res-rate-star" data-v="1"
                                onmouseenter="starHover(1)" onmouseleave="starOut()" onclick="starClick(1)">★</span>
                            <span class="res-rate-star" data-v="2"
                                onmouseenter="starHover(2)" onmouseleave="starOut()" onclick="starClick(2)">★</span>
                            <span class="res-rate-star" data-v="3"
                                onmouseenter="starHover(3)" onmouseleave="starOut()" onclick="starClick(3)">★</span>
                            <span class="res-rate-star" data-v="4"
                                onmouseenter="starHover(4)" onmouseleave="starOut()" onclick="starClick(4)">★</span>
                            <span class="res-rate-star" data-v="5"
                                onmouseenter="starHover(5)" onmouseleave="starOut()" onclick="starClick(5)">★</span>
                        </div>
                        <span class="res-rate-label" id="rateLabel">Click a star to rate</span>
                    </div>

                    <!-- Comment input + submit button -->
                    <div class="res-comment-form">
                        <div class="res-comment-avatar">{{ strtoupper(substr(session('user_first_name','U'),0,1).substr(session('user_last_name','U'),0,1)) }}</div>
                        <div class="res-comment-input-wrap">
                            <textarea id="commentInput" class="res-comment-input"
                                placeholder="Write a comment… (optional)"
                                rows="2" oninput="autoResize(this)"></textarea>
                            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:6px;">
                                <button class="res-comment-submit"
                                    style="background:#f3f4f6;color:var(--text-secondary);"
                                    onclick="submitRatingOnly()"
                                    title="Submit rating without a comment">
                                    ⭐ Rate Only
                                </button>
                                <button id="submitReviewBtn" class="res-comment-submit" onclick="submitComment()">
                                    💬 Post Comment
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Existing comments list -->
                    <div class="res-comments-list" id="commentsList">
                        <div class="res-loading-sm">Loading comments…</div>
                    </div>
                </div>

            </div>

            <!-- RIGHT: sidebar -->
            <aside class="res-detail-sidebar">
                <div class="res-detail-info-card">
                    <div class="res-info-row"><span class="res-info-label">Subject</span><span class="res-info-val" id="infoSubject">—</span></div>
                    <div class="res-info-row"><span class="res-info-label">Type</span><span class="res-info-val" id="infoType">—</span></div>
                    <div class="res-info-row"><span class="res-info-label">Visibility</span><span class="res-info-val" id="infoVis">—</span></div>
                    <div class="res-info-row"><span class="res-info-label">Uploaded by</span><span class="res-info-val" id="infoUploader">—</span></div>
                    <div class="res-info-row"><span class="res-info-label">Date</span><span class="res-info-val" id="infoDate">—</span></div>
                    <div class="res-info-row" id="infoViewsRow" style="display:none;"><span class="res-info-label">Views</span><span class="res-info-val" id="infoViews">—</span></div>
                </div>

                <button class="res-report-btn" id="reportBtn" onclick="openReport()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    Flag / Report
                </button>
            </aside>
        </div>
    </div>
</div>

<!-- ══ EDIT MODAL ══ -->
<div class="modal-overlay" id="editModal">
    <div class="modal" style="max-width:620px;">
        <div class="modal-header">
            <span class="modal-title">Edit Resource</span>
            <button class="modal-close" onclick="closeEditModal()">✕</button>
        </div>
        <div style="display:flex;flex-direction:column;gap:14px;">
            <div>
                <label class="res-label">Title <span class="req">*</span></label>
                <input type="text" id="editTitle" class="res-input" placeholder="Resource title">
            </div>
            <div>
                <label class="res-label">Description <span style="color:var(--text-light);font-weight:400;">(optional)</span></label>
                <textarea id="editDesc" class="res-input" rows="3" style="resize:vertical;" placeholder="Brief description…"></textarea>
            </div>
            <div>
                <label class="res-label">Text Content <span style="color:var(--text-light);font-weight:400;">(optional — for text-only resources)</span></label>
                <textarea id="editContent" class="res-input" rows="6" style="resize:vertical;" placeholder="Write your notes, summaries, or study material here…"></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label class="res-label">Subject <span class="req">*</span></label>
                    <select id="editSubject" class="res-input" onchange="checkEditOtherSubject()">
                        <option value="">Select subject…</option>
                        <option>Mathematics</option><option>Science</option><option>Filipino</option>
                        <option>English</option><option>PE</option><option>Health</option>
                        <option>Music</option><option>Arts</option><option>Social Studies</option>
                        <option>Computer Science</option><option>Values Education</option><option>MAPEH</option>
                        <option>History</option><option>Chemistry</option><option>Physics</option>
                        <option>Biology</option><option>Economics</option><option value="others">Others…</option>
                    </select>
                    <input type="text" id="editSubjectOther" class="res-input" placeholder="Enter subject name…" style="display:none;margin-top:8px;">
                </div>
                <div>
                    <label class="res-label">Type <span class="req">*</span></label>
                    <select id="editType" class="res-input" onchange="checkEditOtherType()">
                        <option value="">Select type…</option>
                        <option value="notes">📄 Notes</option>
                        <option value="exercise">📝 Exercise</option>
                        <option value="slides">📊 Slides</option>
                        <option value="video">🎬 Video</option>
                        <option value="image">🖼️ Image</option>
                        <option value="link">🔗 Link</option>
                        <option value="reviewer">📋 Reviewer</option>
                        <option value="text">✍️ Text only</option>
                        <option value="others">✏️ Others…</option>
                    </select>
                    <input type="text" id="editTypeOther" class="res-input" placeholder="Describe the type…" style="display:none;margin-top:8px;">
                </div>
            </div>
            <div>
                <label class="res-label">Visibility</label>
                <div style="display:flex;gap:10px;">
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px;">
                        <input type="radio" name="editVis" value="public" checked> 🌐 Public
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px;">
                        <input type="radio" name="editVis" value="private"> 🔒 Friends only
                    </label>
                </div>
            </div>
             <div>
                <label class="res-label">
                    Files
                    <span style="color:var(--text-light);font-weight:400;">
                        (rename, remove, or add new files)
                    </span>
                </label>
                <div id="editFileManager">
                    <div style="padding:12px;color:var(--text-light);font-size:13px;">
                        Loading files…
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-actions" style="margin-top:20px;">
            <button class="btn-secondary" onclick="closeEditModal()">Cancel</button>
            <button class="btn-primary" onclick="saveEdit()">💾 Save Changes</button>
        </div>
    </div>
</div>

<!-- ══ REPORT MODAL ══ -->
<div class="modal-overlay" id="reportModal">
    <div class="modal" style="max-width:460px;">
        <div class="modal-header">
            <span class="modal-title">Report Resource</span>
            <button class="modal-close" onclick="closeReport()">✕</button>
        </div>
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">
            Help us keep StudyHub safe. Tell us what's wrong with this resource.
        </p>
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
            <label class="res-report-option"><input type="radio" name="reportReason" value="Inappropriate content"> Inappropriate content</label>
            <label class="res-report-option"><input type="radio" name="reportReason" value="Spam or misleading"> Spam or misleading</label>
            <label class="res-report-option"><input type="radio" name="reportReason" value="Copyright violation"> Copyright violation</label>
            <label class="res-report-option"><input type="radio" name="reportReason" value="Low quality content"> Low quality content</label>
            <label class="res-report-option"><input type="radio" name="reportReason" value="Other"> Other (describe below)</label>
        </div>
        <textarea id="reportDetails" class="res-input" rows="3" style="resize:vertical;" placeholder="Additional details (optional)…"></textarea>
        <div class="modal-actions" style="margin-top:16px;">
            <button class="btn-secondary" onclick="closeReport()">Cancel</button>
            <button class="btn-primary" style="background:var(--accent);border-color:var(--accent);" onclick="submitReport()">Submit Report</button>
        </div>
    </div>
</div>
@include('layouts.admin_bar')
</body>
</html>
