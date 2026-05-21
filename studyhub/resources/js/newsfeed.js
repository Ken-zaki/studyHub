/* ============================================================
   public/js/newsfeed.js  — StudyHub Newsfeed
   Reddit + Facebook fusion: votes, tags, sort modes, discover
   ============================================================ */

const _sb = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// ── STATE ────────────────────────────────────────────────────
let currentSort      = 'trending';
let trendingMode     = 'relevant'; // 'relevant' = hot-score | 'latest' = newest first
let currentTimeRange = 'all';
let currentTagFilter = null;
let currentVis       = 'public';
let stagedMedia      = [];
let stagedFiles      = [];
let stagedLink       = null;
let stagedTags       = [];
let composerOpen     = false;
let activePostId     = null;
let activeReportId   = null;
let activeShareId    = null;
let editingPostId    = null;
let followingSet     = new Set();
let replyingTo       = null;

let editStagedMedia   = [];
let editStagedFiles   = [];
let editExistingMedia = [];
let editExistingFiles = [];
let editStagedLink    = null;
let editStagedTags    = [];

let lightboxImages = [];
let lightboxIndex  = 0;

const STUDY_TIPS = [
    "Use the Pomodoro Technique: 25 min focus, 5 min break.",
    "Teach what you learned — it's the best way to remember it.",
    "Spaced repetition beats cramming every single time.",
    "Review your notes within 24 hours to retain 80% more.",
    "Sleep consolidates memory — don't pull all-nighters before exams.",
    "Write by hand when studying; it boosts recall over typing.",
    'Ask "why?" for every fact — understanding > memorising.',
];

const SUBJECT_TAGS = [
    { label: 'Mathematics',  value: 'Mathematics',  cls: 'tag-color-math'        },
    { label: 'Physics',      value: 'Physics',       cls: 'tag-color-physics'     },
    { label: 'Chemistry',    value: 'Chemistry',     cls: 'tag-color-chemistry'   },
    { label: 'Biology',      value: 'Biology',       cls: 'tag-color-biology'     },
    { label: 'Engineering',  value: 'Engineering',   cls: 'tag-color-engineering' },
    { label: 'CS / IT',      value: 'CS',            cls: 'tag-color-cs'          },
    { label: 'History',      value: 'History',       cls: 'tag-color-history'     },
    { label: 'Literature',   value: 'Literature',    cls: 'tag-color-literature'  },
    { label: 'Economics',    value: 'Economics',     cls: 'tag-color-economics'   },
    { label: 'Other',        value: 'Other',         cls: 'tag-color-other'       },
];

// ── INIT ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
    renderAvatar(currentUser, 'cpAvatar');
    renderAvatar(currentUser, 'commentsAvatar');
    document.getElementById('studyTip').textContent =
        STUDY_TIPS[Math.floor(Math.random() * STUDY_TIPS.length)];

    renderComposerTags();
    renderSidebarTagGrid();
    await loadFollowing();
    loadFeed();
    loadWhoToFollow();
    loadTrending();
    loadTrendingResources();
    loadActiveStudyGroups();

    document.addEventListener('click', e => {
        if (!e.target.closest('.cp-vis-wrap'))
            document.getElementById('cpVisMenu').style.display = 'none';
        if (!e.target.closest('.sort-filter-wrap'))
            document.querySelector('.sort-filter-menu')?.classList.remove('open');
        if (!e.target.closest('.post-menu-wrap'))
            document.querySelectorAll('.post-menu-dropdown.open')
                .forEach(el => el.classList.remove('open'));
    });

    document.addEventListener('keydown', e => {
        const lb = document.getElementById('lightbox');
        if (!lb?.classList.contains('open')) return;
        if (e.key === 'ArrowRight') lightboxNext();
        if (e.key === 'ArrowLeft')  lightboxPrev();
        if (e.key === 'Escape')     closeLightbox();
    });
});

// ── AVATAR HELPERS ────────────────────────────────────────────
function renderAvatar(user, elId) {
    const el = document.getElementById(elId);
    if (!el) return;
    if (user?.profile_photo_url) {
        el.innerHTML = '<img src="' + escH(user.profile_photo_url) + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">';
    } else {
        const fn = user?.first_name || 'U';
        const ln = user?.last_name  || '';
        el.textContent = (fn[0] + (ln[0]||'')).toUpperCase();
    }
}

function avatarHTML(user, size) {
    size = size || 40;
    const radius = Math.round(size * 0.3);
    const fs     = Math.round(size * 0.35);
    const style  = 'width:' + size + 'px;height:' + size + 'px;border-radius:' + radius + 'px;' +
        'background:linear-gradient(135deg,var(--primary),var(--primary-dark));' +
        'color:white;font-size:' + fs + 'px;font-weight:700;' +
        'display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;';
    if (user && user.profile_photo_url) {
        return '<div style="' + style + '"><img src="' + escH(user.profile_photo_url) + '" style="width:100%;height:100%;object-fit:cover;"></div>';
    }
    const fn = (user && user.first_name) ? user.first_name : 'U';
    const ln = (user && user.last_name)  ? user.last_name  : '';
    return '<div style="' + style + '">' + (fn[0] + (ln[0]||'')).toUpperCase() + '</div>';
}

function displayName(author) {
    if (!author) return '@unknown';
    if (author.username) return '@' + author.username;
    const name = ((author.first_name||'') + (author.last_name||'')).toLowerCase();
    return name ? '@' + name : '@unknown';
}

// ── FOLLOW SYSTEM ─────────────────────────────────────────────
async function loadFollowing() {
    if (!currentUser.id) return;
    try {
        const res = await _sb.from('follows').select('following_id').eq('follower_id', currentUser.id);
        if (res.error) console.warn('[follows]', res.error.message);
        followingSet = new Set((res.data||[]).map(function(r){ return r.following_id; }));
    } catch(e) { console.error('[follows fetch]', e); }
}

async function toggleFollow(userId, btn) {
    if (!currentUser.id) return;
    const isFollowing = followingSet.has(userId);
    try {
        if (isFollowing) {
            await _sb.from('follows').delete().eq('follower_id', currentUser.id).eq('following_id', userId);
            followingSet.delete(userId);
        } else {
            await _sb.from('follows').insert({ follower_id: currentUser.id, following_id: userId });
            followingSet.add(userId);
        }
        document.querySelectorAll('[data-follow-uid="' + userId + '"]').forEach(function(b) {
            const now = followingSet.has(userId);
            b.textContent = now ? 'Following' : '+ Follow';
            b.classList.toggle('following', now);
        });
    } catch(e) { console.error(e); }
}

// ── SORT ──────────────────────────────────────────────────────
// Two tabs: 'trending' and 'following'
function switchSort(sort) {
    currentSort = sort;
    // Main tab bar
    ['trending','following'].forEach(function(s) {
        var idMap = { trending:'sortTrending', following:'sortFollowing' };
        document.getElementById(idMap[s])?.classList.toggle('active', s === sort);
    });
    // Show sub-filter only on Trending tab
    var sf = document.getElementById('trendingSubfilter');
    if (sf) sf.style.display = (sort === 'trending') ? '' : 'none';
    // Sidebar sort buttons (inline-styled)
    var sideIds = { trending:'sidebarSortHot', following:'sidebarSortNew' };
    Object.entries(sideIds).forEach(function(entry) {
        var btn = document.getElementById(entry[1]);
        if (!btn) return;
        var isActive = entry[0] === sort;
        btn.style.background  = isActive ? 'var(--primary)' : 'var(--bg-card)';
        btn.style.color       = isActive ? 'white' : 'var(--text-secondary)';
        btn.style.borderColor = isActive ? 'var(--primary)' : 'var(--border)';
    });
    loadFeed();
}

// Sub-filter within Trending: 'relevant' (hot-score) | 'latest' (newest first)
function setTrendingMode(mode) {
    trendingMode = mode;
    document.getElementById('subRelevant')?.classList.toggle('active', mode === 'relevant');
    document.getElementById('subLatest')?.classList.toggle('active',   mode === 'latest');
    loadFeed();
}

// Keep these as no-ops so nothing breaks if called elsewhere
function toggleTimeRangeMenu() {}
function setTimeRange(range, label) {}

// ── SIDEBAR FILTER WIDGET ─────────────────────────────────────
// sidebarSort maps sidebar button IDs to the two feed modes
function sidebarSort(sort) {
    // sidebar still has Hot/New/Top buttons — map them all to trending except 'following'
    var mapped = (sort === 'following') ? 'following' : 'trending';
    switchSort(mapped);
}

