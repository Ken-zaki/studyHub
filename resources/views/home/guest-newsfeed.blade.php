<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsfeed — StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
    <link rel="stylesheet" href="{{ asset('css/newsfeed.css') }}">
    <meta name="data-supabase-url" content="{{ env('SUPABASE_URL') }}">
    <meta name="data-supabase-key" content="{{ env('SUPABASE_ANON_KEY') }}">
    <style>
        /* Gate modal */
        .g-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity 0.2s;}
        .g-modal-overlay.open{opacity:1;pointer-events:all;}
        .g-modal{background:var(--bg-card,white);border-radius:20px;padding:32px;width:90%;max-width:400px;text-align:center;transform:scale(0.95);transition:transform 0.2s;box-shadow:0 20px 60px rgba(0,0,0,0.18);}
        .g-modal-overlay.open .g-modal{transform:scale(1);}
        .g-modal-icon{font-size:40px;margin-bottom:14px;}
        .g-modal h3{font-family:'Crimson Pro',serif;font-size:22px;font-weight:700;margin-bottom:8px;color:var(--text-primary,#1a1a1a);}
        .g-modal p{font-size:14px;color:var(--text-secondary,#6b7280);line-height:1.6;margin-bottom:24px;}
        .g-modal-btns{display:flex;flex-direction:column;gap:10px;}
        .gm-p{display:block;padding:12px;border-radius:12px;background:var(--primary,#1a5f7a);color:white;font-size:14px;font-weight:700;text-decoration:none;}
        .gm-p:hover{opacity:.88;}
        .gm-s{display:block;padding:12px;border-radius:12px;border:1.5px solid var(--border,#e5e7eb);background:var(--bg-card,white);font-size:14px;font-weight:600;color:var(--text-primary,#1a1a1a);text-decoration:none;}
        .gm-s:hover{border-color:var(--primary);color:var(--primary);}
        .gm-d{margin-top:10px;font-size:13px;color:var(--text-light,#9ca3af);cursor:pointer;background:none;border:none;font-family:inherit;}
        /* Guest create-post */
        .create-post.guest-cp{cursor:pointer;}
        .create-post.guest-cp:hover{box-shadow:0 0 0 2px rgba(26,95,122,0.15);border-color:rgba(26,95,122,0.3);}
        /* Like count above action bar */
        .post-counts{padding:4px 14px 0;font-size:12px;color:var(--text-light);}
    </style>
</head>
<body>

@include('layouts.guest-sidebar', ['guestNav' => 'newsfeed'])

<main class="main-content">
    <div class="feed-column" id="feedColumn">

        <header class="page-header">
            <h1 class="page-title">Newsfeed</h1>
            <p class="page-subtitle">Stay updated with your community</p>
        </header>

        {{-- Create post bar --}}
        <div class="create-post guest-cp" onclick="showModal('post')">
            <div class="cp-top">
                <div class="cp-avatar" style="background:var(--bg-main);border:1.5px solid var(--border);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         style="width:18px;height:18px;color:var(--text-light);">
                        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <div class="cp-input-wrap" style="cursor:pointer;">
                    <span class="cp-placeholder">What's on your mind? Join StudyHub to share…</span>
                </div>
            </div>
        </div>

        {{-- Sort bar — Trending + Following only --}}
        <div class="feed-sort-bar">
            <button class="sort-tab active" id="tabTrending" onclick="switchTab(this)">
                Trending
            </button>
            <button class="sort-tab" onclick="showModal('tab')">
                Following
            </button>
            <div class="trending-subfilter" id="trendingSubfilter">
                <button class="subfilter-btn active" id="subRelevant" onclick="setMode('relevant', this)">Relevant</button>
                <button class="subfilter-btn" id="subLatest" onclick="setMode('latest', this)">Latest</button>
            </div>
        </div>

        <div id="guestFeed"><div class="loading-state">Loading posts…</div></div>
    </div>

    <aside class="right-sidebar">

        <div class="widget-card">
            <div class="widget-title">🎓 Join StudyHub</div>
            <p style="font-size:13px;color:var(--text-secondary);line-height:1.6;margin-bottom:14px;">
                Create a free account to vote, comment, post, join study groups, and connect with students.
            </p>
            <a href="{{ route('signup') }}"
               style="display:block;text-align:center;padding:10px;border-radius:10px;background:var(--primary);color:white;font-weight:700;font-size:14px;text-decoration:none;margin-bottom:8px;"
               onmouseover="this.style.opacity='.88';" onmouseout="this.style.opacity='1';">Sign Up Free →</a>
            <a href="{{ route('login') }}"
               style="display:block;text-align:center;padding:10px;border-radius:10px;border:1.5px solid var(--border);background:var(--bg-card);color:var(--text-primary);font-weight:600;font-size:14px;text-decoration:none;"
               onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)';"
               onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-primary)';">Already have an account?</a>
        </div>

        <div class="widget-card">
            <div class="widget-title">🌍 Filter &amp; Sort</div>
            <p style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:.05em;margin:0 0 6px;">Sort by</p>
            <div style="display:flex;gap:5px;margin-bottom:12px;">
                <button id="sidebarSortTrending" onclick="sidebarSort('relevant')"
                    style="flex:1;padding:6px 0;border:1.5px solid var(--primary);border-radius:8px;background:var(--primary);color:white;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;cursor:pointer;">
                    Trending
                </button>
                <button id="sidebarSortLatest" onclick="sidebarSort('latest')"
                    style="flex:1;padding:6px 0;border:1.5px solid var(--border);border-radius:8px;background:var(--bg-card);color:var(--text-secondary);font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;cursor:pointer;">
                    Latest
                </button>
            </div>
            <p style="font-size:11px;font-weight:700;color:var(--text-light);text-transform:uppercase;letter-spacing:.05em;margin:0 0 6px;">Filter by Subject</p>
            <div id="sidebarTagGrid" style="display:flex;flex-wrap:wrap;gap:5px;"></div>
        </div>

        <div class="widget-card">
            <div class="widget-title">⭐ Trending Resources</div>
            <div id="trendingResourcesWidget"><div class="res-empty-small">Loading…</div></div>
            <a href="{{ route('guest.resources') }}"
               style="display:block;text-align:center;margin-top:8px;font-size:12px;font-weight:600;color:var(--primary);text-decoration:none;padding:6px;border-radius:8px;">
                See all resources →
            </a>
        </div>

        <div class="widget-card">
            <div class="widget-title">🔥 Trending Topics</div>
            <div id="trendingList"></div>
        </div>

        <div class="widget-card">
            <div class="widget-title">💡 Study Tip</div>
            <p id="studyTip" style="font-size:14px;color:var(--text-secondary);line-height:1.6;margin:0;"></p>
        </div>

    </aside>
</main>

<div class="g-modal-overlay" id="interactModal" onclick="if(event.target===this)closeModal();">
    <div class="g-modal">
        <div class="g-modal-icon" id="imIcon"></div>
        <h3 id="imTitle"></h3>
        <p id="imBody"></p>
        <div class="g-modal-btns">
            <a href="{{ route('signup') }}" class="gm-p">Create Free Account</a>
            <a href="{{ route('login') }}"  class="gm-s">I already have an account</a>
        </div>
        <button class="gm-d" onclick="closeModal()">Maybe later</button>
    </div>
</div>

<script>
var SB_URL = document.querySelector('meta[name="data-supabase-url"]').content;
var SB_KEY = document.querySelector('meta[name="data-supabase-key"]').content;

var TIPS = [
    'Teach what you learned — the best way to remember it.',
    'Spaced repetition beats cramming every time.',
    'Review your notes within 24 hours to retain 80% more.',
    'Sleep consolidates memory — avoid all-nighters.',
    'Pomodoro: 25 min focus, 5 min break.',
    'Ask "why?" for every fact — understanding beats memorising.',
];
document.getElementById('studyTip').textContent = TIPS[Math.floor(Math.random() * TIPS.length)];

function sbFetch(url) {
    return fetch(url, {
        headers: {
            'apikey': SB_KEY,
            'Authorization': 'Bearer ' + SB_KEY,
            'Content-Type': 'application/json',
        }
    });
}

var allPosts  = [];
var activeTag = null;
var sortMode  = 'relevant';

var SUBJECT_TAGS = [
    'Mathematics','Science','Filipino','English','PE','Health','Music','Arts',
    'Social Studies','Computer Science','Values Education','MAPEH',
    'History','Chemistry','Physics','Biology','Economics',
];

/* Build sidebar tag buttons */
(function() {
    var grid = document.getElementById('sidebarTagGrid');
    SUBJECT_TAGS.forEach(function(tag) {
        var btn = document.createElement('button');
        btn.textContent = tag;
        btn.style.cssText = 'padding:4px 10px;border-radius:20px;border:1.5px solid var(--border);background:var(--bg-card);font-family:\'DM Sans\',sans-serif;font-size:11px;font-weight:600;color:var(--text-secondary);cursor:pointer;transition:all 0.15s;';
        btn.addEventListener('click', function() {
            activeTag = (activeTag === tag) ? null : tag;
            grid.querySelectorAll('button').forEach(function(b) {
                var on = (b.textContent === activeTag);
                b.style.background  = on ? 'var(--primary)' : 'var(--bg-card)';
                b.style.color       = on ? 'white' : 'var(--text-secondary)';
                b.style.borderColor = on ? 'var(--primary)' : 'var(--border)';
            });
            renderFeed();
        });
        grid.appendChild(btn);
    });
})();

/* ── Load feed ── */
async function loadFeed() {
    var feed  = document.getElementById('guestFeed');
    var posts = null;

    /* Attempt 1 — newsfeed_posts with visibility + archived filter */
    try {
        var r = await sbFetch(
            SB_URL + '/rest/v1/newsfeed_posts' +
            '?visibility=eq.public&is_archived=eq.false' +
            '&order=created_at.desc&limit=50' +
            '&select=id,content,media_url,file_urls,link_url,created_at,user_id,post_type,' +
            'profiles(first_name,last_name,username,profile_photo_url)'
        );
        if (r.ok) {
            var d = await r.json();
            if (Array.isArray(d)) posts = d;
        }
    } catch(e) {}

    /* Attempt 2 — newsfeed_posts without visibility filter */
    if (!posts) {
        try {
            var r2 = await sbFetch(
                SB_URL + '/rest/v1/newsfeed_posts' +
                '?order=created_at.desc&limit=50' +
                '&select=id,content,media_url,file_urls,link_url,created_at,user_id,post_type,' +
                'profiles(first_name,last_name,username,profile_photo_url)'
            );
            if (r2.ok) {
                var d2 = await r2.json();
                if (Array.isArray(d2)) {
                    posts = d2.filter(function(p) { return p.is_archived !== true; });
                }
            }
        } catch(e) {}
    }

    /* Attempt 3 — 'posts' table */
    if (!posts) {
        try {
            var r3 = await sbFetch(
                SB_URL + '/rest/v1/posts' +
                '?visibility=eq.public&order=created_at.desc&limit=50' +
                '&select=id,content,media_urls,file_urls,link_meta,created_at,user_id,' +
                'profiles(first_name,last_name,username,profile_photo_url)'
            );
            if (r3.ok) {
                var d3 = await r3.json();
                if (Array.isArray(d3)) posts = d3;
            }
        } catch(e) {}
    }

    if (!posts) {
        feed.innerHTML = '<div class="feed-empty"><div class="ei">⚠️</div><p>Could not connect to the feed. Check your Supabase config.</p></div>';
        return;
    }
    if (!posts.length) {
        feed.innerHTML = '<div class="feed-empty"><div class="ei">📭</div><p>No public posts yet.</p></div>';
        return;
    }

    /* Fetch vote counts (upvotes) */
    var ids   = posts.map(function(p) { return p.id; });
    var votes = {};
    try {
        var lr = await sbFetch(
            SB_URL + '/rest/v1/post_likes?post_id=in.(' + ids.join(',') + ')&select=post_id'
        );
        if (lr.ok) {
            var lrows = await lr.json();
            (lrows || []).forEach(function(row) {
                votes[row.post_id] = (votes[row.post_id] || 0) + 1;
            });
        }
    } catch(e) {}

    allPosts = posts.map(function(p) {
        p._votes = votes[p.id] || 0;
        return p;
    });

    buildTrendingTopics();
    loadTrendingResources();
    renderFeed();
}

function renderFeed() {
    var feed     = document.getElementById('guestFeed');
    var filtered = allPosts.slice();

    if (activeTag) {
        filtered = filtered.filter(function(p) {
            return (p.content || '').toLowerCase().includes(activeTag.toLowerCase());
        });
    }

    if (sortMode === 'latest') {
        filtered.sort(function(a, b) { return new Date(b.created_at) - new Date(a.created_at); });
    } else {
        filtered.sort(function(a, b) {
            if (b._votes !== a._votes) return b._votes - a._votes;
            return new Date(b.created_at) - new Date(a.created_at);
        });
    }

    if (!filtered.length) {
        feed.innerHTML = '<div class="feed-empty"><div class="ei">🔍</div><p>No posts match this filter.</p></div>';
        return;
    }
    feed.innerHTML = filtered.map(postCard).join('');
}

function buildTrendingTopics() {
    var el     = document.getElementById('trendingList');
    var counts = {};
    allPosts.forEach(function(p) {
        SUBJECT_TAGS.forEach(function(tag) {
            if ((p.content || '').toLowerCase().includes(tag.toLowerCase())) {
                counts[tag] = (counts[tag] || 0) + 1;
            }
        });
    });
    var sorted = Object.keys(counts).sort(function(a, b) { return counts[b] - counts[a]; }).slice(0, 8);
    if (!sorted.length) {
        el.innerHTML = '<div class="res-empty-small" style="padding:10px 0;">No topics yet</div>';
        return;
    }
    el.innerHTML = sorted.map(function(tag, i) {
        return '<div class="trending-item" onclick="filterByTag(\'' + esc(tag) + '\')">'
            + '<span class="trending-rank">' + (i + 1) + '</span>'
            + '<span class="trending-tag">' + esc(tag) + '</span>'
            + '<span class="trending-count">' + counts[tag] + ' post' + (counts[tag] !== 1 ? 's' : '') + '</span>'
            + '</div>';
    }).join('');
}

function filterByTag(tag) {
    activeTag = (activeTag === tag) ? null : tag;
    document.getElementById('sidebarTagGrid').querySelectorAll('button').forEach(function(b) {
        var on = (b.textContent === activeTag);
        b.style.background  = on ? 'var(--primary)' : 'var(--bg-card)';
        b.style.color       = on ? 'white' : 'var(--text-secondary)';
        b.style.borderColor = on ? 'var(--primary)' : 'var(--border)';
    });
    renderFeed();
}

async function loadTrendingResources() {
    var el = document.getElementById('trendingResourcesWidget');
    try {
        var r = await sbFetch(
            SB_URL + '/rest/v1/resources' +
            '?is_approved=eq.true&order=view_count.desc&limit=4' +
            '&select=id,title,file_type,subject,view_count'
        );
        if (!r.ok) { el.innerHTML = '<div class="res-empty-small">No resources yet</div>'; return; }
        var data = await r.json();
        if (!Array.isArray(data) || !data.length) { el.innerHTML = '<div class="res-empty-small">No resources yet</div>'; return; }
        var ICONS = { notes:'📄', exercise:'📝', slides:'📊', reviewer:'📋', video:'🎬', image:'🖼️', link:'🔗', text:'✍️' };
        el.innerHTML = data.map(function(res) {
            return '<div class="discover-resource-item" style="cursor:pointer;" onclick="window.location=\'' + "{{ route('guest.resources') }}" + '\'">'
                + '<div class="discover-resource-icon">' + (ICONS[res.file_type] || '📎') + '</div>'
                + '<div class="discover-item-info">'
                + '<div class="discover-item-title">' + esc(res.title || 'Untitled') + '</div>'
                + '<div class="discover-item-meta">'
                + (res.subject ? '<span>' + esc(res.subject) + '</span>' : '')
                + (res.view_count ? '<span>👁 ' + res.view_count + '</span>' : '')
                + '</div></div></div>';
        }).join('');
    } catch(e) {
        el.innerHTML = '<div class="res-empty-small">Could not load</div>';
    }
}

/* ── Post card — uses the registered .post-action-bar-b / .vote-btn-b classes ── */
function postCard(post) {
    var a  = post.profiles || {};
    var fn = a.first_name || ''; var ln = a.last_name || '';
    var name     = (fn + ' ' + ln).trim() || a.username || 'User';
    var initials = ((fn || '?')[0] + (ln || '?')[0]).toUpperCase();
    var ago      = timeAgo(post.created_at);
    var voteCount = post._votes || 0;

    var media = safeJ(post.media_urls, null) || (post.media_url ? [post.media_url] : []);
    var files = safeJ(post.file_urls, []);
    var link  = safeJ(post.link_meta, null) || (post.link_url ? { url: post.link_url } : null);

    var avatarHTML = a.profile_photo_url
        ? '<img src="' + esc(a.profile_photo_url) + '" alt="' + esc(name) + '">'
        : initials;

    var mediaHTML = '';
    if (media && media.length) {
        var cls = 'count-' + (media.length > 4 ? 'many' : media.length);
        mediaHTML = '<div class="post-body"><div class="post-media ' + cls + '">'
            + media.slice(0, 4).map(function(u, i) {
                var isVid = /\.(mp4|mov|webm)(\?|$)/i.test(u);
                var ov = (i === 3 && media.length > 4)
                    ? '<div class="media-more-overlay">+' + (media.length - 4) + '</div>' : '';
                return '<div class="post-media-item">'
                    + (isVid ? '<video src="' + esc(u) + '" controls preload="none"></video>'
                             : '<img src="' + esc(u) + '" loading="lazy" alt="">')
                    + ov + '</div>';
            }).join('') + '</div></div>';
    }

    var filesHTML = '';
    if (files && files.length) {
        filesHTML = '<div class="post-files">' + files.map(function(f) {
            var url = typeof f === 'string' ? f : (f.url || '#');
            var nm  = typeof f === 'string' ? f : (f.name || 'File');
            return '<a class="post-file-row" href="' + esc(url) + '" target="_blank" rel="noopener">'
                + '<span class="post-file-icon">📎</span>'
                + '<span class="post-file-name">' + esc(nm) + '</span></a>';
        }).join('') + '</div>';
    }

    var linkHTML = '';
    if (link && link.url) {
        linkHTML = '<a class="post-link-preview" href="' + esc(link.url) + '" target="_blank" rel="noopener">'
            + (link.image ? '<img class="post-link-img" src="' + esc(link.image) + '" alt="">' : '')
            + '<div class="post-link-info">'
            + '<div class="post-link-title">' + esc(link.title || link.url) + '</div>'
            + '<div class="post-link-url">' + esc(link.url) + '</div>'
            + '</div></a>';
    }

    /* ── Action bar: upvote + downvote + comment + share
          Uses the exact same CSS classes as the registered newsfeed
          (.post-action-bar-b, .vote-group-b, .vote-btn-b, .post-action-btn-b)
          so it inherits all the registered styles without any extra CSS.      ── */
    var actionBar =
        '<div class="post-action-bar-b">'

        /* Left: vote group */
        + '<div class="vote-group-b">'

        /* Upvote */
        + '<button class="vote-btn-b" onclick="showModal(\'vote\')">'
        + '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">'
        + '<polyline points="18 15 12 9 6 15"/>'
        + '</svg>'
        + '<span class="vote-count upvote-count">' + (voteCount > 0 ? voteCount : '') + '</span>'
        + '</button>'

        /* Downvote */
        + '<button class="vote-btn-b" onclick="showModal(\'vote\')">'
        + '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">'
        + '<polyline points="6 9 12 15 18 9"/>'
        + '</svg>'
        + '</button>'

        + '</div>'

        /* Right: comment + share */
        + '<div style="display:flex;gap:4px;">'

        + '<button class="post-action-btn-b" onclick="showModal(\'comment\')">'
        + '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
        + '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>'
        + '</svg>'
        + ' Comment'
        + '</button>'

        + '<button class="post-action-btn-b" onclick="showModal(\'share\')">'
        + '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">'
        + '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>'
        + '<line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>'
        + '<line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>'
        + '</svg>'
        + ' Share'
        + '</button>'

        + '</div>'
        + '</div>';

    return '<div class="post-card">'
        + '<div class="post-header"><div class="post-author">'
        + '<div class="post-avatar">' + avatarHTML + '</div>'
        + '<div>'
        + '<div class="post-author-name">' + esc(name) + '</div>'
        + '<div class="post-author-meta"><span>' + ago + '</span>'
        + '<span class="post-vis-badge">· 🌐 Public</span></div>'
        + '</div></div></div>'
        + (post.content ? '<div class="post-body"><div class="post-text">' + esc(post.content) + '</div></div>' : '')
        + mediaHTML + filesHTML + linkHTML
        + actionBar
        + '</div>';
}

/* ── Sort controls ── */
function switchTab(btn) {
    document.querySelectorAll('.sort-tab').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
}
function setMode(mode, btn) {
    sortMode = mode;
    document.querySelectorAll('.subfilter-btn').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    syncSidebarBtns();
    renderFeed();
}
function sidebarSort(mode) {
    sortMode = mode;
    document.getElementById('subRelevant').classList.toggle('active', mode === 'relevant');
    document.getElementById('subLatest').classList.toggle('active', mode === 'latest');
    syncSidebarBtns();
    renderFeed();
}
function syncSidebarBtns() {
    var t = sortMode === 'relevant';
    document.getElementById('sidebarSortTrending').style.cssText = t
        ? 'flex:1;padding:6px 0;border:1.5px solid var(--primary);border-radius:8px;background:var(--primary);color:white;font-family:\'DM Sans\',sans-serif;font-size:12px;font-weight:600;cursor:pointer;'
        : 'flex:1;padding:6px 0;border:1.5px solid var(--border);border-radius:8px;background:var(--bg-card);color:var(--text-secondary);font-family:\'DM Sans\',sans-serif;font-size:12px;font-weight:600;cursor:pointer;';
    document.getElementById('sidebarSortLatest').style.cssText = !t
        ? 'flex:1;padding:6px 0;border:1.5px solid var(--primary);border-radius:8px;background:var(--primary);color:white;font-family:\'DM Sans\',sans-serif;font-size:12px;font-weight:600;cursor:pointer;'
        : 'flex:1;padding:6px 0;border:1.5px solid var(--border);border-radius:8px;background:var(--bg-card);color:var(--text-secondary);font-family:\'DM Sans\',sans-serif;font-size:12px;font-weight:600;cursor:pointer;';
}

/* ── Gate modal ── */
var MODALS = {
    vote:    { icon:'🔼', title:'Vote on posts',        body:'Sign up or log in to upvote and downvote posts.' },
    comment: { icon:'💬', title:'Join the discussion',  body:'Sign up or log in to comment on posts.' },
    share:   { icon:'🔄', title:'Share this post',      body:'Sign up or log in to share posts with your network.' },
    post:    { icon:'✏️', title:'Create a post',        body:'Join StudyHub to share your own content with the community.' },
    tab:     { icon:'🔒', title:'Members only',         body:'Log in or sign up to see posts from people you follow.' },
};
function showModal(type) {
    var d = MODALS[type] || { icon:'🔒', title:'Join to continue', body:'Create a free StudyHub account.' };
    document.getElementById('imIcon').textContent  = d.icon;
    document.getElementById('imTitle').textContent = d.title;
    document.getElementById('imBody').textContent  = d.body;
    document.getElementById('interactModal').classList.add('open');
}
function closeModal() { document.getElementById('interactModal').classList.remove('open'); }
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeModal(); });

/* ── Helpers ── */
function timeAgo(ts) {
    var s = Math.floor((Date.now() - new Date(ts)) / 1000);
    if (s < 60)     return 'Just now';
    if (s < 3600)   return Math.floor(s / 60) + 'm ago';
    if (s < 86400)  return Math.floor(s / 3600) + 'h ago';
    if (s < 604800) return Math.floor(s / 86400) + 'd ago';
    return new Date(ts).toLocaleDateString();
}
function safeJ(v, fb) {
    if (!v) return fb;
    if (typeof v === 'object') return v;
    try { return JSON.parse(v); } catch(e) { return fb; }
}
function esc(t) {
    if (t == null) return '';
    var d = document.createElement('div');
    d.textContent = String(t);
    return d.innerHTML;
}

loadFeed();
</script>
</body>
</html>
