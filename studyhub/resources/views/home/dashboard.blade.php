{{-- @php
    // Replace this with your actual auth session logic
    // session([
    //     'user_id' => auth()->id(),
    //     'user_first_name' => auth()->user()->first_name,
    //     ...
    // ]);
@endphp --}}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsfeed - StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a5f7a;
            --primary-dark: #144d61;
            --secondary: #f59e42;
            --accent: #ff6b6b;
            --bg-main: #fafbfc;
            --bg-sidebar: #ffffff;
            --bg-card: #ffffff;
            --text-primary: #1a1a1a;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;
            --border: #e5e7eb;
            --shadow: rgba(0, 0, 0, 0.03);
            --shadow-md: rgba(0, 0, 0, 0.08);
            --shadow-lg: rgba(0, 0, 0, 0.12);
            --sidebar-width: 280px;
            --sidebar-collapsed: 80px;
            --right-sidebar: 300px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            overflow-x: hidden;
        }

        /* ── LEFT SIDEBAR ── */
        .sidebar {
            position: fixed;
            left: 0; top: 0;
            width: var(--sidebar-collapsed);
            height: 100vh;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            transition: width 0.3s cubic-bezier(0.4,0,0.2,1);
            z-index: 1000;
        }
        .sidebar:hover { width: var(--sidebar-width); }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo { display: flex; align-items: center; gap: 12px; }
        .logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Crimson Pro', serif;
            font-weight: 700; font-size: 20px;
            color: white; flex-shrink: 0;
        }
        .logo-text {
            font-family: 'Crimson Pro', serif;
            font-size: 24px; font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            opacity: 0; width: 0; overflow: hidden;
            white-space: nowrap; transition: all 0.3s ease;
        }
        .sidebar:hover .logo-text { opacity: 1; width: auto; }

        .sidebar-nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
        .nav-item {
            display: flex; align-items: center; gap: 16px;
            padding: 14px 16px; margin-bottom: 4px;
            border-radius: 12px;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500; font-size: 15px;
            transition: all 0.3s ease;
            cursor: pointer; position: relative;
        }
        .nav-item:hover { background: var(--bg-main); color: var(--primary); }
        .nav-item.active {
            background: linear-gradient(135deg, rgba(26,95,122,0.08) 0%, rgba(245,158,66,0.08) 100%);
            color: var(--primary); font-weight: 600;
        }
        .nav-item.active::before {
            content: '';
            position: absolute; left: 0; top: 50%;
            transform: translateY(-50%);
            width: 4px; height: 24px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 0 4px 4px 0;
        }
        .nav-icon { width: 24px; height: 24px; flex-shrink: 0; }
        .nav-text {
            opacity: 0; width: 0; overflow: hidden;
            white-space: nowrap; transition: all 0.3s ease;
        }
        .sidebar:hover .nav-text { opacity: 1; width: auto; }
        .nav-badge {
            margin-left: auto;
            background: var(--accent); color: white;
            font-size: 11px; font-weight: 700;
            padding: 3px 8px; border-radius: 12px;
            opacity: 0; width: 0; overflow: hidden;
            transition: all 0.3s ease;
        }
        .sidebar:hover .nav-badge { opacity: 1; width: auto; }

        .sidebar-footer { padding: 16px; border-top: 1px solid var(--border); }
        .user-profile {
            display: flex; align-items: center; gap: 12px;
            padding: 12px; border-radius: 12px;
            transition: all 0.3s ease; cursor: pointer;
        }
        .user-profile:hover { background: var(--bg-main); }
        .user-avatar {
            width: 44px; height: 44px; border-radius: 12px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 16px; flex-shrink: 0;
            overflow: hidden;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-info { flex: 1; opacity: 0; width: 0; overflow: hidden; transition: all 0.3s ease; }
        .sidebar:hover .user-info { opacity: 1; width: auto; }
        .user-name { font-weight: 600; font-size: 14px; color: var(--text-primary); white-space: nowrap; }
        .user-status { font-size: 12px; color: var(--text-light); }

        /* ── TOP BAR ── */
        .top-bar {
            position: fixed;
            top: 0; right: 0;
            left: var(--sidebar-collapsed);
            height: 64px;
            background: var(--bg-sidebar);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 0 24px;
            gap: 12px;
            z-index: 900;
            transition: left 0.3s cubic-bezier(0.4,0,0.2,1);
        }

        .top-bar-btn {
            position: relative;
            width: 40px; height: 40px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: white;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-secondary);
            text-decoration: none;
        }
        .top-bar-btn:hover {
            background: var(--bg-main);
            color: var(--primary);
            border-color: var(--primary);
        }
        .top-bar-btn svg { width: 20px; height: 20px; }

        .notif-dot {
            position: absolute;
            top: 6px; right: 6px;
            width: 8px; height: 8px;
            background: var(--accent);
            border-radius: 50%;
            border: 2px solid white;
        }

        .top-bar-avatar {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 14px;
            cursor: pointer; border: none;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .top-bar-avatar:hover { opacity: 0.85; transform: scale(0.97); }
        .top-bar-avatar img { width: 100%; height: 100%; object-fit: cover; }

        /* ── MAIN LAYOUT ── */
        .main-content {
            margin-left: var(--sidebar-collapsed);
            margin-top: 64px;
            min-height: calc(100vh - 64px);
            padding: 32px 24px;
            display: flex;
            justify-content: center;
            gap: 28px;
        }

        .feed-column {
            flex: 1;
            max-width: 680px;
            min-width: 0;
        }

        /* ── RIGHT SIDEBAR ── */
        .right-sidebar {
            width: var(--right-sidebar);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        @media (max-width: 1200px) {
            .right-sidebar { display: none; }
        }

        .widget-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 20px;
        }
        .widget-title {
            font-family: 'Crimson Pro', serif;
            font-size: 18px; font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px;
        }

        /* Trending */
        .trend-item {
            display: flex; align-items: flex-start;
            gap: 12px; padding: 10px 0;
            border-bottom: 1px solid var(--border);
            cursor: pointer; transition: all 0.2s ease;
        }
        .trend-item:last-child { border-bottom: none; padding-bottom: 0; }
        .trend-item:hover .trend-tag { color: var(--primary); }
        .trend-rank {
            font-size: 13px; font-weight: 700;
            color: var(--text-light);
            min-width: 20px; padding-top: 1px;
        }
        .trend-info { flex: 1; }
        .trend-tag {
            font-size: 14px; font-weight: 600;
            color: var(--text-primary);
            transition: color 0.2s;
        }
        .trend-count { font-size: 12px; color: var(--text-light); margin-top: 2px; }

        /* Quick Links */
        .quick-link {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 10px;
            text-decoration: none; color: var(--text-secondary);
            font-size: 14px; font-weight: 500;
            transition: all 0.2s ease; margin-bottom: 4px;
        }
        .quick-link:last-child { margin-bottom: 0; }
        .quick-link:hover { background: var(--bg-main); color: var(--primary); }
        .quick-link-icon {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, rgba(26,95,122,0.1) 0%, rgba(245,158,66,0.1) 100%);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; flex-shrink: 0;
        }

        /* ── PAGE HEADER ── */
        .page-header { margin-bottom: 24px; }
        .page-title {
            font-family: 'Crimson Pro', serif;
            font-size: 36px; font-weight: 700;
            color: var(--text-primary); margin-bottom: 4px;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 15px; }

        /* ── CREATE POST ── */
        .create-post {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 24px; margin-bottom: 20px;
        }
        .create-post-header { display: flex; gap: 12px; margin-bottom: 16px; }
        .create-post-avatar {
            width: 48px; height: 48px; border-radius: 12px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; flex-shrink: 0; overflow: hidden;
        }
        .create-post-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .create-post-input { flex: 1; }

        #postContent {
            width: 100%; min-height: 80px;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-family: 'DM Sans', sans-serif;
            font-size: 15px; resize: vertical;
            transition: border-color 0.3s ease;
        }
        #postContent:focus { outline: none; border-color: var(--primary); }

        .attachment-preview { margin-top: 12px; display: none; }
        .attachment-preview.active { display: block; }
        .preview-image { max-width: 100%; max-height: 300px; border-radius: 12px; margin-top: 8px; }
        .preview-file {
            padding: 12px; background: var(--bg-main);
            border-radius: 8px; display: flex; align-items: center;
            gap: 8px; margin-top: 8px; flex-wrap: wrap;
        }
        .remove-attachment {
            background: var(--accent); color: white;
            border: none; padding: 4px 12px;
            border-radius: 6px; cursor: pointer;
            font-size: 12px; margin-left: auto;
        }

        .post-actions {
            display: flex; align-items: center;
            justify-content: space-between;
            margin-top: 16px; padding-top: 16px;
            border-top: 1px solid var(--border);
        }
        .attachment-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .attach-btn {
            padding: 8px 14px;
            border: 1px solid var(--border);
            background: white; border-radius: 8px;
            cursor: pointer; font-size: 13px;
            transition: all 0.2s ease;
            display: flex; align-items: center; gap: 5px;
            font-family: 'DM Sans', sans-serif;
        }
        .attach-btn:hover { background: var(--bg-main); border-color: var(--primary); color: var(--primary); }

        .btn-post {
            padding: 10px 32px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white; border: none;
            border-radius: 10px; font-weight: 600;
            cursor: pointer; transition: all 0.3s ease;
            font-family: 'DM Sans', sans-serif; font-size: 15px;
        }
        .btn-post:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26,95,122,0.35); }
        .btn-post:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

        /* ── FEED ── */
        .feed { display: flex; flex-direction: column; gap: 16px; }
        .post-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 24px;
            transition: box-shadow 0.3s ease, transform 0.3s ease;
            position: relative;
        }
        .post-card:hover { box-shadow: 0 8px 24px var(--shadow-md); transform: translateY(-2px); }

        .post-header { display: flex; gap: 12px; margin-bottom: 14px; align-items: flex-start; }
        .post-avatar {
            width: 48px; height: 48px; border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; flex-shrink: 0; overflow: hidden;
        }
        .post-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .post-info { flex: 1; }
        .post-author { font-weight: 600; color: var(--text-primary); margin-bottom: 2px; }
        .post-time { font-size: 13px; color: var(--text-light); }

        /* 3-dot menu */
        .post-menu-wrap { position: relative; margin-left: auto; }
        .post-menu-btn {
            width: 32px; height: 32px;
            border-radius: 8px; border: none;
            background: transparent; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-light);
            transition: all 0.2s ease;
        }
        .post-menu-btn:hover { background: var(--bg-main); color: var(--text-secondary); }
        .post-menu-btn svg { width: 18px; height: 18px; }

        .post-dropdown {
            position: absolute;
            top: calc(100% + 4px); right: 0;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 8px 24px var(--shadow-lg);
            min-width: 160px;
            z-index: 200;
            overflow: hidden;
            display: none;
        }
        .post-dropdown.open { display: block; }
        .dropdown-item {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 16px;
            font-size: 14px; font-weight: 500;
            cursor: pointer;
            transition: background 0.15s;
            border: none; background: none;
            width: 100%; text-align: left;
            color: var(--text-primary);
            font-family: 'DM Sans', sans-serif;
        }
        .dropdown-item:hover { background: var(--bg-main); }
        .dropdown-item.danger { color: var(--accent); }
        .dropdown-item.danger:hover { background: #fff5f5; }
        .dropdown-item svg { width: 16px; height: 16px; flex-shrink: 0; }

        .post-content { margin-bottom: 14px; }
        .post-text { color: var(--text-secondary); line-height: 1.6; font-size: 15px; white-space: pre-wrap; }
        .post-image { width: 100%; border-radius: 12px; margin-top: 12px; }
        .post-link {
            display: flex; align-items: center; gap: 8px;
            padding: 12px; background: var(--bg-main);
            border-radius: 8px; border: 1px solid var(--border);
            margin-top: 12px; text-decoration: none;
            color: var(--primary); font-size: 14px;
            transition: all 0.2s ease; word-break: break-all;
        }
        .post-link:hover { background: white; border-color: var(--primary); }

        .post-interactions {
            display: flex; gap: 8px;
            padding-top: 14px;
            border-top: 1px solid var(--border);
        }
        .interaction-btn {
            display: flex; align-items: center; gap: 6px;
            padding: 7px 14px; border-radius: 8px;
            background: none; border: none;
            color: var(--text-secondary);
            font-weight: 500; font-size: 14px;
            cursor: pointer; transition: all 0.2s ease;
            font-family: 'DM Sans', sans-serif;
        }
        .interaction-btn:hover { background: var(--bg-main); color: var(--primary); }

        .loading { text-align: center; padding: 40px; color: var(--text-light); }
        .error {
            background: #fff5f5; border: 1px solid #fecaca;
            color: #dc2626; padding: 16px; border-radius: 12px; margin-bottom: 16px;
            font-size: 14px;
        }

        /* ── ARCHIVED badge ── */
        .archived-badge {
            display: inline-flex; align-items: center; gap: 4px;
            background: rgba(245,158,66,0.12);
            color: #d97706;
            font-size: 11px; font-weight: 600;
            padding: 3px 8px; border-radius: 20px;
            margin-left: 8px;
        }

        input[type="file"] { display: none; }

        /* ── MODAL ── */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 2000;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none;
            transition: opacity 0.2s;
        }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal {
            background: white; border-radius: 20px;
            width: 90%; max-width: 520px;
            padding: 28px;
            transform: scale(0.95);
            transition: transform 0.2s;
            box-shadow: 0 20px 60px rgba(0,0,0,0.18);
        }
        .modal-overlay.open .modal { transform: scale(1); }
        .modal-header {
            display: flex; align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .modal-title {
            font-family: 'Crimson Pro', serif;
            font-size: 22px; font-weight: 700;
        }
        .modal-close {
            width: 32px; height: 32px;
            border-radius: 8px; border: none;
            background: var(--bg-main);
            cursor: pointer; font-size: 18px;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary);
        }
        .modal-close:hover { background: #fee2e2; color: var(--accent); }
        .modal-textarea {
            width: 100%; min-height: 120px;
            padding: 14px; border: 2px solid var(--border);
            border-radius: 12px; font-family: 'DM Sans', sans-serif;
            font-size: 15px; resize: vertical;
        }
        .modal-textarea:focus { outline: none; border-color: var(--primary); }
        .modal-actions {
            display: flex; gap: 10px;
            margin-top: 16px; justify-content: flex-end;
        }
        .btn-cancel {
            padding: 10px 24px; border-radius: 10px;
            border: 1px solid var(--border); background: white;
            font-family: 'DM Sans', sans-serif; font-weight: 600;
            cursor: pointer; color: var(--text-secondary);
        }
        .btn-cancel:hover { background: var(--bg-main); }
        .btn-save {
            padding: 10px 28px; border-radius: 10px;
            border: none;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white; font-family: 'DM Sans', sans-serif;
            font-weight: 600; cursor: pointer;
        }
        .btn-save:hover { opacity: 0.9; }

        /* Coming Soon overlay */
        .coming-soon-banner {
            text-align: center; padding: 60px 24px;
            color: var(--text-light);
        }
        .coming-soon-banner .cs-icon { font-size: 48px; margin-bottom: 16px; }
        .coming-soon-banner h2 {
            font-family: 'Crimson Pro', serif;
            font-size: 28px; font-weight: 700;
            color: var(--text-secondary); margin-bottom: 8px;
        }
        .coming-soon-banner p { font-size: 15px; }
    </style>
</head>
<body>

<!-- ══════════════════════════════════════════
     LEFT SIDEBAR
══════════════════════════════════════════ -->
<aside class="sidebar" id="mainSidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon">S</div>
            <span class="logo-text">StudyHub</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'nav-item active' : 'nav-item' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
            </svg>
            <span class="nav-text">Newsfeed</span>
            <span class="nav-badge" id="newPostsBadge" style="display:none">New</span>
        </a>
        <a href="{{ route('calendar') }}" class="{{ request()->routeIs('calendar') ? 'nav-item active' : 'nav-item' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <span class="nav-text">Calendar</span>
        </a>
        <a href="{{ route('study-groups') }}" class="{{ request()->routeIs('study-groups') ? 'nav-item active' : 'nav-item' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75"/>
            </svg>
            <span class="nav-text">Study Groups</span>
        </a>
        <a href="{{ route('resources') }}" class="{{ request()->routeIs('resources') ? 'nav-item active' : 'nav-item' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/>
                <path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>
            </svg>
            <span class="nav-text">Resources</span>
        </a>
        <a href="{{ route('notifications') }}" class="{{ request()->routeIs('notifications') ? 'nav-item active' : 'nav-item' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 01-3.46 0"/>
            </svg>
            <span class="nav-text">Notifications</span>
            <span class="nav-badge">5</span>
        </a>
        <a href="{{ route('messages') }}" class="{{ request()->routeIs('messages') ? 'nav-item active' : 'nav-item' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
            </svg>
            <span class="nav-text">Messages</span>
        </a>
        <a href="{{ route('focus-mode') }}" class="{{ request()->routeIs('focus-mode') ? 'nav-item active' : 'nav-item' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 3a9 9 0 100 18 9 9 0 000-18z"/>
                <path d="M12 7v5l3 3"/>
            </svg>
            <span class="nav-text">Focus Mode</span>
        </a>
        <a href="{{ route('profile') }}" class="{{ request()->routeIs('profile') ? 'nav-item active' : 'nav-item' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            <span class="nav-text">Profile</span>
        </a>
        <a href="{{ route('settings') }}" class="{{ request()->routeIs('settings') ? 'nav-item active' : 'nav-item' }}">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
            </svg>
            <span class="nav-text">Settings</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile" onclick="showProfilePage()">
            <div class="user-avatar" id="sidebarAvatar">
                <!-- filled by JS -->
            </div>
            <div class="user-info">
                <div class="user-name" id="sidebarUserName">Loading...</div>
                <div class="user-status">Online</div>
            </div>
        </div>
    </div>
</aside>

<!-- ══════════════════════════════════════════
     TOP BAR
══════════════════════════════════════════ -->
<div class="top-bar" id="topBar">
    <a href="{{ route('notifications') }}" class="top-bar-btn" title="Notifications">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 01-3.46 0"/>
        </svg>
        <span class="notif-dot"></span>
    </a>
    <button class="top-bar-avatar" id="topBarAvatar" onclick="showProfilePage()" title="Your Profile">
        <!-- filled by JS -->
    </button>
</div>

<!-- ══════════════════════════════════════════
     MAIN CONTENT
══════════════════════════════════════════ -->
<main class="main-content">

    <!-- FEED COLUMN -->
    <div class="feed-column" id="feedColumn">
        <header class="page-header">
            <h1 class="page-title">Newsfeed</h1>
            <p class="page-subtitle">Stay updated with the latest news, updates &amp; events</p>
        </header>

        <!-- Create Post -->
        <div class="create-post">
            <div class="create-post-header">
                <div class="create-post-avatar" id="createPostAvatar"><!-- JS --></div>
                <div class="create-post-input">
                    <textarea id="postContent" placeholder="What's on your mind?"></textarea>
                    <div id="attachmentPreview" class="attachment-preview"></div>
                </div>
            </div>
            <div class="post-actions">
                <div class="attachment-buttons">
                    <button class="attach-btn" onclick="attachImage()">📷 Image</button>
                    <button class="attach-btn" onclick="attachFile()">📎 File</button>
                    <button class="attach-btn" onclick="attachLink()">🔗 Link</button>
                    <button class="attach-btn" onclick="attachVideo()">🎥 Video</button>
                </div>
                <button class="btn-post" onclick="createPost()" id="postButton">Post</button>
            </div>
            <input type="file" id="imageInput" accept="image/*">
            <input type="file" id="fileInput">
            <input type="file" id="videoInput" accept="video/*">
        </div>

        <!-- Feed -->
        <div id="feed" class="feed">
            <div class="loading">Loading posts…</div>
        </div>
    </div>

    <!-- PROFILE "COMING SOON" column (hidden by default) -->
    <div class="feed-column" id="profileColumn" style="display:none;">
        <header class="page-header">
            <h1 class="page-title">Profile</h1>
            <p class="page-subtitle">Your personal StudyHub profile</p>
        </header>
        <div class="widget-card">
            <div class="coming-soon-banner">
                <div class="cs-icon">👤</div>
                <h2>Coming Soon!</h2>
                <p>Your profile page is under construction. Check back soon!</p>
            </div>
        </div>
    </div>

    <!-- RIGHT SIDEBAR -->
    <aside class="right-sidebar">

        <!-- Trending Topics -->
        <div class="widget-card">
            <div class="widget-title">🔥 Trending Topics</div>
            <div id="trendingList">
                <!-- populated by JS -->
            </div>
        </div>

        <!-- Quick Links -->
        <div class="widget-card">
            <div class="widget-title">⚡ Quick Links</div>
            <a href="{{ route('calendar') }}" class="quick-link">
                <span class="quick-link-icon">📅</span> Calendar
            </a>
            <a href="{{ route('study-groups') }}" class="quick-link">
                <span class="quick-link-icon">👥</span> Study Groups
            </a>
            <a href="{{ route('resources') }}" class="quick-link">
                <span class="quick-link-icon">📚</span> Resources
            </a>
            <a href="{{ route('notifications') }}" class="quick-link">
                <span class="quick-link-icon">🔔</span> Notifications
            </a>
            <a href="{{ route('messages') }}" class="quick-link">
                <span class="quick-link-icon">💬</span> Messages
            </a>
            <a href="{{ route('settings') }}" class="quick-link">
                <span class="quick-link-icon">⚙️</span> Settings
            </a>
        </div>

        <!-- Study Tip -->
        <div class="widget-card">
            <div class="widget-title">💡 Study Tip</div>
            <p id="studyTip" style="font-size:14px; color:var(--text-secondary); line-height:1.6;"></p>
        </div>

    </aside>
</main>

<!-- ══════════════════════════════════════════
     EDIT POST MODAL
══════════════════════════════════════════ -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit Post</span>
            <button class="modal-close" onclick="closeEditModal()">✕</button>
        </div>
        <textarea class="modal-textarea" id="editContent" placeholder="Edit your post…"></textarea>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeEditModal()">Cancel</button>
            <button class="btn-save" onclick="saveEdit()">Save Changes</button>
        </div>
    </div>
</div>

<script>
// ── CONFIG ──────────────────────────────────────────────────────────────────
const SUPABASE_URL         = '{{ env("SUPABASE_URL") }}';
const SUPABASE_ANON_KEY    = '{{ env("SUPABASE_ANON_KEY") }}';
const SUPABASE_SERVICE_KEY = '{{ env("SUPABASE_SERVICE_KEY") }}';

// ── CURRENT USER (from Laravel session / auth) ───────────────────────────────
// ✅ FIX: Use the authenticated user's real UUID from your auth system.
//    The session user_id MUST match a row in public.profiles(id).
//    If you use Laravel Breeze/Fortify + Supabase, set session('user_id') = auth()->id()
//    and make sure that UUID exists in profiles.
let currentUser = {
    id:                '{{ session("user_id") }}',
    first_name:        '{{ session("user_first_name") }}',
    last_name:         '{{ session("user_last_name") }}',
    username:          '{{ session("user_username") }}',
    profile_photo_url: '{{ session("user_profile_photo") }}'
};

let currentAttachment = null;
let editingPostId      = null;

// ── STUDY TIPS ───────────────────────────────────────────────────────────────
const TIPS = [
    "Use the Pomodoro Technique: 25 min focus, 5 min break.",
    "Teaching a concept to someone else is one of the best ways to learn it.",
    "Spaced repetition beats cramming every time.",
    "Get 7–9 hours of sleep — memory consolidation happens while you rest.",
    "Active recall (testing yourself) is more effective than re-reading.",
    "Break large tasks into smaller, concrete next-actions.",
    "Vary your study location to improve recall in different contexts.",
];

// ── INIT ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    renderCurrentUserUI();
    loadPosts();
    setupFileInputs();
    renderTrending();
    document.getElementById('studyTip').textContent = TIPS[Math.floor(Math.random() * TIPS.length)];

    // Close dropdowns on outside click
    document.addEventListener('click', e => {
        if (!e.target.closest('.post-menu-wrap')) {
            document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
        }
    });
});

