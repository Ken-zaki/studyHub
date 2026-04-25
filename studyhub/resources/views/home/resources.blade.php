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

</body>
</html>
