<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications – StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
    <link rel="stylesheet" href="{{ asset('css/notifications.css') }}">
</head>

<body>

    @include('layouts.sidebar')

    <main class="main-content-simple">
        <div class="notif-shell">

            {{-- ── PAGE HEADER ──────────────────────────────────────── --}}
            <div class="notif-page-header">
                <div class="notif-page-title-wrap">
                    <div class="notif-page-title">
                        <div class="notif-page-title-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                            </svg>
                        </div>
                        Notifications
                        <span class="notif-unread-pill" id="topPill"></span>
                    </div>
                    <div class="notif-page-subtitle" id="pageSubtitle">Loading your notifications…</div>
                </div>

                <div class="notif-top-actions">
                    <button class="nb primary" onclick="markAllRead()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        Mark all read
                    </button>
                    <button class="nb danger" onclick="clearAllRead()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6" />
                            <path d="M19 6l-1 14H6L5 6" />
                            <path d="M10 11v6M14 11v6" />
                            <path d="M9 6V4h6v2" />
                        </svg>
                        Clear read
                    </button>
                </div>
            </div>

            {{-- ── STATS BAR ────────────────────────────────────────── --}}
            <div class="notif-stats-bar">
                <div class="stat-chip">
                    <div class="stat-chip-icon gray">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="3" />
                            <path d="M3 9h18" />
                            <path d="M9 21V9" />
                        </svg>
                    </div>
                    <div class="stat-chip-info">
                        <div class="stat-chip-num" id="sTotal">—</div>
                        <div class="stat-chip-label">Total</div>
                    </div>
                </div>
                <div class="stat-chip">
                    <div class="stat-chip-icon blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M12 8v4M12 16h.01" />
                        </svg>
                    </div>
                    <div class="stat-chip-info">
                        <div class="stat-chip-num" id="sUnread">—</div>
                        <div class="stat-chip-label">Unread</div>
                    </div>
                </div>
                <div class="stat-chip">
                    <div class="stat-chip-icon red">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path
                                d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                            <line x1="12" y1="9" x2="12" y2="13" />
                            <line x1="12" y1="17" x2="12.01" y2="17" />
                        </svg>
                    </div>
                    <div class="stat-chip-info">
                        <div class="stat-chip-num" id="sUrgent">—</div>
                        <div class="stat-chip-label">Urgent</div>
                    </div>
                </div>
                <div class="stat-chip">
                    <div class="stat-chip-icon green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 11 12 14 22 4" />
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
                        </svg>
                    </div>
                    <div class="stat-chip-info">
                        <div class="stat-chip-num" id="sRead">—</div>
                        <div class="stat-chip-label">Read</div>
                    </div>
                </div>
            </div>

            {{-- ── BODY ─────────────────────────────────────────────── --}}
            <div class="notif-body">

                {{-- LEFT: Filter panel --}}
                <div class="notif-left">
                    <div class="notif-filter-head">Filter by</div>
                    <div class="notif-filter-list">
                        <button class="flt-btn active" onclick="setFilter('all', this)">
                            <div class="flt-left">
                                <span class="flt-icon">📋</span>
                                All
                            </div>
                            <span class="flt-count" id="fc-all">0</span>
                        </button>
                        <button class="flt-btn" onclick="setFilter('unread', this)">
                            <div class="flt-left">
                                <span class="flt-icon">🔵</span>
                                Unread
                            </div>
                            <span class="flt-count" id="fc-unread">0</span>
                        </button>
                        <button class="flt-btn" onclick="setFilter('urgent', this)">
                            <div class="flt-left">
                                <span class="flt-icon">🚨</span>
                                Urgent
                            </div>
                            <span class="flt-count" id="fc-urgent">0</span>
                        </button>
                        <button class="flt-btn" onclick="setFilter('event', this)">
                            <div class="flt-left">
                                <span class="flt-icon">📅</span>
                                Events
                            </div>
                            <span class="flt-count" id="fc-event">0</span>
                        </button>
                        <button class="flt-btn" onclick="setFilter('task', this)">
                            <div class="flt-left">
                                <span class="flt-icon">✅</span>
                                Tasks
                            </div>
                            <span class="flt-count" id="fc-task">0</span>
                        </button>
                        <button class="flt-btn" onclick="setFilter('announcement', this)">
                            <div class="flt-left">
                                <span class="flt-icon">📣</span>
                                Announcements
                            </div>
                            <span class="flt-count" id="fc-announcement">0</span>
                        </button>
                    </div>
                </div>

                {{-- RIGHT: Notification list --}}
                <div class="notif-right">
                    <div class="notif-list-head">
                        <div>
                            <div class="notif-list-head-title" id="listTitle">All Notifications</div>
                            <div class="notif-list-head-sub" id="listSub">Loading…</div>
                        </div>
                    </div>

                    <div id="notifList">
                        {{-- Skeleton placeholders while loading --}}
                        @for ($i = 0; $i < 6; $i++)
                            <div class="skel-row">
                                <div class="skel"
                                    style="width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;"></div>
                                <div
                                    style="flex: 1; display: flex; flex-direction: column; gap: 8px; padding-top: 3px;">
                                    <div class="skel" style="height: 11px; width: 52%;"></div>
                                    <div class="skel" style="height: 10px; width: 34%;"></div>
                                    <div class="skel" style="height: 9px; width: 20%;"></div>
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>

            </div>
        </div>
    </main>

    @include('layouts.admin_bar')

    <script>
        const SB_URL  = '{{ config('services.supabase.url') }}';
        const SB_ANON = '{{ config('services.supabase.anon_key') }}';
        const SB_SVC  = '{{ config('services.supabase.service_key') }}';
        const UID     = '{{ session('user_id') }}';
        const TABLE   = 'notifications';

        let _all    = [];
        let _filter = 'all';

        const FILTER_TITLES = {
            all:          'All Notifications',
            unread:       'Unread',
            urgent:       'Urgent',
            event:        'Events',
            task:         'Tasks',
            announcement: 'Announcements',
        };

        /* ── ICON per notification_type ─────────────────────────── */
        function notifIcon(type) {
            const map = {
                announcement: '📣',
                urgent:       '🚨',
                event:        '📅',
                task:         '✅',
                friend:       '👋',
                message:      '💬',
                report:       '⚠️',
            };
            return map[type] ?? '🔔';
        }

        /* ── HEADERS ────────────────────────────────────────────── */
        function hdrs(write = false) {
            const key = (write && SB_SVC) ? SB_SVC : SB_ANON;
            return {
                apikey:           key,
                Authorization:    `Bearer ${key}`,
                'Content-Type':   'application/json',
                'Accept-Profile': 'public',
                'Content-Profile':'public',
            };
        }

        /* ── FETCH WITH TIMEOUT ─────────────────────────────────── */
        function fetchWithTimeout(url, options = {}, ms = 8000) {
            const controller = new AbortController();
            const timer = setTimeout(() => controller.abort(), ms);
            return fetch(url, { ...options, signal: controller.signal })
                .finally(() => clearTimeout(timer));
        }

        /* ── SHOW ERROR ─────────────────────────────────────────── */
        function showError(msg) {
            document.getElementById('notifList').innerHTML = `
                <div class="notif-empty">
                    <div class="notif-empty-icon">⚠️</div>
                    <h2>Could not load</h2>
                    <p>${esc(msg)}</p>
                </div>`;
            document.getElementById('pageSubtitle').textContent = 'Something went wrong.';
            document.getElementById('listSub').textContent = '';
        }

        /* ── LOAD ───────────────────────────────────────────────── */
        async function load() {
            if (!SB_URL || !SB_ANON) {
                showError('Supabase is not configured. Check your .env / services.php.');
                return;
            }
            if (!UID) {
                showError('No user session found. Please log in again.');
                document.getElementById('pageSubtitle').textContent = 'Not logged in.';
                return;
            }

            try {
                const res = await fetchWithTimeout(
                    // FIX #4: include `priority` in the select so it arrives in every row
                    `${SB_URL}/rest/v1/${TABLE}?user_id=eq.${UID}&order=created_at.desc&limit=200` +
                    `&select=*`,
                    { headers: hdrs() }
                );
                if (!res.ok) {
                    const text = await res.text();
                    throw new Error(`HTTP ${res.status}: ${text}`);
                }
                _all = await res.json();
            } catch (e) {
                if (e.name === 'AbortError') {
                    showError('Request timed out. Check your Supabase URL and network connection.');
                } else {
                    showError(e.message);
                }
                return;
            }

            updateCounts();
            render();
        }

        /* ── COUNTS ─────────────────────────────────────────────── */
        function updateCounts() {
            const unread = _all.filter(n => !(n.read || n.is_read)).length;

            const urgent = _all.filter(n =>
                n.priority === 'urgent' ||
                n.urgency === 'urgent'
            ).length;

            const read = _all.filter(n =>
                (n.read || n.is_read)
            ).length;

            const ann = _all.filter(n =>
                n.notification_type === 'announcement' ||
                n.source_type === 'announcement'
            ).length;

            document.getElementById('sTotal').textContent  = _all.length;
            document.getElementById('sUnread').textContent = unread;
            document.getElementById('sUrgent').textContent = urgent;
            document.getElementById('sRead').textContent   = read;

            const pill = document.getElementById('topPill');

            pill.textContent   = unread;
            pill.style.display = unread > 0 ? 'inline' : 'none';

            document.getElementById('pageSubtitle').textContent =
                unread > 0
                    ? `You have ${unread} unread notification${unread !== 1 ? 's' : ''}`
                    : "You're all caught up!";

            document.getElementById('fc-all').textContent    = _all.length;
            document.getElementById('fc-unread').textContent = unread;
            document.getElementById('fc-urgent').textContent = urgent;

            document.getElementById('fc-event').textContent =
                _all.filter(n =>
                    n.notification_type === 'event'
                ).length;

            document.getElementById('fc-task').textContent =
                _all.filter(n =>
                    n.notification_type === 'task'
                ).length;

            document.getElementById('fc-announcement').textContent = ann;
        }

        /* ── FILTER ─────────────────────────────────────────────── */
        function setFilter(f, btn) {
            _filter = f;
            document.querySelectorAll('.flt-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('listTitle').textContent = FILTER_TITLES[f] ?? f;
            render();
        }

        function filtered() {

            if (_filter === 'unread') {
                return _all.filter(n => !(n.read || n.is_read));
            }

            if (_filter === 'urgent') {
                return _all.filter(n =>
                    n.priority === 'urgent' ||
                    n.urgency === 'urgent'
                );
            }

            if (_filter === 'event') {
                return _all.filter(n =>
                    n.notification_type === 'event'
                );
            }

            if (_filter === 'task') {
                return _all.filter(n =>
                    n.notification_type === 'task'
                );
            }

            if (_filter === 'announcement') {
                return _all.filter(n =>
                    n.notification_type === 'announcement' ||
                    n.source_type === 'announcement'
                );
            }

            return _all;
        }

        /* ── RENDER ─────────────────────────────────────────────── */
        function render() {
            const list  = document.getElementById('notifList');
            const items = filtered();
            document.getElementById('listSub').textContent =
                `${items.length} notification${items.length !== 1 ? 's' : ''}`;

            if (!items.length) {
                list.innerHTML = `
                    <div class="notif-empty">
                        <div class="notif-empty-icon">🎉</div>
                        <h2>All caught up!</h2>
                        <p>${_filter === 'all' ? 'No notifications yet.' : 'Nothing matches this filter.'}</p>
                    </div>`;
                return;
            }

            const groups = {};
            items.forEach(n => {
                const k = groupLabel(new Date(n.created_at));
                (groups[k] = groups[k] ?? []).push(n);
            });

            let html = '';
            let idx  = 0;
            for (const [label, notifs] of Object.entries(groups)) {
                html += `<div class="ng-label">${esc(label)}</div>`;
                notifs.forEach(n => { html += rowHTML(n, idx++); });
            }
            list.innerHTML = html;
        }

        function rowHTML(n, i) {

            const ago    = timeAgo(new Date(n.created_at));
            const unread = !(n.read || n.is_read);

            const level =
                (n.priority || n.urgency || 'normal')
                .toLowerCase();

            const urgent = level === 'urgent';
            const important = level === 'important';

            const icon = notifIcon(
                n.notification_type ||
                n.source_type ||
                'announcement'
            );

            let priorityClass = 'normal';

            if (urgent) {
                priorityClass = 'urgent';
            }
            else if (important) {
                priorityClass = 'important';
            }

            const readClass = unread ? 'unread' : 'read';

            let tagUrgent = '';

            if (urgent) {
                tagUrgent = `<span class="nr-tag urgent">Urgent</span>`;
            }
            else if (important) {
                tagUrgent = `<span class="nr-tag important">Important</span>`;
            }
            else {
                tagUrgent = `<span class="nr-tag normal">Normal</span>`;
            }

            const tagType = n.notification_type
                ? `<span class="nr-tag ${esc(n.notification_type)}">
                    ${esc(n.notification_type)}
                </span>`
                : '';

            const udot = unread
                ? `<div class="udot"></div>`
                : '';

            // ✨ CHECK IF ANNOUNCEMENT
            const isAnnouncement =
                n.notification_type === 'announcement' ||
                n.source_type === 'announcement';

            // ✨ REDIRECT URL
            const redirectUrl = isAnnouncement
                ? `/announcements?open=${n.related_id}`
                : '#';

            return `

            ${isAnnouncement ? `
            <a href="${redirectUrl}"
            class="notif-link"
            onclick="markRead('${n.id}')">
            ` : ''}

            <div class="nr ${readClass} ${priorityClass}"
                id="nr-${n.id}"
                style="animation-delay: ${Math.min(i * 25, 300)}ms"
                ${!isAnnouncement
                    ? `onclick="markRead('${n.id}')"`
                    : ''
                }>

                <div class="nr-icon-wrap">
                    ${icon}
                </div>

                <div class="nr-body">

                    <div class="nr-title">
                        ${esc(n.title)}
                    </div>

                    ${n.message
                        ? `
                            <div class="nr-sub">
                                ${esc(n.message)}
                            </div>
                        `
                        : ''
                    }

                    <div class="nr-meta">

                        <span class="nr-time">
                            ${ago}
                        </span>

                        ${tagUrgent}

                        ${tagType}

                    </div>

                </div>

                <div class="nr-right">

                    ${udot}

                    <button
                        class="nr-del"
                        onclick="event.stopPropagation();
                                deleteOne('${n.id}')"
                        title="Delete notification"
                    >
                        🗑️
                    </button>

                </div>

            </div>

            ${isAnnouncement ? `</a>` : ''}

            `;
        }

        /* ── ACTIONS ────────────────────────────────────────────── */
        async function markRead(id) {
            const n = _all.find(x => x.id === id);
            if (!n || n.is_read) return;
            n.is_read = true;
            const row = document.getElementById('nr-' + id);
            if (row) {
                row.classList.replace('unread', 'read');
                row.querySelector('.udot')?.remove();
                row.querySelector('.nr-icon-wrap')?.classList.remove('unread');
            }
            updateCounts();
            await fetch(`${SB_URL}/rest/v1/${TABLE}?id=eq.${id}`, {
                method:  'PATCH',
                headers: hdrs(true),
                body:    JSON.stringify({ is_read: true }),
            });
        }

        async function markAllRead() {
            _all.forEach(n => (n.is_read = true));
            render();
            updateCounts();
            await fetch(`${SB_URL}/rest/v1/${TABLE}?user_id=eq.${UID}&is_read=eq.false`, {
                method:  'PATCH',
                headers: hdrs(true),
                body:    JSON.stringify({ is_read: true }),
            });
        }

        async function deleteOne(id) {
            _all = _all.filter(n => n.id !== id);
            render();
            updateCounts();
            await fetch(`${SB_URL}/rest/v1/${TABLE}?id=eq.${id}`, {
                method:  'DELETE',
                headers: hdrs(true),
            });
        }

        async function clearAllRead() {
            if (!confirm('Delete all read notifications?')) return;
            const ids = _all.filter(n => n.is_read).map(n => n.id);
            if (!ids.length) return;
            _all = _all.filter(n => !n.is_read);
            render();
            updateCounts();
            await fetch(
                `${SB_URL}/rest/v1/${TABLE}?id=in.(${ids.map(i => `"${i}"`).join(',')})`, {
                    method:  'DELETE',
                    headers: hdrs(true),
                }
            );
        }

        /* ── HELPERS ────────────────────────────────────────────── */
        function groupLabel(d) {
            const now   = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const dd    = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            const diff  = Math.round((today - dd) / 86400000);
            if (diff === 0) return 'Today';
            if (diff === 1) return 'Yesterday';
            if (diff < 7)  return `${diff} days ago`;
            return d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        }

        function timeAgo(d) {
            const s = Math.floor((Date.now() - d) / 1000);
            if (s < 10)    return 'just now';
            if (s < 60)    return `${s}s ago`;
            if (s < 3600)  return `${Math.floor(s / 60)}m ago`;
            if (s < 86400) return `${Math.floor(s / 3600)}h ago`;
            return `${Math.floor(s / 86400)}d ago`;
        }

        function esc(s) {
            if (s == null) return '';
            const d = document.createElement('div');
            d.textContent = String(s);
            return d.innerHTML;
        }

        document.addEventListener('DOMContentLoaded', load);
    </script>


@include('layouts.admin_bar')

<script>
    window.UID = @json(session('user_id'));
    window.SB_URL = @json(config('services.supabase.url'));
    window.SB_ANON = @json(config('services.supabase.anon_key'));
    window.SB_SVC = @json(config('services.supabase.service_key'));
</script>

<script src="{{ asset('js/notifications.js') }}"></script>

</body>

</html>
