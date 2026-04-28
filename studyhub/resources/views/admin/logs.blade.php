<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Logs - StudyHub Admin</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
@php $activeAdmin = 'logs'; @endphp
@include('admin.sidebar')
<main class="adm-main">
    <div class="adm-card" style="margin-top:0;">
        <div class="adm-card-header">
            <span class="adm-card-title">Admin Action Logs</span>
            <span class="adm-muted" id="logCount">Loading…</span>
        </div>
        <div id="logsList"><div class="adm-loading">Loading logs…</div></div>
    </div>
</main>
<script>
const SB_URL='{{ env("SUPABASE_URL") }}';
const SB_SVC='{{ env("SUPABASE_SERVICE_KEY") }}';
function svcH(){return{'apikey':SB_SVC,'Authorization':`Bearer ${SB_SVC}`};}
const ACTION_COLORS={ban_user:'badge-danger',unban_user:'badge-success',change_role:'badge-warn',approve_resource:'badge-success',reject_resource:'badge-danger',resolve_report:'badge-info'};

async function loadLogs(){
    const res=await fetch(`${SB_URL}/rest/v1/admin_logs?select=id,action,target_type,notes,created_at,admin_id&order=created_at.desc&limit=100`,{headers:svcH()});
    const logs=await res.json();
    document.getElementById('logCount').textContent=`${logs.length} entries`;
    const el=document.getElementById('logsList');
    if(!logs.length){el.innerHTML='<div class="adm-empty">No admin actions logged yet.</div>';return;}
    el.innerHTML=logs.map(l=>`
        <div class="adm-log-entry">
            <div class="adm-log-dot" style="background:${l.action?.includes('ban')||l.action?.includes('reject')?'var(--adm-danger)':l.action?.includes('approve')||l.action?.includes('unban')?'var(--adm-success)':'var(--adm-warning)'}"></div>
            <div style="flex:1;">
                <div class="adm-log-action">
                    <span class="adm-badge ${ACTION_COLORS[l.action]||'badge-gray'}">${escH(l.action||'action')}</span>
                    ${l.target_type?`<span class="adm-muted" style="font-size:12px;margin-left:8px;">on ${escH(l.target_type)}</span>`:''}
                </div>
                ${l.notes?`<div style="font-size:12px;color:var(--adm-muted);margin-top:3px;">${escH(l.notes)}</div>`:''}
                <div class="adm-log-meta">${new Date(l.created_at).toLocaleString()}</div>
            </div>
        </div>`).join('');
}
function escH(t){const d=document.createElement('div');d.textContent=t;return d.innerHTML;}
document.getElementById('adminPageTitle').textContent='Admin Logs';
loadLogs();
</script>
</body></html>