function renderSidebarTagGrid() {
    var grid = document.getElementById('sidebarTagGrid');
    if (!grid) return;
    var all = [{ label: 'All', value: null }].concat(SUBJECT_TAGS);
    grid.innerHTML = all.map(function(t) {
        var isActive = currentTagFilter === t.value;
        var style = 'padding:4px 10px;border-radius:20px;border:1.5px solid ' +
            (isActive ? 'var(--primary);background:var(--primary);color:white;' : 'var(--border);background:var(--bg-card);color:var(--text-secondary);') +
            'font-family:\'DM Sans\',sans-serif;font-size:11px;font-weight:600;cursor:pointer;margin-bottom:2px;';
        return '<button style="' + style + '" ' +
            'onclick="sidebarTagFilter(' + (t.value === null ? 'null' : "'" + t.value + "'") + ')">' +
            escH(t.label) + '</button>';
    }).join('');
}

function sidebarTagFilter(val) {
    setTagFilter(val);
    renderSidebarTagGrid();
}

// ── TAG FILTER ROW ────────────────────────────────────────────
function renderTagFilterRow() {
    var row = document.getElementById('tagFilterRow');
    if (!row) return;
    var all = [{ label: 'All', value: null }].concat(SUBJECT_TAGS);
    row.innerHTML = all.map(function(t) {
        return '<button class="tag-filter-chip ' + (currentTagFilter === t.value ? 'active' : '') + '" ' +
            'onclick="setTagFilter(' + (t.value === null ? 'null' : "'" + t.value + "'") + ')">' +
            escH(t.label) + '</button>';
    }).join('');
}

function setTagFilter(val) {
    currentTagFilter = val;
    renderTagFilterRow();
    renderSidebarTagGrid();
    loadFeed();
}

// ── COMPOSER TAGS ─────────────────────────────────────────────
function renderComposerTags() {
    var row = document.getElementById('cpTagRow');
    if (!row) return;
    row.innerHTML = SUBJECT_TAGS.map(function(t) {
        return '<button class="cp-tag-chip ' + (stagedTags.includes(t.value) ? 'selected' : '') + '" ' +
            'onclick="toggleComposerTag(\'' + t.value + '\')">' + escH(t.label) + '</button>';
    }).join('');
}
function toggleComposerTag(val) {
    if (stagedTags.includes(val)) stagedTags = stagedTags.filter(function(t){ return t !== val; });
    else stagedTags.push(val);
    renderComposerTags();
}
function renderEditTags() {
    var row = document.getElementById('editTagRow');
    if (!row) return;
    row.innerHTML = SUBJECT_TAGS.map(function(t) {
        return '<button class="cp-tag-chip ' + (editStagedTags.includes(t.value) ? 'selected' : '') + '" ' +
            'onclick="toggleEditTag(\'' + t.value + '\')">' + escH(t.label) + '</button>';
    }).join('');
}
function toggleEditTag(val) {
    if (editStagedTags.includes(val)) editStagedTags = editStagedTags.filter(function(t){ return t !== val; });
    else editStagedTags.push(val);
    renderEditTags();
}

// ── FEED LOAD ─────────────────────────────────────────────────
async function loadFeed() {
    var feed = document.getElementById('feed');
    feed.innerHTML = '<div class="loading-state">Loading posts...</div>';
    try {
        var SEL = '*, profiles(id, first_name, last_name, username, profile_photo_url)';
        var allMap = new Map();
        var queries = [];

        if (currentSort === 'following') {
            if (!followingSet.size) {
                feed.innerHTML = '<div class="feed-empty"><div class="ei">&#128065;</div><p>You\'re not following anyone yet.</p></div>';
                return;
            }
            queries.push(_sb.from('posts').select(SEL).in('user_id', [...followingSet]).eq('visibility','public').order('created_at',{ascending:false}).limit(60));
            if (currentUser.id) queries.push(_sb.from('posts').select(SEL).eq('user_id',currentUser.id).order('created_at',{ascending:false}).limit(20));
        } else {
            // 'trending' — fetch latest public posts; sorted client-side by hot-score after vote counts
            queries.push(_sb.from('posts').select(SEL).eq('visibility','public').order('created_at',{ascending:false}).limit(80));
            if (currentUser.id) queries.push(_sb.from('posts').select(SEL).eq('user_id',currentUser.id).order('created_at',{ascending:false}).limit(20));
        }

        var results = await Promise.all(queries);
        results.forEach(function(r) {
            if (r.error) console.warn('[feed]', r.error.message);
            (r.data||[]).forEach(function(p){ allMap.set(p.id, p); });
        });

        var all = [...allMap.values()];

        if (currentTagFilter) {
            all = all.filter(function(p) {
                var tags = parseJSON(p.tags, []);
                return Array.isArray(tags) && tags.includes(currentTagFilter);
            });
        }
        if (currentTimeRange === 'today') {
            var cutoff = Date.now() - 86400000;
            all = all.filter(function(p){ return new Date(p.created_at).getTime() > cutoff; });
        } else if (currentTimeRange === 'week') {
            var cutoff = Date.now() - 604800000;
            all = all.filter(function(p){ return new Date(p.created_at).getTime() > cutoff; });
        }

        if (!all.length) {
            feed.innerHTML = '<div class="feed-empty"><div class="ei">&#128205;</div><p>No posts here yet. Be the first to share something!</p></div>';
            return;
        }

        feed.innerHTML = all.map(function(p){ return postCardHTML(p,0,0,0,null,0,0); }).join('');

        var postIds   = all.map(function(p){ return p.id; });
        var sharedIds = all.filter(function(p){ return p.shared_post_id; }).map(function(p){ return p.shared_post_id; });

        var fetches = [
            _sb.from('posts_votes').select('post_id,vote').in('post_id',postIds),
            _sb.from('post_comments').select('post_id').in('post_id',postIds),
            currentUser.id
                ? _sb.from('posts_votes').select('post_id,vote').in('post_id',postIds).eq('user_id',currentUser.id)
                : Promise.resolve({data:[]}),
            sharedIds.length
                ? _sb.from('posts').select(SEL).in('id',sharedIds)
                : Promise.resolve({data:[]}),
        ];

        var fetched = await Promise.all(fetches);
        var scores={}, upvoteCounts={}, downvoteCounts={}, commentCounts={}, myVotes={}, sharedMap={};

        (fetched[0].data||[]).forEach(function(r){
            scores[r.post_id]=(scores[r.post_id]||0)+r.vote;
            if(r.vote===1)  upvoteCounts[r.post_id]=(upvoteCounts[r.post_id]||0)+1;
            if(r.vote===-1) downvoteCounts[r.post_id]=(downvoteCounts[r.post_id]||0)+1;
        });
        (fetched[1].data||[]).forEach(function(r){ commentCounts[r.post_id]=(commentCounts[r.post_id]||0)+1; });
        (fetched[2].data||[]).forEach(function(r){ myVotes[r.post_id]=r.vote; });
        (fetched[3].data||[]).forEach(function(p){ sharedMap[p.id]=p; });

        // Trending: Relevant = hot-score, Latest = newest first. Following = always newest first.
        if (currentSort === 'trending' && trendingMode === 'relevant') {
            all.sort(function(a,b){ return hotScore(scores[b.id]||0,b.created_at)-hotScore(scores[a.id]||0,a.created_at); });
        } else {
            all.sort(function(a,b){ return new Date(b.created_at)-new Date(a.created_at); });
        }

        feed.innerHTML = all.map(function(p){
            return postCardHTML(p, scores[p.id]||0, commentCounts[p.id]||0, myVotes[p.id]||0, sharedMap[p.shared_post_id], upvoteCounts[p.id]||0, downvoteCounts[p.id]||0);
        }).join('');

    } catch(err) {
        feed.innerHTML = '<div class="alert-error">Failed to load: ' + escH(err.message) + '</div>';
    }
}

function hotScore(score, createdAt) {
    var ageHours = (Date.now() - new Date(createdAt)) / 3600000;
    return score / Math.pow(ageHours + 2, 0.8);
}

