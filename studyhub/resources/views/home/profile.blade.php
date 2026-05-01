<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Profile - StudyHub</title>
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
        }
        .top-bar-btn {
            position: relative;
            width: 40px; height: 40px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: white;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.2s ease;
            color: var(--text-secondary); text-decoration: none;
        }
        .top-bar-btn:hover { background: var(--bg-main); color: var(--primary); border-color: var(--primary); }
        .top-bar-btn svg { width: 20px; height: 20px; }
        .notif-dot {
            position: absolute; top: 6px; right: 6px;
            width: 8px; height: 8px;
            background: var(--accent); border-radius: 50%;
            border: 2px solid white;
        }
        .top-bar-avatar {
            width: 40px; height: 40px; border-radius: 10px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 14px;
            cursor: pointer; border: none; overflow: hidden;
            transition: all 0.2s ease;
        }
        .top-bar-avatar:hover { opacity: 0.85; transform: scale(0.97); }
        .top-bar-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-menu-wrap { position: relative; }
        .profile-menu {
            position: absolute; top: calc(100% + 10px); right: 0;
            min-width: 180px; background: white;
            border: 1px solid var(--border); border-radius: 12px;
            box-shadow: 0 14px 28px rgba(0,0,0,0.12);
            padding: 8px; display: none; z-index: 1200;
        }
        .profile-menu.open { display: block; }
        .profile-menu-item {
            display: block; width: 100%; text-align: left; border: 0;
            border-radius: 10px; background: transparent;
            color: var(--text-primary); font-family: 'DM Sans', sans-serif;
            font-size: 14px; font-weight: 600; padding: 10px 12px;
            cursor: pointer; text-decoration: none;
        }
        .profile-menu-item:hover { background: var(--bg-main); color: var(--primary); }

        /* ── MAIN LAYOUT ── */
        .main-content {
            margin-left: var(--sidebar-collapsed);
            margin-top: 64px;
            min-height: calc(100vh - 64px);
            padding: 32px 24px;
            display: flex;
            justify-content: center;
        }

        /* ── PROFILE COLUMN ── */
        .profile-column {
            flex: 1;
            max-width: 720px;
            min-width: 0;
        }

        /* ── PROFILE HERO ── */
        .profile-hero {
            background: var(--bg-card);
            border-radius: 20px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px var(--shadow);
        }
        .profile-hero-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .profile-cover {
            height: 140px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 40%, var(--secondary) 100%);
            position: relative;
        }
        .profile-cover-pattern {
            position: absolute; inset: 0;
            background-image:
                radial-gradient(circle at 20% 50%, rgba(255,255,255,0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.06) 0%, transparent 40%);
        }
        .profile-body { padding: 0 28px 28px; position: relative; }
        .profile-avatar-wrap {
            position: relative; display: inline-block;
            margin-top: -44px; margin-bottom: 12px;
        }
        .profile-avatar-large {
            width: 88px; height: 88px; border-radius: 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: 4px solid white;
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 28px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        .profile-avatar-large img { width: 100%; height: 100%; object-fit: cover; }
        .profile-upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border);
            background: white;
            color: var(--text-primary);
            border-radius: 12px;
            padding: 10px 14px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .profile-upload-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--bg-main);
        }
        .profile-meta {
            display: flex; align-items: flex-start;
            justify-content: space-between; flex-wrap: wrap; gap: 12px;
        }
        .profile-name {
            font-family: 'Crimson Pro', serif;
            font-size: 26px; font-weight: 700;
            color: var(--text-primary); margin-bottom: 2px;
        }
        .profile-username { font-size: 14px; color: var(--text-light); margin-bottom: 10px; }
        .profile-bio {
            max-width: 58ch;
            font-size: 15px;
            line-height: 1.6;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }
        .profile-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 18px;
            margin-bottom: 14px;
            color: var(--text-light);
            font-size: 13px;
        }
        .profile-meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .profile-stats { display: flex; gap: 24px; flex-wrap: wrap; }
        .profile-insights-row {
            margin-top: 18px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 280px;
            gap: 14px;
            align-items: stretch;
        }
        .profile-stats-list {
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            background: white;
        }
        .profile-stat-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 16px;
            border-top: 1px solid var(--border);
        }
        .profile-stat-row:first-child {
            border-top: 0;
        }
        .profile-stat-row:hover {
            background: rgba(26, 95, 122, 0.03);
        }
        .profile-stat-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        .stat-value {
            font-family: 'Crimson Pro', serif;
            font-size: 24px; font-weight: 700; color: var(--primary);
            line-height: 1;
            text-align: right;
            white-space: nowrap;
        }
        .stat-label { font-size: 12px; color: var(--text-light); font-weight: 500; }
        .profile-stat-note {
            margin-top: 8px;
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.4;
        }
        .profile-photo-input { display: none; }

        .profile-friends-card {
            border: 1px solid var(--border);
            border-radius: 16px;
            background: white;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .profile-friends-header {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--text-light);
        }
        .profile-friends-scroll {
            max-height: 212px;
            overflow-y: auto;
            padding: 8px;
        }
        .profile-friend-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 8px;
            border-radius: 10px;
        }
        .profile-friend-row:hover {
            background: var(--bg-main);
        }
        .profile-friend-main {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .profile-friend-avatar {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }
        .profile-friend-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-friend-name {
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .profile-friend-active-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #22c55e;
            flex-shrink: 0;
            box-shadow: 0 0 0 2px #dcfce7;
        }
        .profile-friends-empty {
            font-size: 13px;
            color: var(--text-light);
            padding: 14px 10px;
        }

        .profile-account-actions {
            margin-top: 26px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }
        .profile-account-btn {
            border: 1px solid var(--border);
            background: white;
            color: var(--text-primary);
            border-radius: 10px;
            padding: 10px 18px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .profile-account-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--bg-main);
        }
        .profile-account-btn.danger {
            background: #fff5f5;
            border-color: #fecaca;
            color: #dc2626;
        }
        .profile-account-btn.danger:hover {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #b91c1c;
        }

        @media (max-width: 720px) {
            .profile-insights-row {
                grid-template-columns: 1fr;
            }
            .profile-stat-row {
                align-items: flex-start;
                flex-direction: column;
            }
            .stat-value {
                text-align: left;
            }
            .profile-friends-scroll {
                max-height: 180px;
            }
            .profile-account-actions {
                justify-content: stretch;
            }
            .profile-account-btn,
            .profile-account-actions form {
                width: 100%;
            }
            .profile-account-btn {
                display: block;
                text-align: center;
            }
        }

        /* ── POSTS SECTION ── */
        .section-header {
            display: flex; align-items: center;
            justify-content: space-between; margin-bottom: 16px;
        }
        .section-title {
            font-family: 'Crimson Pro', serif;
            font-size: 22px; font-weight: 700; color: var(--text-primary);
        }
        .post-count-badge {
            background: linear-gradient(135deg, rgba(26,95,122,0.1) 0%, rgba(245,158,66,0.1) 100%);
            color: var(--primary); font-size: 13px; font-weight: 600;
            padding: 4px 12px; border-radius: 20px;
        }

        /* ── POST CARDS ── */
        .feed { display: flex; flex-direction: column; gap: 16px; }
        .post-card {
            background: var(--bg-card); border-radius: 16px;
            border: 1px solid var(--border); padding: 24px;
            transition: box-shadow 0.3s ease, transform 0.3s ease;
            position: relative;
        }
        .post-card:hover { box-shadow: 0 8px 24px var(--shadow-md); transform: translateY(-2px); }
        .post-header { display: flex; gap: 12px; margin-bottom: 14px; align-items: flex-start; }
        .post-avatar {
            width: 48px; height: 48px; border-radius: 12px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
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
            width: 32px; height: 32px; border-radius: 8px; border: none;
            background: transparent; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-light); transition: all 0.2s ease;
        }
        .post-menu-btn:hover { background: var(--bg-main); color: var(--text-secondary); }
        .post-menu-btn svg { width: 18px; height: 18px; }
        .post-dropdown {
            position: absolute; top: calc(100% + 4px); right: 0;
            background: white; border: 1px solid var(--border);
            border-radius: 12px; box-shadow: 0 8px 24px var(--shadow-lg);
            min-width: 160px; z-index: 200; overflow: hidden; display: none;
        }
        .post-dropdown.open { display: block; }
        .dropdown-item {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 16px; font-size: 14px; font-weight: 500;
            cursor: pointer; transition: background 0.15s;
            border: none; background: none; width: 100%; text-align: left;
            color: var(--text-primary); font-family: 'DM Sans', sans-serif;
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
            padding-top: 14px; border-top: 1px solid var(--border);
        }
        .interaction-btn {
            display: flex; align-items: center; gap: 6px;
            padding: 7px 14px; border-radius: 8px;
            background: none; border: none; color: var(--text-secondary);
            font-weight: 500; font-size: 14px;
            cursor: pointer; transition: all 0.2s ease;
            font-family: 'DM Sans', sans-serif;
        }
        .interaction-btn:hover { background: var(--bg-main); color: var(--primary); }

        /* ── STATES ── */
        .loading { text-align: center; padding: 48px 24px; color: var(--text-light); font-size: 15px; }
        .loading-spinner {
            width: 36px; height: 36px;
            border: 3px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            margin: 0 auto 16px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .empty-state {
            text-align: center; padding: 60px 24px;
            background: var(--bg-card);
            border-radius: 16px; border: 1px solid var(--border);
        }
        .empty-state-icon { font-size: 48px; margin-bottom: 16px; }
        .empty-state h3 {
            font-family: 'Crimson Pro', serif;
            font-size: 22px; font-weight: 700;
            color: var(--text-secondary); margin-bottom: 8px;
        }
        .empty-state p { font-size: 14px; color: var(--text-light); }
        .empty-state-link {
            display: inline-block; margin-top: 20px;
            padding: 10px 28px; border-radius: 10px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white; font-weight: 600; text-decoration: none;
            font-size: 14px; transition: opacity 0.2s;
        }
        .empty-state-link:hover { opacity: 0.88; }
        .error-state {
            background: #fff5f5; border: 1px solid #fecaca;
            color: #dc2626; padding: 16px; border-radius: 12px; font-size: 14px;
        }

        /* ── MODAL ── */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.45);
            z-index: 2000; display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity 0.2s;
        }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal {
            background: white; border-radius: 20px;
            width: 90%; max-width: 520px; padding: 28px;
            transform: scale(0.95); transition: transform 0.2s;
            box-shadow: 0 20px 60px rgba(0,0,0,0.18);
        }
        .modal-overlay.open .modal { transform: scale(1); }
        .modal-header {
            display: flex; align-items: center;
            justify-content: space-between; margin-bottom: 20px;
        }
        .modal-title { font-family: 'Crimson Pro', serif; font-size: 22px; font-weight: 700; }
        .modal-close {
            width: 32px; height: 32px; border-radius: 8px; border: none;
            background: var(--bg-main); cursor: pointer; font-size: 18px;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-secondary);
        }
        .modal-close:hover { background: #fee2e2; color: var(--accent); }
        .modal-textarea {
            width: 100%; min-height: 120px; padding: 14px;
            border: 2px solid var(--border); border-radius: 12px;
            font-family: 'DM Sans', sans-serif; font-size: 15px; resize: vertical;
        }
        .modal-textarea:focus { outline: none; border-color: var(--primary); }
        .modal-actions { display: flex; gap: 10px; margin-top: 16px; justify-content: flex-end; }
        .btn-cancel {
            padding: 10px 24px; border-radius: 10px;
            border: 1px solid var(--border); background: white;
            font-family: 'DM Sans', sans-serif; font-weight: 600;
            cursor: pointer; color: var(--text-secondary);
        }
        .btn-cancel:hover { background: var(--bg-main); }
        .btn-save {
            padding: 10px 28px; border-radius: 10px; border: none;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white; font-family: 'DM Sans', sans-serif;
            font-weight: 600; cursor: pointer;
        }
        .btn-save:hover { opacity: 0.9; }
    </style>
</head>
<body>

@include('partials.universal-search')

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
        <a href="{{ route('dashboard') }}" class="nav-item">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
            </svg>
            <span class="nav-text">Newsfeed</span>
        </a>
        <a href="{{ route('calendar') }}" class="nav-item">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <span class="nav-text">Calendar</span>
        </a>
        <a href="{{ route('study-groups') }}" class="nav-item">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75"/>
            </svg>
            <span class="nav-text">Study Groups</span>
        </a>
        <a href="{{ route('resources') }}" class="nav-item">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/>
                <path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>
            </svg>
            <span class="nav-text">Resources</span>
        </a>
        <a href="{{ route('notifications') }}" class="nav-item">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 01-3.46 0"/>
            </svg>
            <span class="nav-text">Notifications</span>
            <span class="nav-badge">5</span>
        </a>
        <a href="{{ route('messages') }}" class="nav-item">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
            </svg>
            <span class="nav-text">Messages</span>
        </a>
        <a href="{{ route('friends') }}" class="nav-item">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75"/>
            </svg>
            <span class="nav-text">Friends</span>
        </a>
        <a href="{{ route('profile') }}" class="nav-item active">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
            <span class="nav-text">Profile</span>
        </a>
        <a href="{{ route('settings') }}" class="nav-item">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
            </svg>
            <span class="nav-text">Settings</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar" id="sidebarAvatar"></div>
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
<div class="top-bar">
    <a href="{{ route('notifications') }}" class="top-bar-btn" title="Notifications">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 01-3.46 0"/>
        </svg>
        <span class="notif-dot"></span>
    </a>
    <a href="{{ route('friend-requests') }}" class="top-bar-btn" title="Friend Requests">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
            <path d="M22 16s-1-1-2-1"/>
            <path d="M22 19s-1 1-2 1"/>
        </svg>
    </a>
    <div class="profile-menu-wrap" id="profileMenuWrap">
        <button class="top-bar-avatar" id="topBarAvatar" type="button" title="Your Profile"></button>
        <div class="profile-menu" id="profileMenu">
            <a href="{{ route('profile') }}" class="profile-menu-item">View Profile</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="profile-menu-item" type="submit">Logout</button>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     MAIN CONTENT
══════════════════════════════════════════ -->
<main class="main-content">
    <div class="profile-column">

        <!-- ── PROFILE HERO CARD ── -->
        <div class="profile-hero">
            <div class="profile-cover">
                <div class="profile-cover-pattern"></div>
            </div>
            <div class="profile-body">
                <div class="profile-hero-header">
                    <div class="profile-avatar-wrap">
                        <div class="profile-avatar-large" id="profileAvatarLarge"></div>
                    </div>
                    <div>
                        <button class="profile-upload-btn" type="button" id="profilePhotoButton">
                            Change photo
                        </button>
                        <input type="file" class="profile-photo-input" id="profilePhotoInput" accept="image/*">
                    </div>
                </div>
                <div class="profile-meta">
                    <div>
                        <div class="profile-name" id="profileFullName">Loading…</div>
                        <div class="profile-username" id="profileUsername"></div>
                        <div class="profile-bio" id="profileBio"></div>
                        <div class="profile-meta-row">
                            <div class="profile-meta-pill">Joined <span id="profileJoinedDate">—</span></div>
                        </div>
                    </div>
                </div>

                <div class="profile-insights-row">
                    <div class="profile-stats-list">
                        <div class="profile-stat-row">
                            <div class="profile-stat-label">Posts made</div>
                            <div class="stat-value" id="statPostCount">—</div>
                        </div>
                        <div class="profile-stat-row">
                            <div class="profile-stat-label">Resources uploaded</div>
                            <div class="stat-value" id="statResourceCount">—</div>
                        </div>
                        <div class="profile-stat-row">
                            <div class="profile-stat-label">Study sessions (active/completed)</div>
                            <div class="stat-value" id="statStudySessions">—</div>
                        </div>
                        <div class="profile-stat-row">
                            <div class="profile-stat-label">Total focus time</div>
                            <div class="stat-value" id="statFocusTime">—</div>
                        </div>
                    </div>

                    <div class="profile-friends-card">
                        <div class="profile-friends-header">Friends</div>
                        <div class="profile-friends-scroll">
                            @php
                                $friendList = is_array($profileData['friends'] ?? null) ? $profileData['friends'] : [];
                            @endphp

                            @if (count($friendList) === 0)
                                <div class="profile-friends-empty">No friends added yet.</div>
                            @else
                                @foreach ($friendList as $friend)
                                    @php
                                        $friendName = trim((string) ($friend['name'] ?? 'Friend'));
                                        $friendPhoto = trim((string) ($friend['photo'] ?? ''));
                                        $friendInitials = trim((string) ($friend['initials'] ?? ''));
                                        if ($friendInitials === '') {
                                            $parts = preg_split('/\s+/', $friendName) ?: [];
                                            $friendInitials = strtoupper(substr((string) ($parts[0] ?? 'F'), 0, 1) . substr((string) ($parts[1] ?? ''), 0, 1));
                                        }
                                        $isFriendActive = (bool) ($friend['is_active'] ?? false);
                                    @endphp
                                    <div class="profile-friend-row">
                                        <div class="profile-friend-main">
                                            <div class="profile-friend-avatar">
                                                @if ($friendPhoto !== '')
                                                    <img src="{{ $friendPhoto }}" alt="{{ $friendName }}">
                                                @else
                                                    {{ $friendInitials }}
                                                @endif
                                            </div>
                                            <div class="profile-friend-name">{{ $friendName }}</div>
                                        </div>
                                        @if ($isFriendActive)
                                            <span class="profile-friend-active-dot" title="Active"></span>
                                        @endif
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── MY POSTS ── -->
        <div class="section-header">
            <span class="section-title">My Posts</span>
            <span class="post-count-badge" id="postCountBadge">Loading…</span>
        </div>

        {{--
            Posts are fetched from the same newsfeed_posts Supabase table
            as the dashboard, filtered by user_id = current session user,
            ordered newest → oldest.
        --}}
        <div id="profileFeed" class="feed">
            <div class="loading">
                <div class="loading-spinner"></div>
                Loading your posts…
            </div>
        </div>

        <div class="profile-account-actions">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="profile-account-btn">Change Account</button>
            </form>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="profile-account-btn danger">Logout</button>
            </form>
        </div>

    </div>
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
// ── CONFIG ────────────────────────────────────────────────────────────────────
// Identical credentials to dashboard.blade.php — same Supabase project
const SUPABASE_URL         = '{{ env("SUPABASE_URL") }}';
const SUPABASE_ANON_KEY    = '{{ env("SUPABASE_ANON_KEY") }}';
const SUPABASE_SERVICE_KEY = '{{ env("SUPABASE_SERVICE_KEY") }}';

// ── CURRENT USER ──────────────────────────────────────────────────────────────
// Pulled from the same Laravel session that the dashboard sets.
// No extra setup needed — as long as the user is logged in, this works.
const currentUser = {
    id:                '{{ session("user_id") }}',
    first_name:        '{{ session("user_first_name") }}',
    last_name:         '{{ session("user_last_name") }}',
    username:          '{{ session("user_username") }}',
    profile_photo_url: '{{ session("user_profile_photo") }}'
};

const profileData = @json($profileData ?? []);

let editingPostId = null;

// ── INIT ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    renderUserUI();
    loadProfilePosts();

    document.getElementById('profilePhotoButton')?.addEventListener('click', () => {
        document.getElementById('profilePhotoInput')?.click();
    });

    document.getElementById('profilePhotoInput')?.addEventListener('change', handleProfilePhotoChange);

    document.addEventListener('click', e => {
        if (!e.target.closest('.post-menu-wrap')) {
            document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
        }
        if (!e.target.closest('#profileMenuWrap')) {
            document.getElementById('profileMenu')?.classList.remove('open');
        }
    });

    document.getElementById('topBarAvatar')?.addEventListener('click', e => {
        e.stopPropagation();
        document.getElementById('profileMenu')?.classList.toggle('open');
    });
});

