/* ============================================================
   public/js/resources.js  — StudyHub Resources Page
   All data calls go through Laravel API routes — no Supabase
   client needed here.
   ============================================================ */

// ── API HELPER ───────────────────────────────────────────────
async function _api(method, path, body = null) {
    const opts = {
        method,
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    };
    // CSRF token for mutating requests
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf) opts.headers['X-CSRF-TOKEN'] = csrf;
    if (body && !(body instanceof FormData)) opts.body = JSON.stringify(body);
    if (body instanceof FormData) { delete opts.headers['Content-Type']; opts.body = body; }
    const res = await fetch(path, opts);
    if (!res.ok) {
        const err = await res.json().catch(() => ({ error: res.statusText }));
        throw new Error(err.error || `HTTP ${res.status}`);
    }
    return res.json();
}

// ── CATEGORIES ──────────────────────────────────────────────
const ALL_CATEGORIES = [
    'All','Mathematics','Science','Filipino','English','PE',
    'Health','Music','Arts','Social Studies','Computer Science',
    'Values Education','MAPEH','History','Chemistry','Physics',
    'Biology','Economics','Others'
];

// ── STATE ────────────────────────────────────────────────────
let allResources      = [];
let activeCategory    = 'All';
let activeVisFilter   = 'public';
let searchQuery       = '';
let catSearchQuery    = '';
let selectedFiles     = [];
let currentStep       = 1;
let recentlyViewed    = [];
let currentResource   = null;
let currentRating     = 0;
let editingCommentId  = null;

// Bookmark state
let bookmarkedIds     = new Set();   // Set of bookmarked resource IDs (for current user)
let showingBookmarks  = false;       // true when bookmark-filter view is active

// Edit modal file state
let editNewFiles      = [];
let editExistingFiles = [];
let editFilesToDelete = [];

const _resourceMap = {};

try { recentlyViewed = JSON.parse(localStorage.getItem('sh_recent_resources') || '[]'); } catch(e){}
// Persist bookmarks locally for instant UI (truth comes from DB on load)
try {
    const saved = JSON.parse(localStorage.getItem('sh_bookmarks') || '[]');
    bookmarkedIds = new Set(saved);
} catch(e){}

// ── INIT ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadResources();
    renderRecentlyViewed();
    loadMyUploads();
    loadTopRatedWidget();
    if (CURRENT_USER.id) syncBookmarksFromDB();
});

// ─────────────────────────────────────────────────────────────
// SUBJECT DROPDOWN  (replaces the old category-pills row)
// ─────────────────────────────────────────────────────────────

/** Called by the subject <select> in the toolbar */
function setSubjectFromDropdown(selectEl) {
    const cat = selectEl.value || 'All';
    activeCategory = cat;
    const af = document.getElementById('activeFilters');
    const at = document.getElementById('activeFilterTag');
    if (cat !== 'All') { af.style.display = 'flex'; at.textContent = '\u{1F4DA} ' + cat; }
    else { af.style.display = 'none'; }
    loadResources();
}

/** Legacy helper so any existing onclick="setCategory(...)" calls still work */
function setCategory(cat) {
    activeCategory = cat;
    const sel = document.getElementById('subjectSelect');
    if (sel) sel.value = cat;
    const af = document.getElementById('activeFilters');
    const at = document.getElementById('activeFilterTag');
    if (cat !== 'All') { af.style.display = 'flex'; at.textContent = '\u{1F4DA} ' + cat; }
    else { af.style.display = 'none'; }
    loadResources();
}

function clearCategory() {
    activeCategory = 'All';
    const sel = document.getElementById('subjectSelect');
    if (sel) sel.value = 'All';
    document.getElementById('activeFilters').style.display = 'none';
    loadResources();
}

// ─────────────────────────────────────────────────────────────
// FILTERS & SEARCH
// ─────────────────────────────────────────────────────────────
function setVisibility(vis, btn) {
    activeVisFilter = vis;
    showingBookmarks = false;
    document.getElementById('filterPublic').classList.toggle('active',  vis === 'public');
    document.getElementById('filterPrivate').classList.toggle('active', vis === 'private');
    document.getElementById('filterBookmarks')?.classList.remove('bookmark-active');
    loadResources();
}

function filterResources() {
    searchQuery = document.getElementById('searchInput').value;
    document.getElementById('searchClear').style.display = searchQuery ? 'block' : 'none';
    loadResources();
}
function clearSearch() {
    document.getElementById('searchInput').value = '';
    searchQuery = '';
    document.getElementById('searchClear').style.display = 'none';
    loadResources();
}

// ─────────────────────────────────────────────────────────────
// BOOKMARKS — sync & toggle view
// ─────────────────────────────────────────────────────────────

/** Pull all bookmarked IDs from the DB once on page load */
async function syncBookmarksFromDB() {
    try {
        const json = await _api('GET', '/api/resources/bookmarks?limit=40');
        if (json?.data) {
            bookmarkedIds = new Set(json.data.map(r => String(r.id)));
            _persistBookmarks();
        }
    } catch(e) {}
}

function _persistBookmarks() {
    try { localStorage.setItem('sh_bookmarks', JSON.stringify([...bookmarkedIds])); } catch(e){}
    const savedEl = document.getElementById('savedCount');
    if (savedEl) savedEl.textContent = bookmarkedIds.size;
}

/** Toggle the bookmarks-only feed view */
function toggleBookmarkFilter() {
    if (!CURRENT_USER.id) { alert('Please log in to view your bookmarks.'); return; }
    showingBookmarks = !showingBookmarks;
    const btn = document.getElementById('filterBookmarks');
    if (btn) btn.classList.toggle('bookmark-active', showingBookmarks);
    // Deactivate vis filters when in bookmark mode
    document.getElementById('filterPublic').classList.toggle('active', !showingBookmarks);
    document.getElementById('filterPrivate').classList.remove('active');
    renderResources();
}

/**
 * Toggle bookmark on a resource. Optimistic UI update.
 * @param {string} resourceId
 * @param {Event}  event  - to stop card click propagation
 */
async function toggleBookmark(resourceId, event) {
    if (event) event.stopPropagation();
    if (!CURRENT_USER.id) { alert('Please log in to bookmark resources.'); return; }

    const wasBookmarked = bookmarkedIds.has(resourceId);

    // Optimistic update
    if (wasBookmarked) {
        bookmarkedIds.delete(resourceId);
    } else {
        bookmarkedIds.add(resourceId);
    }
    _persistBookmarks();

    // Update all bookmark buttons currently shown for this resource
    _refreshBookmarkButtons(resourceId, !wasBookmarked);

    // Persist to DB
    try {
        const json = await _api('POST', `/api/resources/${resourceId}/bookmark`);
        // Server is the source of truth — sync if it disagrees
        if (typeof json.bookmarked === 'boolean' && json.bookmarked !== !wasBookmarked) {
            if (json.bookmarked) { bookmarkedIds.add(resourceId); } else { bookmarkedIds.delete(resourceId); }
            _persistBookmarks();
            _refreshBookmarkButtons(resourceId, json.bookmarked);
        }
    } catch(e) {
        // Rollback optimistic update on failure
        if (wasBookmarked) { bookmarkedIds.add(resourceId); } else { bookmarkedIds.delete(resourceId); }
        _persistBookmarks();
        _refreshBookmarkButtons(resourceId, wasBookmarked);
        console.error('Bookmark toggle failed:', e.message);
    }

    // If we're currently showing the bookmark-only feed, re-render to hide/show the card
    if (showingBookmarks) renderResources();

    // Update sidebar bookmark list
    loadBookmarksSidebar();
}

function _refreshBookmarkButtons(resourceId, isNowBookmarked) {
    // Update card-level bookmark buttons
    document.querySelectorAll(`.res-bookmark-btn[data-id="${resourceId}"]`).forEach(btn => {
        btn.classList.toggle('bookmarked', isNowBookmarked);
        btn.title = isNowBookmarked ? 'Remove bookmark' : 'Bookmark this resource';
        btn.innerHTML = _bookmarkSVG(isNowBookmarked);
    });
    // Update detail-page bookmark button
    const detailBtn = document.getElementById('detailBookmarkBtn');
    if (detailBtn && currentResource?.id === resourceId) {
        detailBtn.classList.toggle('bookmarked', isNowBookmarked);
        detailBtn.innerHTML = `${_bookmarkSVG(isNowBookmarked)} ${isNowBookmarked ? 'Saved' : 'Save'}`;
    }
    // Update card border
    document.querySelectorAll(`.res-card[data-id="${resourceId}"]`).forEach(card => {
        card.classList.toggle('is-bookmarked', isNowBookmarked);
    });
}