// ── POST CARD HTML ────────────────────────────────────────────
function postCardHTML(post, score, commentCount, myVote, sharedOriginal, upvoteCount, downvoteCount) {
    var author      = post.profiles || {};
    var isOwn       = post.user_id === currentUser.id;
    var isFollowing = followingSet.has(post.user_id);
    var uname       = displayName(author);
    var ago         = timeAgo(post.created_at);
    var visMap      = { public:'🌐', only_me:'🔒' };
    var visIcon     = visMap[post.visibility] || '🌐';

    var media    = parseJSON(post.media_urls, []);
    var files    = parseJSON(post.file_urls,  []);
    var linkMeta = parseJSON(post.link_meta,  null);
    var tags     = parseJSON(post.tags,       []);

    var upvoted   = myVote ===  1;
    var downvoted = myVote === -1;
    var scoreClass = score > 0 ? 'positive' : score < 0 ? 'negative' : '';

    var tagsHTML = '';
    if (tags.length) {
        var tagDefs = SUBJECT_TAGS.reduce(function(m,t){ m[t.value]=t; return m; }, {});
        tagsHTML = '<div class="post-tags-row">' +
            tags.map(function(t){
                var def = tagDefs[t] || { label:t, cls:'tag-color-other' };
                return '<span class="post-tag-badge ' + def.cls + '" onclick="setTagFilter(\'' + escH(t) + '\')">' + escH(def.label) + '</span>';
            }).join('') + '</div>';
    }

    var sharedHTML = '';
    if (post.shared_post_id && sharedOriginal) {
        var og       = sharedOriginal;
        var ogA      = og.profiles || {};
        var ogMedia  = parseJSON(og.media_urls, []);
        var ogLink   = parseJSON(og.link_meta,  null);
        var ogTags   = parseJSON(og.tags,       []);
        var ogTagsHTML = '';
        if (ogTags.length) {
            var td = SUBJECT_TAGS.reduce(function(m,t){ m[t.value]=t; return m; }, {});
            ogTagsHTML = '<div class="post-tags-row" style="margin-top:6px;">' +
                ogTags.map(function(t){ var d=td[t]||{label:t,cls:'tag-color-other'}; return '<span class="post-tag-badge '+d.cls+'">'+escH(d.label)+'</span>'; }).join('')+'</div>';
        }
        var ogMediaHTML = ogMedia.length ? buildMediaGrid(ogMedia, og.id+'_og') : '';
        var ogLinkHTML  = '';
        if (ogLink && ogLink.url) {
            ogLinkHTML = '<a class="post-link-preview" href="'+escH(ogLink.url)+'" target="_blank" rel="noopener" style="margin:8px 0 0;">' +
                (ogLink.image ? '<img class="post-link-img" src="'+escH(ogLink.image)+'" alt="">' : '') +
                '<div class="post-link-info"><div class="post-link-title">'+escH(ogLink.title||ogLink.url)+'</div><div class="post-link-url">'+escH(ogLink.url)+'</div></div></a>';
        }
        sharedHTML = '<div class="shared-post-embed"><div class="shared-post-header">' +
            '<a href="/profile/'+escH(ogA.username||og.user_id)+'" style="text-decoration:none;display:flex;align-items:center;gap:8px;">' +
            avatarHTML(ogA,28) +
            '<div><div style="font-size:13px;font-weight:700;color:var(--primary);">'+escH(displayName(ogA))+'</div>' +
            '<div style="font-size:11px;color:var(--text-light);">'+timeAgo(og.created_at)+'</div></div></a></div>' +
            ogTagsHTML +
            (og.content ? '<div class="post-text" style="font-size:14px;margin-top:6px;">'+escH(og.content)+'</div>' : '') +
            ogMediaHTML + ogLinkHTML + '</div>';
    }

    var mediaHTML = media.length ? buildMediaGrid(media, post.id) : '';

    var filesHTML = '';
    if (files.length) {
        filesHTML = '<div class="post-files">' + files.map(function(f){
            var fname = f.name || (f.url||'').split('/').pop() || 'File';
            var fsize = f.size ? formatBytes(f.size) : '';
            return '<a class="post-file-row" href="'+escH(f.url)+'" target="_blank" download>' +
                '<span class="post-file-icon">'+fileEmojiFromName(fname)+'</span>' +
                '<span class="post-file-name">'+escH(fname)+'</span>' +
                (fsize ? '<span class="post-file-size">'+fsize+'</span>' : '') + '</a>';
        }).join('') + '</div>';
    }

    var linkHTML = '';
    if (linkMeta && linkMeta.url) {
        linkHTML = '<a class="post-link-preview" href="'+escH(linkMeta.url)+'" target="_blank" rel="noopener">' +
            (linkMeta.image ? '<img class="post-link-img" src="'+escH(linkMeta.image)+'" alt="">' : '') +
            '<div class="post-link-info"><div class="post-link-title">'+escH(linkMeta.title||linkMeta.url)+'</div>' +
            '<div class="post-link-url">'+escH(linkMeta.url)+'</div></div></a>';
    }

    var text     = post.content || '';
    var longText = text.length > 300;
    var textHTML = text ? (
        '<div class="post-text ' + (longText ? 'collapsed' : '') + '" id="postText-'+post.id+'">'+escH(text)+'</div>' +
        (longText ? '<button class="post-see-more" onclick="togglePostText(\''+post.id+'\',this)">See more</button>' : '')
    ) : '';

    var menuItems = isOwn
        ? '<button class="post-menu-item" onclick="openEditPost(\''+post.id+'\')">&#9999;&#65039; Edit Post</button>' +
          '<button class="post-menu-item danger" onclick="deletePost(\''+post.id+'\')">&#128465;&#65039; Delete Post</button>'
        : '<button class="post-menu-item" onclick="openReportModal(\''+post.id+'\')">&#128681; Report Post</button>';

    var followBtn = '';
    if (!isOwn && post.user_id) {
        followBtn = '<button class="post-follow-btn '+(isFollowing?'following':'')+'" data-follow-uid="'+post.user_id+'" onclick="toggleFollow(\''+post.user_id+'\',this)">' +
            (isFollowing ? 'Following' : '+ Follow') + '</button>';
    }

    var commentLabel = commentCount > 0
        ? commentCount+' comment'+(commentCount!==1?'s':'') : '';

    return '<div class="post-card" id="post-'+post.id+'">' +
        '<div class="post-main">' +
            '<div class="post-header">' +
                '<div class="post-author">' +
                    '<a href="/profile/'+escH(author.username||post.user_id)+'" class="post-author-avatar-link" onclick="event.stopPropagation()">'+avatarHTML(author,36)+'</a>' +
                    '<div>' +
                        '<div class="post-author-name">' +
                            '<a href="/profile/'+escH(author.username||post.user_id)+'" class="post-author-name-link" onclick="event.stopPropagation()">'+escH(uname)+'</a>' +
                            followBtn +
                        '</div>' +
                        '<div class="post-author-meta"><span>'+ago+'</span><span>&middot;</span><span class="post-vis-badge" title="'+post.visibility+'">'+visIcon+'</span></div>' +
                    '</div>' +
                '</div>' +
                '<div class="post-menu-wrap">' +
                    '<button class="post-menu-btn" onclick="togglePostMenu(\''+post.id+'\')">&#8943;</button>' +
                    '<div class="post-menu-dropdown" id="postMenu-'+post.id+'">'+menuItems+'</div>' +
                '</div>' +
            '</div>' +

            '<div class="post-body">'+tagsHTML+textHTML+'</div>' +

            mediaHTML + filesHTML + linkHTML +
            (sharedHTML ? '<div style="padding:0 14px 10px;">'+sharedHTML+'</div>' : '') +

            '<div class="post-action-bar-b">' +
                '<div class="vote-group-b">' +
                    '<button class="vote-btn-b '+(upvoted?'upvoted-b':'')+'" id="upBtn-'+post.id+'" onclick="castVote(\''+post.id+'\',1)" title="Upvote">' +
                        '<svg viewBox="0 0 24 24" fill="'+(upvoted?'currentColor':'none')+'" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px;"><polyline points="18 15 12 9 6 15"/></svg>' +
                        '<span class="vote-count upvote-count" id="upCount-'+post.id+'">'+(upvoteCount||0)+'</span>' +
                    '</button>' +
                    '<button class="vote-btn-b '+(downvoted?'downvoted-b':'')+'" id="downBtn-'+post.id+'" onclick="castVote(\''+post.id+'\',-1)" title="Downvote">' +
                        '<svg viewBox="0 0 24 24" fill="'+(downvoted?'currentColor':'none')+'" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px;"><polyline points="6 9 12 15 18 9"/></svg>' +
                        '<span class="vote-count downvote-count" id="downCount-'+post.id+'">'+(downvoteCount||0)+'</span>' +
                    '</button>' +
                    '<span id="score-'+post.id+'" style="display:none;" class="vote-score '+scoreClass+'">'+score+'</span>' +
                '</div>' +
                '<div style="display:flex;gap:2px;">' +
                    '<button class="post-action-btn-b" onclick="openComments(\''+post.id+'\')">' +
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>' +
                        (commentCount > 0 ? commentLabel : 'Comment') +
                    '</button>' +
                    '<button class="post-action-btn-b" onclick="openShareModal(\''+post.id+'\')">' +
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>Share' +
                    '</button>' +
                '</div>' +
            '</div>' +
        '</div>' +
    '</div>';
}

