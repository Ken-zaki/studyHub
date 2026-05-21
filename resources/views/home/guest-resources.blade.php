<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources — StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
    <link rel="stylesheet" href="{{ asset('css/resources.css') }}">
    <style>
        /* ── Gate modal ── */
        .g-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity 0.2s;}
        .g-modal-overlay.open{opacity:1;pointer-events:all;}
        .g-modal{background:var(--bg-card,white);border-radius:20px;padding:32px;width:90%;max-width:400px;text-align:center;transform:scale(0.95);transition:transform 0.2s;box-shadow:0 20px 60px rgba(0,0,0,0.18);}
        .g-modal-overlay.open .g-modal{transform:scale(1);}
        .g-modal-icon{font-size:40px;margin-bottom:14px;}
        .g-modal h3{font-family:'Crimson Pro',serif;font-size:22px;font-weight:700;margin-bottom:8px;color:var(--text-primary);}
        .g-modal p{font-size:14px;color:var(--text-secondary);line-height:1.6;margin-bottom:24px;}
        .g-modal-btns{display:flex;flex-direction:column;gap:10px;}
        .gm-p{display:block;padding:12px;border-radius:12px;background:var(--primary,#1a5f7a);color:white;font-size:14px;font-weight:700;text-decoration:none;}
        .gm-p:hover{opacity:.88;}
        .gm-s{display:block;padding:12px;border-radius:12px;border:1.5px solid var(--border);background:var(--bg-card);font-size:14px;font-weight:600;color:var(--text-primary);text-decoration:none;}
        .gm-s:hover{border-color:var(--primary);color:var(--primary);}
        .gm-d{margin-top:10px;font-size:13px;color:var(--text-light);cursor:pointer;background:none;border:none;font-family:inherit;}

        /* ── Comment section inside detail — guest prompt ── */
        .g-comment-prompt{
            background:rgba(26,95,122,0.04);border:1.5px dashed rgba(26,95,122,0.2);
            border-radius:14px;padding:16px;text-align:center;cursor:pointer;
            transition:all 0.2s;margin-bottom:16px;
        }
        .g-comment-prompt:hover{background:rgba(26,95,122,0.07);}
    </style>
</head>
<body>

@include('layouts.guest-sidebar', ['guestNav' => 'resources'])

<main class="main-content">

    {{-- ══ CENTER COLUMN ══ --}}
    <div class="feed-column">

        <header class="page-header">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                <div>
                    <h1 class="page-title">Resources</h1>
                    <p class="page-subtitle">Study materials, notes, and files shared by the community</p>
                </div>
                {{-- Upload button — gated --}}
                <button class="res-upload-trigger" onclick="showModal('upload')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    Upload Resource
                </button>
            </div>
        </header>

        {{-- ── Toolbar mirrors registered: search + subject dropdown + visibility filter ── --}}
        <div class="res-toolbar">
            <div class="res-search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="M21 21l-4.35-4.35"/>
                </svg>
                <input type="text" id="searchInput"
                    placeholder="Search resources by title, subject, description…"
                    oninput="filterResources()">
                <button class="res-search-clear" id="searchClear" onclick="clearSearch()" style="display:none;">✕</button>
            </div>

            <div class="res-subject-wrap">
                <select id="subjectSelect" class="res-subject-select" onchange="setSubjectFromDropdown(this)">
                    <option value="All">All subjects</option>
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
                    <option>History</option>
                    <option>Chemistry</option>
                    <option>Physics</option>
                    <option>Biology</option>
                    <option>Economics</option>
                    <option>Others</option>
                </select>
            </div>

            {{-- Visibility filter: Public only for guests, Friends gated --}}
            <div class="res-filter-group">
                <button class="res-filter-btn active" id="filterPublic">🌐 Public</button>
                <button class="res-filter-btn" onclick="showModal('private')">🔒 Friends</button>
            </div>
        </div>

        {{-- Others subject search (shown when Others selected) --}}
        <div id="othersSubjectSearch" style="display:none;align-items:center;gap:6px;width:100%;margin-bottom:10px;">
            <div class="res-others-search-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
                <input type="text" id="othersSubjectInput"
                    placeholder="Search custom subjects…"
                    oninput="filterResources()">
                <button class="res-search-clear" id="othersClear" onclick="clearOthersSearch()">✕</button>
            </div>
        </div>

        {{-- Active filter tag --}}
        <div class="res-active-filters" id="activeFilters" style="display:none;">
            <span class="res-filter-tag" id="activeFilterTag"></span>
            <button onclick="clearCategory()" class="res-clear-filters">Clear filter</button>
        </div>

        <div id="resCount" style="font-size:13px;color:var(--text-secondary);margin-bottom:14px;">Loading resources…</div>

        {{-- Feed --}}
        <div id="resourceFeed">
            <div class="loading-state">Loading resources…</div>
        </div>
    </div>

    {{-- ══ RIGHT SIDEBAR — mirrors registered ══ --}}
    <aside class="right-sidebar">

        {{-- Recently Viewed --}}
        <div class="widget-card">
            <div class="widget-title">🕐 Recently Viewed</div>
            <div id="recentlyViewed">
                <div class="res-empty-small">Nothing viewed yet</div>
            </div>
        </div>

        {{-- Sign up CTA (replaces My Uploads for guest) --}}
        <div class="widget-card">
            <div class="widget-title">📤 Upload Resources</div>
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:10px;">
                Sign up to upload and manage your own study materials.
            </p>
            <button class="res-upload-btn-sm" onclick="showModal('upload')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     style="width:15px;height:15px;">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/>
                    <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                + Upload File
            </button>
            <a href="{{ route('signup') }}"
               style="display:block;text-align:center;margin-top:10px;padding:9px;border-radius:10px;background:var(--primary);color:white;font-weight:700;font-size:13px;text-decoration:none;"
               onmouseover="this.style.opacity='.88';" onmouseout="this.style.opacity='1';">
                Sign Up Free →
            </a>
        </div>

        {{-- Community Picks --}}
        <div class="widget-card">
            <div class="widget-title">⭐ Community Picks</div>
            <p class="res-widget-sub">Highest rated by the community</p>
            <div id="topRatedWidget">
                <div class="res-empty-small">Loading…</div>
            </div>
        </div>

        {{-- Stats --}}
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
                <div class="res-stat-box">
                    <span class="res-stat-num" id="savedCount">—</span>
                    <span class="res-stat-lbl">Types</span>
                </div>
            </div>
        </div>

    </aside>
</main>

{{-- ══ RESOURCE DETAIL OVERLAY — mirrors registered structure ══ --}}
<div id="resDetailOverlay" class="res-detail-overlay" style="display:none;">
    <div class="res-detail-page" id="resDetailPage">

        <div class="res-detail-header">
            <button class="res-detail-back" onclick="closeDetail()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Back
            </button>
            <div class="res-detail-header-actions">
                {{-- Bookmark gated --}}
                <button class="res-detail-bk-btn" onclick="showModal('save')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>
                    </svg>
                    Save
                </button>
            </div>
        </div>

        <div class="res-detail-body">
            <div>
                {{-- Inline back bar --}}
                <div class="res-detail-top-bar">
                    <button class="res-detail-back-inline" onclick="closeDetail()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                        Back
                    </button>
                </div>

                <div class="res-detail-hero">
                    <div class="res-detail-icon" id="detailIcon">📄</div>
                    <div class="res-detail-meta">
                        <h1 class="res-detail-title" id="detailTitle">Loading…</h1>
                        <div class="res-detail-submeta" id="detailSubmeta"></div>
                        <div class="res-stars-row">
                            <div class="res-stars-display" id="detailStarsDisplay"></div>
                            <span class="res-rating-count" id="detailRatingCount"></span>
                        </div>
                    </div>
                </div>

                <div class="res-detail-section" id="detailDescSection" style="display:none;">
                    <div class="res-detail-section-title">Description</div>
                    <div class="res-detail-desc" id="detailDesc"></div>
                </div>

                <div class="res-detail-section" id="detailContentSection" style="display:none;">
                    <div class="res-detail-section-title">Content</div>
                    <div class="res-detail-content-body" id="detailContent"></div>
                </div>

                <div class="res-detail-section" id="detailFileSection" style="display:none;">
                    <div class="res-detail-section-title">File</div>
                    <a class="res-detail-file-btn" id="detailFileBtn" href="#" target="_blank" download>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Download File
                    </a>
                </div>

                <div class="res-detail-section" id="detailLinkSection" style="display:none;">
                    <div class="res-detail-section-title">Reference Link</div>
                    <a class="res-detail-link-btn" id="detailLinkBtn" href="#" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/>
                            <polyline points="15 3 21 3 21 9"/>
                            <line x1="10" y1="14" x2="21" y2="3"/>
                        </svg>
                        Open Link
                    </a>
                </div>

                {{-- Rate & Comment — gated for guests --}}
                <div class="res-detail-section">
                    <div class="res-detail-section-title">
                        Rate &amp; Comment
                        <span class="res-comment-count" id="commentCount">0</span>
                    </div>
                    <div class="g-comment-prompt" onclick="showModal('comment')">
                        <div style="font-size:22px;margin-bottom:6px;">💬</div>
                        <div style="font-size:14px;font-weight:600;color:var(--primary);margin-bottom:3px;">Sign in to rate and comment</div>
                        <div style="font-size:12px;color:var(--text-secondary);">Create a free account to leave feedback on this resource.</div>
                    </div>
                    <div id="detailComments"><div class="res-loading-sm">Loading comments…</div></div>
                </div>
            </div>

            {{-- Detail sidebar --}}
            <aside class="res-detail-sidebar">
                <div class="res-detail-info-card">
                    <div class="res-info-row"><span class="res-info-label">Subject</span><span class="res-info-val" id="infoSubject">—</span></div>
                    <div class="res-info-row"><span class="res-info-label">Type</span><span class="res-info-val" id="infoType">—</span></div>
                    <div class="res-info-row"><span class="res-info-label">Uploaded by</span><span class="res-info-val" id="infoUploader">—</span></div>
                    <div class="res-info-row"><span class="res-info-label">Date</span><span class="res-info-val" id="infoDate">—</span></div>
                    <div class="res-info-row" id="infoViewsRow" style="display:none;"><span class="res-info-label">Views</span><span class="res-info-val" id="infoViews">—</span></div>
                </div>

                {{-- Sign up CTA in detail --}}
                <div class="res-detail-info-card" style="text-align:center;padding:18px 20px;">
                    <div style="font-size:14px;font-weight:600;color:var(--text-primary);margin-bottom:6px;">🎓 Join StudyHub</div>
                    <p style="font-size:12px;color:var(--text-secondary);line-height:1.5;margin-bottom:12px;">
                        Sign up to rate, comment, save, and upload resources.
                    </p>
                    <a href="{{ route('signup') }}"
                       style="display:block;padding:9px;border-radius:10px;background:var(--primary);color:white;font-weight:700;font-size:13px;text-decoration:none;margin-bottom:8px;"
                       onmouseover="this.style.opacity='.88';" onmouseout="this.style.opacity='1';">
                        Sign Up Free →
                    </a>
                    <a href="{{ route('login') }}"
                       style="display:block;padding:9px;border-radius:10px;border:1.5px solid var(--border);background:var(--bg-card);color:var(--text-primary);font-weight:600;font-size:13px;text-decoration:none;"
                       onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)';"
                       onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-primary)';">
                        Log In
                    </a>
                </div>
            </aside>
        </div>
    </div>