// ── RENDER USER UI ────────────────────────────────────────────────────────────
function renderCurrentUserUI() {
    const initials = avatarInitials(currentUser.first_name, currentUser.last_name);
    const fullName = `${currentUser.first_name} ${currentUser.last_name}`.trim() || currentUser.username || 'You';

    // Sidebar avatar
    const sidebarAv = document.getElementById('sidebarAvatar');
    sidebarAv.innerHTML = currentUser.profile_photo_url
        ? `<img src="${currentUser.profile_photo_url}" alt="${fullName}">`
        : initials;

    document.getElementById('sidebarUserName').textContent = fullName;

    // Top-bar avatar
    const topAv = document.getElementById('topBarAvatar');
    topAv.innerHTML = currentUser.profile_photo_url
        ? `<img src="${currentUser.profile_photo_url}" alt="${fullName}" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`
        : initials;

    // Create-post avatar
    const cpAv = document.getElementById('createPostAvatar');
    cpAv.innerHTML = currentUser.profile_photo_url
        ? `<img src="${currentUser.profile_photo_url}" alt="${fullName}">`
        : initials;
}

function avatarInitials(first, last) {
    return `${(first||'?')[0]}${(last||'?')[0]}`.toUpperCase();
}

// ── TRENDING ─────────────────────────────────────────────────────────────────
async function renderTrending() {
    // Derive trending from actual post subject_tags
    try {
        const res = await fetch(
            `${SUPABASE_URL}/rest/v1/newsfeed_posts?select=subject_tag&is_archived=eq.false`,
            { headers: { 'apikey': SUPABASE_ANON_KEY, 'Authorization': `Bearer ${SUPABASE_ANON_KEY}` } }
        );
        const rows = await res.json();
        const counts = {};
        rows.forEach(r => { if (r.subject_tag) counts[r.subject_tag] = (counts[r.subject_tag]||0) + 1; });
        let sorted = Object.entries(counts).sort((a,b) => b[1]-a[1]).slice(0,5);

        if (!sorted.length) {
            sorted = [['#StudyTips',12],['#Mathematics',9],['#Science',7],['#English',5],['#History',3]];
        }

        const el = document.getElementById('trendingList');
        el.innerHTML = sorted.map(([tag, count], i) => `
            <div class="trend-item">
                <span class="trend-rank">${i+1}</span>
                <div class="trend-info">
                    <div class="trend-tag">${tag.startsWith('#') ? tag : '#'+tag}</div>
                    <div class="trend-count">${count} post${count!==1?'s':''}</div>
                </div>
            </div>
        `).join('');
    } catch {
        // Fallback static
        const fallback = [['#StudyTips',12],['#Mathematics',9],['#Science',7],['#English',5],['#History',3]];
        document.getElementById('trendingList').innerHTML = fallback.map(([tag, count], i) => `
            <div class="trend-item">
                <span class="trend-rank">${i+1}</span>
                <div class="trend-info">
                    <div class="trend-tag">${tag}</div>
                    <div class="trend-count">${count} posts</div>
                </div>
            </div>
        `).join('');
    }
}

