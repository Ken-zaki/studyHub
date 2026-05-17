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
        .post-actions-bar{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:10px;
            padding:14px 16px 16px;
            border-top:1px solid var(--border);
            margin-top:12px;
        }

        .post-action-btn{
            border:none;
            background:var(--bg-main);
            color:var(--text-secondary);
            border-radius:14px;
            padding:12px 10px;
            font-size:14px;
            font-weight:700;
            font-family:'DM Sans',sans-serif;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            cursor:pointer;
            transition:all .22s ease;
        }

        .post-action-btn:hover{
            transform:translateY(-2px);
            background:white;
            box-shadow:0 8px 20px rgba(0,0,0,0.08);
        }

        .action-icon{
            width:28px;
            height:28px;
            border-radius:10px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:15px;
            background:rgba(0,0,0,0.04);
        }

        .like-btn:hover{
            color:#e11d48;
        }

        .like-btn:hover .action-icon{
            background:rgba(225,29,72,0.10);
        }

        .comment-btn:hover{
            color:#1a5f7a;
        }

        .comment-btn:hover .action-icon{
            background:rgba(26,95,122,0.10);
        }

        .share-btn:hover{
            color:#16a34a;
        }

        .share-btn:hover .action-icon{
            background:rgba(22,163,74,0.10);
        }

        @media(max-width:600px){
            .post-actions-bar{
                gap:8px;
                padding:12px;
            }

            .post-action-btn{
                font-size:13px;
                padding:10px 6px;
            }

            .action-icon{
                width:24px;
                height:24px;
                font-size:13px;
            }
        }
            .feed-tabs{
                display:flex;
                gap:14px;
                margin-bottom:22px;
                padding:6px;
                background:rgba(255,255,255,0.7);
                border:1px solid rgba(0,0,0,0.06);
                border-radius:22px;
                backdrop-filter:blur(12px);
                overflow-x:auto;
            }

            .feed-tabs::-webkit-scrollbar{
                display:none;
            }

            .feed-tab{
                flex:1;
                min-width:180px;
                display:flex;
                align-items:center;
                gap:14px;
                padding:14px 18px;
                border:none;
                border-radius:18px;
                background:transparent;
                cursor:pointer;
                transition:all .25s ease;
                position:relative;
                font-family:'DM Sans',sans-serif;
            }

            .feed-tab:hover{
                background:rgba(26,95,122,0.08);
                transform:translateY(-2px);
            }

            .feed-tab.active{
                background:linear-gradient(135deg,#1a5f7a,#2f89a8);
                box-shadow:
                    0 10px 25px rgba(26,95,122,0.25),
                    inset 0 1px 0 rgba(255,255,255,0.15);
            }

            .feed-tab.active .tab-icon,
            .feed-tab.active strong,
            .feed-tab.active small{
                color:white;
            }

            .tab-icon{
                width:48px;
                height:48px;
                border-radius:16px;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:20px;
                background:rgba(26,95,122,0.10);
                color:#1a5f7a;
                flex-shrink:0;
                transition:all .25s ease;
            }

            .feed-tab.active .tab-icon{
                background:rgba(255,255,255,0.15);
            }

            .tab-text{
                display:flex;
                flex-direction:column;
                align-items:flex-start;
                text-align:left;
            }

            .tab-text strong{
                font-size:15px;
                color:var(--text-primary);
                line-height:1.1;
            }

            .tab-text small{
                font-size:12px;
                color:var(--text-secondary);
                margin-top:4px;
                font-weight:500;
            }

            @media(max-width:768px){
                .feed-tab{
                    min-width:160px;
                    padding:12px 14px;
                }

                .tab-icon{
                    width:42px;
                    height:42px;
                    font-size:18px;
                }
            }
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
        /* Create post bar styled to match .create-post from newsfeed.css */
        .cp-guest{background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:16px;margin-bottom:16px;cursor:pointer;transition:box-shadow 0.2s;}
        .cp-guest:hover{box-shadow:0 0 0 2px rgba(26,95,122,0.15);border-color:rgba(26,95,122,0.3);}
        .cp-guest-row{display:flex;align-items:center;gap:12px;}
        .cp-guest-av{width:40px;height:40px;border-radius:12px;flex-shrink:0;background:var(--bg-main);border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;}
        .cp-guest-av svg{width:20px;height:20px;color:var(--text-light);}
        .cp-guest-ph{flex:1;padding:10px 16px;background:var(--bg-main);border:1.5px solid var(--border);border-radius:24px;font-size:14px;color:var(--text-light);font-family:'DM Sans',sans-serif;user-select:none;}
    </style>
</head>
<body>

@include('layouts.guest-sidebar', ['guestNav' => 'newsfeed'])

<main class="main-content">
    <div class="feed-column">

        <header class="page-header">
            <h1 class="page-title">Newsfeed</h1>
            <p class="page-subtitle">Browse what the community is sharing</p>
        </header>

        <div class="cp-guest" onclick="showModal('post')">
            <div class="cp-guest-row">
                <div class="cp-guest-av">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <div class="cp-guest-ph">Join StudyHub to share something with the community…</div>
            </div>
        </div>

        <div class="feed-tabs">
            <button class="feed-tab active">
                <span class="tab-icon">✨</span>
                <span class="tab-text">
                    <strong>For You</strong>
                    <small>Recommended posts</small>
                </span>
            </button>

            <button class="feed-tab" onclick="showModal('tab')">
                <span class="tab-icon">👁</span>
                <span class="tab-text">
                    <strong>Following</strong>
                    <small>People you follow</small>
                </span>
            </button>

            <button class="feed-tab" onclick="showModal('tab')">
                <span class="tab-icon">👥</span>
                <span class="tab-text">
                    <strong>Friends</strong>
                    <small>Your connections</small>
                </span>
            </button>
        </div>

        <div id="guestFeed"><div class="loading-state">Loading posts…</div></div>
    </div>

    <aside class="right-sidebar">
        <div class="widget-card">
            <div class="widget-title">🎓 Join StudyHub</div>
            <p style="font-size:13px;color:var(--text-secondary);line-height:1.6;margin-bottom:14px;">Create a free account to like, comment, post, join study groups, and connect with students.</p>
            <a href="{{ route('signup') }}" style="display:block;text-align:center;padding:10px;border-radius:10px;background:var(--primary);color:white;font-weight:700;font-size:14px;text-decoration:none;margin-bottom:8px;" onmouseover="this.style.opacity='.88';" onmouseout="this.style.opacity='1';">Sign Up Free →</a>
            <a href="{{ route('login') }}"  style="display:block;text-align:center;padding:10px;border-radius:10px;border:1.5px solid var(--border);background:var(--bg-card);color:var(--text-primary);font-weight:600;font-size:14px;text-decoration:none;" onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)';" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-primary)';">Already have an account?</a>
        </div>
        <div class="widget-card">
            <div class="widget-title">🔥 Trending Topics</div>
            <p style="font-size:13px;color:var(--text-secondary);">Sign up to see trending topics in your feed.</p>
        </div>
        <div class="widget-card">
            <div class="widget-title">💡 Study Tip</div>
            <p id="studyTip" style="font-size:13px;color:var(--text-secondary);line-height:1.65;"></p>
        </div>
    </aside>