</div>

{{-- ══ Gate modal ══ --}}
<div class="g-modal-overlay" id="resModal" onclick="if(event.target===this)closeModal();">
    <div class="g-modal">
        <div class="g-modal-icon" id="rmIcon"></div>
        <h3 id="rmTitle"></h3>
        <p id="rmBody"></p>
        <div class="g-modal-btns">
            <a href="{{ route('signup') }}" class="gm-p">Create Free Account</a>
            <a href="{{ route('login') }}"  class="gm-s">I already have an account</a>
        </div>
        <button class="gm-d" onclick="closeModal()">Maybe later</button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script>
var SUPABASE_URL      = '{{ env("SUPABASE_URL") }}';
var SUPABASE_ANON_KEY = '{{ env("SUPABASE_ANON_KEY") }}';
var _sb = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

var TYPE_ICONS  = {notes:'📄',exercise:'📝',slides:'📊',reviewer:'📋',video:'🎬',image:'🖼️',link:'🔗',text:'✍️'};
var TYPE_COLORS = {notes:'#3b82f6',exercise:'#8b5cf6',slides:'#f59e42',reviewer:'#10b981',video:'#ef4444',image:'#ec4899',link:'#6b7280',text:'#14b8a6'};

var allRes     = [];
var currentRes = null;
var activeSubject = 'All';
var othersSearch  = '';