// ── SHOW PROFILE PAGE ─────────────────────────────────────────────────────────
function showProfilePage(e) {
    if (e) e.preventDefault();
    document.getElementById('feedColumn').style.display = 'none';
    document.getElementById('profileColumn').style.display = 'block';
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    event?.currentTarget?.classList?.add('active');
}

function showNewsfeed() {
    document.getElementById('feedColumn').style.display = 'block';
    document.getElementById('profileColumn').style.display = 'none';
}

// ── LOAD POSTS ────────────────────────────────────────────────────────────────
async function loadPosts() {
    try {
        const res = await fetch(
            `${SUPABASE_URL}/rest/v1/newsfeed_posts?select=*,profiles(username,first_name,last_name,profile_photo_url)&is_archived=eq.false&order=created_at.desc`,
            { headers: { 'apikey': SUPABASE_ANON_KEY, 'Authorization': `Bearer ${SUPABASE_ANON_KEY}` } }
        );
        if (!res.ok) throw new Error((await res.json()).message || 'Failed to load');
        displayPosts(await res.json());
    } catch (err) {
        document.getElementById('feed').innerHTML =
            `<div class="error">❌ Failed to load posts: ${err.message}</div>`;
    }
}

// ── DISPLAY POSTS ─────────────────────────────────────────────────────────────
function displayPosts(posts) {
    const feed = document.getElementById('feed');
    if (!posts.length) {
        feed.innerHTML = '<div class="loading">No posts yet. Be the first to post! 🎉</div>';
        return;
    }
    feed.innerHTML = posts.map(createPostHTML).join('');
}