// ── RENDER USER UI ────────────────────────────────────────────────────────────
function renderUserUI() {
    const firstName = profileData.first_name || currentUser.first_name;
    const lastName = profileData.last_name || currentUser.last_name;
    const username = profileData.username || currentUser.username;
    const photoUrl = profileData.profile_photo_url || currentUser.profile_photo_url;
    const initials = avatarInitials(firstName, lastName);
    const fullName = profileData.display_name
        || `${firstName} ${lastName}`.trim()
        || username
        || 'You';
    const joinedDate = formatJoinedDate(profileData.joined_at);
    const bio = profileData.bio || 'StudyHub learner';

    currentUser.first_name = firstName;
    currentUser.last_name = lastName;
    currentUser.username = username;
    currentUser.profile_photo_url = photoUrl;

    // Sidebar
    const sidebarAv = document.getElementById('sidebarAvatar');
    sidebarAv.innerHTML = photoUrl
        ? `<img src="${photoUrl}" alt="${escapeHTML(fullName)}">`
        : initials;
    document.getElementById('sidebarUserName').textContent = fullName;

    // Top bar
    const topAv = document.getElementById('topBarAvatar');
    topAv.innerHTML = photoUrl
        ? `<img src="${photoUrl}" alt="${escapeHTML(fullName)}" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`
        : initials;

    // Profile hero
    const heroAv = document.getElementById('profileAvatarLarge');
    heroAv.innerHTML = photoUrl
        ? `<img src="${photoUrl}" alt="${escapeHTML(fullName)}">`
        : initials;

    document.getElementById('profileFullName').textContent = fullName;
    document.getElementById('profileUsername').textContent  = username ? `@${username}` : '';
    document.getElementById('profileBio').textContent = bio;
    document.getElementById('profileJoinedDate').textContent = joinedDate;

    document.getElementById('statResourceCount').textContent = formatCount(profileData.stats?.resources_uploaded ?? 0);
    document.getElementById('statStudySessions').textContent = `${formatCount(profileData.stats?.study_sessions_active ?? 0)} active / ${formatCount(profileData.stats?.study_sessions_completed ?? 0)} completed`;
    document.getElementById('statFocusTime').textContent = formatFocusTime(profileData.stats?.total_focus_seconds ?? 0);
}