/* ── Load ── */
async function loadRes() {
    try {
        var result = await _sb
            .from('resources')
            .select('*, profiles(first_name, last_name, username)')
            .eq('is_approved', true)
            .order('created_at', { ascending: false });

        if (result.error) throw result.error;
        var data = result.data || [];

        /* Only public resources for guests (mirrors registered renderResources filter) */
        allRes = data.filter(function(r) {
            return r.visibility !== 'private';
        });

        /* Stats */
        var subjects = new Set(allRes.map(function(r) { return r.subject; }).filter(Boolean));
        var types    = new Set(allRes.map(function(r) { return r.file_type; }).filter(Boolean));
        document.getElementById('totalCount').textContent   = allRes.length;
        document.getElementById('subjectCount').textContent = subjects.size;
        document.getElementById('savedCount').textContent   = types.size;

        /* Load community picks */
        loadTopRated();

        filterResources();
    } catch(err) {
        document.getElementById('resourceFeed').innerHTML =
            '<div style="color:#dc2626;padding:16px;font-size:13px;">Failed to load resources: ' + escH(err.message) + '</div>';
    }
}

async function loadTopRated() {
    var el = document.getElementById('topRatedWidget');
    try {
        var rr = await _sb.from('resource_ratings').select('resource_id,rating');
        if (rr.error || !rr.data) { el.innerHTML = '<div class="res-empty-small">No ratings yet</div>'; return; }

        var agg = {};
        rr.data.forEach(function(row) {
            if (!agg[row.resource_id]) agg[row.resource_id] = { sum: 0, count: 0 };
            agg[row.resource_id].sum += row.rating;
            agg[row.resource_id].count++;
        });

        var sorted = Object.keys(agg)
            .map(function(id) { return { id: id, avg: agg[id].sum / agg[id].count, count: agg[id].count }; })
            .filter(function(r) { return r.count >= 1; })
            .sort(function(a, b) { return b.avg - a.avg || b.count - a.count; })
            .slice(0, 5);

        if (!sorted.length) { el.innerHTML = '<div class="res-empty-small">No ratings yet</div>'; return; }

        var RANK_CLS = { 0:'rank-1', 1:'rank-2', 2:'rank-3' };
        var RANK_LBL = { 0:'🥇', 1:'🥈', 2:'🥉' };

        el.innerHTML = sorted.map(function(item, i) {
            var res = allRes.find(function(r) { return r.id === item.id; });
            if (!res) return '';
            var icon  = TYPE_ICONS[res.file_type] || '📎';
            var stars = [1,2,3,4,5].map(function(s) {
                return '<span class="' + (s <= Math.round(item.avg) ? 'res-top-rated-star-filled' : 'res-top-rated-star-empty') + '">★</span>';
            }).join('');
            return '<div class="res-top-rated-item" onclick="openDetail(\'' + escH(res.id) + '\')">'
                + '<div class="res-top-rated-rank ' + (RANK_CLS[i] || 'rank-n') + '">' + (RANK_LBL[i] || (i + 1)) + '</div>'
                + '<div class="res-recent-icon">' + icon + '</div>'
                + '<div style="flex:1;min-width:0;">'
                + '<div class="res-recent-title">' + escH(res.title || 'Untitled') + '</div>'
                + '<div class="res-top-rated-stars">' + stars
                + '<span class="res-top-rated-score">' + item.avg.toFixed(1) + '</span>'
                + '<span class="res-top-rated-count">(' + item.count + ')</span>'
                + '</div></div></div>';
        }).join('');
    } catch(e) { el.innerHTML = '<div class="res-empty-small">Could not load</div>'; }
}