// ── VOTING ────────────────────────────────────────────────────
async function castVote(postId, value) {
    if (!currentUser.id) return;
    var scoreEl    = document.getElementById('score-'+postId);
    var upBtn      = document.getElementById('upBtn-'+postId);
    var downBtn    = document.getElementById('downBtn-'+postId);
    var upCountEl  = document.getElementById('upCount-'+postId);
    var downCountEl= document.getElementById('downCount-'+postId);
    var prevUp     = upBtn?.classList.contains('upvoted');
    var prevDown   = downBtn?.classList.contains('downvoted');
    var prevVote   = prevUp ? 1 : prevDown ? -1 : 0;
    var newVote    = (value===1&&prevUp)||(value===-1&&prevDown) ? 0 : value;
    var diff = newVote - prevVote;
    var prevScore = parseInt(scoreEl?.textContent||'0');
    var prevUpCount   = parseInt(upCountEl?.textContent||'0');
    var prevDownCount = parseInt(downCountEl?.textContent||'0');

    if (scoreEl) {
        var ns = prevScore + diff;
        scoreEl.textContent = ns;
        scoreEl.className = 'vote-score '+(ns>0?'positive':ns<0?'negative':'');
    }
    // Update individual counts
    if (upCountEl) {
        var newUpCount = prevUpCount + (newVote===1 ? 1 : 0) - (prevVote===1 ? 1 : 0);
        upCountEl.textContent = Math.max(0, newUpCount);
    }
    if (downCountEl) {
        var newDownCount = prevDownCount + (newVote===-1 ? 1 : 0) - (prevVote===-1 ? 1 : 0);
        downCountEl.textContent = Math.max(0, newDownCount);
    }
    upBtn?.classList.toggle('upvoted',   newVote===1);
    downBtn?.classList.toggle('downvoted', newVote===-1);
    if (upBtn)   upBtn.querySelector('svg').setAttribute('fill',   newVote===1  ? 'currentColor':'none');
    if (downBtn) downBtn.querySelector('svg').setAttribute('fill', newVote===-1 ? 'currentColor':'none');

    try {
        if (newVote === 0) {
            await _sb.from('posts_votes').delete().eq('post_id',postId).eq('user_id',currentUser.id);
        } else {
            await _sb.from('posts_votes').upsert(
                { post_id:postId, user_id:currentUser.id, vote:newVote },
                { onConflict:'post_id,user_id' }
            );
        }
    } catch(e) {
        console.error('[vote]', e);
        if (scoreEl) scoreEl.textContent = prevScore;
        if (upCountEl)   upCountEl.textContent   = prevUpCount;
        if (downCountEl) downCountEl.textContent = prevDownCount;
        upBtn?.classList.toggle('upvoted',    prevVote===1);
        downBtn?.classList.toggle('downvoted', prevVote===-1);
        if (upBtn)   upBtn.querySelector('svg').setAttribute('fill',   prevVote===1  ? 'currentColor':'none');
        if (downBtn) downBtn.querySelector('svg').setAttribute('fill', prevVote===-1 ? 'currentColor':'none');
    }
}

// ── MEDIA GRID ────────────────────────────────────────────────
function buildMediaGrid(media, postId) {
    var cls = media.length===1?'count-1':media.length===2?'count-2':media.length===3?'count-3':media.length===4?'count-4':'count-many';
    var shown     = media.slice(0,5);
    var moreCount = media.length - 5;
    var imageUrls = media.filter(function(u){ return !/\.(mp4|mov|webm|ogg)(\?|$)/i.test(u); });
    var imgCounter = 0;
    return '<div class="post-media '+cls+'">' +
        shown.map(function(url, i){
            var isVideo = /\.(mp4|mov|webm|ogg)(\?|$)/i.test(url);
            var inner   = isVideo
                ? '<video src="'+escH(url)+'" controls preload="none"></video>'
                : '<img src="'+escH(url)+'" alt="" loading="lazy">';
            var overlay = (i===4&&moreCount>0) ? '<div class="media-more-overlay">+'+moreCount+'</div>' : '';
            if (isVideo) return '<div class="post-media-item">'+inner+overlay+'</div>';
            var idx     = imgCounter++;
            var b64     = btoa(unescape(encodeURIComponent(JSON.stringify(imageUrls))));
            return '<div class="post-media-item" style="cursor:zoom-in;" onclick="openLightboxB64(\''+b64+'\','+idx+')">'+inner+overlay+'</div>';
        }).join('') + '</div>';
}

