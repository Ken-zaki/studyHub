<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a5f7a; --primary-dark: #144d61;
            --secondary: #f59e42; --accent: #ff6b6b;
            --bg-main: #fafbfc; --bg-card: #fff;
            --text-primary: #1a1a1a; --text-secondary: #6b7280;
            --text-light: #9ca3af; --border: #e5e7eb;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DM Sans',sans-serif; background:var(--bg-main); min-height:100vh; display:flex; align-items:center; justify-content:center; }

        .admin-wrap { text-align:center; padding:48px 32px; }
        .admin-badge {
            display:inline-flex; align-items:center; gap:8px;
            background:rgba(26,95,122,0.1); color:var(--primary);
            padding:6px 16px; border-radius:20px;
            font-size:13px; font-weight:700; margin-bottom:24px;
        }
        .admin-title { font-family:'Crimson Pro',serif; font-size:48px; font-weight:700; color:var(--primary); margin-bottom:8px; }
        .admin-sub   { color:var(--text-secondary); font-size:16px; margin-bottom:32px; }
        .admin-user  { font-size:14px; color:var(--text-light); margin-bottom:32px; }
        .admin-user strong { color:var(--text-primary); }

        .admin-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; max-width:700px; margin:0 auto 32px; }
        .admin-card {
            background:var(--bg-card); border:1px solid var(--border);
            border-radius:16px; padding:24px 20px; text-decoration:none;
            transition:all 0.2s ease;
        }
        .admin-card:hover { box-shadow:0 8px 24px rgba(0,0,0,0.08); transform:translateY(-2px); }
        .admin-card-icon { font-size:32px; margin-bottom:10px; }
        .admin-card-title { font-weight:700; font-size:15px; color:var(--text-primary); margin-bottom:4px; }
        .admin-card-sub   { font-size:12px; color:var(--text-light); }

        .admin-cs { font-size:13px; color:var(--text-light); margin-bottom:24px; }
        .btn-logout {
            padding:10px 28px; border-radius:10px; border:none;
            background:var(--accent); color:white; font-family:'DM Sans',sans-serif;
            font-weight:600; font-size:14px; cursor:pointer;
        }
        .btn-logout:hover { opacity:0.9; }
    </style>
</head>
<body>
<div class="admin-wrap">
    <div class="admin-badge">🛡️ Admin Panel</div>
    <h1 class="admin-title">Admin Dashboard</h1>
    <p class="admin-sub">Platform governance and oversight</p>
    <p class="admin-user">
        Logged in as <strong>{{ session('user_username') }}</strong>
        &nbsp;·&nbsp; Role: <strong>{{ session('user_role') }}</strong>
    </p>

    <div class="admin-cards">
        <a href="{{ route('admin.users') }}" class="admin-card">
            <div class="admin-card-icon">👥</div>
            <div class="admin-card-title">User Management</div>
            <div class="admin-card-sub">View, ban, or change user roles</div>
        </a>
        <a href="{{ route('admin.reports') }}" class="admin-card">
            <div class="admin-card-icon">🚨</div>
            <div class="admin-card-title">Reports</div>
            <div class="admin-card-sub">Review and resolve content reports</div>
        </a>
        <a href="{{ route('admin.resources') }}" class="admin-card">
            <div class="admin-card-icon">📚</div>
            <div class="admin-card-title">Resource Approval</div>
            <div class="admin-card-sub">Approve or reject uploaded resources</div>
        </a>
        <a href="{{ route('admin.logs') }}" class="admin-card">
            <div class="admin-card-icon">📋</div>
            <div class="admin-card-title">Admin Logs</div>
            <div class="admin-card-sub">Audit trail of all admin actions</div>
        </a>
    </div>

    <p class="admin-cs">⚙️ Full admin UI coming soon — database and auth are live.</p>

    <form action="{{ route('logout') }}" method="POST" style="display:inline;">
        @csrf
        <button type="submit" class="btn-logout">Log Out</button>
    </form>
</div>
</body>
</html>