function _bookmarkSVG(filled) {
    if (filled) {
        return `<svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2">
            <path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>
        </svg>`;
    }
    return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>
    </svg>`;
}

/** Render the sidebar bookmarks list */
async function loadBookmarksSidebar() {
    const el = document.getElementById('bookmarksSidebar');
    if (!el || !CURRENT_USER.id) return;

    if (bookmarkedIds.size === 0) {
        el.innerHTML = '<div class="res-empty-small">No saved resources yet</div>';
        return;
    }

    // Render from local map first for instant feedback
    const localRows = [...bookmarkedIds].slice(0, 5)
        .map(id => _resourceMap[id]).filter(Boolean);

    const renderRows = (rows) => {
        el.innerHTML = rows.map(r => `
            <div class="res-recent-item" onclick="openDetailById('${escH(r.id)}')">
                <div class="res-recent-icon">${fileTypeIcon(r.file_type||'other', r.file_url||null)}</div>
                <div style="min-width:0;">
                    <div class="res-recent-title">${escH(r.title)}</div>
                    <div class="res-recent-sub">${escH(r.subject||'General')} · ${fileTypeLabel(r.file_type||'other')}</div>
                </div>
            </div>`).join('');
    };

    if (localRows.length) { renderRows(localRows); return; }

    try {
        const json = await _api('GET', '/api/resources/bookmarks?limit=5');
        if (json?.data?.length) {
            json.data.forEach(r => { _resourceMap[r.id] = r; });
            renderRows(json.data);
        } else {
            el.innerHTML = '<div class="res-empty-small">No saved resources yet</div>';
        }
    } catch(e) {}
}

// ─────────────────────────────────────────────────────────────
// LOAD & RENDER FEED
// ─────────────────────────────────────────────────────────────
async function loadResources() {
    const feed = document.getElementById('resourceFeed');
    feed.innerHTML = '<div class="loading-state">Loading resources…</div>';
    try {
        const params = new URLSearchParams({ visibility: activeVisFilter, limit: 100 });
        if (activeCategory !== 'All') params.set('subject', activeCategory);
        if (searchQuery) params.set('search', searchQuery);

        const json = await _api('GET', `/api/resources?${params}`);
        allResources = json.data || [];
        allResources.forEach(r => { _resourceMap[r.id] = r; });

        // Sync bookmark state from server response
        allResources.forEach(r => {
            if (r.is_bookmarked) { bookmarkedIds.add(String(r.id)); }
        });
        _persistBookmarks();

        renderResources();
        updateStats();
        loadBookmarksSidebar();
    } catch(err) {
        feed.innerHTML = `<div class="alert-error">❌ Failed to load: ${escH(err.message)}</div>`;
    }
}

function renderResources() {
    const feed = document.getElementById('resourceFeed');

    // ── Bookmark-only mode ────────────────────────────────────
    if (showingBookmarks) {
        const bkList = allResources.filter(r => bookmarkedIds.has(r.id));
        if (!bkList.length) {
            feed.innerHTML = `
                <div class="res-bookmarks-banner">
                    <span class="res-bookmarks-banner-text">🔖 Your saved resources</span>
                    <button class="res-bookmarks-banner-close" onclick="toggleBookmarkFilter()">✕ Exit</button>
                </div>
                <div class="res-empty">
                    <div class="ei">🔖</div>
                    <p>You haven't saved any resources yet.<br>Click the bookmark icon on any resource to save it here.</p>
                </div>`;
            return;
        }
        feed.innerHTML = `
            <div class="res-bookmarks-banner">
                <span class="res-bookmarks-banner-text">🔖 ${bkList.length} saved resource${bkList.length !== 1 ? 's' : ''}</span>
                <button class="res-bookmarks-banner-close" onclick="toggleBookmarkFilter()">✕ Exit</button>
            </div>
            ${bkList.map(r => cardHTML(r)).join('')}`;
        return;
    }

    // ── Normal feed ───────────────────────────────────────────
    const filtered = allResources.filter(r => {
        const effectiveVis = r.visibility === 'private' || r.education_level === 'private'
            ? 'private' : 'public';
        if (activeVisFilter === 'public'  && effectiveVis !== 'public')  return false;
        if (activeVisFilter === 'private' && effectiveVis !== 'private') return false;
        if (activeCategory !== 'All' && r.subject !== activeCategory)    return false;
        const q = searchQuery.toLowerCase();
        if (q) {
            const hit = [r.title, r.description, r.subject, ...(r.tags||[])]
                .some(v => (v||'').toLowerCase().includes(q));
            if (!hit) return false;
        }
        return true;
    });

    if (!filtered.length) {
        feed.innerHTML = `<div class="res-empty"><div class="ei">🔍</div>
            <p>${searchQuery
                ? `No results for "<strong>${escH(searchQuery)}</strong>"`
                : 'No resources found in this category yet.'}</p></div>`;
        return;
    }

    const groups = {};
    filtered.forEach(r => { const s = r.subject||'Other'; if(!groups[s])groups[s]=[]; groups[s].push(r); });

    const EMOJIS = {
        'Mathematics':'📐','Science':'🔬','Filipino':'🇵🇭',
        'English':'📖','PE':'⚽','Health':'❤️‍🩹',
        'Music':'🎵','Arts':'🎨','Social Studies':'🌍',
        'Computer Science':'💻','Values Education':'🌟','MAPEH':'🎭',
        'History':'🏛️','Chemistry':'⚗️','Physics':'⚛️',
        'Biology':'🧬','Economics':'📈','Others':'📚'
    };

    feed.innerHTML = Object.entries(groups).map(([subj, items]) => `
        <div class="res-group">
            <div class="res-group-header">
                <div class="res-group-title">
                    ${EMOJIS[subj]||'📚'} ${escH(subj)}
                    <span class="res-group-count">${items.length}</span>
                </div>
            </div>
            ${items.map(r => cardHTML(r)).join('')}
        </div>`).join('');
}

function cardHTML(r) {
    const fileType   = (r.file_type||'other').toLowerCase();
    const icon       = fileTypeIcon(fileType, r.file_url);
    const iconCls    = fileIconClass(fileType, r.file_url);
    const label      = r.tags?.length ? r.tags[0] : fileTypeLabel(fileType);
    const uploader   = r.profiles
        ? `${r.profiles.first_name||''} ${r.profiles.last_name||''}`.trim() || r.profiles.username
        : 'Unknown';
    const ago        = timeAgo(r.created_at);
    const desc       = r.description ? escH(r.description.slice(0,90))+(r.description.length>90?'…':'') : '';
    const effectiveVis = r.visibility === 'private' || r.education_level === 'private' ? 'private' : 'public';
    const isOwner    = r.uploaded_by === CURRENT_USER.id;
    const isBookmarked = bookmarkedIds.has(r.id);

    let actionBtn = '';
    if (fileType === 'link' && r.file_url) {
        actionBtn = `<a href="${escH(r.file_url)}" target="_blank" class="res-action-btn primary"
                        onclick="event.stopPropagation()">Open</a>`;
    } else if (r.file_url) {
        actionBtn = `<a href="${escH(r.file_url)}" target="_blank" download class="res-action-btn primary"
                        onclick="event.stopPropagation()">Download</a>`;
    } else if (r.content) {
        actionBtn = `<span class="res-action-btn" style="background:rgba(26,95,122,0.08);color:var(--primary);cursor:default;">✍️ Text</span>`;
    } else {
        actionBtn = `<span class="res-action-btn" style="opacity:.4;cursor:default;">No file</span>`;
    }

    const ownerBtn = isOwner
        ? `<button class="res-card-delete-btn" title="Delete"
               onclick="event.stopPropagation(); deleteResource('${r.id}', this)">🗑</button>`
        : '';

    _resourceMap[r.id] = r;

    return `
        <div class="res-card ${isBookmarked ? 'is-bookmarked' : ''}" data-id="${r.id}" onclick="openDetailById('${r.id}')">
            <div class="res-card-icon ${iconCls}">${icon}</div>
            <div class="res-card-body">
                <div class="res-card-title">${escH(r.title)}</div>
                ${desc ? `<div class="res-card-desc">${desc}</div>` : ''}
                <div class="res-card-meta">
                    <span>${escH(uploader)}</span>
                    <span class="dot">·</span>
                    <span>${ago}</span>
                    <span class="dot">·</span>
                    <span class="res-vis-badge ${effectiveVis}">${effectiveVis==='private'?'🔒 Friends':'🌐 Public'}</span>
                    ${isOwner && !r.is_approved ? '<span class="res-vis-badge" style="background:#fef9c3;color:#854d0e;">⏳ Pending</span>' : ''}
                </div>
            </div>
            <div class="res-card-actions">
                ${ownerBtn}
                <button class="res-bookmark-btn ${isBookmarked ? 'bookmarked' : ''}"
                    data-id="${r.id}"
                    title="${isBookmarked ? 'Remove bookmark' : 'Bookmark this resource'}"
                    onclick="toggleBookmark('${r.id}', event)">
                    ${_bookmarkSVG(isBookmarked)}
                </button>
                <span class="res-type-badge ${fileType}">${escH(label)}</span>
                ${actionBtn}
            </div>
        </div>`;
}

// ─────────────────────────────────────────────────────────────
// RECENTLY VIEWED
// ─────────────────────────────────────────────────────────────
function renderRecentlyViewed() {
    const el = document.getElementById('recentlyViewed');
    if (!recentlyViewed.length) {
        el.innerHTML = '<div class="res-empty-small">Nothing viewed yet</div>'; return;
    }
    el.innerHTML = recentlyViewed.map(r => `
        <div class="res-recent-item" onclick="openDetailById('${escH(r.id)}')">
            <div class="res-recent-icon">${fileTypeIcon(r.file_type||'other', r.file_url)}</div>
            <div style="min-width:0;">
                <div class="res-recent-title">${escH(r.title)}</div>
                <div class="res-recent-sub">${escH(r.subject||'General')} · ${fileTypeLabel(r.file_type||'other')}</div>
            </div>
        </div>`).join('');
}

// ─────────────────────────────────────────────────────────────
// MY UPLOADS
// ─────────────────────────────────────────────────────────────
async function loadMyUploads() {
    if (!CURRENT_USER.id) return;
    const el = document.getElementById('myUploads');
    try {
        const json = await _api('GET', '/api/resources/my-uploads');
        if (!json?.data?.length) return;
        json.data.forEach(r => { if (!_resourceMap[r.id]) _resourceMap[r.id] = r; });
        el.innerHTML = json.data.map(r => `
            <div class="res-recent-item" onclick="openDetailById('${escH(r.id)}')">
                <div class="res-recent-icon">${fileTypeIcon(r.file_type||'other', null)}</div>
                <div style="min-width:0;">
                    <div class="res-recent-title">${escH(r.title)}</div>
                    <div class="res-recent-sub">${escH(r.subject||'General')}</div>
                </div>
            </div>`).join('');
    } catch(e){}
}

// ─────────────────────────────────────────────────────────────
// COMMUNITY TOP-RATED WIDGET
// ─────────────────────────────────────────────────────────────
async function loadTopRatedWidget() {
    const el = document.getElementById('topRatedWidget');
    if (!el) return;

    try {
        const json = await _api('GET', '/api/resources/top-rated?limit=5');
        const items = json?.data || [];

        if (!items.length) {
            el.innerHTML = '<div class="res-empty-small">No ratings yet</div>';
            return;
        }

        const rankEmoji = ['🥇','🥈','🥉'];
        const rankClass = ['rank-1','rank-2','rank-3'];

        el.innerHTML = items.map((r, i) => {
            const rankLabel = i < 3 ? rankEmoji[i] : `#${i+1}`;
            const rc        = i < 3 ? rankClass[i] : 'rank-n';
            const stars     = _miniStars(r.avg_rating);
            _resourceMap[r.id] = r;
            return `
                <div class="res-top-rated-item" onclick="openDetailById('${escH(r.id)}')">
                    <div class="res-top-rated-rank ${rc}">${rankLabel}</div>
                    <div class="res-recent-icon" style="font-size:16px;width:32px;height:32px;">
                        ${fileTypeIcon(r.file_type||'other', r.file_url)}
                    </div>
                    <div style="min-width:0;flex:1;">
                        <div class="res-recent-title">${escH(r.title)}</div>
                        <div class="res-top-rated-stars">
                            ${stars}
                            <span class="res-top-rated-score">${Number(r.avg_rating).toFixed(1)}</span>
                            <span class="res-top-rated-count">(${r.rating_count})</span>
                        </div>
                    </div>
                </div>`;
        }).join('');

        el.innerHTML += `<button class="res-see-top-rated" onclick="openTopRatedModal()">
            ⭐ See all top-rated resources
        </button>`;

    } catch(e) {
        el.innerHTML = '<div class="res-empty-small">Could not load ratings</div>';
    }
}

