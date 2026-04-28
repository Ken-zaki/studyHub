/* ============================================================
   public/js/resources.js
   ============================================================ */

const _supabase = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// ── CATEGORIES ────────────────────────────────────────────────
const ALL_CATEGORIES = [
    'All','Mathematics','Science','Filipino','English','PE',
    'Health','Music','Arts','Social Studies','Computer Science',
    'Values Education','MAPEH','History','Chemistry','Physics',
    'Biology','Economics','Others'
];

// ── STATE ─────────────────────────────────────────────────────
let allResources    = [];
let activeCategory  = 'All';
let activeVisFilter = 'public';
let searchQuery     = '';
let catSearchQuery  = '';
let selectedFiles   = [];
let currentStep     = 1;
let recentlyViewed  = [];

try { recentlyViewed = JSON.parse(localStorage.getItem('sh_recent_resources') || '[]'); } catch(e){}

// ── INIT ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    buildCategoryPills();
    loadResources();
    renderRecentlyViewed();
    loadMyUploads();
});

// ── CATEGORY PILLS ────────────────────────────────────────────
function buildCategoryPills() {
    const container = document.getElementById('categoryPills');
    container.innerHTML = ALL_CATEGORIES.map(cat => `
        <button class="res-pill ${cat === 'All' ? 'active' : ''}"
                data-cat="${cat}"
                onclick="setCategory('${cat}', this)">
            ${cat}
        </button>`).join('');
}

function filterCategories() {
    catSearchQuery = document.getElementById('catSearch').value.toLowerCase();
    document.querySelectorAll('.res-pill').forEach(pill => {
        const cat = pill.dataset.cat.toLowerCase();
        if (!catSearchQuery || cat.includes(catSearchQuery) || cat === 'all') {
            pill.classList.remove('hidden');
        } else {
            pill.classList.add('hidden');
        }
    });
}

function setCategory(cat, btn) {
    activeCategory = cat;
    document.querySelectorAll('.res-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');

    const af = document.getElementById('activeFilters');
    const at = document.getElementById('activeFilterTag');
    if (cat !== 'All') {
        af.style.display = 'flex';
        at.textContent = '📚 ' + cat;
    } else {
        af.style.display = 'none';
    }
    renderResources();
}

function clearCategory() {
    activeCategory = 'All';
    document.querySelectorAll('.res-pill').forEach(p => p.classList.remove('active'));
    const allPill = document.querySelector('.res-pill[data-cat="All"]');
    if (allPill) allPill.classList.add('active');
    document.getElementById('activeFilters').style.display = 'none';
    renderResources();
}

// ── VISIBILITY FILTER ─────────────────────────────────────────
function setVisibility(vis, btn) {
    activeVisFilter = vis;
    document.getElementById('filterPublic').classList.toggle('active', vis === 'public');
    document.getElementById('filterPrivate').classList.toggle('active', vis === 'private');
    renderResources();
}

// ── SEARCH ────────────────────────────────────────────────────
function filterResources() {
    searchQuery = document.getElementById('searchInput').value;
    document.getElementById('searchClear').style.display = searchQuery ? 'block' : 'none';
    renderResources();
}
function clearSearch() {
    document.getElementById('searchInput').value = '';
    searchQuery = '';
    document.getElementById('searchClear').style.display = 'none';
    renderResources();
}

// ── LOAD RESOURCES ────────────────────────────────────────────
async function loadResources() {
    const feed = document.getElementById('resourceFeed');
    feed.innerHTML = '<div class="loading-state">Loading resources…</div>';
    try {
        const { data, error } = await _supabase
            .from('resources')
            .select('*, profiles(first_name, last_name, username)')
            .eq('is_approved', true)
            .order('created_at', { ascending: false });

        if (error) throw error;
        allResources = data || [];
        renderResources();
        updateStats();
    } catch(err) {
        feed.innerHTML = `<div class="alert-error">❌ Failed to load: ${err.message}</div>`;
    }
}