</main>

<div class="g-modal-overlay" id="interactModal" onclick="if(event.target===this)closeModal();">
    <div class="g-modal">
        <div class="g-modal-icon" id="imIcon"></div>
        <h3 id="imTitle"></h3><p id="imBody"></p>
        <div class="g-modal-btns">
            <a href="{{ route('signup') }}" class="gm-p">Create Free Account</a>
            <a href="{{ route('login') }}"  class="gm-s">I already have an account</a>
        </div>
        <button class="gm-d" onclick="closeModal()">Maybe later</button>
    </div>
</div>

<script>
var SB_URL=document.querySelector('meta[name="data-supabase-url"]').content;
var SB_KEY=document.querySelector('meta[name="data-supabase-key"]').content;

var TIPS=['Teach what you learned — the best way to remember it.','Spaced repetition beats cramming every time.','Review your notes within 24 hours to retain 80% more.','Sleep consolidates memory — avoid all-nighters.','Pomodoro: 25 min focus, 5 min break.','Ask "why?" for every fact — understanding beats memorising.'];
document.getElementById('studyTip').textContent=TIPS[Math.floor(Math.random()*TIPS.length)];

function sbFetch(url){
    return fetch(url,{headers:{'apikey':SB_KEY,'Authorization':'Bearer '+SB_KEY,'Content-Type':'application/json'}});
}