/* ── Filter & render ── */
function filterResources() {
    var q   = (document.getElementById('searchInput').value || '').toLowerCase();
    var sub = activeSubject;
    var oth = othersSearch.toLowerCase();

    document.getElementById('searchClear').style.display = q ? 'block' : 'none';

    var filtered = allRes.filter(function(r) {
        var matchQ = !q || (r.title || '').toLowerCase().includes(q) || (r.description || '').toLowerCase().includes(q);
        var matchS;
        if (sub === 'All') {
            matchS = true;
        } else if (sub === 'Others') {
            var known = ['Mathematics','Science','Filipino','English','PE','Health','Music','Arts',
                'Social Studies','Computer Science','Values Education','MAPEH',
                'History','Chemistry','Physics','Biology','Economics'];
            matchS = !known.includes(r.subject);
            if (matchS && oth) matchS = (r.subject || '').toLowerCase().includes(oth);
        } else {
            matchS = r.subject === sub;
        }
        return matchQ && matchS;
    });

    /* Active filter tag */
    var af = document.getElementById('activeFilters');
    if (sub !== 'All') {
        af.style.display = 'flex';
        document.getElementById('activeFilterTag').textContent = sub + (oth ? ': "' + oth + '"' : '');
    } else {
        af.style.display = 'none';
    }

    document.getElementById('resCount').textContent = filtered.length + ' resource' + (filtered.length !== 1 ? 's' : '') + ' found';
    renderResources(filtered);
}