function avatarInitials(first, last) {
    return `${(first || '?')[0]}${(last || '?')[0]}`.toUpperCase();
}

function formatJoinedDate(value) {
    if (!value) return 'Recently';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'Recently';

    return new Intl.DateTimeFormat('en', { month: 'short', year: 'numeric' }).format(date);
}

function formatCount(value) {
    const count = Number(value || 0);
    return Number.isFinite(count) ? count.toLocaleString() : '0';
}

function formatFocusTime(totalSeconds) {
    const seconds = Number(totalSeconds || 0);
    if (!Number.isFinite(seconds) || seconds <= 0) return '0 min';

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (hours > 0) {
        return minutes > 0 ? `${hours}h ${minutes}m` : `${hours}h`;
    }

    return `${Math.max(1, minutes)}m`;
}

function handleProfilePhotoChange(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = () => {
        const dataUrl = String(reader.result || '');
        if (!dataUrl) return;

        currentUser.profile_photo_url = dataUrl;
        profileData.profile_photo_url = dataUrl;
        document.getElementById('profileAvatarLarge').innerHTML = `<img src="${dataUrl}" alt="${escapeHTML(profileData.display_name || 'Profile photo')}">`;
        document.getElementById('sidebarAvatar').innerHTML = `<img src="${dataUrl}" alt="${escapeHTML(profileData.display_name || 'Profile photo')}">`;
        document.getElementById('topBarAvatar').innerHTML = `<img src="${dataUrl}" alt="${escapeHTML(profileData.display_name || 'Profile photo')}" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`;

        fetch('{{ route('set-session') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                user_id: currentUser.id,
                first_name: currentUser.first_name,
                last_name: currentUser.last_name,
                username: currentUser.username,
                profile_photo: dataUrl,
                student_type: profileData.student_type || ''
            })
        }).catch(() => {
            // The UI is already updated locally; session sync will happen on the next successful save.
        });
    };

    reader.readAsDataURL(file);
}