function _miniStars(avg) {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        html += `<span class="${i <= Math.round(avg) ? 'res-top-rated-star-filled' : 'res-top-rated-star-empty'}">★</span>`;
    }
    return html;
}

// ─────────────────────────────────────────────────────────────
// COMMUNITY TOP-RATED MODAL
// ─────────────────────────────────────────────────────────────
async function openTopRatedModal() {
    // Show overlay
    let overlay = document.getElementById('topRatedModalOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'topRatedModalOverlay';
        overlay.className = 'res-top-rated-overlay';
        overlay.innerHTML = `
            <div class="res-top-rated-modal">
                <div class="res-top-rated-modal-header">
                    <div class="res-top-rated-modal-title">⭐ Community Recommended</div>
                    <button class="res-top-rated-modal-close" onclick="closeTopRatedModal()">✕</button>
                </div>
                <div class="res-top-rated-modal-body" id="topRatedModalBody">
                    <div class="res-loading-sm">Loading top-rated resources…</div>
                </div>
            </div>`;
        document.body.appendChild(overlay);
        overlay.addEventListener('click', e => { if (e.target === overlay) closeTopRatedModal(); });
    }
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    await _populateTopRatedModal();
}

function closeTopRatedModal() {
    const overlay = document.getElementById('topRatedModalOverlay');
    if (overlay) overlay.style.display = 'none';
    document.body.style.overflow = '';
}

async function _populateTopRatedModal() {
    const body = document.getElementById('topRatedModalBody');
    if (!body) return;

    try {
        const json = await _api('GET', '/api/resources/top-rated?limit=20&min_ratings=1');
        const items = json?.data || [];

        if (!items.length) {
            body.innerHTML = '<div class="res-empty"><div class="ei">⭐</div><p>No ratings yet. Be the first to rate a resource!</p></div>';
            return;
        }

        const rankEmoji  = ['🥇','🥈','🥉'];
        const rankClass  = ['rank-1','rank-2','rank-3'];

        body.innerHTML = items.map((r, i) => {
            _resourceMap[r.id] = r;
            const rankLabel = i < 3 ? rankEmoji[i] : `#${i+1}`;
            const rc        = i < 3 ? rankClass[i] : 'rank-n';
            const isBookmarked = bookmarkedIds.has(String(r.id));
            const desc = r.description
                ? escH(r.description.slice(0, 80)) + (r.description.length > 80 ? '…' : '')
                : '';

            let starsHtml = '';
            for (let s = 1; s <= 5; s++) {
                starsHtml += `<span class="${s <= Math.round(r.avg_rating) ? 's-filled' : 's-empty'}">★</span>`;
            }

            return `
                <div class="res-top-rated-card" onclick="closeTopRatedModal(); openDetailById('${escH(r.id)}')">
                    <div class="res-top-rated-card-rank ${rc}">${rankLabel}</div>
                    <div class="res-top-rated-card-icon">${fileTypeIcon(r.file_type||'other', r.file_url)}</div>
                    <div class="res-top-rated-card-body">
                        <div class="res-top-rated-card-title">${escH(r.title)}</div>
                        ${desc ? `<div class="res-top-rated-card-desc">${desc}</div>` : ''}
                        <div class="res-top-rated-card-meta">
                            <div class="res-top-rated-card-stars">${starsHtml}</div>
                            <span class="res-top-rated-card-score">${Number(r.avg_rating).toFixed(1)}</span>
                            <span class="res-top-rated-card-count">(${r.rating_count} rating${r.rating_count!==1?'s':''})</span>
                            ${r.subject ? `<span class="res-top-rated-card-subj">${escH(r.subject)}</span>` : ''}
                        </div>
                        ${r.uploader_name ? `<div style="font-size:11px;color:var(--text-light);margin-top:3px;">by ${escH(r.uploader_name)}</div>` : ''}
                    </div>
                    <div class="res-top-rated-card-actions" onclick="event.stopPropagation()">
                        <button class="res-bookmark-btn ${isBookmarked ? 'bookmarked' : ''}"
                            data-id="${r.id}"
                            title="${isBookmarked ? 'Remove bookmark' : 'Bookmark'}"
                            onclick="toggleBookmark('${r.id}', event)">
                            ${_bookmarkSVG(isBookmarked)}
                        </button>
                    </div>
                </div>`;
        }).join('');

        if (!body.innerHTML.trim()) {
            body.innerHTML = '<div class="res-empty"><div class="ei">⭐</div><p>No approved resources with ratings found.</p></div>';
        }

    } catch(e) {
        body.innerHTML = `<div class="res-empty"><div class="ei">⚠️</div><p>Could not load top-rated resources.</p></div>`;
        console.error('Top-rated modal error:', e);
    }
}

