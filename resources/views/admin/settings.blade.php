<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - StudyHub Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin_settings.css') }}">
</head>
<body>
@php $activeAdmin = 'settings'; @endphp
@include('admin.sidebar')

<main class="adm-main">
<div class="adm-settings-wrap">

    <!-- ── ADMIN ACCOUNT ── -->
    <div class="adm-settings-section">
        <div class="adm-settings-section-head">
            <div class="adm-settings-title">👤 Admin Account</div>
            <div class="adm-settings-sub">Your admin profile information — read only</div>
        </div>
        <div class="adm-settings-body">

            <!-- Profile card -->
            @php
                $firstName = session('user_first_name', 'Admin');
                $lastName  = session('user_last_name', '');
                $username  = session('user_username', 'useradmin');
                $photo     = session('user_profile_photo', '');
                $initials  = strtoupper(substr($firstName,0,1).substr($lastName,0,1));
                $fullName  = trim("$firstName $lastName") ?: $username;
            @endphp
            <div class="adm-settings-profile-card">
                <div class="adm-settings-avatar">
                    @if($photo)
                        <img src="{{ $photo }}" alt="{{ $fullName }}"
                             style="width:100%;height:100%;object-fit:cover;border-radius:14px;">
                    @else
                        {{ $initials }}
                    @endif
                </div>
                <div>
                    <div class="adm-settings-profile-name">{{ $fullName }}</div>
                    <div class="adm-settings-profile-role">Platform Admin · @{{ $username }}</div>
                </div>
            </div>

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

            <p class="adm-form-hint">
                🔒 To change admin account details, update the profile directly in Supabase.
            </p>
        </div>
    </div>

    <!-- ── PLATFORM SETTINGS ── -->
    <div class="adm-settings-section">
        <div class="adm-settings-section-head">
            <div class="adm-settings-title">⚙️ Platform Settings</div>
            <div class="adm-settings-sub">Control platform-wide behaviour for all users</div>
        </div>
        <div class="adm-settings-body">

            <div class="adm-toggle-row">
                <div class="adm-toggle-info">
                    <div class="adm-toggle-label">Resource auto-approval</div>
                    <div class="adm-toggle-desc">Automatically approve uploaded resources without admin review. Not recommended.</div>
                </div>
                <div class="adm-toggle" id="toggleAutoApprove" onclick="toggle('toggleAutoApprove')" title="Toggle auto-approval"></div>
            </div>

            <div class="adm-toggle-row">
                <div class="adm-toggle-info">
                    <div class="adm-toggle-label">New user registrations</div>
                    <div class="adm-toggle-desc">Allow new users to sign up. Disable to freeze all new registrations.</div>
                </div>
                <div class="adm-toggle on" id="toggleRegistrations" onclick="toggle('toggleRegistrations')" title="Toggle registrations"></div>
            </div>

            <div class="adm-toggle-row">
                <div class="adm-toggle-info">
                    <div class="adm-toggle-label">Public resource access</div>
                    <div class="adm-toggle-desc">Allow guests to browse and download resources without logging in.</div>
                </div>
                <div class="adm-toggle on" id="togglePublicRes" onclick="toggle('togglePublicRes')" title="Toggle public access"></div>
            </div>

            <div class="adm-toggle-row">
                <div class="adm-toggle-info">
                    <div class="adm-toggle-label">Maintenance mode</div>
                    <div class="adm-toggle-desc">Show a maintenance page to all non-admin users. Admins can still log in.</div>
                </div>
                <div class="adm-toggle danger" id="toggleMaintenance" onclick="toggle('toggleMaintenance')" title="Toggle maintenance mode"></div>
            </div>

        </div>
    </div>

    <!-- ── DANGER ZONE ── -->
    <div class="adm-settings-section" style="border-color:rgba(255,107,107,0.3);">
        <div class="adm-settings-section-head" style="background:rgba(255,107,107,0.04);">
            <div class="adm-settings-title" style="color:var(--adm-danger);">⚠️ Danger Zone</div>
            <div class="adm-settings-sub">Irreversible actions — proceed with extreme caution</div>
        </div>
        <div class="adm-settings-body">

            <div class="adm-danger-row">
                <div>
                    <div class="adm-danger-label">Clear all admin logs</div>
                    <div class="adm-danger-desc">Permanently delete all admin action logs. This cannot be undone and will remove your audit trail.</div>
                </div>
                <button class="adm-btn adm-btn-danger" onclick="clearLogs()">🗑 Clear Logs</button>
            </div>

            <div class="adm-danger-row">
                <div>
                    <div class="adm-danger-label">Clear all pending reports</div>
                    <div class="adm-danger-desc">Mark all pending reports as resolved. Use only if you have reviewed them all.</div>
                </div>
                <button class="adm-btn adm-btn-danger" onclick="clearReports()">🗑 Clear Reports</button>
            </div>

        </div>
    </div>

</div>
</main>

<script>
const SB_URL = '{{ env("SUPABASE_URL") }}';
const SB_SVC = '{{ env("SUPABASE_SERVICE_KEY") }}';
function svcH() { return { 'apikey': SB_SVC, 'Authorization': `Bearer ${SB_SVC}`, 'Content-Type': 'application/json' }; }

function toggle(id) {
    document.getElementById(id).classList.toggle('on');
}

async function clearLogs() {
    if (!confirm('Delete ALL admin logs permanently? This cannot be undone.')) return;
    try {
        const res = await fetch(`${SB_URL}/rest/v1/admin_logs`, {
            method: 'DELETE',
            headers: svcH()
        });
        if (res.ok) {
            showToast('✅ All admin logs cleared.');
        } else {
            showToast('❌ Failed to clear logs. Check Supabase RLS.', true);
        }
    } catch(e) {
        showToast('❌ ' + e.message, true);
    }
}

async function clearReports() {
    if (!confirm('Mark all pending reports as resolved? This cannot be undone.')) return;
    try {
        const res = await fetch(
            `${SB_URL}/rest/v1/reports?status=eq.pending`,
            { method: 'PATCH', headers: svcH(), body: JSON.stringify({ status: 'resolved', reviewed_at: new Date().toISOString() }) }
        );
        if (res.ok) {
            showToast('✅ All pending reports resolved.');
        } else {
            showToast('❌ Failed. Check Supabase RLS.', true);
        }
    } catch(e) {
        showToast('❌ ' + e.message, true);
    }
}

// Simple toast notification
function showToast(msg, isError = false) {
    const t = document.createElement('div');
    t.textContent = msg;
    Object.assign(t.style, {
        position: 'fixed', bottom: '24px', right: '24px', zIndex: '9999',
        padding: '12px 20px', borderRadius: '10px',
        background: isError ? 'var(--adm-danger)' : 'var(--adm-success)',
        color: 'white', fontFamily: "'DM Sans', sans-serif",
        fontSize: '14px', fontWeight: '600',
        boxShadow: '0 4px 16px rgba(0,0,0,0.15)',
        transition: 'opacity 0.3s',
    });
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 3000);
}

document.getElementById('adminPageTitle').textContent = 'Settings';
</script>
</body>
</html>
