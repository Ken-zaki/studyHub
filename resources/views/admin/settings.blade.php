<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - StudyHub Admin</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
@php $activeAdmin = 'settings'; @endphp
@include('admin.sidebar')
<main class="adm-main">

    <div class="adm-settings-section">
        <div class="adm-settings-title">Admin Account</div>
        <div class="adm-settings-sub">Your admin profile information</div>
        <div class="adm-form-row">
            <div class="adm-form-group">
                <label class="adm-form-label">First Name</label>
                <input class="adm-form-input" value="{{ session('user_first_name') }}" readonly>
            </div>
            <div class="adm-form-group">
                <label class="adm-form-label">Last Name</label>
                <input class="adm-form-input" value="{{ session('user_last_name') }}" readonly>
            </div>
        </div>
        <div class="adm-form-group">
            <label class="adm-form-label">Username</label>
            <input class="adm-form-input" value="{{ session('user_username') }}" readonly>
        </div>
        <p style="font-size:12px;color:var(--adm-muted);">To change admin account details, update directly in Supabase.</p>
    </div>

    <div class="adm-settings-section">
        <div class="adm-settings-title">Platform Settings</div>
        <div class="adm-settings-sub">Control platform-wide behaviour</div>
        <div class="adm-toggle-row">
            <div class="adm-toggle-info">
                <div class="adm-toggle-label">Resource auto-approval</div>
                <div class="adm-toggle-desc">Automatically approve uploaded resources without admin review</div>
            </div>
            <div class="adm-toggle" id="toggleAutoApprove" onclick="toggle('toggleAutoApprove')"></div>
        </div>
        <div class="adm-toggle-row">
            <div class="adm-toggle-info">
                <div class="adm-toggle-label">New user registrations</div>
                <div class="adm-toggle-desc">Allow new users to sign up. Disable to freeze registrations.</div>
            </div>
            <div class="adm-toggle on" id="toggleRegistrations" onclick="toggle('toggleRegistrations')"></div>
        </div>
        <div class="adm-toggle-row">
            <div class="adm-toggle-info">
                <div class="adm-toggle-label">Public resource access</div>
                <div class="adm-toggle-desc">Allow guests to browse and download resources without logging in</div>
            </div>
            <div class="adm-toggle on" id="togglePublicRes" onclick="toggle('togglePublicRes')"></div>
        </div>
        <div class="adm-toggle-row">
            <div class="adm-toggle-info">
                <div class="adm-toggle-label">Maintenance mode</div>
                <div class="adm-toggle-desc">Show a maintenance page to all non-admin users</div>
            </div>
            <div class="adm-toggle" id="toggleMaintenance" onclick="toggle('toggleMaintenance')"></div>
        </div>
    </div>

    <div class="adm-settings-section">
        <div class="adm-settings-title">Danger Zone</div>
        <div class="adm-settings-sub">Irreversible actions — proceed with caution</div>
        <div class="adm-toggle-row">
            <div class="adm-toggle-info">
                <div class="adm-toggle-label" style="color:var(--adm-danger);">Clear all admin logs</div>
                <div class="adm-toggle-desc">Permanently delete all admin action logs. This cannot be undone.</div>
            </div>
            <button class="adm-btn adm-btn-danger" onclick="clearLogs()">Clear Logs</button>
        </div>
    </div>

</main>
<script>
function toggle(id){document.getElementById(id).classList.toggle('on');}
function clearLogs(){if(confirm('Delete ALL admin logs? This cannot be undone.'))alert('Feature coming soon — connect to Supabase admin_logs DELETE.');}
document.getElementById('adminPageTitle').textContent='Settings';
</script>
</body></html>
