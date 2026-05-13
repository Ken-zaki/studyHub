<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - StudyHub Admin</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
@php $activeAdmin = 'reports'; @endphp
@include('admin.sidebar')
<main class="adm-main">
    <div class="adm-toolbar">
        <div class="adm-search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" id="reportSearch" placeholder="Search reports…" oninput="filterReports()">
        </div>
        <select id="reportStatusFilter" class="adm-select" onchange="filterReports()">
            <option value="pending">Pending</option>
            <option value="reviewed">Reviewed</option>
            <option value="resolved">Resolved</option>
            <option value="">All</option>
        </select>
    </div>
    <div class="adm-card" style="margin-top:0;">
        <div class="adm-card-header">
            <span class="adm-card-title">User Reports</span>
            <span class="adm-muted" id="reportCount">Loading…</span>
        </div>
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead><tr><th>Type</th><th>Reason</th><th>Reported By</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody id="reportsBody"><tr><td colspan="6" class="adm-loading">Loading…</td></tr></tbody>
            </table>
        </div>
    </div>
</main>

<div class="adm-modal-overlay" id="resolveModal" style="display:none;">
    <div class="adm-modal">
        <div class="adm-modal-header">
            <span class="adm-modal-title">Resolve Report</span>
            <button class="adm-modal-close" onclick="closeResolve()">✕</button>
        </div>
        <p style="font-size:13px;color:var(--adm-muted);margin-bottom:12px;">Admin notes (optional):</p>
        <textarea class="adm-textarea" id="adminNotes" placeholder="Describe the action taken…"></textarea>
        <div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end;flex-wrap:wrap;">
            <button class="adm-btn" onclick="closeResolve()">Cancel</button>
            <button class="adm-btn adm-btn-primary" onclick="updateReport('reviewed')">Mark Reviewed</button>
            <button class="adm-btn adm-btn-danger"  onclick="updateReport('resolved')">Resolve</button>
        </div>
    </div>
</div>

<script>
const SB_URL='{{ env("SUPABASE_URL") }}';
const SB_SVC='{{ env("SUPABASE_SERVICE_KEY") }}';
function svcH(){return{'apikey':SB_SVC,'Authorization':`Bearer ${SB_SVC}`,'Content-Type':'application/json'};}
let allReports=[],pendingReportId=null;

async function loadReports(){
    const status=document.getElementById('reportStatusFilter').value;
    const url=`${SB_URL}/rest/v1/reports?${status?`status=eq.${status}&`:''}select=id,reason,reported_content_type,status,created_at,admin_notes&order=created_at.desc`;
    const res=await fetch(url,{headers:svcH()});
    allReports=await res.json();
    renderReports(allReports);
}
function renderReports(reports){
    document.getElementById('reportCount').textContent=`${reports.length} report${reports.length!==1?'s':''}`;
    const body=document.getElementById('reportsBody');
    if(!reports.length){body.innerHTML='<tr><td colspan="6" class="adm-empty">No reports found. ✅</td></tr>';return;}
    body.innerHTML=reports.map(r=>`<tr>
        <td><span class="adm-badge badge-info">${escH(r.reported_content_type||'content')}</span></td>
        <td style="max-width:220px;"><span title="${escH(r.reason||'')}">${escH((r.reason||'').slice(0,60))}${(r.reason||'').length>60?'…':''}</span></td>
        <td><span class="adm-muted" style="font-size:12px;">—</span></td>
        <td><span class="adm-badge ${r.status==='pending'?'badge-danger':r.status==='reviewed'?'badge-warn':'badge-success'}">${r.status}</span></td>
        <td><span class="adm-muted" style="font-size:12px;">${new Date(r.created_at).toLocaleDateString()}</span></td>
        <td>${r.status==='resolved'?'<span class="adm-muted" style="font-size:12px;">Done</span>':`<button class="adm-act-btn" onclick="openResolve('${r.id}')">Review</button>`}</td>
    </tr>`).join('');
}
function filterReports(){
    const q=document.getElementById('reportSearch').value.toLowerCase();
    renderReports(allReports.filter(r=>!q||(r.reason||'').toLowerCase().includes(q)||(r.reported_content_type||'').toLowerCase().includes(q)));
}
function openResolve(id){pendingReportId=id;document.getElementById('adminNotes').value='';document.getElementById('resolveModal').style.display='flex';}
function closeResolve(){document.getElementById('resolveModal').style.display='none';}
async function updateReport(status){
    const notes=document.getElementById('adminNotes').value;
    await fetch(`${SB_URL}/rest/v1/reports?id=eq.${pendingReportId}`,{
        method:'PATCH',headers:svcH(),
        body:JSON.stringify({status,admin_notes:notes,reviewed_at:new Date().toISOString()})
    });
    closeResolve();loadReports();
}
function escH(t){const d=document.createElement('div');d.textContent=t;return d.innerHTML;}
document.getElementById('adminPageTitle').textContent='Reports';
loadReports();
document.getElementById('reportStatusFilter').addEventListener('change',loadReports);
</script>
</body></html>