// ─────────────────────────────────────────────────────────────
// STATS
// ─────────────────────────────────────────────────────────────
function updateStats() {
    document.getElementById('totalCount').textContent   = allResources.length;
    document.getElementById('subjectCount').textContent =
        [...new Set(allResources.map(r => r.subject).filter(Boolean))].length;
    const savedEl = document.getElementById('savedCount');
    if (savedEl) savedEl.textContent = bookmarkedIds.size;
}

// ─────────────────────────────────────────────────────────────
// UPLOAD MODAL  (3-step)
// ─────────────────────────────────────────────────────────────
function openUploadModal()  { document.getElementById('uploadModal').classList.add('open'); goStep(1); }
function closeUploadModal() { document.getElementById('uploadModal').classList.remove('open'); resetUploadForm(); }

function goStep(n) {
    if (n > currentStep) {
        if (currentStep === 1) {
            const hasFiles   = selectedFiles.length > 0;
            const hasLink    = document.getElementById('uploadLink').value.trim();
            const contentEl  = document.querySelector('#usec1 #uploadContent') ||
                               document.getElementById('uploadContent');
            const hasContent = contentEl ? contentEl.value.trim() : false;
            if (!hasFiles && !hasLink && !hasContent) {
                alert('Please attach a file, paste a link, or write some text content.');
                return;
            }
        }
        if (currentStep === 2) {
            const title     = document.getElementById('uploadTitle').value.trim();
            const subject   = document.getElementById('uploadSubject').value;
            const type      = document.getElementById('uploadType').value;
            const subjOther = document.getElementById('uploadSubjectOther').value.trim();
            const typeOther = document.getElementById('uploadTypeOther').value.trim();
            let ok = true;
            ['errTitle','errSubject','errType'].forEach(id => { document.getElementById(id).textContent = ''; });
            if (!title)                            { document.getElementById('errTitle').textContent   = 'Title is required.'; ok = false; }
            if (!subject)                          { document.getElementById('errSubject').textContent = 'Please select a subject.'; ok = false; }
            if (subject === 'others' && !subjOther){ document.getElementById('errSubject').textContent = 'Please specify the subject.'; ok = false; }
            if (!type)                             { document.getElementById('errType').textContent    = 'Please select a type.'; ok = false; }
            if (type === 'others' && !typeOther)   { document.getElementById('errType').textContent   = 'Please specify the material type.'; ok = false; }
            if (!ok) return;
            buildSummary();
        }
    }
    currentStep = n;
    document.querySelectorAll('.upload-section').forEach((s, i) => s.classList.toggle('active', i + 1 === n));
    document.querySelectorAll('.upload-step').forEach((s, i) => {
        s.classList.remove('active', 'done');
        if (i + 1 === n) s.classList.add('active');
        if (i + 1 <  n) s.classList.add('done');
    });
}

function buildSummary() {
    const title     = document.getElementById('uploadTitle').value.trim();
    const subject   = document.getElementById('uploadSubject').value;
    const subjOther = document.getElementById('uploadSubjectOther').value.trim();
    const type      = document.getElementById('uploadType').value;
    const typeOther = document.getElementById('uploadTypeOther').value.trim();
    const desc      = document.getElementById('uploadDesc').value.trim();
    const contentEl = document.querySelector('#usec2 #uploadContent') || document.getElementById('uploadContent');
    const content   = contentEl ? contentEl.value.trim() : '';
    const finalSubj = subject === 'others' ? subjOther : subject;
    const finalType = type    === 'others' ? typeOther : fileTypeLabel(type);
    document.getElementById('uploadSummary').innerHTML = `
        <strong>Title:</strong> ${escH(title)}<br>
        <strong>Subject:</strong> ${escH(finalSubj)}<br>
        <strong>Type:</strong> ${escH(finalType)}<br>
        ${desc    ? `<strong>Description:</strong> ${escH(desc.slice(0,60))}${desc.length>60?'…':''}<br>` : ''}
        ${content ? `<strong>Content:</strong> ✍️ Text included<br>` : ''}
        <strong>Files:</strong> ${selectedFiles.length ? selectedFiles.map(f=>escH(f.name)).join(', ') : 'None'}
    `;
}

function handleFiles(fileList) {
    Array.from(fileList).forEach(f => {
        if (!selectedFiles.find(x => x.name === f.name && x.size === f.size)) selectedFiles.push(f);
    });
    renderFilePreviews();
}
function renderFilePreviews() {
    document.getElementById('filePreviewList').innerHTML = selectedFiles.map((f, i) => `
        <div class="file-preview-item">
            <span class="file-preview-icon">${fileEmojiFromName(f.name)}</span>
            <div class="file-preview-info">
                <div class="file-preview-name">${escH(f.name)}</div>
                <div class="file-preview-size">${formatBytes(f.size)}</div>
            </div>
            <button class="file-preview-remove" onclick="removeFile(${i})">✕</button>
        </div>`).join('');
}
function removeFile(i) { selectedFiles.splice(i, 1); renderFilePreviews(); }
function dragOver(e)  { e.preventDefault(); document.getElementById('dropzone').classList.add('dragover'); }
function dragLeave(e) { document.getElementById('dropzone').classList.remove('dragover'); }
function dropFiles(e) { e.preventDefault(); document.getElementById('dropzone').classList.remove('dragover'); handleFiles(e.dataTransfer.files); }

function checkOtherSubject() {
    document.getElementById('uploadSubjectOther').style.display =
        document.getElementById('uploadSubject').value === 'others' ? 'block' : 'none';
}
function checkOtherType() {
    document.getElementById('uploadTypeOther').style.display =
        document.getElementById('uploadType').value === 'others' ? 'block' : 'none';
}
function setVis(v) {
    document.getElementById('visPublic').style.borderColor  = v === 'public'  ? 'var(--primary)' : '';
    document.getElementById('visPrivate').style.borderColor = v === 'private' ? 'var(--primary)' : '';
}

async function submitUpload() {
    const btn = document.getElementById('submitUploadBtn');
    btn.disabled = true; btn.textContent = 'Uploading…';
    try {
        const title     = document.getElementById('uploadTitle').value.trim();
        const desc      = document.getElementById('uploadDesc').value.trim();
        const contentEl = document.querySelector('#usec2 #uploadContent') || document.getElementById('uploadContent');
        const content   = contentEl ? contentEl.value.trim() : '';
        const subject   = document.getElementById('uploadSubject').value;
        const subjOther = document.getElementById('uploadSubjectOther').value.trim();
        const type      = document.getElementById('uploadType').value;
        const typeOther = document.getElementById('uploadTypeOther').value.trim();
        const linkEl    = document.getElementById('uploadLink');
        const linkVal   = linkEl ? linkEl.value.trim() : '';
        const vis       = document.querySelector('input[name="visibility"]:checked').value;
        const finalSubj = subject === 'others' ? subjOther : subject;
        const finalType = type    === 'others' ? 'others'  : type;
        const tags      = typeOther ? [typeOther] : [];

        const base = {
            uploaded_by: CURRENT_USER.id, title,
            description: desc    || null,
            content:     content || null,
            subject:     finalSubj,
            file_type:   finalType,
            education_level: vis, visibility: vis,
            is_approved: false, tags
        };

        if (!selectedFiles.length && !linkVal) {
            const { error } = await _supabase.from('resources').insert({ ...base, file_url: null });
            if (error) throw error;
        } else if (!selectedFiles.length && linkVal) {
            const { error } = await _supabase.from('resources').insert({
                ...base, file_url: linkVal, file_type: finalType || 'link'
            });
            if (error) throw error;
        } else {
            let primaryUrl = null;
            const fileRows = [];
            for (const file of selectedFiles) {
                const ext  = file.name.split('.').pop();
                const path = `${CURRENT_USER.id}/${Date.now()}_${Math.random().toString(36).slice(2)}.${ext}`;
                const { error: upErr } = await _supabase.storage
                    .from('resources').upload(path, file, { upsert: true });
                if (upErr) throw upErr;
                const { data: urlData } = _supabase.storage.from('resources').getPublicUrl(path);
                if (!primaryUrl) primaryUrl = urlData.publicUrl;
                fileRows.push({ file_name: file.name, file_url: urlData.publicUrl,
                                file_size: file.size, storage_path: path });
            }
            const { data: inserted, error: insErr } = await _supabase.from('resources')
                .insert({ ...base, file_url: primaryUrl,
                          original_filename: selectedFiles[0].name,
                          file_size: selectedFiles[0].size })
                .select('id').single();
            if (insErr) throw insErr;

            if (fileRows.length) {
                const { error: rfErr } = await _supabase.from('resource_files').insert(
                    fileRows.map(f => ({ ...f, resource_id: inserted.id, uploaded_by: CURRENT_USER.id }))
                );
                if (rfErr) console.warn('resource_files insert:', rfErr.message);
            }
        }

        closeUploadModal();
        alert('✅ Upload submitted! It will appear after admin approval.');
        loadResources(); loadMyUploads();
    } catch(err) {
        alert('Upload failed: ' + err.message);
    } finally {
        btn.disabled = false; btn.textContent = '📤 Upload';
    }
}