function openLightboxB64(b64, startIndex) {
    try { lightboxImages = JSON.parse(decodeURIComponent(escape(atob(b64)))); }
    catch(e) { console.error('lightbox decode',e); return; }
    lightboxIndex = startIndex;
    renderLightboxImage();
    document.getElementById('lightbox')?.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function togglePostText(postId, btn) {
    var el = document.getElementById('postText-'+postId);
    if (!el) return;
    var isCollapsed = el.classList.contains('collapsed');
    el.classList.toggle('collapsed', !isCollapsed);
    btn.textContent = isCollapsed ? 'See less' : 'See more';
}

// ── LIGHTBOX ──────────────────────────────────────────────────
function renderLightboxImage() {
    var img     = document.getElementById('lightboxImg');
    var counter = document.getElementById('lightboxCounter');
    if (img) img.src = lightboxImages[lightboxIndex] || '';
    if (counter) counter.textContent = (lightboxIndex+1) + ' / ' + lightboxImages.length;
    document.getElementById('lightboxPrev').style.display = lightboxImages.length > 1 ? '' : 'none';
    document.getElementById('lightboxNext').style.display = lightboxImages.length > 1 ? '' : 'none';
}
function lightboxNext() { lightboxIndex = (lightboxIndex+1)%lightboxImages.length; renderLightboxImage(); }
function lightboxPrev() { lightboxIndex = (lightboxIndex-1+lightboxImages.length)%lightboxImages.length; renderLightboxImage(); }
function closeLightbox() { document.getElementById('lightbox')?.classList.remove('open'); document.body.style.overflow=''; }

// ── COMMENTS ─────────────────────────────────────────────────
function openComments(postId) {
    activePostId = postId;
    document.getElementById('commentsOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
    loadComments(postId);
}
function closeComments(e, force) {
    if (!force && e && e.target !== document.getElementById('commentsOverlay')) return;
    document.getElementById('commentsOverlay').classList.remove('open');
    document.body.style.overflow = '';
    activePostId = null; replyingTo = null;
}
async function loadComments(postId) {
    var list = document.getElementById('commentsList');
    list.innerHTML = '<div class="res-loading-sm">Loading...</div>';
    try {
        var r = await _sb.from('post_comments')
            .select('*, profiles(id,first_name,last_name,username,profile_photo_url)')
            .eq('post_id',postId).is('parent_id',null).order('created_at',{ascending:true});
        if (r.error) throw r.error;
        var commentIds = (r.data||[]).map(function(c){ return c.id; });
        var repliesMap = {};
        if (commentIds.length) {
            var rr = await _sb.from('post_comments')
                .select('*, profiles(id,first_name,last_name,username,profile_photo_url)')
                .in('parent_id',commentIds).order('created_at',{ascending:true});
            (rr.data||[]).forEach(function(rep){
                if (!repliesMap[rep.parent_id]) repliesMap[rep.parent_id]=[];
                repliesMap[rep.parent_id].push(rep);
            });
        }
        var total = (r.data?.length||0) + Object.values(repliesMap).reduce(function(s,a){ return s+a.length; },0);
        updateCommentCount(postId, total);
        if (!r.data?.length) { list.innerHTML='<div class="res-loading-sm">No comments yet!</div>'; return; }
        list.innerHTML = r.data.map(function(c){ return commentHTML(c, repliesMap[c.id]||[]); }).join('');
    } catch(e) { list.innerHTML='<div class="res-loading-sm">Failed to load.</div>'; }
}
function updateCommentCount(postId, total) {
    var el = document.getElementById('commentCount-'+postId);
    if (el) el.textContent = total>0 ? '&#128172; '+total+' comment'+(total!==1?'s':'') : '';
}
function commentHTML(c, replies) {
    replies = replies || [];
    var author = c.profiles || {};
    var name   = displayName(author);
    var isOwn  = c.user_id === currentUser.id;
    var repliesHTML = replies.map(function(r){ return commentHTML(r,[]); }).join('');
    return '<div class="comment-item" id="comment-'+c.id+'">'+
        avatarHTML(author,34)+
        '<div class="comment-body">'+
            '<div class="comment-bubble">'+
                '<div class="comment-name">'+escH(name)+'</div>'+
                '<div class="comment-text" id="ctext-'+c.id+'">'+escH(c.content)+'</div>'+
            '</div>'+
            '<div class="comment-meta">'+
                '<span>'+timeAgo(c.created_at)+'</span>'+
                '<button class="comment-action-btn" onclick="startReply(\''+c.id+'\',\''+escH(name)+'\')">Reply</button>'+
                (isOwn?'<button class="comment-action-btn" onclick="startEditComment(\''+c.id+'\')">Edit</button>'+
                    '<button class="comment-action-btn danger" onclick="deleteComment(\''+c.id+'\')">Delete</button>':'')+
            '</div>'+
            (replies.length?'<div class="comment-replies">'+repliesHTML+'</div>':'')+
            '<div id="replyBox-'+c.id+'" style="display:none;" class="reply-input-wrap">'+
                '<textarea class="reply-textarea" id="replyInput-'+c.id+'" placeholder="Reply to '+escH(name)+'..." '+
                    'onkeydown="if(event.key===\'Enter\'&&!event.shiftKey){event.preventDefault();submitReply(\''+c.id+'\');}"></textarea>'+
                '<button class="reply-send-btn" onclick="submitReply(\''+c.id+'\')">Send</button>'+
            '</div>'+
        '</div>'+
    '</div>';
}
function startReply(commentId, authorName) {
    document.querySelectorAll('[id^="replyBox-"]').forEach(function(b){ b.style.display='none'; });
    var box = document.getElementById('replyBox-'+commentId);
    if (box) { box.style.display='flex'; document.getElementById('replyInput-'+commentId)?.focus(); }
    replyingTo = { commentId, authorName };
}
async function submitReply(parentId) {
    var inp  = document.getElementById('replyInput-'+parentId);
    var text = inp?.value.trim();
    if (!text||!activePostId||!currentUser.id) return;
    try {
        var r = await _sb.from('post_comments').insert({ post_id:activePostId, user_id:currentUser.id, content:text, parent_id:parentId });
        if (r.error) throw r.error;
        if (inp) inp.value='';
        loadComments(activePostId);
    } catch(e) { alert('Reply failed: '+e.message); }
}
async function submitComment() {
    var input = document.getElementById('commentInput');
    var text  = input?.value.trim();
    if (!text||!activePostId||!currentUser.id) return;
    var btn = document.querySelector('.comments-send-btn');
    if (btn) btn.disabled=true;
    try {
        var r = await _sb.from('post_comments').insert({ post_id:activePostId, user_id:currentUser.id, content:text });
        if (r.error) throw r.error;
        input.value=''; input.style.height='';
        loadComments(activePostId);
    } catch(e) { alert('Comment failed: '+e.message); }
    finally { if (btn) btn.disabled=false; }
}
function startEditComment(commentId) {
    var textEl = document.getElementById('ctext-'+commentId);
    if (!textEl) return;
    var original = textEl.textContent;
    textEl.innerHTML = '<textarea class="reply-textarea" style="width:100%;min-height:40px;" id="cedit-'+commentId+'">'+escH(original)+'</textarea>'+
        '<div style="display:flex;gap:6px;margin-top:6px;justify-content:flex-end;">'+
        '<button class="comment-action-btn" onclick="cancelEditComment(\''+commentId+'\',\''+escH(original)+'\')">Cancel</button>'+
        '<button class="reply-send-btn" style="padding:5px 12px;font-size:12px;" onclick="saveEditComment(\''+commentId+'\')">Save</button></div>';
}
function cancelEditComment(commentId, original) {
    var el = document.getElementById('ctext-'+commentId);
    if (el) el.innerHTML = escH(original);
}
async function saveEditComment(commentId) {
    var inp = document.getElementById('cedit-'+commentId);
    var text = inp?.value.trim();
    if (!text) return;
    try {
        var r = await _sb.from('post_comments').update({ content:text }).eq('id',commentId);
        if (r.error) throw r.error;
        var el = document.getElementById('ctext-'+commentId);
        if (el) el.textContent = text;
    } catch(e) { alert('Edit failed: '+e.message); }
}
async function deleteComment(commentId) {
    if (!confirm('Delete this comment?')) return;
    try {
        var r = await _sb.from('post_comments').delete().eq('id',commentId);
        if (r.error) throw r.error;
        document.getElementById('comment-'+commentId)?.remove();
        if (activePostId) loadComments(activePostId);
    } catch(e) { alert('Delete failed: '+e.message); }
}

// ── COMPOSER ─────────────────────────────────────────────────
function expandComposer() {
    if (composerOpen) return;
    composerOpen = true;
    document.getElementById('cpPlaceholder').parentElement.style.display = 'none';
    document.getElementById('cpExpanded').style.display = '';
    document.getElementById('postContent')?.focus();
}
function cancelComposer() {
    composerOpen = false;
    document.getElementById('cpExpanded').style.display = 'none';
    document.getElementById('cpPlaceholder').parentElement.style.display = '';
    document.getElementById('postContent').value = '';
    stagedMedia=[]; stagedFiles=[]; stagedLink=null; stagedTags=[];
    document.getElementById('cpMediaPreview').innerHTML = '';
    document.getElementById('cpMediaPreview').className = 'cp-media-preview';
    document.getElementById('cpLinkPreview').style.display = 'none';
    document.getElementById('cpLinkPreview').innerHTML = '';
    document.getElementById('cpLinkRow').style.display = 'none';
    var chips = document.getElementById('cpFileChips');
    if (chips) chips.innerHTML = '';
    renderComposerTags();
}
function autoResizeCp(el) { el.style.height='auto'; el.style.height=el.scrollHeight+'px'; }
function toggleVisMenu() {
    var m = document.getElementById('cpVisMenu');
    m.style.display = m.style.display==='none' ? 'block' : 'none';
}
function setVisibility(val, icon, label) {
    currentVis = val;
    document.getElementById('cpVisIcon').textContent  = icon;
    document.getElementById('cpVisLabel').textContent = label;
    document.getElementById('cpVisMenu').style.display = 'none';
}
function toggleLinkInput() {
    var row = document.getElementById('cpLinkRow');
    row.style.display = row.style.display==='none' ? '' : 'none';
    if (row.style.display!=='none') document.getElementById('cpLinkInput')?.focus();
}
async function fetchLinkPreview(url) {
    if (!url) return;
    stagedLink = { url, title:url, image:null };
    renderLinkPreview();
    document.getElementById('cpLinkRow').style.display = 'none';
    try {
        var res = await fetch('/api/og-preview?url='+encodeURIComponent(url), { headers:{ 'X-CSRF-TOKEN': typeof CSRF_TOKEN!=='undefined'?CSRF_TOKEN:'' } });
        if (res.ok) {
            var data = await res.json();
            if (data && !data.error) { stagedLink={ url:data.url||url, title:data.title||url, image:data.image||null }; renderLinkPreview(); }
        }
    } catch(e) { console.warn('[OG]',e.message); }
}
function renderLinkPreview() {
    if (!stagedLink) return;
    var el = document.getElementById('cpLinkPreview');
    el.style.display = '';
    el.innerHTML = '<div class="cp-link-preview-inner">' +
        (stagedLink.image?'<img class="cp-link-preview-thumb" src="'+escH(stagedLink.image)+'" alt="">':'') +
        '<div class="cp-link-preview-info"><div class="cp-link-preview-title">'+escH(stagedLink.title||stagedLink.url)+'</div>' +
        '<div class="cp-link-preview-url">'+escH(stagedLink.url)+'</div></div>' +
        '<button class="cp-link-preview-remove" onclick="removeLinkPreview()">&#10005;</button></div>';
}
function removeLinkPreview() { stagedLink=null; document.getElementById('cpLinkPreview').style.display='none'; document.getElementById('cpLinkPreview').innerHTML=''; }

function handleMediaFiles(fileList) {
    Array.from(fileList).forEach(function(file){
        var url  = URL.createObjectURL(file);
        var type = file.type.startsWith('video') ? 'video' : 'image';
        if (!stagedMedia.find(function(m){ return m.file.name===file.name&&m.file.size===file.size; }))
            stagedMedia.push({ file, objectUrl:url, type });
    });
    renderMediaPreview();
}
function renderMediaPreview() {
    var grid = document.getElementById('cpMediaPreview');
    var n = stagedMedia.length;
    grid.className = 'cp-media-preview count-'+(n>4?'many':n);
    grid.innerHTML = stagedMedia.map(function(m,i){
        return '<div class="cp-media-item">'+(m.type==='video'?'<video src="'+m.objectUrl+'" preload="metadata"></video>':'<img src="'+m.objectUrl+'" alt="">')+
            '<button class="cp-remove-media" onclick="removeMedia('+i+')">&#10005;</button></div>';
    }).join('');
}
function removeMedia(i) { URL.revokeObjectURL(stagedMedia[i]?.objectUrl); stagedMedia.splice(i,1); renderMediaPreview(); }
function handleAttachFiles(fileList) {
    Array.from(fileList).forEach(function(f){
        if (!stagedFiles.find(function(x){ return x.file.name===f.name&&x.file.size===f.size; }))
            stagedFiles.push({ file:f, name:f.name, size:f.size });
    });
    renderFileChips();
}
function renderFileChips() {
    var chips = document.getElementById('cpFileChips');
    if (!chips) {
        chips = document.createElement('div');
        chips.id='cpFileChips'; chips.className='cp-file-chips';
        document.getElementById('cpMediaPreview').insertAdjacentElement('afterend',chips);
    }
    chips.innerHTML = stagedFiles.map(function(f,i){
        return '<div class="cp-file-chip">'+fileEmojiFromName(f.name)+' '+escH(f.name)+'<button onclick="removeFile('+i+')">&#10005;</button></div>';
    }).join('');
}
function removeFile(i) { stagedFiles.splice(i,1); renderFileChips(); }

// ── CREATE POST ───────────────────────────────────────────────
async function createPost() {
    if (!currentUser.id) return;
    var text = document.getElementById('postContent')?.value.trim()||'';
    if (!text&&!stagedMedia.length&&!stagedFiles.length&&!stagedLink) { alert('Please add some content to post.'); return; }
    var btn = document.getElementById('postButton');
    btn.disabled=true; btn.textContent='Posting...';
    try {
        var mediaUrls=[]; var fileUrls=[];
        for (var m of stagedMedia) {
            var ext=m.file.name.split('.').pop();
            var path=currentUser.id+'/'+Date.now()+'_'+Math.random().toString(36).slice(2)+'.'+ext;
            var u=await _sb.storage.from('posts').upload(path,m.file,{upsert:true});
            if (u.error) throw u.error;
            mediaUrls.push(_sb.storage.from('posts').getPublicUrl(path).data.publicUrl);
        }
        for (var f of stagedFiles) {
            var ext=f.file.name.split('.').pop();
            var path=currentUser.id+'/files/'+Date.now()+'_'+Math.random().toString(36).slice(2)+'.'+ext;
            var u=await _sb.storage.from('posts').upload(path,f.file,{upsert:true});
            if (u.error) throw u.error;
            fileUrls.push({ url:_sb.storage.from('posts').getPublicUrl(path).data.publicUrl, name:f.name, size:f.size });
        }
        var r = await _sb.from('posts').insert({
            user_id:currentUser.id, content:text||null, visibility:currentVis,
            media_urls:mediaUrls.length?mediaUrls:null, file_urls:fileUrls.length?fileUrls:null,
            link_meta:stagedLink?stagedLink:null, tags:stagedTags.length?stagedTags:null,
        });
        if (r.error) throw r.error;
        cancelComposer(); loadFeed();
    } catch(e) { alert('Post failed: '+e.message); }
    finally { btn.disabled=false; btn.textContent='Post'; }
}

// ── EDIT POST ─────────────────────────────────────────────────
async function openEditPost(postId) {
    editingPostId=postId; editStagedMedia=[]; editStagedFiles=[];
    var r = await _sb.from('posts').select('*').eq('id',postId).single();
    var post = r.data;
    editExistingMedia=parseJSON(post?.media_urls,[]); editExistingFiles=parseJSON(post?.file_urls,[]);
    editStagedLink=parseJSON(post?.link_meta,null); editStagedTags=parseJSON(post?.tags,[]);
    document.getElementById('editContent').value   = post?.content||'';
    document.getElementById('editVisSelect').value = post?.visibility||'public';
    renderEditMediaPreview(); renderEditFileChips(); renderEditLinkPreview(); renderEditTags();
    var lr=document.getElementById('editLinkRow'); if(lr) lr.style.display='none';
    document.getElementById('editModal').classList.add('open');
    document.getElementById('postMenu-'+postId)?.classList.remove('open');
}
function renderEditMediaPreview() {
    var grid=document.getElementById('editMediaPreview'); if (!grid) return;
    var items=[
        ...editExistingMedia.map(function(url,i){ return {type:'existing',url,i,isVideo:/\.(mp4|mov|webm|ogg)(\?|$)/i.test(url)}; }),
        ...editStagedMedia.map(function(m,i){ return {type:'new',url:m.objectUrl,i,isVideo:m.type==='video'}; })
    ];
    var n=items.length;
    grid.className='cp-media-preview count-'+(n>4?'many':n||1);
    grid.innerHTML=items.map(function(item){
        return '<div class="cp-media-item">'+(item.isVideo?'<video src="'+escH(item.url)+'" preload="metadata"></video>':'<img src="'+escH(item.url)+'" alt="">')+
            '<button class="cp-remove-media" onclick="'+(item.type==='existing'?'removeEditExistingMedia('+item.i+')':'removeEditNewMedia('+item.i+')')+'">&#10005;</button></div>';
    }).join('');
}
function removeEditExistingMedia(i){ editExistingMedia.splice(i,1); renderEditMediaPreview(); }
function removeEditNewMedia(i){ URL.revokeObjectURL(editStagedMedia[i]?.objectUrl); editStagedMedia.splice(i,1); renderEditMediaPreview(); }
function handleEditMediaFiles(fileList){ Array.from(fileList).forEach(function(f){ editStagedMedia.push({file:f,objectUrl:URL.createObjectURL(f),type:f.type.startsWith('video')?'video':'image'}); }); renderEditMediaPreview(); }
function renderEditFileChips(){
    var wrap=document.getElementById('editFileChips'); if(!wrap) return;
    var ex=editExistingFiles.map(function(f,i){ return '<div class="cp-file-chip">'+fileEmojiFromName(f.name||'')+' '+escH(f.name||f.url?.split('/').pop()||'File')+'<button onclick="removeEditExistingFile('+i+')">&#10005;</button></div>'; }).join('');
    var nf=editStagedFiles.map(function(f,i){ return '<div class="cp-file-chip" style="border-color:var(--primary);">'+fileEmojiFromName(f.name)+' '+escH(f.name)+' <small>(new)</small><button onclick="removeEditNewFile('+i+')">&#10005;</button></div>'; }).join('');
    wrap.innerHTML=ex+nf;
}
function removeEditExistingFile(i){ editExistingFiles.splice(i,1); renderEditFileChips(); }
function handleEditFileAttach(fileList){ Array.from(fileList).forEach(function(f){ editStagedFiles.push({file:f,name:f.name,size:f.size}); }); renderEditFileChips(); }
function removeEditNewFile(i){ editStagedFiles.splice(i,1); renderEditFileChips(); }
function toggleEditLinkInput(){
    var row=document.getElementById('editLinkRow'); if(!row) return;
    row.style.display=row.style.display==='none'?'':'none';
    if(row.style.display!=='none'){ var inp=document.getElementById('editLinkInput'); if(inp){inp.value=editStagedLink?.url||'';inp.focus();} }
}
async function fetchEditLinkPreview(url){
    if(!url) return;
    editStagedLink={url,title:url,image:null}; renderEditLinkPreview();
    document.getElementById('editLinkRow').style.display='none';
    try{
        var res=await fetch('/api/og-preview?url='+encodeURIComponent(url),{headers:{'X-CSRF-TOKEN':typeof CSRF_TOKEN!=='undefined'?CSRF_TOKEN:''}});
        if(res.ok){ var d=await res.json(); if(d&&!d.error){ editStagedLink={url:d.url||url,title:d.title||url,image:d.image||null}; renderEditLinkPreview(); } }
    }catch(e){ console.warn('[EditOG]',e.message); }
}
function renderEditLinkPreview(){
    var el=document.getElementById('editLinkPreview'); if(!el) return;
    if(!editStagedLink){el.style.display='none';el.innerHTML='';return;}
    el.style.display='';
    el.innerHTML='<div class="cp-link-preview-inner">'+(editStagedLink.image?'<img class="cp-link-preview-thumb" src="'+escH(editStagedLink.image)+'" alt="">':'')+
        '<div class="cp-link-preview-info"><div class="cp-link-preview-title">'+escH(editStagedLink.title||editStagedLink.url)+'</div>'+
        '<div class="cp-link-preview-url">'+escH(editStagedLink.url)+'</div></div>'+
        '<button class="cp-link-preview-remove" onclick="removeEditLinkPreview()">&#10005;</button></div>';
}
function removeEditLinkPreview(){ editStagedLink=null; renderEditLinkPreview(); }
function closeEditModal(){
    document.getElementById('editModal').classList.remove('open');
    editingPostId=null;
    editStagedMedia.forEach(function(m){ URL.revokeObjectURL(m.objectUrl); });
    editStagedMedia=[]; editStagedFiles=[]; editExistingMedia=[]; editExistingFiles=[]; editStagedLink=null; editStagedTags=[];
    var el=document.getElementById('editLinkPreview'); if(el){el.style.display='none';el.innerHTML='';}
    var row=document.getElementById('editLinkRow'); if(row) row.style.display='none';
}
async function saveEdit(){
    if(!editingPostId) return;
    var content=document.getElementById('editContent').value.trim();
    var vis=document.getElementById('editVisSelect').value;
    var btn=document.querySelector('#editModal .btn-primary');
    btn.disabled=true; btn.textContent='Saving...';
    try{
        var fm=[...editExistingMedia];
        for(var m of editStagedMedia){ var ext=m.file.name.split('.').pop(); var path=currentUser.id+'/'+Date.now()+'_'+Math.random().toString(36).slice(2)+'.'+ext; var u=await _sb.storage.from('posts').upload(path,m.file,{upsert:true}); if(u.error) throw u.error; fm.push(_sb.storage.from('posts').getPublicUrl(path).data.publicUrl); }
        var ff=[...editExistingFiles];
        for(var f of editStagedFiles){ var ext=f.file.name.split('.').pop(); var path=currentUser.id+'/files/'+Date.now()+'_'+Math.random().toString(36).slice(2)+'.'+ext; var u=await _sb.storage.from('posts').upload(path,f.file,{upsert:true}); if(u.error) throw u.error; ff.push({url:_sb.storage.from('posts').getPublicUrl(path).data.publicUrl,name:f.name,size:f.size}); }
        var r=await _sb.from('posts').update({ content:content||null, visibility:vis, media_urls:fm.length?fm:null, file_urls:ff.length?ff:null, link_meta:editStagedLink?editStagedLink:null, tags:editStagedTags.length?editStagedTags:null }).eq('id',editingPostId);
        if(r.error) throw r.error;
        closeEditModal(); loadFeed();
    }catch(e){ alert('Save failed: '+e.message); }
    finally{ btn.disabled=false; btn.textContent='Save Changes'; }
}

// ── DELETE ────────────────────────────────────────────────────
async function deletePost(postId){
    document.getElementById('postMenu-'+postId)?.classList.remove('open');
    if(!confirm('Delete this post?')) return;
    try{ var r=await _sb.from('posts').delete().eq('id',postId); if(r.error) throw r.error; document.getElementById('post-'+postId)?.remove(); }
    catch(e){ alert('Delete failed: '+e.message); }
}
function togglePostMenu(postId){
    var menu=document.getElementById('postMenu-'+postId);
    var isOpen=menu?.classList.contains('open');
    document.querySelectorAll('.post-menu-dropdown.open').forEach(function(m){ m.classList.remove('open'); });
    if(!isOpen) menu?.classList.add('open');
}

// ── REPORT ────────────────────────────────────────────────────
function openReportModal(postId){ activeReportId=postId; document.getElementById('postMenu-'+postId)?.classList.remove('open'); document.getElementById('reportModal').classList.add('open'); document.querySelectorAll('input[name="postReportReason"]').forEach(function(r){ r.checked=false; }); document.getElementById('reportDetails').value=''; }
function closeReportModal(){ document.getElementById('reportModal').classList.remove('open'); activeReportId=null; }
async function submitPostReport(){
    var reason=document.querySelector('input[name="postReportReason"]:checked');
    if(!reason){ alert('Please select a reason.'); return; }
    var details=document.getElementById('reportDetails').value.trim();
    var fullReason=details?reason.value+': '+details:reason.value;
    try{ var r=await _sb.from('reports').insert({reported_by:currentUser.id,reported_content_type:'post',reported_content_id:activeReportId,reason:fullReason,status:'pending'}); if(r.error) throw r.error; closeReportModal(); alert('Report submitted.'); }
    catch(e){ alert('Report failed: '+e.message); }
}

// ── SHARE ─────────────────────────────────────────────────────
function openShareModal(postId){ activeShareId=postId; document.getElementById('shareModal').classList.add('open'); document.getElementById('shareConfirmMsg').textContent=''; }
function closeShareModal(){ document.getElementById('shareModal').classList.remove('open'); activeShareId=null; }
async function shareToFeed(){
    if(!activeShareId||!currentUser.id) return;
    try{ var r=await _sb.from('posts').insert({user_id:currentUser.id,content:null,shared_post_id:activeShareId,visibility:'public'}); if(r.error) throw r.error; document.getElementById('shareConfirmMsg').textContent='Shared!'; setTimeout(closeShareModal,1200); loadFeed(); }
    catch(e){ alert('Share failed: '+e.message); }
}
function copyPostLink(postId){
    var id=postId||activeShareId; if(!id) return;
    navigator.clipboard.writeText(window.location.origin+'/posts/'+id).then(function(){
        var msg=document.getElementById('shareConfirmMsg');
        if(msg){ msg.textContent='Link copied!'; setTimeout(closeShareModal,1200); }
    });
}

// ── WHO TO FOLLOW ─────────────────────────────────────────────
async function loadWhoToFollow(){
    if(!currentUser.id) return;
    var el=document.getElementById('whoToFollow');
    try{
        var r=await _sb.from('profiles').select('id,first_name,last_name,username,profile_photo_url').neq('id',currentUser.id).limit(20);
        var suggestions=(r.data||[]).filter(function(u){ return !followingSet.has(u.id); }).slice(0,5);
        if(!suggestions.length){ el.innerHTML='<div class="res-empty-small">Nothing to suggest.</div>'; return; }
        el.innerHTML=suggestions.map(function(u){
            var uname=displayName(u);
            var full=((u.first_name||'')+' '+(u.last_name||'')).trim();
            var profileUrl='/profile/'+escH(u.username||u.id);
            return '<div class="wtf-item">'+
                '<a href="'+profileUrl+'" class="wtf-avatar" style="text-decoration:none;color:inherit;">'+(u.profile_photo_url?'<img src="'+escH(u.profile_photo_url)+'" alt="'+escH(uname)+'" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">':((u.first_name||'?')[0]+(u.last_name||'?')[0]).toUpperCase())+'</a>'+
                '<a href="'+profileUrl+'" class="wtf-info-link"><div class="wtf-name">'+escH(uname)+'</div><div class="wtf-sub">'+escH(full)+'</div></a>'+
                '<button class="wtf-follow-btn '+(followingSet.has(u.id)?'following':'')+'" data-follow-uid="'+u.id+'" onclick="event.preventDefault();toggleFollow(\''+u.id+'\',this)">'+(followingSet.has(u.id)?'Following':'+ Follow')+'</button>'+
            '</div>';
        }).join('');
    }catch(e){}
}

// ── TRENDING TOPICS ───────────────────────────────────────────
async function loadTrending(){
    var el=document.getElementById('trendingList');
    try{
        var r=await _sb.from('posts').select('content,tags').eq('visibility','public').order('created_at',{ascending:false}).limit(100);
        var counts={};
        (r.data||[]).forEach(function(p){
            var matches=(p.content||'').match(/#[a-zA-Z0-9_]+/g)||[];
            matches.forEach(function(tag){ counts[tag]=(counts[tag]||0)+1; });
            var tags=parseJSON(p.tags,[]);
            tags.forEach(function(t){ var key='#'+t; counts[key]=(counts[key]||0)+1; });
        });
        var sorted=Object.entries(counts).sort(function(a,b){ return b[1]-a[1]; }).slice(0,8);
        if(!sorted.length){ el.innerHTML='<div class="res-empty-small">No trending topics yet.</div>'; return; }
        el.innerHTML=sorted.map(function(entry,i){
            return '<div class="trending-item" onclick="searchByTag(\''+escH(entry[0])+'\')"><span class="trending-rank">'+(i+1)+'</span><span class="trending-tag">'+escH(entry[0])+'</span><span class="trending-count">'+entry[1]+' post'+(entry[1]!==1?'s':'')+'</span></div>';
        }).join('');
    }catch(e){}
}
function searchByTag(tag){
    var clean=tag.startsWith('#')?tag.slice(1):tag;
    var match=SUBJECT_TAGS.find(function(t){ return t.value===clean; });
    if(match){ setTagFilter(match.value); window.scrollTo({top:0,behavior:'smooth'}); }
}

// ── TRENDING RESOURCES ────────────────────────────────────────
// GET /api/resources/trending — ResourceController::trending()
// DB query: resources JOIN resource_ratings (AVG+COUNT)
// WHERE is_approved=true AND visibility='public'
// ORDER BY avg_rating DESC, rating_count DESC
// Columns returned: id, title, subject, file_type, avg_rating, rating_count
async function loadTrendingResources(){
    var el=document.getElementById('trendingResources');
    if(!el) return;
    try{
        var res=await fetch('/api/resources/trending?limit=5',{headers:{'X-CSRF-TOKEN':window.CSRF_TOKEN||''}});
        if(!res.ok) throw new Error('HTTP '+res.status);
        var json=await res.json();
        var data=json.data||[];
        if(!data.length){ el.innerHTML='<div class="res-empty-small">No rated resources yet.</div>'; return; }
        el.innerHTML=data.map(function(r){
            var stars  = starsHTML(parseFloat(r.avg_rating)||0);
            var rating = r.avg_rating ? Number(r.avg_rating).toFixed(1) : '—';
            var count  = r.rating_count ? '('+r.rating_count+')' : '';
            var icon   = resourceTypeIcon(r.file_type);
            var subj   = r.subject||'';
            return '<a href="/resources?open='+escH(r.id)+'" style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);text-decoration:none;">'+
                '<div style="width:32px;height:32px;border-radius:8px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:16px;background:var(--bg-main);">'+icon+'</div>'+
                '<div style="flex:1;min-width:0;">'+
                    '<div style="font-size:12px;font-weight:600;color:var(--text-primary);line-height:1.4;margin-bottom:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+escH(r.title)+'">'+escH(r.title)+'</div>'+
                    '<div style="font-size:11px;color:var(--text-light);display:flex;align-items:center;gap:4px;">'+
                        '<span style="color:#f59e0b;">'+stars+'</span>'+
                        '<span style="font-weight:700;color:var(--text-secondary);">'+rating+'</span>'+
                        '<span>'+count+'</span>'+
                        (subj?'<span>&middot; '+escH(subj)+'</span>':'')+
                    '</div>'+
                '</div>'+
            '</a>';
        }).join('');
    }catch(e){ el.innerHTML='<div class="res-empty-small">Could not load resources.</div>'; console.warn('[trendingResources]',e.message); }
}

// ── ACTIVE STUDY GROUPS ───────────────────────────────────────
// GET /api/study-groups/active — ResourceController::activeGroups()
// DB query: study_groups
//   LEFT JOIN study_group_members (COUNT per group_id) AS member_count
//   LEFT JOIN group_messages (MAX created_at per group_id) AS last_message_at
// WHERE is_public=true
// ORDER BY member_count DESC, last_message_at DESC
// Columns returned: id, name, subject, member_count, last_message_at
async function loadActiveStudyGroups(){
    var el=document.getElementById('activeStudyGroups');
    if(!el) return;
    try{
        var res=await fetch('/api/study-groups/active?limit=4',{headers:{'X-CSRF-TOKEN':window.CSRF_TOKEN||''}});
        if(!res.ok) throw new Error('HTTP '+res.status);
        var json=await res.json();
        var data=json.data||[];
        if(!data.length){ el.innerHTML='<div class="res-empty-small">No active groups yet.</div>'; return; }
        el.innerHTML=data.map(function(g){
            var initials   =(g.name||'?').slice(0,2).toUpperCase();
            var memberCount=g.member_count?g.member_count+' member'+(g.member_count!=1?'s':''):'';
            var subj       =g.subject||'';
            var isActive   =g.last_message_at&&(Date.now()-new Date(g.last_message_at))<172800000;
            return '<a href="/study-groups" style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);text-decoration:none;">'+
                '<div style="width:32px;height:32px;border-radius:10px;flex-shrink:0;background:linear-gradient(135deg,var(--primary),var(--primary-dark));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:white;">'+escH(initials)+'</div>'+
                '<div style="flex:1;min-width:0;">'+
                    '<div style="font-size:12px;font-weight:600;color:var(--text-primary);line-height:1.4;margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+escH(g.name)+'">'+escH(g.name)+'</div>'+
                    '<div style="font-size:11px;color:var(--text-light);display:flex;align-items:center;gap:4px;">'+
                        (memberCount?'<span>'+memberCount+'</span>':'')+
                        (subj?'<span>&middot; '+escH(subj)+'</span>':'')+
                    '</div>'+
                '</div>'+
                (isActive?'<div style="width:7px;height:7px;border-radius:50%;background:#22c55e;flex-shrink:0;" title="Active in last 48h"></div>':'')+
            '</a>';
        }).join('');
    }catch(e){ el.innerHTML='<div class="res-empty-small">Could not load groups.</div>'; console.warn('[activeGroups]',e.message); }
}

// ── STAR RATING HTML ─────────────────────────────────────────
function starsHTML(rating){
    var r=Math.max(0,Math.min(5,parseFloat(rating)||0));
    var full=Math.floor(r); var half=r-full>=0.5?1:0; var empty=5-full-half;
    return '\u2605'.repeat(full)+(half?'\u00bd':'')+'\u2606'.repeat(empty);
}

// ── RESOURCE TYPE ICON ───────────────────────────────────────
function resourceTypeIcon(fileType){
    var t=(fileType||'').toLowerCase();
    if(t==='notes')    return '\uD83D\uDCC4';
    if(t==='slides')   return '\uD83D\uDCCA';
    if(t==='video')    return '\uD83C\uDFA6';
    if(t==='image')    return '\uD83D\uDDBC\uFE0F';
    if(t==='link')     return '\uD83D\uDD17';
    if(t==='reviewer') return '\uD83D\uDCCB';
    if(t==='exercise') return '\uD83D\uDCDD';
    if(t==='text')     return '\u270D\uFE0F';
    return '\uD83D\uDCCE';
}

// ── KEYBOARD SHORTCUTS ────────────────────────────────────────
document.addEventListener('keydown', function(e){
    if(e.key!=='Escape') return;
    if(document.getElementById('reportModal').classList.contains('open'))     { closeReportModal(); return; }
    if(document.getElementById('editModal').classList.contains('open'))       { closeEditModal(); return; }
    if(document.getElementById('shareModal').classList.contains('open'))      { closeShareModal(); return; }
    if(document.getElementById('commentsOverlay').classList.contains('open')) { closeComments(null,true); return; }
    if(document.getElementById('lightbox')?.classList.contains('open'))       { closeLightbox(); return; }
});

// ── HELPERS ───────────────────────────────────────────────────
function parseJSON(val, fallback){
    if(!val) return fallback;
    if(typeof val==='object') return val;
    try{ return JSON.parse(val); }catch(e){ return fallback; }
}
function timeAgo(ts){
    var s=Math.floor((Date.now()-new Date(ts))/1000);
    if(s<60)     return 'Just now';
    if(s<3600)   return Math.floor(s/60)+'m ago';
    if(s<86400)  return Math.floor(s/3600)+'h ago';
    if(s<604800) return Math.floor(s/86400)+'d ago';
    return new Date(ts).toLocaleDateString();
}
function formatBytes(b){
    if(!b) return '';
    if(b<1024)    return b+' B';
    if(b<1048576) return (b/1024).toFixed(1)+' KB';
    return (b/1048576).toFixed(1)+' MB';
}
function fileEmojiFromName(name){
    var ext=(name||'').split('.').pop().toLowerCase();
    var map={pdf:'📄',doc:'📝',docx:'📝',ppt:'📊',pptx:'📊',mp4:'🎬',mov:'🎬',webm:'🎬',jpg:'🖼️',jpeg:'🖼️',png:'🖼️',gif:'🖼️',webp:'🖼️',zip:'🗜️',rar:'🗜️'};
    return map[ext]||'📎';
}
function escH(t){
    if(t===null||t===undefined) return '';
    if(typeof t!=='string') t=String(t);
    var d=document.createElement('div'); d.textContent=t; return d.innerHTML;
}