// ── LOAD PROFILE POSTS ────────────────────────────────────────────────────────
// Reads from the same newsfeed_posts table as the dashboard.
// Filter: user_id = logged-in user | is_archived = false | order = newest first
async function loadProfilePosts() {
    const feed = document.getElementById('profileFeed');

    if (!currentUser.id || currentUser.id === '') {
        feed.innerHTML = `<div class="error-state">❌ Not logged in. Please <a href="{{ route('login') }}">log in</a>.</div>`;
        return;
    }

    try {
        const res = await fetch(
            `${SUPABASE_URL}/rest/v1/newsfeed_posts` +
            `?select=*,profiles(username,first_name,last_name,profile_photo_url)` +
            `&user_id=eq.${currentUser.id}` +
            `&is_archived=eq.false` +
            `&order=created_at.desc`,
            {
                headers: {
                    'apikey':        SUPABASE_ANON_KEY,
                    'Authorization': `Bearer ${SUPABASE_ANON_KEY}`
                }
            }
        );

        if (!res.ok) throw new Error((await res.json()).message || 'Failed to load');

        const posts = await res.json();

        // Update stat counters in the hero card
        document.getElementById('statPostCount').textContent  = posts.length;
        document.getElementById('postCountBadge').textContent =
            `${posts.length} post${posts.length !== 1 ? 's' : ''}`;

        if (!posts.length) {
            feed.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">✏️</div>
                    <h3>No posts yet</h3>
                    <p>Anything you post on the newsfeed will appear here.</p>
                    <a href="{{ route('dashboard') }}" class="empty-state-link">Go to Newsfeed</a>
                </div>`;
            return;
        }

        // Render posts — identical card layout as the dashboard
        feed.innerHTML = posts.map(createPostHTML).join('');

    } catch (err) {
        feed.innerHTML = `<div class="error-state">❌ Failed to load posts: ${err.message}</div>`;
        document.getElementById('statPostCount').textContent  = '—';
        document.getElementById('postCountBadge').textContent = 'Error';
    }
}

// ── POST HTML ─────────────────────────────────────────────────────────────────
// Same rendering logic as dashboard so posts look identical in both places.
// All posts on this page belong to the current user so the 3-dot menu
// is always shown (no isOwn check needed here).
function createPostHTML(post) {
    const author   = post.profiles || {};
    const firstName = author.first_name || currentUser.first_name || 'Unknown';
    const lastName  = author.last_name  || currentUser.last_name  || 'User';
    const fullName  = `${firstName} ${lastName}`.trim();
    const initials  = avatarInitials(firstName, lastName);
    const timeAgo   = formatTimeAgo(post.created_at);
    const photoUrl  = author.profile_photo_url || currentUser.profile_photo_url || '';

    const avatarHTML = photoUrl
        ? `<img src="${photoUrl}" alt="${escapeHTML(fullName)}">`
        : initials;

    // Media
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

    return `
        <div class="post-card" id="post-${post.id}">
            <div class="post-header">
                <div class="post-avatar">${avatarHTML}</div>
                <div class="post-info">
                    <div class="post-author">${escapeHTML(fullName)}</div>
                    <div class="post-time">${timeAgo}</div>
                </div>
                <div class="post-menu-wrap">
                    <button class="post-menu-btn" onclick="toggleMenu('${post.id}', event)" title="Options">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="12" cy="5" r="1.5"/>
                            <circle cx="12" cy="12" r="1.5"/>
                            <circle cx="12" cy="19" r="1.5"/>
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
        </div>`;
}

// ── 3-DOT MENU ────────────────────────────────────────────────────────────────
function toggleMenu(postId, e) {
    e.stopPropagation();
    const menu    = document.getElementById(`menu-${postId}`);
    const wasOpen = menu.classList.contains('open');
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
    if (!wasOpen) menu.classList.add('open');
}

// ── EDIT ──────────────────────────────────────────────────────────────────────
function openEditModal(postId) {
    document.querySelectorAll('.post-dropdown.open').forEach(d => d.classList.remove('open'));
    const text = document.getElementById(`post-${postId}`).querySelector('.post-text').textContent;
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
                'apikey':        SUPABASE_SERVICE_KEY,
                'Authorization': `Bearer ${SUPABASE_SERVICE_KEY}`,
                'Content-Type':  'application/json',
                'Prefer':        'return=representation'
            },
            body: JSON.stringify({ content: newContent })
        });
        if (!res.ok) throw new Error((await res.json()).message || 'Update failed');
        closeEditModal();
        // Full reload keeps this page and the newsfeed in sync
        await loadProfilePosts();
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
                'apikey':        SUPABASE_SERVICE_KEY,
                'Authorization': `Bearer ${SUPABASE_SERVICE_KEY}`,
                'Content-Type':  'application/json'
            },
            body: JSON.stringify({ is_archived: true })
        });
        if (!res.ok) throw new Error((await res.json()).message || 'Archive failed');
        document.getElementById(`post-${postId}`)?.remove();
        refreshPostCount();
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
                'apikey':        SUPABASE_SERVICE_KEY,
                'Authorization': `Bearer ${SUPABASE_SERVICE_KEY}`
            }
        });
        if (!res.ok) throw new Error((await res.json()).message || 'Delete failed');
        document.getElementById(`post-${postId}`)?.remove();
        refreshPostCount();
    } catch (err) {
        alert('Failed to delete post: ' + err.message);
    }
}

// ── REFRESH COUNTS (after archive / delete) ───────────────────────────────────
function refreshPostCount() {
    const remaining = document.querySelectorAll('.post-card').length;
    document.getElementById('statPostCount').textContent  = remaining;
    document.getElementById('postCountBadge').textContent =
        `${remaining} post${remaining !== 1 ? 's' : ''}`;

    if (remaining === 0) {
        document.getElementById('profileFeed').innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">✏️</div>
                <h3>No posts yet</h3>
                <p>Anything you post on the newsfeed will appear here.</p>
                <a href="{{ route('dashboard') }}" class="empty-state-link">Go to Newsfeed</a>
            </div>`;
    }
}

// ── INTERACTIONS ──────────────────────────────────────────────────────────────
function likePost(id)    { alert('Like functionality coming soon!'); }
function commentPost(id) { alert('Comment functionality coming soon!'); }
function sharePost(id)   { alert('Share functionality coming soon!'); }

// ── UTILS ─────────────────────────────────────────────────────────────────────
function formatTimeAgo(ts) {
    const s = Math.floor((Date.now() - new Date(ts)) / 1000);
    if (s < 60)     return 'Just now';
    if (s < 3600)   return `${Math.floor(s / 60)}m ago`;
    if (s < 86400)  return `${Math.floor(s / 3600)}h ago`;
    if (s < 604800) return `${Math.floor(s / 86400)}d ago`;
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