function resetUploadForm() {
    selectedFiles = []; currentStep = 1;
    const fpl = document.getElementById('filePreviewList'); if (fpl) fpl.innerHTML = '';
    ['uploadLink','uploadTitle','uploadDesc','uploadSubjectOther','uploadTypeOther']
        .forEach(id => { const el = document.getElementById(id); if(el) el.value=''; });
    document.querySelectorAll('#uploadContent').forEach(el => { el.value = ''; });
    const subj = document.getElementById('uploadSubject'); if(subj) subj.value='';
    const type = document.getElementById('uploadType');    if(type) type.value='';
    ['uploadSubjectOther','uploadTypeOther'].forEach(id => {
        const el = document.getElementById(id); if(el) el.style.display = 'none';
    });
    ['errTitle','errSubject','errType'].forEach(id => {
        const el = document.getElementById(id); if(el) el.textContent='';
    });
    const pubRadio = document.querySelector('input[name="visibility"][value="public"]');
    if (pubRadio) pubRadio.checked = true;
}

// ─────────────────────────────────────────────────────────────
// DETAIL PAGE
// ─────────────────────────────────────────────────────────────
async function openDetailById(id) {
    let r = _resourceMap[id];
    if (!r) {
        try {
            const { data, error } = await _supabase
                .from('resources')
                .select('*, profiles(first_name, last_name, username)')
                .eq('id', id).maybeSingle();
            if (error || !data) { console.warn('Resource not found:', id); return; }
            _resourceMap[data.id] = data;
            r = data;
        } catch(e) { console.error(e); return; }
    }
    openDetail(r);
}

function openDetail(r) {
    if (!r) return;
    if (typeof r === 'string') { try { r = JSON.parse(r); } catch(e){ return; } }

    currentResource  = r;
    currentRating    = 0;
    editingCommentId = null;
    _resourceMap[r.id] = r;

    recentlyViewed = [r, ...recentlyViewed.filter(x => x.id !== r.id)].slice(0, 5);
    try { localStorage.setItem('sh_recent_resources', JSON.stringify(recentlyViewed)); } catch(e){}
    renderRecentlyViewed();

    const oldList = document.getElementById('detailFileList');
    if (oldList) oldList.remove();
    document.getElementById('detailFileBtn').style.display = '';

    document.getElementById('resDetailOverlay').style.display = 'block';
    document.body.style.overflow = 'hidden';
    document.getElementById('resDetailOverlay').scrollTop = 0;

    document.getElementById('detailIcon').textContent  = fileTypeIcon(r.file_type||'other', r.file_url);
    document.getElementById('detailTitle').textContent = r.title || 'Untitled';

    const uploader = r.profiles
        ? `${r.profiles.first_name||''} ${r.profiles.last_name||''}`.trim() || r.profiles.username
        : 'Unknown';
    const effectiveVis = r.visibility === 'private' || r.education_level === 'private' ? 'private' : 'public';
    document.getElementById('detailSubmeta').innerHTML = `
        <span>${escH(uploader)}</span><span class="dot">·</span>
        <span>${timeAgo(r.created_at)}</span><span class="dot">·</span>
        <span class="res-vis-badge ${effectiveVis}">${effectiveVis==='private'?'🔒 Friends':'🌐 Public'}</span>`;

    document.getElementById('infoSubject').textContent  = r.subject   || '—';
    document.getElementById('infoType').textContent     = fileTypeLabel(r.file_type||'other');
    document.getElementById('infoVis').textContent      = effectiveVis === 'private' ? 'Friends only' : 'Public';
    document.getElementById('infoUploader').textContent = uploader;
    document.getElementById('infoDate').textContent     =
        new Date(r.created_at).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});
    const viewsRow = document.getElementById('infoViewsRow');
    if (r.view_count) { viewsRow.style.display=''; document.getElementById('infoViews').textContent=r.view_count; }
    else { viewsRow.style.display='none'; }

    const descSec = document.getElementById('detailDescSection');
    if (r.description) { descSec.style.display=''; document.getElementById('detailDesc').textContent=r.description; }
    else { descSec.style.display='none'; }

    const contentSec = document.getElementById('detailContentSection');
    if (r.content) { contentSec.style.display=''; document.getElementById('detailContent').textContent=r.content; }
    else { contentSec.style.display='none'; }

    const fileSec = document.getElementById('detailFileSection');
    const linkSec = document.getElementById('detailLinkSection');
    if (r.file_type === 'link' && r.file_url) {
        fileSec.style.display='none'; linkSec.style.display='';
        document.getElementById('detailLinkBtn').href = r.file_url;
    } else {
        linkSec.style.display='none';
        fileSec.style.display='';
        document.getElementById('detailFileBtn').style.display = 'none';
    }

    const isOwner      = r.uploaded_by === CURRENT_USER.id;
    const isBookmarked = bookmarkedIds.has(r.id);

    // Build bookmark button (shown for all logged-in users, including owner)
    const bookmarkBtnHtml = CURRENT_USER.id ? `
        <button id="detailBookmarkBtn"
            class="res-detail-bookmark-btn ${isBookmarked ? 'bookmarked' : ''}"
            onclick="toggleBookmark('${r.id}', event)">
            ${_bookmarkSVG(isBookmarked)} ${isBookmarked ? 'Saved' : 'Save'}
        </button>` : '';

    document.getElementById('detailActions').innerHTML = (isOwner
        ? `<button class="res-detail-edit-btn" onclick="openEditModal()">
               <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                   <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                   <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
               </svg> Edit
           </button>
           <button class="res-detail-edit-btn" style="border-color:rgba(255,107,107,0.4);color:var(--accent);"
               onclick="deleteResource('${r.id}', null)">
               <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                   <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                   <path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/>
               </svg> Delete
           </button>`
        : '') + bookmarkBtnHtml;

    document.getElementById('reportBtn').style.display = isOwner ? 'none' : '';
    document.getElementById('rateLabel').textContent = 'Click a star to rate';
    renderRateStars(0);

    loadDetailFiles(r);
    loadRatings(r.id);
    loadComments(r.id);

    _supabase.from('resources').update({ view_count: (r.view_count||0)+1 }).eq('id', r.id).then(()=>{});
}

// Read-only file list shown in the detail page
async function loadDetailFiles(r) {
    const fileSec = document.getElementById('detailFileSection');
    if (r.file_type === 'link') return;

    try {
        const { data: files, error } = await _supabase
            .from('resource_files')
            .select('id, file_name, file_url, file_size')
            .eq('resource_id', r.id)
            .order('created_at', { ascending: true });

        if (!error && files?.length) {
            fileSec.style.display = '';
            renderDetailFileList(files);
            return;
        }
    } catch(e) {}

    if (r.file_url) {
        fileSec.style.display = '';
        const name = r.original_filename || r.file_url.split('/').pop().split('?')[0] || 'Download file';
        renderDetailFileList([{ id: null, file_name: name, file_url: r.file_url, file_size: r.file_size }]);
    } else {
        fileSec.style.display = 'none';
    }
}

function renderDetailFileList(files) {
    document.getElementById('detailFileBtn').style.display = 'none';

    let listEl = document.getElementById('detailFileList');
    if (!listEl) {
        listEl = document.createElement('div');
        listEl.id = 'detailFileList';
        const btn = document.getElementById('detailFileBtn');
        btn.parentNode.insertBefore(listEl, btn.nextSibling);
    }

    listEl.innerHTML = files.map(f => `
        <div class="res-detail-file-row">
            <span class="res-detail-file-icon">${fileEmojiFromName(f.file_name||'')}</span>
            <span class="res-detail-file-name" title="${escH(f.file_name)}">${escH(f.file_name||'File')}</span>
            ${f.file_size ? `<span class="res-detail-file-size">${formatBytes(f.file_size)}</span>` : ''}
            <a class="res-detail-file-dl" href="${escH(f.file_url)}" target="_blank" download title="Download">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     style="width:16px;height:16px;">
                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
            </a>
        </div>`).join('');
}