/*
 * We try fetching from 'newsfeed_posts' first (the table your app uses,
 * as seen in web.php: queryTable('newsfeed_posts',...)).
 * If that returns an error object (not an array), we fall back to 'posts'.
 * We handle both column name variants:
 *   media_urls (array)  vs  media_url (string)
 *   link_meta  (object) vs  link_url  (string)
 */
async function loadFeed(){
    var feed=document.getElementById('guestFeed');
    var posts=null;

    /* Attempt 1 — newsfeed_posts, public + not archived */
    try{
        var r=await sbFetch(
            SB_URL+'/rest/v1/newsfeed_posts'+
            '?visibility=eq.public&is_archived=eq.false'+
            '&order=created_at.desc&limit=30'+
            '&select=id,content,media_url,file_urls,link_url,created_at,user_id,post_type,'+
            'profiles(first_name,last_name,username,profile_photo_url)'
        );
        var d=await r.json();
        if(Array.isArray(d)){posts=d;}
    }catch(e){}

    /* Attempt 2 — newsfeed_posts without visibility filter
       (in case the column name or value differs) */
    if(!posts){
        try{
            var r2=await sbFetch(
                SB_URL+'/rest/v1/newsfeed_posts'+
                '?order=created_at.desc&limit=30'+
                '&select=id,content,media_url,file_urls,link_url,created_at,user_id,post_type,'+
                'profiles(first_name,last_name,username,profile_photo_url)'
            );
            var d2=await r2.json();
            if(Array.isArray(d2)){
                posts=d2.filter(function(p){return p.is_archived!==true;});
            }
        }catch(e){}
    }

    /* Attempt 3 — 'posts' table with media_urls column name */
    if(!posts){
        try{
            var r3=await sbFetch(
                SB_URL+'/rest/v1/posts'+
                '?visibility=eq.public&order=created_at.desc&limit=30'+
                '&select=id,content,media_urls,file_urls,link_meta,created_at,user_id,'+
                'profiles(first_name,last_name,username,profile_photo_url)'
            );
            var d3=await r3.json();
            if(Array.isArray(d3)){posts=d3;}
        }catch(e){}
    }

    if(!posts){
        feed.innerHTML='<div class="feed-empty"><div class="ei">⚠️</div><p>Could not connect to the feed. Check your Supabase config.</p></div>';
        return;
    }
    if(!posts.length){
        feed.innerHTML='<div class="feed-empty"><div class="ei">📭</div><p>No public posts yet.</p></div>';
        return;
    }

    /* Like counts */
    var ids=posts.map(function(p){return p.id;});
    var likes={};
    try{
        var lr=await sbFetch(SB_URL+'/rest/v1/post_likes?post_id=in.('+ids.join(',')+')'+'&select=post_id');
        var lrows=await lr.json();
        (lrows||[]).forEach(function(row){likes[row.post_id]=(likes[row.post_id]||0)+1;});
    }catch(e){}

    feed.innerHTML=posts.map(function(p){return postCard(p,likes[p.id]||0);}).join('');
}

