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
        /* Toolbar */
        .g-res-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:12px;}
        .g-res-search{flex:1;min-width:200px;position:relative;}
        .g-res-search svg{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:15px;height:15px;color:var(--text-light);pointer-events:none;}
        .g-res-search input{width:100%;padding:9px 12px 9px 34px;box-sizing:border-box;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13px;background:var(--bg-card);color:var(--text-primary);}
        .g-res-search input:focus{outline:none;border-color:var(--primary);}
        .g-res-select{padding:9px 12px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:13px;background:var(--bg-card);color:var(--text-primary);cursor:pointer;}
        .g-res-select:focus{outline:none;border-color:var(--primary);}
        /* Resource rows */
        .g-res-row{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:14px;background:var(--bg-card);border:1px solid var(--border);margin-bottom:10px;cursor:pointer;transition:box-shadow 0.2s,transform 0.15s,border-color 0.18s;}
        .g-res-row:hover{box-shadow:0 3px 14px rgba(0,0,0,0.07);transform:translateY(-1px);border-color:var(--primary);}
        .g-res-icon{width:44px;height:44px;border-radius:11px;flex-shrink:0;background:rgba(26,95,122,0.07);display:flex;align-items:center;justify-content:center;font-size:20px;}
        .g-res-body{flex:1;min-width:0;}
        .g-res-title{font-size:14px;font-weight:600;color:var(--text-primary);margin-bottom:3px;}
        .g-res-desc{font-size:12px;color:var(--text-secondary);margin-bottom:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .g-res-meta{display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:11px;color:var(--text-light);}
        .g-res-tag{padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:rgba(26,95,122,0.08);color:var(--primary);}
        .g-res-type{padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;}
        .g-res-right{display:flex;align-items:center;gap:8px;flex-shrink:0;}
        .g-dl-btn{padding:6px 14px;border-radius:8px;border:none;background:var(--primary);color:white;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:700;cursor:pointer;text-decoration:none;transition:opacity 0.18s;display:flex;align-items:center;gap:4px;white-space:nowrap;}
        .g-dl-btn:hover{opacity:.88;}
        .g-chevron{color:var(--text-light);font-size:18px;line-height:1;}
        /* Right widgets */
        .g-widget{background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:18px 20px;margin-bottom:16px;}
        .g-widget-title{font-size:14px;font-weight:700;color:var(--text-primary);margin-bottom:12px;}
        /* Detail overlay */
        .g-detail-overlay{position:fixed;inset:0;background:var(--bg-main,#f5f6fa);z-index:5000;overflow-y:auto;display:none;}
        .g-detail-topbar{position:sticky;top:0;z-index:10;background:var(--bg-card,white);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 32px;height:56px;}
        .g-detail-back{display:flex;align-items:center;gap:6px;background:none;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;color:var(--text-secondary);padding:0;}
        .g-detail-back:hover{color:var(--primary);}
        .g-detail-back svg{width:18px;height:18px;}
        .g-detail-body{max-width:1080px;margin:0 auto;padding:32px;display:flex;gap:28px;align-items:flex-start;}
        .g-detail-main{flex:1;min-width:0;}
        .g-detail-side{width:260px;flex-shrink:0;}
        .g-detail-hdr{display:flex;align-items:flex-start;gap:18px;background:var(--bg-card);border:1px solid var(--border);border-radius:18px;padding:24px;margin-bottom:20px;}
        .g-detail-icon{width:64px;height:64px;border-radius:16px;flex-shrink:0;background:rgba(26,95,122,0.08);display:flex;align-items:center;justify-content:center;font-size:28px;}
        .g-detail-title{font-family:'Crimson Pro',serif;font-size:26px;font-weight:700;color:var(--text-primary);margin-bottom:8px;}
        .g-detail-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:13px;color:var(--text-secondary);}
        .g-section{background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:20px 24px;margin-bottom:16px;}
        .g-section-title{font-size:14px;font-weight:700;color:var(--text-primary);margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--border);}
        /* Comments */
        .g-comment{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);}
        .g-comment:last-child{border-bottom:none;}
        .g-comment-av{width:36px;height:36px;border-radius:10px;flex-shrink:0;background:linear-gradient(135deg,var(--primary),var(--primary-dark,#144d61));color:white;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;overflow:hidden;}
        .g-comment-body{flex:1;min-width:0;}
        .g-comment-author{font-size:13px;font-weight:600;color:var(--text-primary);}
        .g-comment-date{font-size:11px;color:var(--text-light);margin-left:6px;}
        .g-comment-text{font-size:14px;color:var(--text-secondary);line-height:1.6;margin-top:3px;}
        /* Info card */
        .g-info-card{background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:18px 20px;margin-bottom:14px;}
        .g-info-row{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px;}
        .g-info-row:last-child{border-bottom:none;}
        .g-info-lbl{color:var(--text-secondary);flex-shrink:0;}
        .g-info-val{font-weight:600;color:var(--text-primary);text-align:right;}
        /* Guest comment prompt */
        .g-comment-prompt{background:rgba(26,95,122,0.04);border:1.5px dashed rgba(26,95,122,0.2);border-radius:14px;padding:16px;text-align:center;cursor:pointer;transition:all 0.2s;margin-bottom:16px;}
        .g-comment-prompt:hover{background:rgba(26,95,122,0.07);}
        /* Modal */
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
        .gm-s{display:block;padding:12px;border-radius:12px;border:1.5px solid var(--border,#e5e7eb);background:var(--bg-card,white);font-size:14px;font-weight:600;color:var(--text-primary);text-decoration:none;}
        .gm-s:hover{border-color:var(--primary);color:var(--primary);}
        .gm-d{margin-top:10px;font-size:13px;color:var(--text-light);cursor:pointer;background:none;border:none;font-family:inherit;}
    </style>
</head>
<body>

@include('layouts.guest-sidebar', ['guestNav' => 'resources'])

<main class="main-content">
    <div class="feed-column">
        <header class="page-header">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                <div>
                    <h1 class="page-title">Resources</h1>
                    <p class="page-subtitle">Study materials, notes, and files shared by the community</p>
                </div>
                <button onclick="showModal('upload')" style="display:flex;align-items:center;gap:6px;padding:9px 18px;border-radius:10px;border:none;background:var(--primary);color:white;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:700;cursor:pointer;" onmouseover="this.style.opacity='.88';" onmouseout="this.style.opacity='1';">
                    <svg style="width:15px;height:15px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Upload Resource
                </button>
            </div>
        </header>

        <div class="g-res-toolbar">
            <div class="g-res-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                <input type="text" id="resSearch" placeholder="Search resources by title, subject, description…" oninput="filterRes()">
            </div>
        </div>
        <div class="g-res-toolbar" style="margin-top:-4px;">
            <select class="g-res-select" id="typeFilter" onchange="filterRes()">
                <option value="">All Types</option>
                <option value="notes">Notes</option><option value="exercise">Exercises</option>
                <option value="slides">Slides</option><option value="reviewer">Reviewers</option>
                <option value="video">Videos</option><option value="image">Images</option>
                <option value="text">Text</option><option value="link">Links</option>
            </select>
            <select class="g-res-select" id="subjectFilter" onchange="filterRes()">
                <option value="">All Subjects</option>
                <option>Mathematics</option><option>Science</option><option>Filipino</option>
                <option>English</option><option>PE</option><option>Health</option>
                <option>Music</option><option>Arts</option><option>Social Studies</option>
                <option>Computer Science</option><option>Values Education</option><option>MAPEH</option>
                <option>History</option><option>Chemistry</option><option>Physics</option>
                <option>Biology</option><option>Economics</option><option>Others</option>
            </select>
        </div>

        <div id="resCount" style="font-size:13px;color:var(--text-secondary);margin-bottom:14px;">Loading resources…</div>
        <div id="resList"><div class="loading-state">Loading…</div></div>
    </div>

    <aside class="right-sidebar">
        <div class="g-widget">
            <div class="g-widget-title">📤 My Uploads</div>
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:10px;">Sign up to upload and manage your own study materials.</p>
            <a href="{{ route('signup') }}" style="display:block;text-align:center;padding:9px;border-radius:10px;background:var(--primary);color:white;font-weight:700;font-size:13px;text-decoration:none;" onmouseover="this.style.opacity='.88';" onmouseout="this.style.opacity='1';">Sign Up Free →</a>
        </div>
        <div class="g-widget">
            <div class="g-widget-title">📊 Stats</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div style="background:var(--bg-main);border-radius:10px;padding:12px;text-align:center;">
                    <div style="font-size:20px;font-weight:700;color:var(--text-primary);" id="statTotal">—</div>
                    <div style="font-size:11px;color:var(--text-secondary);">Resources</div>
                </div>
                <div style="background:var(--bg-main);border-radius:10px;padding:12px;text-align:center;">
                    <div style="font-size:20px;font-weight:700;color:var(--text-primary);" id="statSubjects">—</div>
                    <div style="font-size:11px;color:var(--text-secondary);">Subjects</div>
                </div>
            </div>
        </div>
    </aside>
</main>

{{-- Detail overlay --}}
<div class="g-detail-overlay" id="resDetailOverlay">
    <div class="g-detail-topbar">
        <button class="g-detail-back" onclick="closeDetail()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Resources
        </button>
    </div>
    <div class="g-detail-body">
        <div class="g-detail-main">
            <div class="g-detail-hdr">
                <div class="g-detail-icon" id="dIcon">📄</div>
                <div style="flex:1;min-width:0;">
                    <h1 class="g-detail-title" id="dTitle">Loading…</h1>
                    <div class="g-detail-meta" id="dMeta"></div>
                    <div id="dRating" style="margin-top:8px;font-size:13px;color:var(--text-light);"></div>
                </div>
            </div>
            <div class="g-section" id="dDescSec" style="display:none;">
                <div class="g-section-title">Description</div>
                <div id="dDesc" style="font-size:14px;color:var(--text-secondary);line-height:1.7;"></div>
            </div>
            <div class="g-section" id="dContentSec" style="display:none;">
                <div class="g-section-title">Content</div>
                <div id="dContent" style="font-size:14px;color:var(--text-secondary);line-height:1.7;white-space:pre-wrap;font-family:'DM Sans',sans-serif;"></div>
            </div>
            <div class="g-section" id="dFileSec" style="display:none;">
                <div class="g-section-title">File</div>
                <a id="dFileBtn" href="#" target="_blank" download style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:10px;background:var(--primary);color:white;font-weight:700;font-size:14px;text-decoration:none;" onmouseover="this.style.opacity='.88';" onmouseout="this.style.opacity='1';">
                    <svg style="width:16px;height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download File
                </a>
            </div>
            <div class="g-section" id="dLinkSec" style="display:none;">
                <div class="g-section-title">Reference Link</div>
                <a id="dLinkBtn" href="#" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:10px;border:1.5px solid var(--border);background:var(--bg-card);color:var(--primary);font-weight:600;font-size:14px;text-decoration:none;">Open Link →</a>
            </div>
            <div class="g-section">
                <div class="g-section-title">
                    Comments &amp; Ratings
                    <span id="dCommentCount" style="margin-left:6px;background:var(--bg-main);padding:2px 8px;border-radius:20px;font-size:12px;font-weight:600;color:var(--text-secondary);">0</span>
                </div>
                <div class="g-comment-prompt" onclick="showModal('comment')">
                    <div style="font-size:22px;margin-bottom:6px;">💬</div>
                    <div style="font-size:14px;font-weight:600;color:var(--primary);margin-bottom:3px;">Sign in to rate and comment</div>
                    <div style="font-size:12px;color:var(--text-secondary);">Create a free account to leave feedback on this resource.</div>
                </div>
                <div id="dComments"><div style="padding:16px 0;color:var(--text-light);font-size:13px;">Loading comments…</div></div>
            </div>
        </div>
        <div class="g-detail-side">
            <div class="g-info-card">
                <div class="g-info-row"><span class="g-info-lbl">Subject</span><span class="g-info-val" id="iSubject">—</span></div>
                <div class="g-info-row"><span class="g-info-lbl">Type</span><span class="g-info-val" id="iType">—</span></div>
                <div class="g-info-row"><span class="g-info-lbl">Uploaded by</span><span class="g-info-val" id="iUploader">—</span></div>
                <div class="g-info-row"><span class="g-info-lbl">Date</span><span class="g-info-val" id="iDate">—</span></div>
                <div class="g-info-row" id="iViewsRow" style="display:none;"><span class="g-info-lbl">Views</span><span class="g-info-val" id="iViews">—</span></div>
            </div>
            <div class="g-info-card" style="text-align:center;">
                <div style="font-size:14px;font-weight:600;color:var(--text-primary);margin-bottom:6px;">🎓 Join StudyHub</div>
                <p style="font-size:12px;color:var(--text-secondary);line-height:1.5;margin-bottom:12px;">Sign up to rate, comment, upload resources, and more.</p>
                <a href="{{ route('signup') }}" style="display:block;padding:9px;border-radius:10px;background:var(--primary);color:white;font-weight:700;font-size:13px;text-decoration:none;margin-bottom:8px;">Sign Up Free →</a>
                <a href="{{ route('login') }}"  style="display:block;padding:9px;border-radius:10px;border:1.5px solid var(--border);background:var(--bg-card);color:var(--text-primary);font-weight:600;font-size:13px;text-decoration:none;">Log In</a>
            </div>
        </div>
    </div>
</div>

{{-- Interact modal --}}
<div class="g-modal-overlay" id="resModal" onclick="if(event.target===this)closeModal();">
    <div class="g-modal">
        <div class="g-modal-icon" id="rmIcon"></div>
        <h3 id="rmTitle"></h3><p id="rmBody"></p>
        <div class="g-modal-btns">
            <a href="{{ route('signup') }}" class="gm-p">Create Free Account</a>
            <a href="{{ route('login') }}"  class="gm-s">I already have an account</a>
        </div>
        <button class="gm-d" onclick="closeModal()">Maybe later</button>
    </div>
</div>

{{-- Supabase JS — same CDN as registered resources.blade.php --}}
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script>
// ── Config ─────────────────────────────────────────────────────
var SUPABASE_URL      = '{{ env("SUPABASE_URL") }}';
var SUPABASE_ANON_KEY = '{{ env("SUPABASE_ANON_KEY") }}';
var _sb = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

var TYPE_ICONS={notes:'📄',exercise:'📝',slides:'📊',reviewer:'📋',video:'🎬',image:'🖼️',link:'🔗',text:'✍️'};
var TYPE_COLORS={notes:'#3b82f6',exercise:'#8b5cf6',slides:'#f59e42',reviewer:'#10b981',video:'#ef4444',image:'#ec4899',link:'#6b7280',text:'#14b8a6'};
var allRes=[];

// ── Load resources ─────────────────────────────────────────────
// Mirrors resources.js exactly:
//   .from('resources')
//   .select('*, profiles(first_name, last_name, username)')
//   .eq('is_approved', true)
//   .order('created_at', { ascending: false })
// Then client-side filters by visibility === 'public' (same as renderResources()).
async function loadRes(){
    try{
        var result=await _sb
            .from('resources')
            .select('*, profiles(first_name, last_name, username)')
            .eq('is_approved',true)
            .order('created_at',{ascending:false});

        if(result.error) throw result.error;

        var data=result.data||[];

        // Client-side filter: only show public resources (same logic as renderResources())
        allRes=data.filter(function(r){
            var vis=r.visibility==='private'||r.education_level==='private'?'private':'public';
            return vis==='public';
        });

        var subjects=new Set(allRes.map(function(r){return r.subject;}).filter(Boolean));
        document.getElementById('statTotal').textContent=allRes.length;
        document.getElementById('statSubjects').textContent=subjects.size;
        filterRes();
    }catch(err){
        document.getElementById('resList').innerHTML=
            '<div style="color:#dc2626;padding:16px;font-size:13px;">Failed to load resources: '+escH(err.message)+'</div>';
    }
}

function filterRes(){
    var q=(document.getElementById('resSearch').value||'').toLowerCase();
    var t=document.getElementById('typeFilter').value;
    var s=document.getElementById('subjectFilter').value;
    var f=allRes.filter(function(r){
        return(!q||(r.title||'').toLowerCase().includes(q)||(r.description||'').toLowerCase().includes(q))
            &&(!t||r.file_type===t)&&(!s||r.subject===s);
    });
    document.getElementById('resCount').textContent=f.length+' resource'+(f.length!==1?'s':'')+' found';
    renderRes(f);
}

function renderRes(items){
    var el=document.getElementById('resList');
    if(!items.length){
        el.innerHTML='<div style="padding:48px;text-align:center;color:var(--text-light);"><div style="font-size:36px;margin-bottom:10px;">📭</div><div>No resources match your search.</div></div>';
        return;
    }
    el.innerHTML=items.map(function(r){
        var up=r.profiles?((r.profiles.first_name||'')+' '+(r.profiles.last_name||'')).trim()||('@'+(r.profiles.username||'')):'—';
        var icon=TYPE_ICONS[r.file_type]||'📎';
        var col=TYPE_COLORS[r.file_type]||'#6b7280';
        var desc=r.description?r.description.slice(0,80)+(r.description.length>80?'…':''):'';
        return '<div class="g-res-row" onclick="openDetail(\''+escH(r.id)+'\')">'
            +'<div class="g-res-icon">'+icon+'</div>'
            +'<div class="g-res-body">'
            +'<div class="g-res-title">'+escH(r.title||'Untitled')+'</div>'
            +(desc?'<div class="g-res-desc">'+escH(desc)+'</div>':'')
            +'<div class="g-res-meta">'
            +(r.subject?'<span class="g-res-tag">'+escH(r.subject)+'</span>':'')
            +(r.file_type?'<span class="g-res-type" style="background:'+col+'18;color:'+col+';">'+escH(r.file_type.toUpperCase())+'</span>':'')
            +'<span>by '+escH(up)+'</span>'
            +(r.view_count?'<span>👁 '+r.view_count+'</span>':'')
            +'</div></div>'
            +'<div class="g-res-right">'
            +(r.file_url&&r.file_type!=='link'?'<a href="'+escH(r.file_url)+'" target="_blank" download class="g-dl-btn" onclick="event.stopPropagation()">⬇ Download</a>':'')
            +(r.file_type==='link'&&r.file_url?'<a href="'+escH(r.file_url)+'" target="_blank" class="g-dl-btn" onclick="event.stopPropagation()">Open</a>':'')
            +'<span class="g-chevron">›</span>'
            +'</div></div>';
    }).join('');
}

// ── Open detail ─────────────────────────────────────────────────
async function openDetail(id){
    var res=allRes.find(function(r){return r.id===id;});
    if(!res) return;
    var overlay=document.getElementById('resDetailOverlay');
    overlay.style.display='block'; overlay.scrollTop=0;
    document.body.style.overflow='hidden';

    var icon=TYPE_ICONS[res.file_type]||'📎';
    var col=TYPE_COLORS[res.file_type]||'#6b7280';
    var up=res.profiles?((res.profiles.first_name||'')+' '+(res.profiles.last_name||'')).trim()||('@'+(res.profiles.username||'')):'Unknown';

    document.getElementById('dIcon').textContent=icon;
    document.getElementById('dTitle').textContent=res.title||'Untitled';

    var meta='';
    if(res.subject) meta+='<span class="g-res-tag">'+escH(res.subject)+'</span>';
    if(res.file_type) meta+='<span class="g-res-type" style="background:'+col+'18;color:'+col+';">'+escH(res.file_type.toUpperCase())+'</span>';
    meta+='<span>by '+escH(up)+'</span>';
    document.getElementById('dMeta').innerHTML=meta;

    var dds=document.getElementById('dDescSec');
    if(res.description){dds.style.display='block';document.getElementById('dDesc').textContent=res.description;}else{dds.style.display='none';}
    var dcs=document.getElementById('dContentSec');
    if(res.content){dcs.style.display='block';document.getElementById('dContent').textContent=res.content;}else{dcs.style.display='none';}
    var dfs=document.getElementById('dFileSec');
    if(res.file_url&&res.file_type!=='link'){dfs.style.display='block';document.getElementById('dFileBtn').href=res.file_url;}else{dfs.style.display='none';}
    var dls=document.getElementById('dLinkSec');
    if(res.file_type==='link'&&res.file_url){dls.style.display='block';document.getElementById('dLinkBtn').href=res.file_url;}else{dls.style.display='none';}

    document.getElementById('iSubject').textContent=res.subject||'—';
    document.getElementById('iType').textContent=res.file_type||'—';
    document.getElementById('iUploader').textContent=up;
    document.getElementById('iDate').textContent=res.created_at?new Date(res.created_at).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'}):'—';
    if(res.view_count){document.getElementById('iViewsRow').style.display='flex';document.getElementById('iViews').textContent=res.view_count;}
    else{document.getElementById('iViewsRow').style.display='none';}

    loadComments(id);

    // Increment view count (non-blocking)
    _sb.from('resources').update({view_count:(res.view_count||0)+1}).eq('id',id).then(function(){});
}

async function loadComments(resourceId){
    var el=document.getElementById('dComments');
    var badge=document.getElementById('dCommentCount');
    el.innerHTML='<div style="padding:16px 0;color:var(--text-light);font-size:13px;">Loading comments…</div>';
    try{
        // Ratings avg
        var rr=await _sb.from('resource_ratings').select('rating').eq('resource_id',resourceId);
        if(!rr.error&&rr.data&&rr.data.length){
            var avg=rr.data.reduce(function(s,r){return s+r.rating;},0)/rr.data.length;
            var stars=[1,2,3,4,5].map(function(i){return '<span style="font-size:18px;color:'+(i<=Math.round(avg)?'#f59e0b':'#d1d5db')+';">★</span>';}).join('');
            document.getElementById('dRating').innerHTML='<div style="display:flex;gap:2px;align-items:center;">'+stars+'<span style="font-size:13px;color:var(--text-secondary);margin-left:6px;">'+avg.toFixed(1)+' ('+rr.data.length+' rating'+(rr.data.length!==1?'s':'')+')</span></div>';
        }else{
            document.getElementById('dRating').innerHTML='<span style="font-size:13px;color:var(--text-light);">No ratings yet</span>';
        }
        // Comments
        var cr=await _sb.from('resource_comments').select('*, profiles(first_name,last_name,username,profile_photo_url)').eq('resource_id',resourceId).order('created_at',{ascending:true});
        if(cr.error) throw cr.error;
        var comments=cr.data||[];
        badge.textContent=comments.length;
        if(!comments.length){el.innerHTML='<div style="padding:16px 0;color:var(--text-light);font-size:13px;">No comments yet. Be the first!</div>';return;}
        el.innerHTML=comments.map(function(c){
            var p=c.profiles||{};
            var name=((p.first_name||'')+' '+(p.last_name||'')).trim()||p.username||'User';
            var initials=((p.first_name||'?')[0]+(p.last_name||'?')[0]).toUpperCase();
            var stars=c.rating?[1,2,3,4,5].map(function(i){return '<span style="font-size:13px;color:'+(i<=c.rating?'#f59e0b':'#d1d5db')+';">★</span>';}).join(''):'';
            var av=p.profile_photo_url?'<img src="'+escH(p.profile_photo_url)+'" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">':initials;
            return '<div class="g-comment"><div class="g-comment-av">'+av+'</div>'
                +'<div class="g-comment-body">'
                +'<div><span class="g-comment-author">'+escH(name)+'</span><span class="g-comment-date">'+timeAgo(c.created_at)+'</span></div>'
                +(stars?'<div style="display:flex;gap:2px;margin:3px 0;">'+stars+'</div>':'')
                +(c.comment?'<div class="g-comment-text">'+escH(c.comment)+'</div>':'')
                +'</div></div>';
        }).join('');
    }catch(e){el.innerHTML='<div style="color:#dc2626;font-size:13px;">Failed to load comments.</div>';}
}

function closeDetail(){document.getElementById('resDetailOverlay').style.display='none';document.body.style.overflow='';}

// ── Interact modal ──────────────────────────────────────────────
var MODALS={
    rate:{icon:'⭐',title:'Rate this resource',body:'Sign up or log in to rate and review study materials.'},
    comment:{icon:'💬',title:'Add a comment',body:'Sign up or log in to leave feedback on resources.'},
    upload:{icon:'⬆️',title:'Upload resources',body:'Create a free account to share your notes, slides, and study materials.'},
};
function showModal(type){var d=MODALS[type]||{icon:'🔒',title:'Join to continue',body:'Create a free StudyHub account.'};document.getElementById('rmIcon').textContent=d.icon;document.getElementById('rmTitle').textContent=d.title;document.getElementById('rmBody').textContent=d.body;document.getElementById('resModal').classList.add('open');}
function closeModal(){document.getElementById('resModal').classList.remove('open');}
document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeModal();if(document.getElementById('resDetailOverlay').style.display!=='none')closeDetail();}});

// ── Helpers ─────────────────────────────────────────────────────
function timeAgo(ts){var s=Math.floor((Date.now()-new Date(ts))/1000);if(s<60)return 'Just now';if(s<3600)return Math.floor(s/60)+'m ago';if(s<86400)return Math.floor(s/3600)+'h ago';if(s<604800)return Math.floor(s/86400)+'d ago';return new Date(ts).toLocaleDateString();}
function escH(t){if(t==null)return '';if(typeof t!=='string')t=String(t);var d=document.createElement('div');d.textContent=t;return d.innerHTML;}

loadRes();
</script>
</body>
</html>
