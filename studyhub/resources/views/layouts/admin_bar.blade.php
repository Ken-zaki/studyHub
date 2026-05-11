{{--
  ════════════════════════════════════════════════════════════
  resources/views/layouts/admin_bar.blade.php

  Add @include('layouts.admin_bar') just before </body>
  in every user-facing blade page:
    - newsfeed.blade.php
    - profile.blade.php
    - profile-view.blade.php
    - resources.blade.php  (user side)
    - etc.

  For regular users this outputs nothing at all.
  For admins it injects a collapsed icon-only sidebar on the
  left (matching the screenshot) with tooltips + an exit button.
  ════════════════════════════════════════════════════════════
--}}
<script>
    (function() {
        if (!sessionStorage.getItem('admViewAsUser')) return;

        const dashUrl = sessionStorage.getItem('admDashboardUrl') || '/admin';
        const reportsUrl = sessionStorage.getItem('admReportsUrl') || '/admin/reports';
        const resourcesUrl = sessionStorage.getItem('admResourcesUrl') || '/admin/resources';
        const logsUrl = sessionStorage.getItem('admLogsUrl') || '/admin/logs';
        const usersUrl = sessionStorage.getItem('admUsersUrl') || '/admin/users';
        const settingsUrl = sessionStorage.getItem('admSettingsUrl') || '/admin/settings';

        /* ── Inject styles ── */
        const style = document.createElement('style');
        style.textContent = `
        /* Push existing sidebar/content right to make room */
        .adm-preview-push {
            margin-left: 64px !important;
        }

        /* The admin mini-sidebar */
        #admMiniBar {
            position: fixed;
            top: 0; left: 0;
            width: 64px;
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 99999;
            box-shadow: 2px 0 12px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        /* Logo mark at top */
        #admMiniBar .adm-mini-logo {
            width: 36px; height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #1a5f7a, #f59e42);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 16px;
            font-family: 'Crimson Pro', serif;
            margin: 14px 0 10px;
            flex-shrink: 0;
        }

        /* Admin badge pill */
        #admMiniBar .adm-mini-badge {
            font-size: 9px; font-weight: 800;
            letter-spacing: 0.06em; text-transform: uppercase;
            color: #1a5f7a;
            background: rgba(26,95,122,0.1);
            padding: 2px 7px; border-radius: 20px;
            margin-bottom: 14px;
            white-space: nowrap;
            font-family: 'DM Sans', sans-serif;
        }

        /* Divider */
        #admMiniBar .adm-mini-divider {
            width: 32px; height: 1px;
            background: #e5e7eb;
            margin: 4px 0;
            flex-shrink: 0;
        }

        /* Nav items */
        #admMiniBar .adm-mini-item {
            position: relative;
            width: 44px; height: 44px;
            border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            color: #9ca3af;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.18s, color 0.18s;
            flex-shrink: 0;
            margin: 2px 0;
        }
        #admMiniBar .adm-mini-item:hover {
            background: rgba(26,95,122,0.08);
            color: #1a5f7a;
        }
        #admMiniBar .adm-mini-item svg {
            width: 18px; height: 18px;
            stroke: currentColor; flex-shrink: 0;
        }

        /* Tooltip on hover */
        #admMiniBar .adm-mini-item::after {
            content: attr(data-tip);
            position: absolute;
            left: calc(100% + 10px);
            top: 50%; transform: translateY(-50%);
            background: #1a1a1a;
            color: white;
            font-size: 12px; font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            padding: 5px 10px;
            border-radius: 7px;
            white-space: nowrap;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s;
            z-index: 100000;
        }
        #admMiniBar .adm-mini-item::before {
            content: '';
            position: absolute;
            left: calc(100% + 5px);
            top: 50%; transform: translateY(-50%);
            border: 5px solid transparent;
            border-right-color: #1a1a1a;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s;
            z-index: 100000;
        }
        #admMiniBar .adm-mini-item:hover::after,
        #admMiniBar .adm-mini-item:hover::before {
            opacity: 1;
        }

        /* Badge dot on Reports / Resources */
        #admMiniBar .adm-mini-dot {
            position: absolute;
            top: 7px; right: 7px;
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #ff6b6b;
            border: 2px solid white;
        }

        /* Spacer pushes exit to bottom */
        #admMiniBar .adm-mini-spacer { flex: 1; }

        /* Exit button at bottom */
        #admMiniBar .adm-mini-exit {
            width: 44px; height: 44px;
            border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            color: #ff6b6b;
            cursor: pointer;
            transition: background 0.18s;
            flex-shrink: 0;
            margin-bottom: 12px;
            background: none; border: none;
            position: relative;
        }
        #admMiniBar .adm-mini-exit:hover { background: rgba(255,107,107,0.1); }
        #admMiniBar .adm-mini-exit svg { width: 18px; height: 18px; stroke: currentColor; }
        #admMiniBar .adm-mini-exit::after {
            content: 'Exit User View';
            position: absolute;
            left: calc(100% + 10px);
            top: 50%; transform: translateY(-50%);
            background: #1a1a1a; color: white;
            font-size: 12px; font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            padding: 5px 10px; border-radius: 7px;
            white-space: nowrap; pointer-events: none;
            opacity: 0; transition: opacity 0.15s; z-index: 100000;
        }
        #admMiniBar .adm-mini-exit::before {
            content: '';
            position: absolute; left: calc(100% + 5px);
            top: 50%; transform: translateY(-50%);
            border: 5px solid transparent;
            border-right-color: #1a1a1a;
            pointer-events: none; opacity: 0; transition: opacity 0.15s; z-index: 100000;
        }
        #admMiniBar .adm-mini-exit:hover::after,
        #admMiniBar .adm-mini-exit:hover::before { opacity: 1; }
    `;
        document.head.appendChild(style);

        /* ── Push existing sidebar & main content right ── */
        // The user sidebar (.sidebar) uses margin-left on .main-content
        // We push both right by adding 64px
        document.querySelectorAll('.sidebar').forEach(el => {
            el.style.left = '64px';
        });
        document.querySelectorAll('.main-content, .top-bar').forEach(el => {
            const cur = parseInt(window.getComputedStyle(el).marginLeft || '0');
            el.style.marginLeft = (cur + 64) + 'px';
        });
        // Also shift the top bar left anchor
        document.querySelectorAll('.top-bar').forEach(el => {
            el.style.left = (parseInt(el.style.left || el.style.marginLeft || '64') + 64) + 'px';
            el.style.marginLeft = '0';
        });

        /* ── Build the mini sidebar ── */
        const bar = document.createElement('div');
        bar.id = 'admMiniBar';
        bar.innerHTML = `
        <!-- Logo -->
        <div class="adm-mini-logo">S</div>
        <div class="adm-mini-badge">Admin</div>
        <div class="adm-mini-divider"></div>

        <!-- Dashboard -->
        <a class="adm-mini-item" href="${dashUrl}" data-tip="Dashboard">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                <rect x="3" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/>
                <rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
        </a>

        <div class="adm-mini-divider"></div>

        <!-- User Management -->
        <a class="adm-mini-item" href="${usersUrl}" data-tip="User Management">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75"/>
            </svg>
        </a>

        <!-- Reports -->
        <a class="adm-mini-item" href="${reportsUrl}" data-tip="Reports" id="admMiniReports">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </a>

        <!-- Resource Approval -->
        <a class="adm-mini-item" href="${resourcesUrl}" data-tip="Resource Approval" id="admMiniResources">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                <path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/>
                <path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>
            </svg>
        </a>

        <!-- Admin Logs -->
        <a class="adm-mini-item" href="${logsUrl}" data-tip="Admin Logs">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/>
                <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
        </a>

        <!-- Settings -->
        <a class="adm-mini-item" href="${settingsUrl}" data-tip="Settings">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06A1.65 1.65 0 004.6 9a1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9"/>
            </svg>
        </a>

        <div class="adm-mini-spacer"></div>

        <!-- Exit user view -->
        <button class="adm-mini-exit" onclick="_admExit(event)">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
        </button>
    `;
        document.body.insertBefore(bar, document.body.firstChild);

        /* ── Load pending counts for dot badges ── */
        const SB_URL = sessionStorage.getItem('admSbUrl');
        const SB_KEY = sessionStorage.getItem('admSbKey');
        if (SB_URL && SB_KEY) {
            Promise.all([
                fetch(`${SB_URL}/rest/v1/reports?status=eq.pending&select=id`, {
                    headers: {
                        'apikey': SB_KEY,
                        'Prefer': 'count=exact'
                    }
                }),
                fetch(`${SB_URL}/rest/v1/resources?is_approved=eq.false&select=id`, {
                    headers: {
                        'apikey': SB_KEY,
                        'Prefer': 'count=exact'
                    }
                })
            ]).then(([rRes, resRes]) => {
                const rCount = parseInt(rRes.headers.get('content-range')?.split('/')[1] || '0');
                const resCount = parseInt(resRes.headers.get('content-range')?.split('/')[1] || '0');
                if (rCount > 0) {
                    const dot = document.createElement('div');
                    dot.className = 'adm-mini-dot';
                    document.getElementById('admMiniReports')?.appendChild(dot);
                }
                if (resCount > 0) {
                    const dot = document.createElement('div');
                    dot.className = 'adm-mini-dot';
                    dot.style.background = '#f4a261';
                    document.getElementById('admMiniResources')?.appendChild(dot);
                }
            }).catch(() => {});
        }

        /* ── Exit handler ── */
        window._admExit = function(e) {
            e.preventDefault();
            sessionStorage.removeItem('admViewAsUser');
            sessionStorage.removeItem('admDashboardUrl');
            sessionStorage.removeItem('admReportsUrl');
            sessionStorage.removeItem('admResourcesUrl');
            sessionStorage.removeItem('admLogsUrl');
            sessionStorage.removeItem('admUsersUrl');
            sessionStorage.removeItem('admSettingsUrl');
            sessionStorage.removeItem('admSbUrl');
            sessionStorage.removeItem('admSbKey');
            window.location.href = dashUrl;
        };
    })();
</script>