function postCard(post,likeCount){
    var a=post.profiles||{};
    var fn=a.first_name||'';var ln=a.last_name||'';
    var name=(fn+' '+ln).trim()||a.username||'User';
    var initials=((fn||'?')[0]+(ln||'?')[0]).toUpperCase();
    var ago=timeAgo(post.created_at);

    /* Handle both column name variants */
    var media=safeJ(post.media_urls,null)||(post.media_url?[post.media_url]:[]);
    var files=safeJ(post.file_urls,[]);
    var link=safeJ(post.link_meta,null)||(post.link_url?{url:post.link_url}:null);

    var avatarHTML=a.profile_photo_url
        ?'<img src="'+esc(a.profile_photo_url)+'" alt="'+esc(name)+'">'
        :initials;

    var mediaHTML='';
    if(media&&media.length){
        var cls='count-'+(media.length>4?'many':media.length);
        mediaHTML='<div class="post-body"><div class="post-media '+cls+'">'
            +media.slice(0,4).map(function(u,i){
                var isVid=/\.(mp4|mov|webm)(\?|$)/i.test(u);
                var ov=(i===3&&media.length>4)?'<div class="media-more-overlay">+'+(media.length-4)+'</div>':'';
                return '<div class="post-media-item">'
                    +(isVid?'<video src="'+esc(u)+'" controls preload="none"></video>'
                           :'<img src="'+esc(u)+'" loading="lazy" alt="">')
                    +ov+'</div>';
            }).join('')+'</div></div>';
    }

    var filesHTML='';
    if(files&&files.length){
        filesHTML='<div class="post-files">'+files.map(function(f){
            var url=typeof f==='string'?f:(f.url||'#');
            var nm=typeof f==='string'?f:(f.name||'File');
            return '<a class="post-file-row" href="'+esc(url)+'" target="_blank" rel="noopener">'
                +'<span class="post-file-icon">📎</span>'
                +'<span class="post-file-name">'+esc(nm)+'</span></a>';
        }).join('')+'</div>';
    }

    var linkHTML='';
    if(link&&link.url){
        linkHTML='<a class="post-link-preview" href="'+esc(link.url)+'" target="_blank" rel="noopener">'
            +(link.image?'<img class="post-link-img" src="'+esc(link.image)+'" alt="">':'')
            +'<div class="post-link-info">'
            +'<div class="post-link-title">'+esc(link.title||link.url)+'</div>'
            +'<div class="post-link-url">'+esc(link.url)+'</div>'
            +'</div></a>';
    }

    var countsHTML='';
    if(likeCount){
        countsHTML='<div class="post-counts">'
            +'<span class="post-counts-likes">❤️ '+likeCount+' like'+(likeCount!==1?'s':'')+'</span>'
            +'</div>';
    }

    /* Uses exact CSS class names from newsfeed.css */
    return '<div class="post-card">'
        +'<div class="post-header"><div class="post-author">'
        +'<div class="post-avatar">'+avatarHTML+'</div>'
        +'<div>'
        +'<div class="post-author-name">'+esc(name)+'</div>'
        +'<div class="post-author-meta"><span>'+ago+'</span>'
        +'<span class="post-vis-badge">· 🌐 Public</span></div>'
        +'</div></div></div>'
        +(post.content?'<div class="post-body"><div class="post-text">'+esc(post.content)+'</div></div>':'')
        +mediaHTML+filesHTML+linkHTML+countsHTML
        +'<div class="post-actions-bar">'
            +'<button class="post-action-btn like-btn" onclick="showModal(\'like\')">'
                +'<span class="action-icon">❤️</span>'
                +'<span>Like</span>'
            +'</button>'

            +'<button class="post-action-btn comment-btn" onclick="showModal(\'comment\')">'
                +'<span class="action-icon">💬</span>'
                +'<span>Comment</span>'
            +'</button>'

            +'<button class="post-action-btn share-btn" onclick="showModal(\'share\')">'
                +'<span class="action-icon">↗</span>'
                +'<span>Share</span>'
            +'</button>'
        +'</div></div>';
}

var MODALS={
    like:{icon:'❤️',title:'Like posts',body:'Sign up or log in to like and react to posts.'},
    comment:{icon:'💬',title:'Join the discussion',body:'Sign up or log in to comment on posts.'},
    share:{icon:'🔄',title:'Share this post',body:'Sign up or log in to share posts with your network.'},
    post:{icon:'✏️',title:'Create a post',body:'Join StudyHub to share your own content with the community.'},
    tab:{icon:'🔒',title:'Members only',body:'Log in or sign up to see posts from people you follow and your friends.'},
};
function showModal(type){var d=MODALS[type]||{icon:'🔒',title:'Join to continue',body:'Create a free StudyHub account.'};document.getElementById('imIcon').textContent=d.icon;document.getElementById('imTitle').textContent=d.title;document.getElementById('imBody').textContent=d.body;document.getElementById('interactModal').classList.add('open');}
function closeModal(){document.getElementById('interactModal').classList.remove('open');}
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeModal();});

function timeAgo(ts){var s=Math.floor((Date.now()-new Date(ts))/1000);if(s<60)return 'Just now';if(s<3600)return Math.floor(s/60)+'m ago';if(s<86400)return Math.floor(s/3600)+'h ago';if(s<604800)return Math.floor(s/86400)+'d ago';return new Date(ts).toLocaleDateString();}
function safeJ(v,fb){if(!v)return fb;if(typeof v==='object')return v;try{return JSON.parse(v);}catch(e){return fb;}}
function esc(t){if(t==null)return '';var d=document.createElement('div');d.textContent=String(t);return d.innerHTML;}

loadFeed();
</script>
</body>
</html>