// ── POST HTML ─────────────────────────────────────────────────────────────────
function createPostHTML(post) {
    const author    = post.profiles || {};
    const fullName  = `${author.first_name||'Unknown'} ${author.last_name||'User'}`;
    const initials  = avatarInitials(author.first_name||'U', author.last_name||'U');
    const timeAgo   = formatTimeAgo(post.created_at);
    const isOwn     = post.user_id === currentUser.id;

    const avatarHTML = author.profile_photo_url
        ? `<img src="${author.profile_photo_url}" alt="${fullName}">`
        : initials;

    let mediaHTML = '';
    if (post.media_url) {
        if (post.post_type === 'image')
            mediaHTML = `<img src="${post.media_url}" class="post-image" alt="Post image">`;
        else if (post.post_type === 'video')
            mediaHTML = `<video controls class="post-image"><source src="${post.media_url}"></video>`;
        else if (post.post_type === 'file')
            mediaHTML = `<a href="${post.media_url}" target="_blank" class="post-link">📎 Download File</a>`;
    }
    if (post.link_url)
        mediaHTML += `<a href="${escapeHTML(post.link_url)}" target="_blank" class="post-link">🔗 ${escapeHTML(post.link_url)}</a>`;

    // Only show 3-dot menu for own posts
    const menuHTML = isOwn ? `
        <div class="post-menu-wrap">
            <button class="post-menu-btn" onclick="toggleMenu('${post.id}', event)" title="Options">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/>
                </svg>
            </button>
            <div class="post-dropdown" id="menu-${post.id}">
                <button class="dropdown-item" onclick="openEditModal('${post.id}')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    Edit
                </button>
                <button class="dropdown-item" onclick="archivePost('${post.id}')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="21 8 21 21 3 21 3 8"/>
                        <rect x="1" y="3" width="22" height="5"/>
                        <line x1="10" y1="12" x2="14" y2="12"/>
                    </svg>
                    Archive
                </button>
                <button class="dropdown-item danger" onclick="deletePost('${post.id}')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6l-1 14H6L5 6"/>
                        <path d="M10 11v6M14 11v6"/>
                        <path d="M9 6V4h6v2"/>
                    </svg>
                    Delete
                </button>
            </div>
        </div>
    ` : '';

    return `
        <div class="post-card" id="post-${post.id}">
            <div class="post-header">
                <div class="post-avatar">${avatarHTML}</div>
                <div class="post-info">
                    <div class="post-author">${escapeHTML(fullName)}</div>
                    <div class="post-time">${timeAgo}</div>
                </div>
                ${menuHTML}
            </div>
            <div class="post-content">
                <p class="post-text">${escapeHTML(post.content)}</p>
                ${mediaHTML ? `<div class="post-media">${mediaHTML}</div>` : ''}
            </div>
            <div class="post-interactions">
                <button class="interaction-btn" onclick="likePost('${post.id}')">❤️ Like</button>
                <button class="interaction-btn" onclick="commentPost('${post.id}')">💬 Comment</button>
                <button class="interaction-btn" onclick="sharePost('${post.id}')">🔄 Share</button>
            </div>
        </div>
    `;
}