function renderResources(items) {
    var el = document.getElementById('resourceFeed');
    if (!items.length) {
        el.innerHTML = '<div class="res-empty"><div class="ei">📭</div><p>No resources match your search.</p></div>';
        return;
    }

    el.innerHTML = items.map(function(r) {
        var up  = r.profiles ? ((r.profiles.first_name || '') + ' ' + (r.profiles.last_name || '')).trim() || ('@' + (r.profiles.username || '')) : '—';
        var icon = TYPE_ICONS[r.file_type] || '📎';
        var col  = TYPE_COLORS[r.file_type] || '#6b7280';
        var desc = r.description ? r.description.slice(0, 80) + (r.description.length > 80 ? '…' : '') : '';
        var typeClass = r.file_type ? r.file_type : 'others';

        return '<div class="res-card" onclick="openDetail(\'' + escH(r.id) + '\')">'
            + '<div class="res-card-icon ' + typeClass + '">' + icon + '</div>'
            + '<div class="res-card-body">'
            + '<div class="res-card-title">' + escH(r.title || 'Untitled') + '</div>'
            + (desc ? '<div class="res-card-desc">' + escH(desc) + '</div>' : '')
            + '<div class="res-card-meta">'
            + (r.subject ? '<span class="res-type-badge notes">' + escH(r.subject) + '</span>' : '')
            + (r.file_type ? '<span class="res-type-badge ' + typeClass + '">' + escH(r.file_type.toUpperCase()) + '</span>' : '')
            + '<span class="dot">·</span><span>by ' + escH(up) + '</span>'
            + (r.view_count ? '<span class="dot">·</span><span>👁 ' + r.view_count + '</span>' : '')
            + '</div></div>'
            + '<div class="res-card-actions">'
            + (r.file_url && r.file_type !== 'link'
                ? '<a href="' + escH(r.file_url) + '" target="_blank" download class="res-action-btn primary" onclick="event.stopPropagation()">⬇ Download</a>'
                : '')
            + (r.file_type === 'link' && r.file_url
                ? '<a href="' + escH(r.file_url) + '" target="_blank" class="res-action-btn primary" onclick="event.stopPropagation()">Open</a>'
                : '')
            + '</div></div>';
    }).join('');
}

/* ── Detail overlay ── */
async function openDetail(id) {
    var res = allRes.find(function(r) { return r.id === id; });
    if (!res) return;
    currentRes = res;

    var overlay = document.getElementById('resDetailOverlay');
    overlay.style.display = 'block';
    overlay.scrollTop = 0;
    document.body.style.overflow = 'hidden';

    /* Track in recently viewed */
    trackRecentlyViewed(res);

    var icon = TYPE_ICONS[res.file_type] || '📎';
    var col  = TYPE_COLORS[res.file_type] || '#6b7280';
    var up   = res.profiles ? ((res.profiles.first_name || '') + ' ' + (res.profiles.last_name || '')).trim() || ('@' + (res.profiles.username || '')) : 'Unknown';

    document.getElementById('detailIcon').textContent = icon;
    document.getElementById('detailTitle').textContent = res.title || 'Untitled';

    var meta = '';
    if (res.subject) meta += '<span class="res-type-badge notes">' + escH(res.subject) + '</span> ';
    if (res.file_type) meta += '<span class="res-type-badge ' + (res.file_type || 'others') + '" style="background:' + col + '18;color:' + col + ';">' + escH(res.file_type.toUpperCase()) + '</span> ';
    meta += '<span class="dot">·</span> by ' + escH(up);
    document.getElementById('detailSubmeta').innerHTML = meta;

    /* Desc */
    var dds = document.getElementById('detailDescSection');
    if (res.description) { dds.style.display = 'block'; document.getElementById('detailDesc').textContent = res.description; }
    else { dds.style.display = 'none'; }

    /* Content */
    var dcs = document.getElementById('detailContentSection');
    if (res.content) { dcs.style.display = 'block'; document.getElementById('detailContent').textContent = res.content; }
    else { dcs.style.display = 'none'; }

    /* File */
    var dfs = document.getElementById('detailFileSection');
    if (res.file_url && res.file_type !== 'link') { dfs.style.display = 'block'; document.getElementById('detailFileBtn').href = res.file_url; }
    else { dfs.style.display = 'none'; }

    /* Link */
    var dls = document.getElementById('detailLinkSection');
    if (res.file_type === 'link' && res.file_url) { dls.style.display = 'block'; document.getElementById('detailLinkBtn').href = res.file_url; }
    else { dls.style.display = 'none'; }

    /* Sidebar info */
    document.getElementById('infoSubject').textContent  = res.subject || '—';
    document.getElementById('infoType').textContent     = res.file_type || '—';
    document.getElementById('infoUploader').textContent = up;
    document.getElementById('infoDate').textContent     = res.created_at
        ? new Date(res.created_at).toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' })
        : '—';
    if (res.view_count) {
        document.getElementById('infoViewsRow').style.display = 'flex';
        document.getElementById('infoViews').textContent = res.view_count;
    } else {
        document.getElementById('infoViewsRow').style.display = 'none';
    }

    /* Ratings + comments */
    loadDetailRatings(id);

    /* Increment view count */
    _sb.from('resources').update({ view_count: (res.view_count || 0) + 1 }).eq('id', id).then(function() {});
}