function closeDetail() {
    document.getElementById('resDetailOverlay').style.display = 'none';
    document.body.style.overflow = '';
    currentResource  = null;
    editingCommentId = null;
    currentRating    = 0;
    _ratingLocked    = false;
    const listEl = document.getElementById('detailFileList');
    if (listEl) listEl.remove();
    document.getElementById('detailFileBtn').style.display = '';
    const lockMsg = document.getElementById('rateLockMsg');
    if (lockMsg) lockMsg.remove();
    const rateOnlyBtn = document.querySelector('.res-comment-submit[onclick="submitRatingOnly()"]');
    if (rateOnlyBtn) rateOnlyBtn.style.display = '';
    const starsRow = document.getElementById('rateStars');
    if (starsRow) starsRow.classList.remove('locked');
}

async function deleteResource(resourceId, triggerEl) {
    if (!confirm('Are you sure you want to delete this resource? This cannot be undone.')) return;
    try {
        const { error } = await _supabase.from('resources').delete().eq('id', resourceId);
        if (error) throw error;
        allResources = allResources.filter(r => r.id !== resourceId);
        delete _resourceMap[resourceId];
        // Remove bookmark if any
        bookmarkedIds.delete(resourceId);
        _persistBookmarks();
        renderResources(); updateStats(); loadMyUploads(); loadBookmarksSidebar();
        if (currentResource?.id === resourceId) closeDetail();
    } catch(e) { alert('Delete failed: ' + e.message); }
}

// ─────────────────────────────────────────────────────────────
// RATINGS
// ─────────────────────────────────────────────────────────────
let _ratingLocked = false;

async function loadRatings(resourceId) {
    _ratingLocked = false;
    try {
        const { data } = await _supabase
            .from('resource_ratings').select('rating').eq('resource_id', resourceId);
        if (!data?.length) { renderStarsDisplay(0, 0); }
        else {
            const avg = data.reduce((s, r) => s + r.rating, 0) / data.length;
            renderStarsDisplay(avg, data.length);
        }

        if (CURRENT_USER.id) {
            const { data: mine } = await _supabase
                .from('resource_ratings').select('rating')
                .eq('resource_id', resourceId).eq('user_id', CURRENT_USER.id).maybeSingle();
            if (mine) {
                currentRating = mine.rating;
                renderRateStars(currentRating);
                lockRatingUI(mine.rating);
            } else {
                currentRating = 0;
                renderRateStars(0);
                unlockRatingUI();
            }
        }
    } catch(e) {}
}

function lockRatingUI(val) {
    _ratingLocked = true;
    const starsRow = document.getElementById('rateStars');
    if (starsRow) starsRow.classList.add('locked');
    document.getElementById('rateLabel').textContent = `Your rating: ${val}/5`;

    const rateOnlyBtn = document.querySelector('.res-comment-submit[onclick="submitRatingOnly()"]');
    if (rateOnlyBtn) rateOnlyBtn.style.display = 'none';

    let lockMsg = document.getElementById('rateLockMsg');
    if (!lockMsg) {
        lockMsg = document.createElement('div');
        lockMsg.id = 'rateLockMsg';
        lockMsg.className = 'res-rate-locked-msg';
        const rateRow = document.getElementById('rateStars')?.closest('.res-rate-row');
        if (rateRow) rateRow.insertAdjacentElement('afterend', lockMsg);
    }
    lockMsg.innerHTML = `🔒 Ratings are permanent and cannot be changed.`;
    lockMsg.style.display = 'flex';
}

function unlockRatingUI() {
    _ratingLocked = false;
    const starsRow = document.getElementById('rateStars');
    if (starsRow) starsRow.classList.remove('locked');
    document.getElementById('rateLabel').textContent = 'Click a star to rate';

    const rateOnlyBtn = document.querySelector('.res-comment-submit[onclick="submitRatingOnly()"]');
    if (rateOnlyBtn) rateOnlyBtn.style.display = '';

    const lockMsg = document.getElementById('rateLockMsg');
    if (lockMsg) lockMsg.style.display = 'none';
}

function renderStarsDisplay(avg, count) {
    let html = '';
    for (let i = 1; i <= 5; i++)
        html += `<span class="${i <= Math.round(avg) ? 'res-star-filled' : 'res-star-empty'}">★</span>`;
    document.getElementById('detailStarsDisplay').innerHTML = html;
    document.getElementById('detailRatingCount').textContent =
        count ? `${avg.toFixed(1)} (${count} rating${count!==1?'s':''})` : 'No ratings yet';
}
function renderRateStars(val) {
    document.querySelectorAll('.res-rate-star').forEach(s =>
        s.classList.toggle('selected', parseInt(s.dataset.v) <= val));
}
function starHover(val) {
    if (_ratingLocked) return;
    document.querySelectorAll('.res-rate-star').forEach(s =>
        s.classList.toggle('hovered', parseInt(s.dataset.v) <= val));
}
function starOut() {
    if (_ratingLocked) return;
    document.querySelectorAll('.res-rate-star').forEach(s => s.classList.remove('hovered'));
    renderRateStars(currentRating);
}

async function starClick(val) {
    if (_ratingLocked) return;
    if (!CURRENT_USER.id) { alert('Please log in to rate.'); return; }
    if (!currentResource) return;
    try {
        const { error } = await _supabase.from('resource_ratings').insert({
            resource_id: currentResource.id,
            user_id:     CURRENT_USER.id,
            rating:      val
        });
        if (error) {
            if (error.code === '23505' || error.message?.toLowerCase().includes('unique')) {
                lockRatingUI(currentRating || val);
                return;
            }
            throw error;
        }
        currentRating = val;
        renderRateStars(val);
        lockRatingUI(val);
        loadRatings(currentResource.id);
        // Refresh top-rated widget since a new rating was submitted
        loadTopRatedWidget();
    } catch(e) { alert('Rating failed: ' + e.message); }
}

async function submitRatingOnly() {
    if (_ratingLocked) return;
    if (!CURRENT_USER.id) { alert('Please log in to rate.'); return; }
    if (!currentResource) return;
    if (!currentRating)   { alert('Please click a star to rate first.'); return; }
    lockRatingUI(currentRating);
}

// ─────────────────────────────────────────────────────────────
// COMMENTS
// ─────────────────────────────────────────────────────────────
async function loadComments(resourceId) {
    const el = document.getElementById('commentsList');
    el.innerHTML = '<div class="res-loading-sm">Loading comments…</div>';
    try {
        const { data, error } = await _supabase
            .from('resource_comments')
            .select('*, profiles(first_name, last_name, username)')
            .eq('resource_id', resourceId)
            .order('created_at', { ascending: true });
        if (error) throw error;
        document.getElementById('commentCount').textContent = data.length;
        if (!data.length) { el.innerHTML = '<div class="res-loading-sm">No comments yet — be the first!</div>'; return; }
        el.innerHTML = data.map(c => renderCommentHTML(c)).join('');
    } catch(e) {
        el.innerHTML = '<div class="res-loading-sm">Failed to load comments.</div>';
    }
}

function renderCommentHTML(c) {
    const name     = c.profiles
        ? `${c.profiles.first_name||''} ${c.profiles.last_name||''}`.trim() || c.profiles.username
        : 'Unknown';
    const initials = ((c.profiles?.first_name||'?')[0]+(c.profiles?.last_name||'?')[0]).toUpperCase();
    const isOwn    = c.user_id === CURRENT_USER.id;
    return `
        <div class="res-comment-item" id="comment-${c.id}">
            <div style="width:34px;height:34px;border-radius:9px;
                background:linear-gradient(135deg,var(--primary),var(--primary-dark));
                color:white;font-size:12px;font-weight:700;display:flex;align-items:center;
                justify-content:center;flex-shrink:0;">${initials}</div>
            <div class="res-comment-body">
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:4px;">
                    <span class="res-comment-name">${escH(name)}</span>
                    <span class="res-comment-time">${timeAgo(c.created_at)}</span>
                    ${isOwn ? `
                        <button class="res-comment-action-btn" onclick="startEditComment('${c.id}')">✏️ Edit</button>
                        <button class="res-comment-action-btn danger" onclick="deleteComment('${c.id}')">🗑 Delete</button>
                    ` : ''}
                </div>
                <div class="res-comment-text" id="commentText-${c.id}">${escH(c.comment)}</div>
                <div id="commentEdit-${c.id}" style="display:none;margin-top:8px;">
                    <textarea class="res-comment-input" id="commentEditInput-${c.id}"
                        style="min-height:50px;width:100%;" rows="2"></textarea>
                    <div style="display:flex;gap:6px;margin-top:6px;justify-content:flex-end;">
                        <button class="res-comment-submit"
                            style="background:#f3f4f6;color:var(--text-secondary);padding:6px 14px;"
                            onclick="cancelEditComment('${c.id}')">Cancel</button>
                        <button class="res-comment-submit" onclick="saveEditComment('${c.id}')">Save</button>
                    </div>
                </div>
            </div>
        </div>`;
}