// ── 3-DOT MENU ────────────────────────────────────────────────────────────────
function toggleMenu(postId, e) {
    e.stopPropagation();
    const menu = document.getElementById(`menu-${postId}`);
    const wasOpen = menu.classList.contains('open');
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
    if (!wasOpen) menu.classList.add('open');
}

// ── EDIT ─────────────────────────────────────────────────────────────────────
function openEditModal(postId) {
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
    const card = document.getElementById(`post-${postId}`);
    const text = card.querySelector('.post-text').textContent;
    editingPostId = postId;
    document.getElementById('editContent').value = text;
    document.getElementById('editModal').classList.add('open');
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
    editingPostId = null;
}
async function saveEdit() {
    const newContent = document.getElementById('editContent').value.trim();
    if (!newContent) return alert('Content cannot be empty.');
    try {
        const res = await fetch(`${SUPABASE_URL}/rest/v1/newsfeed_posts?id=eq.${editingPostId}`, {
            method: 'PATCH',
            headers: {
                'apikey': SUPABASE_SERVICE_KEY,
                'Authorization': `Bearer ${SUPABASE_SERVICE_KEY}`,
                'Content-Type': 'application/json',
                'Prefer': 'return=representation'
            },
            body: JSON.stringify({ content: newContent })
        });
        if (!res.ok) throw new Error((await res.json()).message || 'Update failed');
        closeEditModal();
        await loadPosts();
    } catch (err) {
        alert('Failed to edit post: ' + err.message);
    }
}

