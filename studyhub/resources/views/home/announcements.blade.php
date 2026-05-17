<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements – StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
    <style>
        /* ── SHELL ── */
        .ann-shell {
            max-width: 780px;
            margin: 0 auto;
            padding: 32px 24px 60px;
        }

        /* ── HEADER ── */
        .ann-header {
            display: flex; align-items: center; gap: 14px;
            margin-bottom: 28px;
        }
        .ann-header-icon {
            width: 46px; height: 46px;
            background: linear-gradient(135deg, var(--primary, #1a5f7a), #2a9d8f);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(26,95,122,0.25);
        }
        .ann-header-icon svg { width: 22px; height: 22px; }
        .ann-header-text {}
        .ann-page-title {
            font-family: 'Crimson Pro', serif;
            font-size: 26px; font-weight: 700;
            color: var(--text-primary, #1a1a1a);
            margin: 0;
        }
        .ann-page-sub {
            font-size: 13px; color: var(--text-light, #9ca3af);
            margin-top: 2px;
        }

        /* ── FILTER TABS ── */
        .ann-tabs {
            display: flex; gap: 6px; margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .ann-tab {
            padding: 6px 16px; border-radius: 99px;
            font-size: 13px; font-weight: 600;
            border: 1.5px solid var(--border, #e5e7eb);
            background: var(--bg-card, #fff);
            color: var(--text-secondary, #374151);
            cursor: pointer; transition: all 0.18s;
            font-family: 'DM Sans', sans-serif;
        }
        .ann-tab:hover { border-color: var(--primary, #1a5f7a); color: var(--primary, #1a5f7a); }
        .ann-tab.active {
            background: var(--primary, #1a5f7a);
            color: #fff; border-color: transparent;
        }

        /* ── CARDS ── */
        .ann-card {
            background: var(--bg-card, #fff);
            border: 1.5px solid var(--border, #e5e7eb);
            border-radius: 16px;
            padding: 22px 24px;
            margin-bottom: 14px;
            transition: box-shadow 0.18s, border-color 0.18s;
            animation: annFadeIn 0.3s ease both;
        }
        .ann-card:hover {
            box-shadow: 0 4px 18px rgba(0,0,0,0.07);
            border-color: color-mix(in srgb, var(--primary,#1a5f7a) 30%, var(--border,#e5e7eb));
        }
        .ann-card.urgent   { border-left: 4px solid #ef4444; }
        .ann-card.important{ border-left: 4px solid #f59e0b; }
        .ann-card.normal   { border-left: 4px solid var(--border, #e5e7eb); }

        .ann-card-header {
            display: flex; align-items: flex-start;
            gap: 10px; margin-bottom: 10px;
        }
        .ann-card-title {
            font-size: 16px; font-weight: 700;
            color: var(--text-primary, #1a1a1a);
            flex: 1; line-height: 1.35;
        }
        .ann-badge {
            font-size: 10px; font-weight: 700;
            padding: 3px 10px; border-radius: 99px;
            flex-shrink: 0; white-space: nowrap;
        }
        .ann-badge.urgent    { color: #ef4444; background: #fef2f2; }
        .ann-badge.important { color: #d97706; background: #fffbeb; }
        .ann-badge.normal    { color: #6b7280; background: #f3f4f6; }

        .ann-card-body {
            font-size: 14px; color: var(--text-secondary, #374151);
            line-height: 1.7; margin-bottom: 12px;
            white-space: pre-wrap;
        }

        /* ── FILE ATTACHMENTS ── */
        .ann-files-label {
            font-size: 12px; font-weight: 700;
            color: var(--text-light, #9ca3af);
            text-transform: uppercase; letter-spacing: .05em;
            margin-bottom: 8px;
        }
        .ann-files-grid {
            display: flex; flex-wrap: wrap; gap: 8px;
            margin-bottom: 12px;
        }
        .ann-file-chip {
            display: inline-flex; align-items: center; gap: 7px;
            background: var(--bg-hover, rgba(0,0,0,0.04));
            border: 1.5px solid var(--border, #e5e7eb);
            border-radius: 10px;
            padding: 8px 14px;
            font-size: 13px; font-weight: 600;
            color: var(--primary, #1a5f7a);
            text-decoration: none;
            transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
            max-width: 240px;
        }
        .ann-file-chip:hover {
            background: color-mix(in srgb, var(--primary, #1a5f7a) 8%, transparent);
            border-color: var(--primary, #1a5f7a);
            box-shadow: 0 2px 8px rgba(26,95,122,0.12);
        }
        .ann-file-chip-icon { font-size: 18px; flex-shrink: 0; }
        .ann-file-chip-info { min-width: 0; }
        .ann-file-chip-name {
            display: block; overflow: hidden;
            text-overflow: ellipsis; white-space: nowrap;
        }
        .ann-file-chip-size {
            display: block; font-size: 10px;
            font-weight: 400; color: var(--text-light, #9ca3af);
        }
        .ann-file-chip-dl {
            margin-left: auto; flex-shrink: 0;
            color: var(--text-light, #9ca3af);
            font-size: 14px;
        }

        /* ── META ── */
        .ann-card-meta {
            font-size: 12px; color: var(--text-light, #9ca3af);
            display: flex; align-items: center; gap: 10px;
            flex-wrap: wrap;
        }
        .ann-card-meta-dot { opacity: .4; }

        /* ── EMPTY / SKELETON ── */
        .ann-empty {
            text-align: center; padding: 64px 0;
            color: var(--text-light, #9ca3af); font-size: 15px;
        }
        .ann-empty-icon { font-size: 48px; margin-bottom: 12px; }
        .ann-skel {
            background: var(--bg-hover, #f3f4f6);
            border-radius: 16px; margin-bottom: 14px;
            animation: annPulse 1.4s ease-in-out infinite;
        }
        @keyframes annPulse { 0%,100%{opacity:1} 50%{opacity:.45} }
        @keyframes annFadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }
    </style>
</head>
<body>

@include('layouts.sidebar')

<main class="main-content-simple">
    <div class="ann-shell">

        {{-- ── PAGE HEADER ── --}}
        <div class="ann-header">
            <div class="ann-header-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 11l19-9-9 19-2-8-8-2z"/>
                </svg>
            </div>
            <div class="ann-header-text">
                <div class="ann-page-title">Announcements</div>
                <div class="ann-page-sub" id="annSubtitle">Loading…</div>
            </div>
        </div>

        {{-- ── FILTER TABS ── --}}
        <div class="ann-tabs">
            <button class="ann-tab active" onclick="setFilter('all', this)">All</button>
            <button class="ann-tab" onclick="setFilter('urgent', this)">🚨 Urgent</button>
            <button class="ann-tab" onclick="setFilter('important', this)">⚠️ Important</button>
            <button class="ann-tab" onclick="setFilter('normal', this)">📢 Normal</button>
        </div>

        {{-- ── LIST ── --}}
        <div id="annList">
            {{-- Skeleton placeholders while loading --}}
            <div class="ann-skel" style="height:120px;"></div>
            <div class="ann-skel" style="height:100px;opacity:.7;"></div>
            <div class="ann-skel" style="height:100px;opacity:.45;"></div>
        </div>

    </div>
</main>

<script>
    const SB_URL  = '{{ $supabaseUrl }}';
    const SB_ANON = '{{ $supabaseAnonKey }}';
    const UID     = '{{ $userId }}';

    // Pre-loaded from PHP (server already fetched + joined files)
    const PRELOADED = @json($announcements);

    let _all    = [];
    let _filter = 'all';

    /* ── HELPERS ── */
    function esc(s) {
        if (s == null) return '';
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function timeAgo(d) {
        const s = Math.floor((Date.now() - new Date(d)) / 1000);
        if (s < 60)    return `${s}s ago`;
        if (s < 3600)  return `${Math.floor(s/60)}m ago`;
        if (s < 86400) return `${Math.floor(s/3600)}h ago`;
        return `${Math.floor(s/86400)}d ago`;
    }

    function formatSize(bytes) {
        if (!bytes)            return '';
        if (bytes < 1024)      return bytes + ' B';
        if (bytes < 1048576)   return (bytes/1024).toFixed(1) + ' KB';
        return (bytes/1048576).toFixed(1) + ' MB';
    }

    function fileIcon(name) {
        const ext = (name || '').split('.').pop().toLowerCase();
        const map = {
            pdf:'📄', doc:'📝', docx:'📝', xls:'📊', xlsx:'📊',
            ppt:'📋', pptx:'📋', png:'🖼', jpg:'🖼', jpeg:'🖼',
            gif:'🖼', zip:'🗜', rar:'🗜', txt:'📃',
        };
        return map[ext] ?? '📎';
    }

    /* ── FILTER ── */
    function setFilter(f, btn) {
        _filter = f;
        document.querySelectorAll('.ann-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        render();
    }

    function filtered() {
        if (_filter === 'all') return _all;
        return _all.filter(a => (a.priority || 'normal') === _filter);
    }

    /* ── RENDER ── */
    function render() {
        const list  = document.getElementById('annList');
        const items = filtered();

        document.getElementById('annSubtitle').textContent =
            `${items.length} announcement${items.length !== 1 ? 's' : ''}` +
            (_filter !== 'all' ? ` · filtered by ${_filter}` : '');

        if (!items.length) {
            list.innerHTML = `
                <div class="ann-empty">
                    <div class="ann-empty-icon">📭</div>
                    <div>${_filter === 'all' ? 'No announcements right now. Check back later!' : 'No ' + _filter + ' announcements.'}</div>
                </div>`;
            return;
        }

        list.innerHTML = items.map((a, i) => {
            const priority    = a.priority || 'normal';
            const badgeLabel  = priority.charAt(0).toUpperCase() + priority.slice(1);
            const files       = Array.isArray(a.files) ? a.files : [];
            const dateStr     = new Date(a.created_at).toLocaleDateString('en-US', {
                month:'long', day:'numeric', year:'numeric'
            });

            const filesHtml = files.length ? `
                <div class="ann-files-label">📎 Attachments</div>
                <div class="ann-files-grid">
                    ${files.map(f => `
                        <a class="ann-file-chip"
                           href="${esc(f.file_url)}"
                           target="_blank" rel="noopener"
                           download="${esc(f.file_name)}">
                            <span class="ann-file-chip-icon">${fileIcon(f.file_name)}</span>
                            <span class="ann-file-chip-info">
                                <span class="ann-file-chip-name">${esc(f.file_name)}</span>
                                ${f.file_size ? `<span class="ann-file-chip-size">${formatSize(f.file_size)}</span>` : ''}
                            </span>
                            <span class="ann-file-chip-dl">⬇</span>
                        </a>`).join('')}
                </div>` : '';

            return `
            <div class="ann-card ${esc(priority)}"
                 style="animation-delay:${Math.min(i * 50, 400)}ms">
                <div class="ann-card-header">
                    <div class="ann-card-title">${esc(a.title)}</div>
                    <span class="ann-badge ${esc(priority)}">${esc(badgeLabel)}</span>
                </div>
                <div class="ann-card-body">${esc(a.body)}</div>
                ${filesHtml}
                <div class="ann-card-meta">
                    <span>${dateStr}</span>
                    <span class="ann-card-meta-dot">·</span>
                    <span>${timeAgo(a.created_at)}</span>
                    ${files.length ? `<span class="ann-card-meta-dot">·</span><span>📎 ${files.length} file${files.length !== 1 ? 's' : ''}</span>` : ''}
                </div>
            </div>`;
        }).join('');
    }

    /* ── INIT ── */
    function init() {
        _all = PRELOADED || [];

        // Sort: urgent first, then by date desc
        _all.sort((a, b) => {
            const order = { urgent: 0, important: 1, normal: 2 };
            const pa = order[a.priority] ?? 2;
            const pb = order[b.priority] ?? 2;
            if (pa !== pb) return pa - pb;
            return new Date(b.created_at) - new Date(a.created_at);
        });

        render();
    }

    document.addEventListener('DOMContentLoaded', init);
</script>

</body>
</html>