async function loadDetailRatings(resourceId) {
    var starsEl = document.getElementById('detailStarsDisplay');
    var countEl = document.getElementById('detailRatingCount');
    var commEl  = document.getElementById('detailComments');
    var badge   = document.getElementById('commentCount');

    starsEl.innerHTML = '';
    countEl.textContent = '';
    commEl.innerHTML = '<div class="res-loading-sm">Loading…</div>';

    try {
        /* Ratings avg */
        var rr = await _sb.from('resource_ratings').select('rating').eq('resource_id', resourceId);
        if (!rr.error && rr.data && rr.data.length) {
            var avg = rr.data.reduce(function(s, r) { return s + r.rating; }, 0) / rr.data.length;
            starsEl.innerHTML = [1,2,3,4,5].map(function(i) {
                return '<span class="' + (i <= Math.round(avg) ? 'res-star-filled' : 'res-star-empty') + '">★</span>';
            }).join('');
            countEl.textContent = avg.toFixed(1) + ' (' + rr.data.length + ' rating' + (rr.data.length !== 1 ? 's' : '') + ')';
        } else {
            countEl.textContent = 'No ratings yet';
        }

        /* Comments */
        var cr = await _sb
            .from('resource_comments')
            .select('*, profiles(first_name,last_name,username,profile_photo_url)')
            .eq('resource_id', resourceId)
            .order('created_at', { ascending: true });
        if (cr.error) throw cr.error;

        var comments = cr.data || [];
        badge.textContent = comments.length;

        if (!comments.length) {
            commEl.innerHTML = '<div style="padding:16px 0;color:var(--text-light);font-size:13px;">No comments yet. Sign up to be the first!</div>';
            return;
        }

        commEl.innerHTML = comments.map(function(c) {
            var p = c.profiles || {};
            var name = ((p.first_name || '') + ' ' + (p.last_name || '')).trim() || p.username || 'User';
            var initials = ((p.first_name || '?')[0] + (p.last_name || '?')[0]).toUpperCase();
            var av = p.profile_photo_url
                ? '<img src="' + escH(p.profile_photo_url) + '" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">'
                : initials;
            var stars = c.rating
                ? [1,2,3,4,5].map(function(i) { return '<span style="font-size:13px;color:' + (i <= c.rating ? '#f59e0b' : '#d1d5db') + ';">★</span>'; }).join('')
                : '';
            return '<div class="res-comment-item">'
                + '<div class="res-comment-avatar">' + av + '</div>'
                + '<div class="res-comment-body">'
                + '<span class="res-comment-name">' + escH(name) + '</span>'
                + '<span class="res-comment-time">' + timeAgo(c.created_at) + '</span>'
                + (stars ? '<div style="display:flex;gap:2px;margin:3px 0;">' + stars + '</div>' : '')
                + (c.comment ? '<div class="res-comment-text">' + escH(c.comment) + '</div>' : '')
                + '</div></div>';
        }).join('');
    } catch(e) {
        commEl.innerHTML = '<div style="color:#dc2626;font-size:13px;">Failed to load comments.</div>';
    }
}