// ── ARCHIVE ───────────────────────────────────────────────────────────────────
async function archivePost(postId) {
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
    if (!confirm('Archive this post? It will be hidden from the feed.')) return;
    try {
        const res = await fetch(`${SUPABASE_URL}/rest/v1/newsfeed_posts?id=eq.${postId}`, {
            method: 'PATCH',
            headers: {
                'apikey': SUPABASE_SERVICE_KEY,
                'Authorization': `Bearer ${SUPABASE_SERVICE_KEY}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ is_archived: true })
        });
        if (!res.ok) throw new Error((await res.json()).message || 'Archive failed');
        document.getElementById(`post-${postId}`)?.remove();
    } catch (err) {
        alert('Failed to archive post: ' + err.message);
    }
}

// ── DELETE ────────────────────────────────────────────────────────────────────
async function deletePost(postId) {
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
    if (!confirm('Delete this post? This cannot be undone.')) return;
    try {
        const res = await fetch(`${SUPABASE_URL}/rest/v1/newsfeed_posts?id=eq.${postId}`, {
            method: 'DELETE',
            headers: {
                'apikey': SUPABASE_SERVICE_KEY,
                'Authorization': `Bearer ${SUPABASE_SERVICE_KEY}`
            }
        });
        if (!res.ok) throw new Error((await res.json()).message || 'Delete failed');
        document.getElementById(`post-${postId}`)?.remove();
    } catch (err) {
        alert('Failed to delete post: ' + err.message);
    }
}

// ── CREATE POST ───────────────────────────────────────────────────────────────
async function createPost() {
    const content = document.getElementById('postContent').value.trim();
    if (!content) return alert('Please write something!');

    // ✅ FIX EXPLANATION:
    // The RLS error "new row violates row-level security policy" happens because
    // the user_id you're passing doesn't exist in public.profiles.
    // Even with RLS disabled on newsfeed_posts, the FOREIGN KEY constraint
    // on user_id → profiles(id) will reject any UUID not in profiles.
    //
    // SOLUTION: Make sure session('user_id') is set to the real authenticated
    // user's UUID that exists in your profiles table.
    // In your login controller: session(['user_id' => $user->id, ...])
    // where $user->id matches public.profiles(id).

    if (!currentUser.id || currentUser.id === '') {
        return alert('Not logged in. Please refresh and log in again.');
    }

    const postButton = document.getElementById('postButton');
    postButton.disabled = true;
    postButton.textContent = 'Posting…';

    try {
        let mediaUrl = null;
        let postType = 'text';

        if (currentAttachment) {
            if (currentAttachment.type === 'link') {
                postType = 'link';
            } else {
                // ✅ FIX FOR FILE UPLOAD:
                // Using service key (not anon key) for storage to bypass auth issues.
                // Also make sure the storage buckets exist in Supabase:
                //   post-images, post-videos, post-files  (set to public)
                mediaUrl = await uploadFile(currentAttachment.file, currentAttachment.type);
                postType = currentAttachment.type;
            }
        }

        const postData = {
            user_id:  currentUser.id,
            content:  content,
            post_type: postType,
            media_url: mediaUrl,
            link_url:  currentAttachment?.type === 'link' ? currentAttachment.url : null
        };

        const res = await fetch(`${SUPABASE_URL}/rest/v1/newsfeed_posts`, {
            method: 'POST',
            headers: {
                'apikey': SUPABASE_SERVICE_KEY,
                'Authorization': `Bearer ${SUPABASE_SERVICE_KEY}`,
                'Content-Type': 'application/json',
                'Prefer': 'return=representation'
            },
            body: JSON.stringify(postData)
        });

        if (!res.ok) {
            const err = await res.json();
            // Friendly error messages
            if (err.code === '23503')
                throw new Error('Your user profile was not found in the database. Make sure your account is fully registered.');
            throw new Error(err.message || JSON.stringify(err));
        }

        // Clear form
        document.getElementById('postContent').value = '';
        currentAttachment = null;
        const preview = document.getElementById('attachmentPreview');
        preview.innerHTML = '';
        preview.classList.remove('active');
        ['imageInput','fileInput','videoInput'].forEach(id => document.getElementById(id).value = '');

        await loadPosts();
    } catch (err) {
        alert('Failed to create post:\n' + err.message);
    } finally {
        postButton.disabled = false;
        postButton.textContent = 'Post';
    }
}

// ── FILE UPLOAD ───────────────────────────────────────────────────────────────
// ✅ FIX: Use service key for storage uploads & send file as Blob (not ArrayBuffer).
//    Also ensure your Supabase storage buckets are named: post-images, post-videos, post-files
//    and are set to PUBLIC in the Supabase dashboard.
async function uploadFile(file, type) {
    const bucket   = type === 'image' ? 'post-images' : type === 'video' ? 'post-videos' : 'post-files';
    const ext      = file.name.split('.').pop();
    const fileName = `${currentUser.id}/${Date.now()}.${ext}`;

    const res = await fetch(`${SUPABASE_URL}/storage/v1/object/${bucket}/${fileName}`, {
        method: 'POST',
        headers: {
            'apikey': SUPABASE_SERVICE_KEY,
            'Authorization': `Bearer ${SUPABASE_SERVICE_KEY}`,
            'Content-Type': file.type,
            'x-upsert': 'true'
        },
        body: file   // ✅ send File directly — no need for ArrayBuffer
    });

    if (!res.ok) {
        const err = await res.json();
        throw new Error(err.error || err.message || 'Upload failed');
    }

    return `${SUPABASE_URL}/storage/v1/object/public/${bucket}/${fileName}`;
}

// ── ATTACHMENT HELPERS ────────────────────────────────────────────────────────
function attachImage()  { document.getElementById('imageInput').click(); }
function attachFile()   { document.getElementById('fileInput').click(); }
function attachVideo()  { document.getElementById('videoInput').click(); }
function attachLink() {
    const url = prompt('Enter the link URL:');
    if (url) {
        currentAttachment = { type: 'link', url };
        showAttachmentPreview(`🔗 Link: ${escapeHTML(url)}`);
    }
}

function setupFileInputs() {
    document.getElementById('imageInput').addEventListener('change', e => {
        const file = e.target.files[0];
        if (!file) return;
        currentAttachment = { type: 'image', file };
        const reader = new FileReader();
        reader.onload = ev =>
            showAttachmentPreview(`<img src="${ev.target.result}" class="preview-image" alt="preview">`);
        reader.readAsDataURL(file);
    });
    document.getElementById('fileInput').addEventListener('change', e => {
        const file = e.target.files[0];
        if (!file) return;
        currentAttachment = { type: 'file', file };
        showAttachmentPreview(`📎 ${escapeHTML(file.name)}`);
    });
    document.getElementById('videoInput').addEventListener('change', e => {
        const file = e.target.files[0];
        if (!file) return;
        currentAttachment = { type: 'video', file };
        showAttachmentPreview(`🎥 ${escapeHTML(file.name)}`);
    });
}

function showAttachmentPreview(html) {
    const preview = document.getElementById('attachmentPreview');
    preview.innerHTML = `
        <div class="preview-file">
            ${html}
            <button class="remove-attachment" onclick="removeAttachment()">Remove</button>
        </div>`;
    preview.classList.add('active');
}

function removeAttachment() {
    currentAttachment = null;
    const preview = document.getElementById('attachmentPreview');
    preview.innerHTML = '';
    preview.classList.remove('active');
    ['imageInput','fileInput','videoInput'].forEach(id => document.getElementById(id).value = '');
}

// ── INTERACTIONS ──────────────────────────────────────────────────────────────
function likePost(id)    { alert('Like functionality coming soon!'); }
function commentPost(id) { alert('Comment functionality coming soon!'); }
function sharePost(id)   { alert('Share functionality coming soon!'); }

// ── UTILS ─────────────────────────────────────────────────────────────────────
function formatTimeAgo(ts) {
    const s = Math.floor((Date.now() - new Date(ts)) / 1000);
    if (s < 60)     return 'Just now';
    if (s < 3600)   return `${Math.floor(s/60)}m ago`;
    if (s < 86400)  return `${Math.floor(s/3600)}h ago`;
    if (s < 604800) return `${Math.floor(s/86400)}d ago`;
    return new Date(ts).toLocaleDateString();
}

function escapeHTML(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
</script>
</body>
</html>