function startEditComment(commentId) {
    if (editingCommentId && editingCommentId !== commentId) cancelEditComment(editingCommentId);
    editingCommentId = commentId;
    const textEl = document.getElementById(`commentText-${commentId}`);
    const editEl = document.getElementById(`commentEdit-${commentId}`);
    const inp    = document.getElementById(`commentEditInput-${commentId}`);
    inp.value = textEl.textContent;
    textEl.style.display = 'none'; editEl.style.display = 'block'; inp.focus();
}
function cancelEditComment(commentId) {
    const textEl = document.getElementById(`commentText-${commentId}`);
    const editEl = document.getElementById(`commentEdit-${commentId}`);
    if (textEl) textEl.style.display = '';
    if (editEl) editEl.style.display = 'none';
    editingCommentId = null;
}
async function saveEditComment(commentId) {
    const inp  = document.getElementById(`commentEditInput-${commentId}`);
    const text = inp.value.trim();
    if (!text) { alert('Comment cannot be empty.'); return; }
    try {
        const { error } = await _supabase.from('resource_comments').update({ comment: text }).eq('id', commentId);
        if (error) throw error;
        document.getElementById(`commentText-${commentId}`).textContent = text;
        cancelEditComment(commentId);
    } catch(e) { alert('Edit failed: ' + e.message); }
}
async function deleteComment(commentId) {
    if (!confirm('Delete this comment?')) return;
    try {
        const { error } = await _supabase.from('resource_comments').delete().eq('id', commentId);
        if (error) throw error;
        document.getElementById(`comment-${commentId}`)?.remove();
        const count = document.getElementById('commentCount');
        count.textContent = Math.max(0, parseInt(count.textContent||'0') - 1);
    } catch(e) { alert('Delete failed: ' + e.message); }
}
async function submitComment() {
    if (!CURRENT_USER.id) { alert('Please log in to comment.'); return; }
    if (!currentResource) return;
    const input = document.getElementById('commentInput');
    const text  = input.value.trim();
    if (!text) { alert('Please write a comment first.'); return; }
    const btn = document.getElementById('submitReviewBtn');
    btn.disabled = true; btn.textContent = 'Posting…';
    try {
        const { error } = await _supabase.from('resource_comments').insert({
            resource_id: currentResource.id, user_id: CURRENT_USER.id, comment: text
        });
        if (error) throw error;
        input.value = ''; input.style.height = '';
        loadComments(currentResource.id);
    } catch(e) { alert('Failed to post comment: ' + e.message); }
    finally { btn.disabled = false; btn.textContent = '💬 Post Comment'; }
}

// ─────────────────────────────────────────────────────────────
// EDIT MODAL
// ─────────────────────────────────────────────────────────────
async function openEditModal() {
    if (!currentResource) return;
    const r = currentResource;

    editNewFiles      = [];
    editExistingFiles = [];
    editFilesToDelete = [];

    document.getElementById('editTitle').value   = r.title       || '';
    document.getElementById('editDesc').value    = r.description || '';
    document.getElementById('editContent').value = r.content     || '';

    const subjectSelect = document.getElementById('editSubject');
    const knownSubjects = [...subjectSelect.options].map(o => o.value);
    if (r.subject && knownSubjects.includes(r.subject)) {
        subjectSelect.value = r.subject;
        document.getElementById('editSubjectOther').style.display = 'none';
    } else if (r.subject) {
        subjectSelect.value = 'others';
        document.getElementById('editSubjectOther').value = r.subject;
        document.getElementById('editSubjectOther').style.display = 'block';
    } else { subjectSelect.value = ''; }

    const typeSelect = document.getElementById('editType');
    const knownTypes = [...typeSelect.options].map(o => o.value);
    if (r.file_type && knownTypes.includes(r.file_type)) {
        typeSelect.value = r.file_type;
        document.getElementById('editTypeOther').style.display = 'none';
    } else if (r.file_type) {
        typeSelect.value = 'others';
        document.getElementById('editTypeOther').value = r.file_type;
        document.getElementById('editTypeOther').style.display = 'block';
    } else { typeSelect.value = ''; }

    const vis = r.visibility === 'private' || r.education_level === 'private' ? 'private' : 'public';
    const visRadio = document.querySelector(`input[name="editVis"][value="${vis}"]`);
    if (visRadio) visRadio.checked = true;

    document.getElementById('editModal').classList.add('open');
    await loadEditFileList(r);
}

async function loadEditFileList(r) {
    const container = document.getElementById('editFileManager');
    if (container) container.innerHTML = '<div style="padding:12px;color:var(--text-light);font-size:13px;">Loading files…</div>';

    try {
        const { data: files, error } = await _supabase
            .from('resource_files')
            .select('id, file_name, file_url, file_size, storage_path')
            .eq('resource_id', r.id)
            .order('created_at', { ascending: true });

        if (!error && files?.length) {
            editExistingFiles = files;
        } else if (r.file_url && r.file_type !== 'link') {
            editExistingFiles = [{
                id: '__legacy__',
                file_name:    r.original_filename || r.file_url.split('/').pop().split('?')[0] || 'Uploaded file',
                file_url:     r.file_url,
                file_size:    r.file_size || null,
                storage_path: null
            }];
        } else {
            editExistingFiles = [];
        }
    } catch(e) {
        editExistingFiles = [];
    }
    renderEditFileManager();
}

function renderEditFileManager() {
    const container = document.getElementById('editFileManager');
    if (!container) return;

    const existingHTML = editExistingFiles.length
        ? editExistingFiles.map((f, i) => `
            <div class="efm-row" id="efm-ex-${i}">
                <span class="efm-icon">${fileEmojiFromName(f.file_name||'')}</span>
                <input class="efm-name-input res-input"
                       value="${escH(f.file_name)}"
                       onchange="editExistingFiles[${i}].file_name = this.value"
                       style="flex:1;padding:7px 10px;font-size:13px;" title="Rename this file">
                ${f.file_size ? `<span class="efm-size">${formatBytes(f.file_size)}</span>` : ''}
                <a class="efm-dl-btn" href="${escH(f.file_url)}" target="_blank" download title="Download">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                </a>
                <button class="efm-del-btn" onclick="removeExistingFile(${i})" title="Remove this file">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6l-1 14H6L5 6"/>
                        <path d="M10 11v6M14 11v6"/>
                        <path d="M9 6V4h6v2"/>
                    </svg>
                </button>
            </div>`).join('')
        : `<div class="efm-empty">No files attached yet.</div>`;

    const newHTML = editNewFiles.length
        ? `<div class="efm-new-header">Will be added on save:</div>` +
          editNewFiles.map((f, i) => `
            <div class="efm-row efm-row-new">
                <span class="efm-icon">${fileEmojiFromName(f.name)}</span>
                <span class="efm-name-new" style="flex:1;font-size:13px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escH(f.name)}</span>
                <span class="efm-size">${formatBytes(f.size)}</span>
                <button class="efm-del-btn" onclick="removeNewFile(${i})" title="Cancel">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>`).join('')
        : '';

    container.innerHTML = `
        <div class="efm-existing-list">${existingHTML}</div>
        ${newHTML}
        <div class="efm-add-zone" id="efmDropzone"
             onclick="document.getElementById('efmFileInput').click()"
             ondragover="event.preventDefault();this.classList.add('dragover')"
             ondragleave="this.classList.remove('dragover')"
             ondrop="efmDrop(event)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 style="width:20px;height:20px;color:var(--primary);flex-shrink:0;">
                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                <polyline points="17 8 12 3 7 8"/>
                <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
            <span style="font-size:13px;color:var(--text-secondary);">
                Drop files here or <u style="color:var(--primary);cursor:pointer;">click to browse</u>
            </span>
            <input type="file" id="efmFileInput" multiple style="display:none;"
                   accept="image/*,video/*,.pdf,.doc,.docx,.ppt,.pptx,.txt,.zip"
                   onchange="efmAddFiles(this.files); this.value='';">
        </div>`;
}

function efmAddFiles(fileList) {
    Array.from(fileList).forEach(f => {
        if (!editNewFiles.find(x => x.name === f.name && x.size === f.size)) editNewFiles.push(f);
    });
    renderEditFileManager();
}
function efmDrop(e) {
    e.preventDefault();
    document.getElementById('efmDropzone')?.classList.remove('dragover');
    if (e.dataTransfer.files?.length) efmAddFiles(e.dataTransfer.files);
}
function removeNewFile(i) { editNewFiles.splice(i, 1); renderEditFileManager(); }
function removeExistingFile(i) {
    const f = editExistingFiles[i];
    if (!f) return;
    if (!confirm(`Remove "${f.file_name || 'this file'}" from this resource? This cannot be undone.`)) return;
    editFilesToDelete.push(f);
    editExistingFiles.splice(i, 1);
    renderEditFileManager();
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
    editNewFiles = []; editExistingFiles = []; editFilesToDelete = [];
}
function checkEditOtherSubject() {
    document.getElementById('editSubjectOther').style.display =
        document.getElementById('editSubject').value === 'others' ? 'block' : 'none';
}
function checkEditOtherType() {
    document.getElementById('editTypeOther').style.display =
        document.getElementById('editType').value === 'others' ? 'block' : 'none';
}