function closeDetail() {
    document.getElementById('resDetailOverlay').style.display = 'none';
    document.body.style.overflow = '';
    currentRes = null;
}

/* ── Recently viewed (localStorage) ── */
function trackRecentlyViewed(res) {
    try {
        var rv = JSON.parse(localStorage.getItem('sh_guest_rv') || '[]');
        rv = rv.filter(function(r) { return r.id !== res.id; });
        rv.unshift({ id: res.id, title: res.title, type: res.file_type });
        rv = rv.slice(0, 5);
        localStorage.setItem('sh_guest_rv', JSON.stringify(rv));
        renderRecentlyViewed(rv);
    } catch(e) {}
}
function renderRecentlyViewed(items) {
    var el = document.getElementById('recentlyViewed');
    if (!items || !items.length) { el.innerHTML = '<div class="res-empty-small">Nothing viewed yet</div>'; return; }
    el.innerHTML = items.map(function(r) {
        return '<div class="res-recent-item" onclick="openDetail(\'' + escH(r.id) + '\')">'
            + '<div class="res-recent-icon">' + (TYPE_ICONS[r.type] || '📎') + '</div>'
            + '<div><div class="res-recent-title">' + escH(r.title || 'Untitled') + '</div>'
            + '<div class="res-recent-sub">' + escH(r.type || '') + '</div></div></div>';
    }).join('');
}
(function() {
    try {
        var rv = JSON.parse(localStorage.getItem('sh_guest_rv') || '[]');
        renderRecentlyViewed(rv);
    } catch(e) {}
})();

/* ── Toolbar helpers (mirror registered) ── */
function setSubjectFromDropdown(sel) {
    activeSubject = sel.value;
    var othersWrap = document.getElementById('othersSubjectSearch');
    othersWrap.style.display = (activeSubject === 'Others') ? 'flex' : 'none';
    othersSearch = '';
    if (document.getElementById('othersSubjectInput')) document.getElementById('othersSubjectInput').value = '';
    filterResources();
}
function clearCategory() {
    activeSubject = 'All';
    othersSearch  = '';
    document.getElementById('subjectSelect').value = 'All';
    document.getElementById('othersSubjectSearch').style.display = 'none';
    filterResources();
}
function clearSearch() {
    document.getElementById('searchInput').value = '';
    filterResources();
}
function clearOthersSearch() {
    othersSearch = '';
    if (document.getElementById('othersSubjectInput')) document.getElementById('othersSubjectInput').value = '';
    filterResources();
}

/* ── Gate modal ── */
var MODALS = {
    upload:  { icon:'⬆️', title:'Upload resources',   body:'Create a free account to share your notes, slides, and study materials.' },
    save:    { icon:'🔖', title:'Save resources',      body:'Sign up or log in to bookmark and save resources for later.' },
    comment: { icon:'💬', title:'Add a comment',       body:'Sign up or log in to leave feedback on resources.' },
    private: { icon:'🔒', title:'Friends Only',        body:'Log in to see resources shared with friends.' },
};
function showModal(type) {
    var d = MODALS[type] || { icon:'🔒', title:'Join to continue', body:'Create a free StudyHub account.' };
    document.getElementById('rmIcon').textContent  = d.icon;
    document.getElementById('rmTitle').textContent = d.title;
    document.getElementById('rmBody').textContent  = d.body;
    document.getElementById('resModal').classList.add('open');
}
function closeModal() { document.getElementById('resModal').classList.remove('open'); }
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        if (document.getElementById('resDetailOverlay').style.display !== 'none') closeDetail();
    }
});

/* ── Helpers ── */
function timeAgo(ts) {
    var s = Math.floor((Date.now() - new Date(ts)) / 1000);
    if (s < 60)     return 'Just now';
    if (s < 3600)   return Math.floor(s / 60) + 'm ago';
    if (s < 86400)  return Math.floor(s / 3600) + 'h ago';
    if (s < 604800) return Math.floor(s / 86400) + 'd ago';
    return new Date(ts).toLocaleDateString();
}
function escH(t) {
    if (t == null) return '';
    if (typeof t !== 'string') t = String(t);
    var d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

loadRes();
</script>
</body>
</html>