// ── RENDER ────────────────────────────────────────────────────
function renderResources() {
    const feed = document.getElementById('resourceFeed');

    const filtered = allResources.filter(r => {
        // Visibility
        if (activeVisFilter === 'public' && r.education_level === 'private') return false;
        if (activeVisFilter === 'private' && r.education_level !== 'private') return false;

        // Category
        if (activeCategory !== 'All' && r.subject !== activeCategory) return false;

        // Search
        const q = searchQuery.toLowerCase();
        if (q) {
            const inTitle = (r.title||'').toLowerCase().includes(q);
            const inDesc  = (r.description||'').toLowerCase().includes(q);
            const inSubj  = (r.subject||'').toLowerCase().includes(q);
            const inTags  = (r.tags||[]).join(' ').toLowerCase().includes(q);
            if (!inTitle && !inDesc && !inSubj && !inTags) return false;
        }
        return true;
    });

    if (!filtered.length) {
        feed.innerHTML = `
            <div class="res-empty">
                <div class="ei">📭</div>
                <p>${searchQuery
                    ? `No results for "<strong>${escH(searchQuery)}</strong>"`
                    : 'No resources found in this category yet.'}</p>
            </div>`;
        return;
    }

    // Group by subject
    const groups = {};
    filtered.forEach(r => {
        const s = r.subject || 'Other';
        if (!groups[s]) groups[s] = [];
        groups[s].push(r);
    });

    const EMOJIS = {
        'Mathematics':'📐','Science':'🔬','Filipino':'🇵🇭','English':'📖',
        'PE':'⚽','Health':'❤️‍🩹','Music':'🎵','Arts':'🎨',
        'Social Studies':'🌍','Computer Science':'💻','Values Education':'🌟',
        'MAPEH':'🎭','History':'🏛️','Chemistry':'⚗️','Physics':'⚛️',
        'Biology':'🧬','Economics':'💹','Others':'📚'
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

// ── CARD HTML ─────────────────────────────────────────────────
function cardHTML(r) {
    const fileType = (r.file_type || 'other').toLowerCase();
    const icon     = fileTypeIcon(fileType, r.file_url);
    const iconCls  = fileIconClass(fileType, r.file_url);
    const label    = r.tags && r.tags.length ? r.tags[0] : fileTypeLabel(fileType);
    const uploader = r.profiles
        ? `${r.profiles.first_name||''} ${r.profiles.last_name||''}`.trim() || r.profiles.username
        : 'Unknown';
    const ago      = timeAgo(r.created_at);
    const desc     = r.description ? escH(r.description.slice(0, 90)) + (r.description.length > 90 ? '…' : '') : '';
    const vis      = r.education_level === 'private' ? 'private' : 'public';
    const visLabel = vis === 'private' ? '🔒 Friends' : '🌐 Public';

    const actionBtn = fileType === 'link'
        ? `<a href="${escH(r.file_url||'#')}" target="_blank" class="res-action-btn primary">Open</a>`
        : r.file_url
            ? `<a href="${escH(r.file_url)}" target="_blank" download class="res-action-btn primary">Download</a>`
            : `<span class="res-action-btn" style="opacity:.4;cursor:default;">No file</span>`;

    return `
        <div class="res-card" onclick="markViewed(${JSON.stringify(r).replace(/"/g,'&quot;')})">
            <div class="res-card-icon ${iconCls}">${icon}</div>
            <div class="res-card-body">
                <div class="res-card-title">${escH(r.title)}</div>
                ${desc ? `<div class="res-card-desc">${desc}</div>` : ''}
                <div class="res-card-meta">
                    <span>${escH(uploader)}</span>
                    <span class="dot">·</span>
                    <span>${ago}</span>
                    <span class="dot">·</span>
                    <span class="res-vis-badge ${vis}">${visLabel}</span>
                </div>
            </div>
            <div class="res-card-actions">
                <span class="res-type-badge ${fileType}">${escH(label)}</span>
                ${actionBtn}
            </div>
        </div>`;
}

// ── RECENTLY VIEWED ───────────────────────────────────────────
function markViewed(r) {
    recentlyViewed = [r, ...recentlyViewed.filter(x => x.id !== r.id)].slice(0, 5);
    try { localStorage.setItem('sh_recent_resources', JSON.stringify(recentlyViewed)); } catch(e){}
    renderRecentlyViewed();
}

function renderRecentlyViewed() {
    const el = document.getElementById('recentlyViewed');
    if (!recentlyViewed.length) {
        el.innerHTML = '<div class="res-empty-small">Nothing viewed yet</div>';
        return;
    }
    el.innerHTML = recentlyViewed.map(r => `
        <div class="res-recent-item" onclick="markViewed(${JSON.stringify(r).replace(/"/g,'&quot;')})">
            <div class="res-recent-icon">${fileTypeIcon(r.file_type||'other', r.file_url)}</div>
            <div style="min-width:0;">
                <div class="res-recent-title">${escH(r.title)}</div>
                <div class="res-recent-sub">${escH(r.subject||'General')} · ${fileTypeLabel(r.file_type||'other')}</div>
            </div>
        </div>`).join('');
}

// ── MY UPLOADS ────────────────────────────────────────────────
async function loadMyUploads() {
    if (!CURRENT_USER.id) return;
    const el = document.getElementById('myUploads');
    try {
        const { data } = await _supabase
            .from('resources')
            .select('id, title, file_type, subject')
            .eq('uploaded_by', CURRENT_USER.id)
            .order('created_at', { ascending: false })
            .limit(5);

        if (!data || !data.length) return;
        el.innerHTML = data.map(r => `
            <div class="res-recent-item">
                <div class="res-recent-icon">${fileTypeIcon(r.file_type||'other', null)}</div>
                <div style="min-width:0;">
                    <div class="res-recent-title">${escH(r.title)}</div>
                    <div class="res-recent-sub">${escH(r.subject||'General')}</div>
                </div>
            </div>`).join('');
    } catch(e){}
}

// ── STATS ─────────────────────────────────────────────────────
function updateStats() {
    document.getElementById('totalCount').textContent = allResources.length;
    const subjects = [...new Set(allResources.map(r => r.subject).filter(Boolean))];
    document.getElementById('subjectCount').textContent = subjects.length;
}

// ── UPLOAD MODAL ──────────────────────────────────────────────
function openUploadModal()  { document.getElementById('uploadModal').classList.add('open'); goStep(1); }
function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('open');
    resetUploadForm();
}

function goStep(n) {
    // Validate before advancing
    if (n > currentStep) {
        if (currentStep === 1) {
            const hasFiles   = selectedFiles.length > 0;
            const hasLink    = document.getElementById('uploadLink').value.trim();
            const hasContent = (document.getElementById('uploadContent')?.value || '').trim();
            if (!hasFiles && !hasLink && !hasContent) {
                alert('Please attach a file, paste a link, or write some text content.');
                return;
            }
        }
        if (currentStep === 2) {
            let ok = true;
            const title   = document.getElementById('uploadTitle').value.trim();
            const subject = document.getElementById('uploadSubject').value;
            const type    = document.getElementById('uploadType').value;
            const subjOther = document.getElementById('uploadSubjectOther').value.trim();
            const typeOther = document.getElementById('uploadTypeOther').value.trim();

            document.getElementById('errTitle').textContent   = '';
            document.getElementById('errSubject').textContent = '';
            document.getElementById('errType').textContent    = '';

            if (!title)                           { document.getElementById('errTitle').textContent   = 'Title is required.'; ok = false; }
            if (!subject)                         { document.getElementById('errSubject').textContent = 'Please select a subject.'; ok = false; }
            if (subject === 'others' && !subjOther){ document.getElementById('errSubject').textContent = 'Please specify the subject.'; ok = false; }
            if (!type)                            { document.getElementById('errType').textContent    = 'Please select a type.'; ok = false; }
            if (type === 'others' && !typeOther)  { document.getElementById('errType').textContent    = 'Please specify the material type.'; ok = false; }
            if (!ok) return;

            // Build summary for step 3
            buildSummary();
        }
    }

    currentStep = n;
    document.querySelectorAll('.upload-section').forEach((s, i) => {
        s.classList.toggle('active', i + 1 === n);
    });
    document.querySelectorAll('.upload-step').forEach((s, i) => {
        s.classList.remove('active', 'done');
        if (i + 1 === n) s.classList.add('active');
        if (i + 1 < n)  s.classList.add('done');
    });
}

function buildSummary() {
    const title   = document.getElementById('uploadTitle').value.trim();
    const subject = document.getElementById('uploadSubject').value;
    const subjOther = document.getElementById('uploadSubjectOther').value.trim();
    const type    = document.getElementById('uploadType').value;
    const typeOther = document.getElementById('uploadTypeOther').value.trim();
    const desc    = document.getElementById('uploadDesc').value.trim();

    const finalSubj = subject === 'others' ? subjOther : subject;
    const finalType = type === 'others' ? typeOther : fileTypeLabel(type);

    const el = document.getElementById('uploadSummary');
    el.innerHTML = `
        <strong>Title:</strong> ${escH(title)}<br>
        <strong>Subject:</strong> ${escH(finalSubj)}<br>
        <strong>Type:</strong> ${escH(finalType)}<br>
        ${desc ? `<strong>Description:</strong> ${escH(desc.slice(0,60))}${desc.length>60?'…':''}<br>` : ''}
        <strong>Files:</strong> ${selectedFiles.length ? selectedFiles.map(f=>f.name).join(', ') : 'Link only'}
    `;
}

// File handling
function handleFiles(fileList) {
    Array.from(fileList).forEach(f => {
        if (!selectedFiles.find(x => x.name === f.name && x.size === f.size)) {
            selectedFiles.push(f);
        }
    });
    renderFilePreviews();
}

function renderFilePreviews() {
    const el = document.getElementById('filePreviewList');
    el.innerHTML = selectedFiles.map((f, i) => `
        <div class="file-preview-item">
            <span class="file-preview-icon">${fileEmojiFromName(f.name)}</span>
            <div class="file-preview-info">
                <div class="file-preview-name">${escH(f.name)}</div>
                <div class="file-preview-size">${formatBytes(f.size)}</div>
            </div>
            <button class="file-preview-remove" onclick="removeFile(${i})">✕</button>
        </div>`).join('');
}

function removeFile(i) {
    selectedFiles.splice(i, 1);
    renderFilePreviews();
}

function dragOver(e)  { e.preventDefault(); document.getElementById('dropzone').classList.add('dragover'); }
function dragLeave(e) { document.getElementById('dropzone').classList.remove('dragover'); }
function dropFiles(e) {
    e.preventDefault();
    document.getElementById('dropzone').classList.remove('dragover');
    handleFiles(e.dataTransfer.files);
}

function checkOtherSubject() {
    const sel = document.getElementById('uploadSubject').value;
    document.getElementById('uploadSubjectOther').style.display = sel === 'others' ? 'block' : 'none';
}
function checkOtherType() {
    const sel = document.getElementById('uploadType').value;
    document.getElementById('uploadTypeOther').style.display = sel === 'others' ? 'block' : 'none';
}

function setVis(v) {
    document.getElementById('visPublic').style.borderColor  = v === 'public'  ? 'var(--primary)' : '';
    document.getElementById('visPrivate').style.borderColor = v === 'private' ? 'var(--primary)' : '';
}

// ── SUBMIT UPLOAD ─────────────────────────────────────────────
async function submitUpload() {
    const btn = document.getElementById('submitUploadBtn');
    btn.disabled = true; btn.textContent = 'Uploading…';

    try {
        const title      = document.getElementById('uploadTitle').value.trim();
        const desc       = document.getElementById('uploadDesc').value.trim();
        const subject    = document.getElementById('uploadSubject').value;
        const subjOther  = document.getElementById('uploadSubjectOther').value.trim();
        const type       = document.getElementById('uploadType').value;
        const typeOther  = document.getElementById('uploadTypeOther').value.trim();
        const linkVal    = document.getElementById('uploadLink').value.trim();
        const vis        = document.querySelector('input[name="visibility"]:checked').value;

        const finalSubject = subject === 'others' ? subjOther : subject;
        const finalType    = type === 'others' ? 'others' : type;

        // Upload each file
        const textContent2 = (document.getElementById('uploadContent')?.value || '').trim();

        // Allow text-only upload (no files required if content exists)
        if (!selectedFiles.length && !linkVal && textContent2) {
            // Text-only resource — insert directly
            const { error: textErr } = await _supabase.from('resources').insert({
                uploaded_by:     CURRENT_USER.id,
                title,
                description:     desc || null,
                content:         textContent2,
                subject:         finalSubject,
                file_type:       finalType || 'text',
                file_url:        null,
                education_level: vis === 'private' ? 'private' : 'public',
                visibility:      vis === 'private' ? 'private' : 'public',
                is_approved:     false,
                tags:            typeOther ? [typeOther] : []
            });
            if (textErr) throw textErr;
            closeUploadModal();
            alert('✅ Resource uploaded successfully!');
            loadResources(); loadMyUploads();
            return;
        }

        const uploadedUrls = [];
        for (const file of selectedFiles) {
            const ext  = file.name.split('.').pop();
            const path = `${CURRENT_USER.id}/${Date.now()}_${Math.random().toString(36).slice(2)}.${ext}`;

            const { error: upErr } = await _supabase.storage
                .from('resources')
                .upload(path, file, { upsert: true });
            if (upErr) throw upErr;

            const { data: urlData } = _supabase.storage.from('resources').getPublicUrl(path);
            uploadedUrls.push({ url: urlData.publicUrl, name: file.name });
        }

        // If link only
        if (!selectedFiles.length && linkVal) {
            uploadedUrls.push({ url: linkVal, name: linkVal });
        }

        const textContent = (document.getElementById('uploadContent')?.value || '').trim();

        // Insert one row per file (or one row for link)
        for (const u of uploadedUrls) {
            const { error: insErr } = await _supabase.from('resources').insert({
                uploaded_by:     CURRENT_USER.id,
                title:           selectedFiles.length > 1 ? `${title} — ${u.name}` : title,
                description:     desc || null,
                content:         textContent || null,
                subject:         finalSubject,
                file_type:       finalType,
                file_url:        u.url,
                education_level: vis === 'private' ? 'private' : 'public',
                visibility:      vis === 'private' ? 'private' : 'public',
                is_approved:     false,
                tags:            typeOther ? [typeOther] : []
            });
            if (insErr) throw insErr;
        }

        closeUploadModal();
        alert('✅ Upload submitted! It will appear after admin approval.');
        loadMyUploads();

    } catch(err) {
        alert('Upload failed: ' + err.message);
    } finally {
        btn.disabled = false; btn.textContent = '📤 Upload';
    }
}

function resetUploadForm() {
    selectedFiles = [];
    currentStep   = 1;
    document.getElementById('filePreviewList').innerHTML = '';
    document.getElementById('uploadLink').value  = '';
    document.getElementById('uploadTitle').value = '';
    document.getElementById('uploadDesc').value  = '';
    document.getElementById('uploadSubject').value = '';
    document.getElementById('uploadType').value    = '';
    document.getElementById('uploadSubjectOther').style.display = 'none';
    document.getElementById('uploadTypeOther').style.display    = 'none';
    ['errTitle','errSubject','errType'].forEach(id => document.getElementById(id).textContent = '');
    document.querySelector('input[name="visibility"][value="public"]').checked = true;
}

// ── FILE TYPE HELPERS ─────────────────────────────────────────
function fileTypeIcon(type, url) {
    if (type === 'link') return '🔗';
    if (type === 'video') return '🎬';
    if (type === 'image') return '🖼️';
    if (type === 'slides') return '📊';
    if (type === 'exercise') return '📝';
    if (type === 'reviewer') return '📋';
    if (type === 'notes') return '📄';
    if (!url) return '📎';
    const ext = (url.split('.').pop()||'').toLowerCase();
    if (ext === 'pdf')  return '📄';
    if (['doc','docx'].includes(ext)) return '📝';
    if (['ppt','pptx'].includes(ext)) return '📊';
    if (['mp4','mov','webm'].includes(ext)) return '🎬';
    if (['jpg','jpeg','png','gif','webp'].includes(ext)) return '🖼️';
    if (['zip','rar'].includes(ext)) return '🗜️';
    return '📎';
}
function fileIconClass(type, url) {
    if (type === 'link')  return 'link';
    if (type === 'video') return 'video';
    if (type === 'image') return 'image';
    if (!url) return 'other';
    const ext = (url.split('.').pop()||'').toLowerCase();
    if (ext === 'pdf') return 'pdf';
    if (['doc','docx'].includes(ext)) return 'docx';
    if (['ppt','pptx'].includes(ext)) return 'pptx';
    if (['mp4','mov','webm'].includes(ext)) return 'video';
    if (['jpg','jpeg','png','gif','webp'].includes(ext)) return 'image';
    return 'other';
}
function fileTypeLabel(type) {
    const m = { notes:'Notes', exercise:'Exercise', slides:'Slides', video:'Video',
                image:'Image', link:'Link', reviewer:'Reviewer', others:'Other' };
    return m[type] || type || 'Other';
}
function fileEmojiFromName(name) {
    const ext = (name.split('.').pop()||'').toLowerCase();
    if (ext === 'pdf') return '📄';
    if (['doc','docx'].includes(ext)) return '📝';
    if (['ppt','pptx'].includes(ext)) return '📊';
    if (['mp4','mov','webm'].includes(ext)) return '🎬';
    if (['jpg','jpeg','png','gif','webp'].includes(ext)) return '🖼️';
    if (['zip','rar'].includes(ext)) return '🗜️';
    return '📎';
}
function formatBytes(b) {
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(1) + ' MB';
}

// ── UTILS ─────────────────────────────────────────────────────
function timeAgo(ts) {
    const s = Math.floor((Date.now() - new Date(ts)) / 1000);
    if (s < 60) return 'Just now';
    if (s < 3600) return `${Math.floor(s/60)}m ago`;
    if (s < 86400) return `${Math.floor(s/3600)}h ago`;
    if (s < 604800) return `${Math.floor(s/86400)}d ago`;
    return new Date(ts).toLocaleDateString();
}
function escH(t) {
    if (!t) return '';
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}


/* ============================================================
   RESOURCE DETAIL — append to resources.js
   Handles: detail view, ratings, comments, edit, report
   ============================================================ */

let currentResource = null;
let currentRating   = 0;

// ── OPEN DETAIL ───────────────────────────────────────────────
function openDetail(r) {
    currentResource = r;
    currentRating   = 0;

    document.getElementById('resDetailOverlay').style.display = 'block';
    document.body.style.overflow = 'hidden';

    // Scroll to top
    document.getElementById('resDetailOverlay').scrollTop = 0;

    // Icon
    document.getElementById('detailIcon').textContent =
        fileTypeIcon(r.file_type || 'other', r.file_url);

    // Title
    document.getElementById('detailTitle').textContent = r.title || 'Untitled';

    // Submeta
    const uploader = r.profiles
        ? `${r.profiles.first_name||''} ${r.profiles.last_name||''}`.trim() || r.profiles.username
        : 'Unknown';
    document.getElementById('detailSubmeta').innerHTML = `
        <span>${escH(uploader)}</span>
        <span class="dot">·</span>
        <span>${timeAgo(r.created_at)}</span>
        <span class="dot">·</span>
        <span class="res-vis-badge ${r.visibility==='private'?'private':'public'}">${r.visibility==='private'?'🔒 Friends':'🌐 Public'}</span>
    `;

    // Info sidebar
    document.getElementById('infoSubject').textContent  = r.subject || '—';
    document.getElementById('infoType').textContent     = fileTypeLabel(r.file_type || 'other');
    document.getElementById('infoVis').textContent      = r.visibility === 'private' ? 'Friends only' : 'Public';
    document.getElementById('infoUploader').textContent = uploader;
    document.getElementById('infoDate').textContent     = new Date(r.created_at).toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });

    if (r.view_count) {
        document.getElementById('infoViewsRow').style.display = '';
        document.getElementById('infoViews').textContent = r.view_count;
    }

    // Description
    if (r.description) {
        document.getElementById('detailDescSection').style.display = '';
        document.getElementById('detailDesc').textContent = r.description;
    } else {
        document.getElementById('detailDescSection').style.display = 'none';
    }

    // Text content
    if (r.content) {
        document.getElementById('detailContentSection').style.display = '';
        document.getElementById('detailContent').textContent = r.content;
    } else {
        document.getElementById('detailContentSection').style.display = 'none';
    }

    // File
    if (r.file_url && r.file_type !== 'link') {
        document.getElementById('detailFileSection').style.display = '';
        const btn = document.getElementById('detailFileBtn');
        btn.href = r.file_url;
        btn.textContent = '';
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Download File`;
    } else {
        document.getElementById('detailFileSection').style.display = 'none';
    }

    // Link
    if (r.file_url && r.file_type === 'link') {
        document.getElementById('detailLinkSection').style.display = '';
        document.getElementById('detailLinkBtn').href = r.file_url;
    } else {
        document.getElementById('detailLinkSection').style.display = 'none';
    }

    // Edit button — only for uploader
    const actions = document.getElementById('detailActions');
    const isOwner = r.uploaded_by === CURRENT_USER.id;
    actions.innerHTML = isOwner ? `
        <button class="res-detail-edit-btn" onclick="openEditModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
        </button>` : '';

    // Hide report for own resources
    document.getElementById('reportBtn').style.display = isOwner ? 'none' : '';

    // Load ratings + comments
    loadRatings(r.id);
    loadComments(r.id);
    setupRatingStars(r.id);

    // Increment view count (fire and forget)
    _supabase.from('resources')
        .update({ view_count: (r.view_count || 0) + 1 })
        .eq('id', r.id).then(() => {});
}

function closeDetail() {
    document.getElementById('resDetailOverlay').style.display = 'none';
    document.body.style.overflow = '';
    currentResource = null;
}

// ── RATINGS ───────────────────────────────────────────────────
async function loadRatings(resourceId) {
    try {
        const { data } = await _supabase
            .from('resource_ratings')
            .select('rating')
            .eq('resource_id', resourceId);

        if (!data || !data.length) {
            renderStarsDisplay(0, 0);
            return;
        }
        const avg = data.reduce((s, r) => s + r.rating, 0) / data.length;
        renderStarsDisplay(avg, data.length);

        // Check user's existing rating
        if (CURRENT_USER.id) {
            const { data: myRating } = await _supabase
                .from('resource_ratings')
                .select('rating')
                .eq('resource_id', resourceId)
                .eq('user_id', CURRENT_USER.id)
                .single();

            if (myRating) {
                currentRating = myRating.rating;
                highlightStars(currentRating);
                document.getElementById('rateLabel').textContent = `Your rating: ${myRating.rating}/5`;
            }
        }
    } catch(e) {}
}

function renderStarsDisplay(avg, count) {
    const display = document.getElementById('detailStarsDisplay');
    let html = '';
    for (let i = 1; i <= 5; i++) {
        html += `<span class="${i <= Math.round(avg) ? 'res-star-filled' : 'res-star-empty'}">★</span>`;
    }
    display.innerHTML = html;
    document.getElementById('detailRatingCount').textContent =
        count ? `${avg.toFixed(1)} (${count} rating${count !== 1 ? 's' : ''})` : 'No ratings yet';
}

function setupRatingStars(resourceId) {
    document.querySelectorAll('.res-rate-star').forEach(star => {
        const val = parseInt(star.dataset.v);
        star.addEventListener('mouseenter', () => highlightStars(val));
        star.addEventListener('mouseleave', () => highlightStars(currentRating));
        star.addEventListener('click', () => submitRating(resourceId, val));
    });
}

function highlightStars(val) {
    document.querySelectorAll('.res-rate-star').forEach(s => {
        const v = parseInt(s.dataset.v);
        s.classList.toggle('selected', v <= val);
        s.classList.toggle('hovered', false);
    });
}

async function submitRating(resourceId, val) {
    if (!CURRENT_USER.id) { alert('Please log in to rate resources.'); return; }
    try {
        await _supabase.from('resource_ratings').upsert({
            resource_id: resourceId,
            user_id:     CURRENT_USER.id,
            rating:      val
        }, { onConflict: 'resource_id,user_id' });

        currentRating = val;
        highlightStars(val);
        document.getElementById('rateLabel').textContent = `Your rating: ${val}/5 ✓`;
        loadRatings(resourceId);
    } catch(e) { alert('Rating failed: ' + e.message); }
}

// ── COMMENTS ──────────────────────────────────────────────────
async function loadComments(resourceId) {
    const el = document.getElementById('commentsList');
    el.innerHTML = '<div class="res-loading-sm">Loading comments…</div>';
    try {
        const { data, error } = await _supabase
            .from('resource_comments')
            .select('*, profiles(first_name, last_name, username)')
            .eq('resource_id', resourceId)
            .order('created_at', { ascending: false });

        if (error) throw error;
        document.getElementById('commentCount').textContent = data.length;

        if (!data.length) {
            el.innerHTML = '<div class="res-loading-sm">No comments yet. Be the first!</div>';
            return;
        }
        el.innerHTML = data.map(c => {
            const name = c.profiles
                ? `${c.profiles.first_name||''} ${c.profiles.last_name||''}`.trim() || c.profiles.username
                : 'Unknown';
            const isOwn = c.user_id === CURRENT_USER.id;
            return `
                <div class="res-comment-item">
                    <div class="res-comment-avatar" style="width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        ${((c.profiles?.first_name||'?')[0]+(c.profiles?.last_name||'?')[0]).toUpperCase()}
                    </div>
                    <div class="res-comment-body">
                        <div>
                            <span class="res-comment-name">${escH(name)}</span>
                            <span class="res-comment-time">${timeAgo(c.created_at)}</span>
                            ${isOwn ? `<button class="res-comment-del" onclick="deleteComment('${c.id}')">✕</button>` : ''}
                        </div>
                        <div class="res-comment-text">${escH(c.comment)}</div>
                    </div>
                </div>`;
        }).join('');
    } catch(e) {
        el.innerHTML = '<div class="res-loading-sm">Failed to load comments.</div>';
    }
}

async function submitComment() {
    if (!CURRENT_USER.id) { alert('Please log in to comment.'); return; }
    const input = document.getElementById('commentInput');
    const text  = input.value.trim();
    if (!text) return;
    if (!currentResource) return;

    const btn = document.querySelector('.res-comment-submit');
    btn.disabled = true; btn.textContent = 'Posting…';

    try {
        const { error } = await _supabase.from('resource_comments').insert({
            resource_id: currentResource.id,
            user_id:     CURRENT_USER.id,
            comment:     text
        });
        if (error) throw error;
        input.value = '';
        input.style.height = '';
        loadComments(currentResource.id);
    } catch(e) {
        alert('Failed to post comment: ' + e.message);
    } finally {
        btn.disabled = false; btn.textContent = 'Post';
    }
}

async function deleteComment(commentId) {
    if (!confirm('Delete this comment?')) return;
    try {
        await _supabase.from('resource_comments').delete().eq('id', commentId);
        loadComments(currentResource.id);
    } catch(e) { alert('Delete failed: ' + e.message); }
}

// ── EDIT MODAL ────────────────────────────────────────────────
function openEditModal() {
    if (!currentResource) return;
    const r = currentResource;

    document.getElementById('editTitle').value   = r.title || '';
    document.getElementById('editDesc').value    = r.description || '';
    document.getElementById('editContent').value = r.content || '';
    document.getElementById('editSubject').value = r.subject || '';
    document.getElementById('editType').value    = r.file_type || '';

    // Others fields
    checkEditOtherSubject();
    checkEditOtherType();

    const vis = r.visibility === 'private' ? 'private' : 'public';
    document.querySelector(`input[name="editVis"][value="${vis}"]`).checked = true;

    document.getElementById('editModal').classList.add('open');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
}

function checkEditOtherSubject() {
    const sel = document.getElementById('editSubject').value;
    document.getElementById('editSubjectOther').style.display = sel === 'others' ? 'block' : 'none';
}
function checkEditOtherType() {
    const sel = document.getElementById('editType').value;
    document.getElementById('editTypeOther').style.display = sel === 'others' ? 'block' : 'none';
}

async function saveEdit() {
    if (!currentResource) return;

    const title   = document.getElementById('editTitle').value.trim();
    const subject = document.getElementById('editSubject').value;
    const subjOth = document.getElementById('editSubjectOther').value.trim();
    const type    = document.getElementById('editType').value;
    const typeOth = document.getElementById('editTypeOther').value.trim();
    const desc    = document.getElementById('editDesc').value.trim();
    const content = document.getElementById('editContent').value.trim();
    const vis     = document.querySelector('input[name="editVis"]:checked').value;

    if (!title)   { alert('Title is required.'); return; }
    if (!subject) { alert('Please select a subject.'); return; }
    if (subject === 'others' && !subjOth) { alert('Please specify the subject.'); return; }
    if (!type)    { alert('Please select a type.'); return; }

    const finalSubject = subject === 'others' ? subjOth : subject;
    const finalType    = type === 'others' ? (typeOth || 'others') : type;

    const btn = document.querySelector('#editModal .btn-primary');
    btn.disabled = true; btn.textContent = 'Saving…';

    try {
        const { error } = await _supabase
            .from('resources')
            .update({
                title:           title,
                description:     desc || null,
                content:         content || null,
                subject:         finalSubject,
                file_type:       finalType,
                visibility:      vis,
                education_level: vis,
            })
            .eq('id', currentResource.id);

        if (error) throw error;

        // Update local data
        currentResource = { ...currentResource, title, description: desc, content, subject: finalSubject, file_type: finalType, visibility: vis };
        closeEditModal();

        // Refresh detail view
        openDetail(currentResource);

        // Refresh feed
        loadResources();
    } catch(e) {
        alert('Save failed: ' + e.message);
    } finally {
        btn.disabled = false; btn.textContent = '💾 Save Changes';
    }
}

// ── REPORT ────────────────────────────────────────────────────
function openReport()  { document.getElementById('reportModal').classList.add('open'); }
function closeReport() { document.getElementById('reportModal').classList.remove('open'); }

async function submitReport() {
    const reason = document.querySelector('input[name="reportReason"]:checked');
    if (!reason) { alert('Please select a reason.'); return; }
    const details = document.getElementById('reportDetails').value.trim();
    const fullReason = details ? `${reason.value}: ${details}` : reason.value;

    try {
        const { error } = await _supabase.from('reports').insert({
            reported_by:           CURRENT_USER.id,
            reported_content_type: 'resource',
            reported_content_id:   currentResource.id,
            reason:                fullReason,
            status:                'pending'
        });
        if (error) throw error;
        closeReport();
        alert('✅ Report submitted. Our team will review it soon.');
    } catch(e) {
        alert('Report failed: ' + e.message);
    }
}

// ── UPLOAD MODAL: add text-only support ───────────────────────
// Patch the existing uploadModal to add a content textarea in Step 2
// This runs after DOM loads and inserts the field
document.addEventListener('DOMContentLoaded', () => {
    // Add "Text Content" field after description in Step 2
    const descGroup = document.getElementById('uploadDesc')?.closest('.form-group') ||
                      document.getElementById('uploadDesc')?.parentElement;
    if (descGroup) {
        const textGroup = document.createElement('div');
        textGroup.innerHTML = `
            <label class="res-label" style="margin-top:8px;">
                Text Content
                <span style="color:var(--text-light);font-weight:400;">(optional — write notes, summaries, or study material directly)</span>
            </label>
            <div class="res-text-area-wrap">
                <textarea id="uploadContent" class="res-input" rows="5"
                    style="resize:vertical;"
                    placeholder="Write your study notes, summaries, or any text content here…"
                    oninput="document.getElementById('contentCounter').textContent = this.value.length + ' chars'"></textarea>
                <span class="res-text-counter" id="contentCounter">0 chars</span>
            </div>`;
        descGroup.parentNode.insertBefore(textGroup, descGroup.nextSibling);
    }

    // Patch submitUpload to include content
    const origSubmit = window.submitUpload;
    if (origSubmit) {
        window.submitUpload = async function() {
            // temporarily handled by overriding below
        };
    }
});

// ── CARD CLICK → OPEN DETAIL ──────────────────────────────────
// Override the original cardHTML to make clicking open detail
// We patch markViewed to also open detail
const _origMarkViewed = window.markViewed;
window.markViewed = function(r) {
    if (_origMarkViewed) _origMarkViewed(r);
    openDetail(r);
};

// ── UTILITIES ─────────────────────────────────────────────────
function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = (el.scrollHeight) + 'px';
}

// Escape key closes detail
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (document.getElementById('reportModal').classList.contains('open')) { closeReport(); return; }
        if (document.getElementById('editModal').classList.contains('open'))   { closeEditModal(); return; }
        if (document.getElementById('resDetailOverlay').style.display !== 'none') { closeDetail(); return; }
    }
});