async function saveEdit() {
    if (!currentResource) return;
    const title   = document.getElementById('editTitle').value.trim();
    const desc    = document.getElementById('editDesc').value.trim();
    const content = document.getElementById('editContent').value.trim();
    const subject = document.getElementById('editSubject').value;
    const subjOth = document.getElementById('editSubjectOther').value.trim();
    const type    = document.getElementById('editType').value;
    const typeOth = document.getElementById('editTypeOther').value.trim();
    const vis     = document.querySelector('input[name="editVis"]:checked').value;

    if (!title)                           { alert('Title is required.'); return; }
    if (!subject)                         { alert('Please select a subject.'); return; }
    if (subject === 'others' && !subjOth) { alert('Please specify the subject.'); return; }
    if (!type)                            { alert('Please select a type.'); return; }
    if (type === 'others' && !typeOth)    { alert('Please specify the material type.'); return; }

    const finalSubject = subject === 'others' ? subjOth : subject;
    const finalType    = type    === 'others' ? (typeOth || 'others') : type;

    const btn = document.querySelector('#editModal .btn-primary');
    btn.disabled = true; btn.textContent = 'Saving…';

    try {
        for (const f of editExistingFiles) {
            if (f.id && f.id !== '__legacy__') {
                await _supabase.from('resource_files').update({ file_name: f.file_name }).eq('id', f.id);
            }
        }

        for (const f of editFilesToDelete) {
            if (f.id && f.id !== '__legacy__') {
                await _supabase.from('resource_files').delete().eq('id', f.id);
                if (f.storage_path) {
                    await _supabase.storage.from('resources').remove([f.storage_path]);
                }
            }
        }

        let primaryUrl = currentResource.file_url;
        for (const file of editNewFiles) {
            const ext  = file.name.split('.').pop();
            const path = `${CURRENT_USER.id}/${Date.now()}_${Math.random().toString(36).slice(2)}.${ext}`;
            const { error: upErr } = await _supabase.storage
                .from('resources').upload(path, file, { upsert: true });
            if (upErr) throw upErr;
            const { data: urlData } = _supabase.storage.from('resources').getPublicUrl(path);
            const { error: rfErr } = await _supabase.from('resource_files').insert({
                resource_id:  currentResource.id,
                uploaded_by:  CURRENT_USER.id,
                file_name:    file.name,
                file_url:     urlData.publicUrl,
                file_size:    file.size,
                storage_path: path
            });
            if (rfErr) console.warn('resource_files insert:', rfErr.message);
            if (!primaryUrl) primaryUrl = urlData.publicUrl;
        }

        if (editExistingFiles.length > 0 && editExistingFiles[0].id !== '__legacy__') {
            primaryUrl = editExistingFiles[0].file_url;
        } else if (editExistingFiles.length === 0 && editNewFiles.length === 0) {
            primaryUrl = null;
        }

        const { error } = await _supabase.from('resources').update({
            title,
            description:     desc    || null,
            content:         content || null,
            subject:         finalSubject,
            file_type:       finalType,
            visibility:      vis,
            education_level: vis,
            file_url:        primaryUrl
        }).eq('id', currentResource.id);
        if (error) throw error;

        const updated = {
            ...currentResource,
            title, description: desc||null, content: content||null,
            subject: finalSubject, file_type: finalType,
            visibility: vis, education_level: vis, file_url: primaryUrl
        };
        _resourceMap[updated.id] = updated;
        const idx = allResources.findIndex(r => r.id === updated.id);
        if (idx !== -1) allResources[idx] = updated;

        closeEditModal();
        openDetail(updated);
        renderResources(); loadMyUploads();

    } catch(e) {
        alert('Save failed: ' + e.message);
    } finally {
        btn.disabled = false; btn.textContent = '💾 Save Changes';
    }
}

// ─────────────────────────────────────────────────────────────
// REPORT
// ─────────────────────────────────────────────────────────────
function openReport()  { document.getElementById('reportModal').classList.add('open'); }
function closeReport() { document.getElementById('reportModal').classList.remove('open'); }
async function submitReport() {
    const reason = document.querySelector('input[name="reportReason"]:checked');
    if (!reason) { alert('Please select a reason.'); return; }
    const details    = document.getElementById('reportDetails').value.trim();
    const fullReason = details ? `${reason.value}: ${details}` : reason.value;
    try {
        const { error } = await _supabase.from('reports').insert({
            reported_by: CURRENT_USER.id, reported_content_type: 'resource',
            reported_content_id: currentResource.id, reason: fullReason, status: 'pending'
        });
        if (error) throw error;
        closeReport(); alert('✅ Report submitted. Our team will review it soon.');
    } catch(e) { alert('Report failed: ' + e.message); }
}

// ─────────────────────────────────────────────────────────────
// KEYBOARD SHORTCUTS
// ─────────────────────────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    const topRatedOverlay = document.getElementById('topRatedModalOverlay');
    if (topRatedOverlay?.style.display === 'flex') { closeTopRatedModal(); return; }
    if (document.getElementById('reportModal').classList.contains('open')) { closeReport(); return; }
    if (document.getElementById('editModal').classList.contains('open'))   { closeEditModal(); return; }
    if (document.getElementById('uploadModal')?.classList.contains('open')) { closeUploadModal(); return; }
    if (document.getElementById('resDetailOverlay').style.display !== 'none') { closeDetail(); return; }
});

// ─────────────────────────────────────────────────────────────
// MISC UI HELPERS
// ─────────────────────────────────────────────────────────────
function autoResize(el) { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; }

// ─────────────────────────────────────────────────────────────
// FILE TYPE HELPERS
// ─────────────────────────────────────────────────────────────
function fileTypeIcon(type, url) {
    const m = { link:'🔗', video:'🎬', image:'🖼️', slides:'📊',
                exercise:'📝', reviewer:'📋', notes:'📄', text:'✍️' };
    if (m[type]) return m[type];
    if (!url) return '📎';
    const ext = (url.split('.').pop()||'').toLowerCase().split('?')[0];
    return { pdf:'📄', doc:'📝', docx:'📝', ppt:'📊', pptx:'📊',
             mp4:'🎬', mov:'🎬', webm:'🎬', jpg:'🖼️',
             jpeg:'🖼️', png:'🖼️', gif:'🖼️',
             webp:'🖼️', zip:'🗜️', rar:'🗜️' }[ext] || '📎';
}
function fileIconClass(type, url) {
    if (type === 'link')  return 'link';
    if (type === 'video') return 'video';
    if (type === 'image') return 'image';
    if (!url) return 'other';
    const ext = (url.split('.').pop()||'').toLowerCase().split('?')[0];
    return { pdf:'pdf', doc:'docx', docx:'docx', ppt:'pptx', pptx:'pptx',
             mp4:'video', mov:'video', webm:'video', jpg:'image',
             jpeg:'image', png:'image', gif:'image', webp:'image' }[ext] || 'other';
}
function fileTypeLabel(type) {
    return { notes:'Notes', exercise:'Exercise', slides:'Slides', video:'Video',
             image:'Image', link:'Link', reviewer:'Reviewer', text:'Text',
             others:'Other' }[type] || type || 'Other';
}
function fileEmojiFromName(name) {
    const ext = (name.split('.').pop()||'').toLowerCase();
    return { pdf:'📄', doc:'📝', docx:'📝', ppt:'📊', pptx:'📊',
             mp4:'🎬', mov:'🎬', webm:'🎬', jpg:'🖼️',
             jpeg:'🖼️', png:'🖼️', gif:'🖼️',
             webp:'🖼️', zip:'🗜️', rar:'🗜️' }[ext] || '📎';
}
function formatBytes(b) {
    if (!b) return '';
    if (b < 1024)    return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(1) + ' MB';
}
function timeAgo(ts) {
    const s = Math.floor((Date.now() - new Date(ts)) / 1000);
    if (s < 60)     return 'Just now';
    if (s < 3600)   return `${Math.floor(s/60)}m ago`;
    if (s < 86400)  return `${Math.floor(s/3600)}h ago`;
    if (s < 604800) return `${Math.floor(s/86400)}d ago`;
    return new Date(ts).toLocaleDateString();
}
function escH(t) {
    if (t === null || t === undefined) return '';
    if (typeof t !== 'string') t = String(t);
    const d = document.createElement('div'); d.textContent = t; return d.innerHTML;
}