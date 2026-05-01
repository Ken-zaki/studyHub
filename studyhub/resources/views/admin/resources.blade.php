<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Approval - StudyHub Admin</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
@php $activeAdmin = 'resources'; @endphp
@include('admin.sidebar')
<main class="adm-main">
    <div class="adm-toolbar">
        <div class="adm-search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" id="resSearch" placeholder="Search resources…" oninput="filterRes()">
        </div>
        <select id="resFilter" class="adm-select" onchange="loadResources()">
            <option value="false">Pending Approval</option>
            <option value="true">Approved</option>
            <option value="">All</option>
        </select>
    </div>
    <div id="resourcesList"><div class="adm-loading">Loading…</div></div>
</main>

<script>
const SB_URL='{{ env("SUPABASE_URL") }}';
const SB_SVC='{{ env("SUPABASE_SERVICE_KEY") }}';
function svcH(){return{'apikey':SB_SVC,'Authorization':`Bearer ${SB_SVC}`,'Content-Type':'application/json'};}
let allRes=[];
const ICONS={pdf:'📄',docx:'📝',pptx:'📊',video:'🎬',image:'🖼️',link:'🔗',notes:'📄',exercise:'📝',slides:'📊',reviewer:'📋'};

async function loadResources(){
    const ap=document.getElementById('resFilter').value;
    const q=ap===''?'':`is_approved=eq.${ap}&`;
    const res=await fetch(`${SB_URL}/rest/v1/resources?${q}select=id,title,description,subject,file_type,file_url,visibility,is_approved,created_at,uploaded_by&order=created_at.desc`,{headers:svcH()});
    allRes=await res.json();
    renderRes(allRes);
}
function renderRes(resources){
    const el=document.getElementById('resourcesList');
    if(!resources.length){el.innerHTML='<div class="adm-card"><div class="adm-empty">No resources found. ✅</div></div>';return;}
    el.innerHTML=resources.map(r=>`
        <div class="adm-res-card">
            <div class="adm-res-icon">${ICONS[r.file_type||'']||'📎'}</div>
            <div class="adm-res-body">
                <div class="adm-res-title">${escH(r.title)}</div>
                ${r.description?`<div class="adm-res-desc">${escH(r.description.slice(0,80))}${r.description.length>80?'…':''}</div>`:''}
                <div class="adm-res-meta">
                    ${escH(r.subject||'—')} · ${escH(r.file_type||'file')} ·
                    <span class="adm-badge ${r.visibility==='public'?'badge-success':'badge-warn'}" style="font-size:10px;">${r.visibility||'public'}</span>
                    · ${new Date(r.created_at).toLocaleDateString()}
                </div>
            </div>
            <div class="adm-res-actions">
                ${r.file_url?`<a href="${escH(r.file_url)}" target="_blank" class="adm-act-btn">View</a>`:''}
                ${!r.is_approved
                    ?`<button class="adm-act-btn success" onclick="approveRes('${r.id}')">Approve</button>
                      <button class="adm-act-btn danger"  onclick="rejectRes('${r.id}')">Reject</button>`
                    :`<span class="adm-badge badge-success">Approved</span>
                      <button class="adm-act-btn danger" onclick="rejectRes('${r.id}')">Remove</button>`
                }
            </div>
        </div>`).join('');
}
function filterRes(){const q=document.getElementById('resSearch').value.toLowerCase();renderRes(allRes.filter(r=>!q||(r.title||'').toLowerCase().includes(q)||(r.subject||'').toLowerCase().includes(q)));}
async function approveRes(id){await fetch(`${SB_URL}/rest/v1/resources?id=eq.${id}`,{method:'PATCH',headers:svcH(),body:JSON.stringify({is_approved:true})});loadResources();}
async function rejectRes(id){if(!confirm('Delete this resource? This cannot be undone.'))return;await fetch(`${SB_URL}/rest/v1/resources?id=eq.${id}`,{method:'DELETE',headers:svcH()});loadResources();}
function escH(t){const d=document.createElement('div');d.textContent=t;return d.innerHTML;}
document.getElementById('adminPageTitle').textContent='Resource Approval';
loadResources();
</script>
</body></html>
