<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
    <style>
        /* ── Settings layout — identical to registered ── */
        .settings-layout { display:flex; width:100%; min-height:calc(100vh - 60px); }

        /* Left sub-nav */
        .settings-subnav { width:220px; flex-shrink:0; padding:24px 12px; border-right:1px solid var(--border); }
        .settings-nav-group {
            font-size:10px; font-weight:700; letter-spacing:0.08em;
            text-transform:uppercase; color:var(--text-light);
            padding:0 12px; margin:18px 0 6px;
        }
        .settings-nav-group:first-child { margin-top:4px; }
        .settings-nav-item {
            display:flex; align-items:center; gap:10px; padding:9px 12px;
            border-radius:10px; font-size:14px; font-weight:500;
            color:var(--text-secondary); text-decoration:none; transition:all 0.15s;
            cursor:pointer; background:none; border:none; width:100%;
            text-align:left; font-family:inherit; margin-bottom:2px;
        }
        .settings-nav-item:hover  { background:var(--bg-hover); color:var(--text-primary); }
        .settings-nav-item.active { background:var(--bg-hover); color:var(--text-primary); font-weight:600; }
        .settings-nav-item.locked { opacity:0.4; cursor:not-allowed; }
        .settings-nav-item svg    { width:17px; height:17px; flex-shrink:0; }
        .settings-nav-item.danger { color:#ef4444; }
        .settings-nav-item.danger:hover { background:rgba(239,68,68,0.06); color:#dc2626; }

        /* Main content */
        .settings-content { flex:1; padding:28px 36px; max-width:860px; }
        .settings-page { display:none; }
        .settings-page.active { display:block; }

        /* Section cards */
        .settings-card { background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:24px; margin-bottom:20px; }
        .settings-card-title { font-size:15px; font-weight:700; color:var(--text-primary); margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--border); }
        .settings-row { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:12px 0; border-bottom:1px solid var(--border); }
        .settings-row:last-child { border-bottom:none; padding-bottom:0; }
        .settings-row-label { font-size:14px; font-weight:600; color:var(--text-primary); }
        .settings-row-desc  { font-size:12px; color:var(--text-secondary); margin-top:2px; line-height:1.4; }

        /* Theme cards */
        .theme-cards { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:4px; }
        .theme-card { border:2px solid var(--border); border-radius:14px; overflow:hidden; cursor:pointer; transition:border-color 0.18s; position:relative; }
        .theme-card.selected { border-color:var(--primary,#1a5f7a); }
        .theme-card-preview { height:72px; position:relative; overflow:hidden; }
        .theme-card-label   { padding:10px 12px 12px; background:var(--bg-card); }
        .theme-card-name    { font-size:13px; font-weight:700; color:var(--text-primary); }
        .theme-card-sub     { font-size:11px; color:var(--text-secondary); margin-top:2px; }
        .theme-card-radio   { position:absolute; bottom:12px; right:12px; width:18px; height:18px; border-radius:50%; border:2px solid var(--border); background:var(--bg-card); display:flex; align-items:center; justify-content:center; transition:all 0.18s; }
        .theme-card.selected .theme-card-radio { background:var(--primary,#1a5f7a); border-color:var(--primary,#1a5f7a); }
        .theme-card.selected .theme-card-radio::after { content:''; width:6px; height:6px; border-radius:50%; background:white; }

        .tp-light { background:#f5f6fa; }
        .tp-light .tp-bar  { height:10px; background:#fff; border-bottom:1px solid #e5e7eb; margin-bottom:8px; }
        .tp-light .tp-card { height:36px; background:#fff; border:1px solid #e5e7eb; border-radius:6px; margin:0 8px; }
        .tp-dark  { background:#1a1a2e; }
        .tp-dark .tp-bar  { height:10px; background:#16213e; border-bottom:1px solid #0f3460; margin-bottom:8px; }
        .tp-dark .tp-card { height:36px; background:#16213e; border:1px solid #0f3460; border-radius:6px; margin:0 8px; }
        .tp-auto  { background:linear-gradient(90deg,#f5f6fa 50%,#1a1a2e 50%); }
        .tp-auto .tp-bar  { height:10px; background:linear-gradient(90deg,#fff 50%,#16213e 50%); border-bottom:1px solid #e5e7eb; margin-bottom:8px; }
        .tp-auto .tp-card { height:36px; background:linear-gradient(90deg,#fff 50%,#16213e 50%); border:1px solid #e5e7eb; border-radius:6px; margin:0 8px; }

        /* Accent swatches */
        .accent-swatches { display:flex; gap:10px; flex-wrap:wrap; }
        .accent-swatch { width:34px; height:34px; border-radius:50%; cursor:pointer; border:3px solid transparent; transition:all 0.18s; box-shadow:0 0 0 2px transparent; }
        .accent-swatch:hover { transform:scale(1.1); }
        .accent-swatch.selected { box-shadow:0 0 0 3px var(--bg-card), 0 0 0 5px currentColor; }

        /* Font size control */
        .font-size-control { display:flex; align-items:center; gap:12px; }
        .fs-btn { width:32px; height:32px; border-radius:8px; border:1.5px solid var(--border); background:var(--bg-card); color:var(--text-primary); font-size:18px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.18s; }
        .fs-btn:hover { border-color:var(--primary); color:var(--primary); }
        .fs-value { font-size:15px; font-weight:700; color:var(--text-primary); min-width:36px; text-align:center; }

        /* Toggle */
        .toggle { position:relative; display:inline-block; width:44px; height:24px; }
        .toggle input { opacity:0; width:0; height:0; }
        .toggle-slider { position:absolute; cursor:pointer; inset:0; background:var(--border); border-radius:24px; transition:0.25s; }
        .toggle-slider::before { content:''; position:absolute; width:18px; height:18px; border-radius:50%; background:white; left:3px; bottom:3px; transition:0.25s; box-shadow:0 1px 3px rgba(0,0,0,0.15); }
        input:checked + .toggle-slider { background:var(--primary); }
        input:checked + .toggle-slider::before { transform:translateX(20px); }

        /* Right panel */
        .settings-right { width:240px; flex-shrink:0; padding:28px 20px 28px 0; }
        .settings-widget { background:var(--bg-card); border:1px solid var(--border); border-radius:14px; padding:16px 18px; margin-bottom:14px; }
        .settings-widget-label { font-size:10px; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:var(--text-light); margin-bottom:10px; }
        .settings-widget a { display:block; font-size:13px; color:var(--text-secondary); text-decoration:none; padding:5px 0; transition:color 0.15s; }
        .settings-widget a:hover { color:var(--primary); }
    </style>
</head>
<body>

@include('layouts.guest-sidebar', ['guestNav' => 'settings'])

<main class="main-content" style="padding:0;display:flex;align-items:flex-start;">

    <div class="settings-layout">

        {{-- Left sub-nav --}}
        <div class="settings-subnav">

            <div class="settings-nav-group">General</div>
            <button class="settings-nav-item active" onclick="showPage('about', this)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                About
            </button>
            <button class="settings-nav-item" onclick="showPage('theme', this)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 2a10 10 0 000 20 5 5 0 000-10"/>
                </svg>
                Theme
            </button>

            <div class="settings-nav-group">Preferences</div>
            <button class="settings-nav-item locked" onclick="showLockedModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
                Notifications
            </button>

            <div class="settings-nav-group">Account</div>
            <button class="settings-nav-item locked" onclick="showLockedModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                Account
            </button>
            <button class="settings-nav-item locked" onclick="showLockedModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2"/>
                    <path d="M7 11V7a5 5 0 0110 0v4"/>
                </svg>
                Privacy
            </button>
            <button class="settings-nav-item locked" onclick="showLockedModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Edit Profile
            </button>

            <div class="settings-nav-group">Session</div>
            <a href="{{ route('login') }}" class="settings-nav-item danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                Leave Guest Mode
            </a>

        </div>

        {{-- Settings content --}}
        <div class="settings-content">

            {{-- About page --}}
            <div id="page-about" class="settings-page active">
                <h2 style="font-family:'Crimson Pro',serif;font-size:26px;font-weight:700;color:var(--text-primary);margin-bottom:4px;">About StudyHub</h2>
                <p style="font-size:14px;color:var(--text-secondary);margin-bottom:24px;">Learn more about the platform and the people behind it</p>

                <div class="settings-card">
                    <p style="font-size:14px;color:var(--text-secondary);line-height:1.7;">
                        StudyHub is a web-based platform that helps students organise their academic life, manage study schedules, track tasks and deadlines, and collaborate with peers. It brings together essential tools like a calendar planner, task manager, study groups, and a shared resource library into one centralised environment to improve productivity and learning efficiency.
                    </p>
                </div>

                <div style="background:linear-gradient(135deg,var(--primary,#1a5f7a),#144d61);border-radius:16px;padding:28px;color:white;margin-bottom:20px;text-align:center;">
                    <h3 style="font-family:'Crimson Pro',serif;font-size:20px;font-weight:700;margin-bottom:10px;">
                        Study smarter, stay organised, and succeed together.
                    </h3>
                    <p style="font-size:14px;opacity:0.85;line-height:1.65;max-width:480px;margin:0 auto;">
                        StudyHub aims to empower students by providing a centralised and structured digital study environment where they can manage their academic responsibilities, collaborate with others, and access shared learning resources.
                    </p>
                </div>

                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
                    @foreach([['2,400+','Students'],['340+','Study Groups'],['1,800+','Resources Shared'],['96%','Satisfaction Rate']] as $s)
                    <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:16px;text-align:center;">
                        <div style="font-family:'Crimson Pro',serif;font-size:22px;font-weight:700;color:var(--text-primary);">{{ $s[0] }}</div>
                        <div style="font-size:12px;color:var(--text-secondary);margin-top:3px;">{{ $s[1] }}</div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Theme page — identical to registered user ── --}}
            <div id="page-theme" class="settings-page">
                <h2 style="font-family:'Crimson Pro',serif;font-size:26px;font-weight:700;color:var(--text-primary);margin-bottom:4px;">Theme</h2>
                <p style="font-size:14px;color:var(--text-secondary);margin-bottom:24px;">Customize how StudyHub looks and feels</p>

                {{-- Color Mode --}}
                <div class="settings-card">
                    <div class="settings-card-title">Color Mode</div>
                    <div class="theme-cards">
                        <div class="theme-card" id="tc-light" onclick="selectTheme('light')">
                            <div class="theme-card-preview tp-light">
                                <div class="tp-bar"></div><div class="tp-card"></div>
                            </div>
                            <div class="theme-card-label">
                                <div class="theme-card-name">Light</div>
                                <div class="theme-card-sub">Clean and bright</div>
                                <div class="theme-card-radio"></div>
                            </div>
                        </div>
                        <div class="theme-card" id="tc-dark" onclick="selectTheme('dark')">
                            <div class="theme-card-preview tp-dark">
                                <div class="tp-bar"></div><div class="tp-card"></div>
                            </div>
                            <div class="theme-card-label">
                                <div class="theme-card-name">Dark</div>
                                <div class="theme-card-sub">Easy on the eyes</div>
                                <div class="theme-card-radio"></div>
                            </div>
                        </div>
                        <div class="theme-card" id="tc-auto" onclick="selectTheme('auto')">
                            <div class="theme-card-preview tp-auto">
                                <div class="tp-bar"></div><div class="tp-card"></div>
                            </div>
                            <div class="theme-card-label">
                                <div class="theme-card-name">Auto</div>
                                <div class="theme-card-sub">Follows your device</div>
                                <div class="theme-card-radio"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Accent Color --}}
                <div class="settings-card">
                    <div class="settings-card-title">Accent Color</div>
                    <div class="accent-swatches" id="accentSwatches">
                        @foreach([
                            ['#1a5f7a','Teal (default)'],
                            ['#7c3aed','Purple'],
                            ['#16a34a','Green'],
                            ['#dc2626','Red'],
                            ['#ea580c','Orange'],
                            ['#db2777','Pink'],
                            ['#0891b2','Sky'],
                        ] as [$color, $label])
                        <div class="accent-swatch" title="{{ $label }}"
                             style="background:{{ $color }};color:{{ $color }};"
                             data-color="{{ $color }}"
                             onclick="selectAccent('{{ $color }}', this)">
                        </div>
                        @endforeach
                    </div>
                    <p style="font-size:12px;color:var(--text-secondary);margin-top:12px;">
                        Accent color is applied to active nav items, buttons, and highlights.
                    </p>
                </div>

                {{-- Text Size --}}
                <div class="settings-card">
                    <div class="settings-card-title">Text Size</div>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Interface font size</div>
                            <div class="settings-row-desc">Adjusts text size across the platform</div>
                        </div>
                        <div class="font-size-control">
                            <button class="fs-btn" onclick="changeFontSize(-1)">−</button>
                            <span class="fs-value" id="fsValue">16px</span>
                            <button class="fs-btn" onclick="changeFontSize(1)">+</button>
                        </div>
                    </div>
                </div>

                {{-- Accessibility --}}
                <div class="settings-card">
                    <div class="settings-card-title">Accessibility</div>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">Reduce motion</div>
                            <div class="settings-row-desc">Minimise animations across the interface</div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" id="reduceMotion" onchange="applyMotion(this.checked)">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="settings-row">
                        <div>
                            <div class="settings-row-label">High contrast</div>
                            <div class="settings-row-desc">Increase contrast for better readability</div>
                        </div>
                        <label class="toggle">
                            <input type="checkbox" id="highContrast" onchange="applyContrast(this.checked)">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

        </div>

        {{-- Right info panel --}}
        <div class="settings-right">
            <div class="settings-widget">
                <div class="settings-widget-label">Platform Info</div>
                <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px;padding:6px 0;border-bottom:1px solid var(--border);">
                    <span style="color:var(--text-secondary);">Version</span>
                    <span style="font-weight:600;background:var(--bg-main);padding:2px 8px;border-radius:6px;font-size:12px;">1.0.0</span>
                </div>
                <div style="padding-top:8px;">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                </div>
            </div>
            <div class="settings-widget">
                <div class="settings-widget-label">Contact</div>
                <a href="#">Support Center</a>
                <a href="#">Send Feedback</a>
            </div>
            <div class="settings-widget">
                <div class="settings-widget-label">Quick Links</div>
                <a href="{{ route('guest.newsfeed') }}">📰 Newsfeed</a>
                <a href="{{ route('guest.resources') }}">📚 Resources</a>
                <a href="{{ route('login') }}" style="color:var(--primary);font-weight:600;">🔑 Log In</a>
                <a href="{{ route('signup') }}" style="color:var(--primary);font-weight:600;">✨ Sign Up</a>
            </div>
        </div>

    </div>
</main>

{{-- Locked modal --}}
<div id="lockedModal"
     style="position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9999;
            display:flex;align-items:center;justify-content:center;
            opacity:0;pointer-events:none;transition:opacity 0.2s;"
     onclick="if(event.target===this)closeLockedModal();">
    <div id="lockedModalBox"
         style="background:var(--bg-card,white);border-radius:20px;padding:32px;
                width:90%;max-width:400px;text-align:center;
                transform:scale(0.95);transition:transform 0.2s;
                box-shadow:0 20px 60px rgba(0,0,0,0.18);">
        <div style="font-size:40px;margin-bottom:14px;">🔒</div>
        <h3 style="font-family:'Crimson Pro',serif;font-size:22px;font-weight:700;margin-bottom:8px;color:var(--text-primary);">Sign in to continue</h3>
        <p style="font-size:14px;color:var(--text-secondary);line-height:1.6;margin-bottom:24px;">
            Create a free account or log in to access notifications, privacy settings, and profile customisation.
        </p>
        <a href="{{ route('signup') }}"
           style="display:block;padding:12px;border-radius:12px;margin-bottom:10px;background:var(--primary,#1a5f7a);color:white;font-size:14px;font-weight:700;text-decoration:none;"
           onmouseover="this.style.opacity='.88';" onmouseout="this.style.opacity='1';">
            Create Free Account
        </a>
        <a href="{{ route('login') }}"
           style="display:block;padding:12px;border-radius:12px;border:1.5px solid var(--border);background:var(--bg-card);font-size:14px;font-weight:600;color:var(--text-primary);text-decoration:none;"
           onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)';"
           onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-primary)';">
            I already have an account
        </a>
        <button onclick="closeLockedModal()"
                style="margin-top:12px;font-size:13px;color:var(--text-light);cursor:pointer;background:none;border:none;font-family:inherit;">
            Maybe later
        </button>
    </div>
</div>

<script>
/* ── Page nav ── */
function showPage(id, btn) {
    document.querySelectorAll('.settings-page').forEach(function(p) { p.classList.remove('active'); });
    document.querySelectorAll('.settings-nav-item').forEach(function(b) { b.classList.remove('active'); });
    var p = document.getElementById('page-' + id);
    if (p) p.classList.add('active');
    if (btn && !btn.classList.contains('locked') && !btn.classList.contains('danger')) btn.classList.add('active');
}

/* ── Locked modal ── */
function showLockedModal() {
    var m = document.getElementById('lockedModal');
    m.style.opacity = '1'; m.style.pointerEvents = 'all';
    document.getElementById('lockedModalBox').style.transform = 'scale(1)';
}
function closeLockedModal() {
    var m = document.getElementById('lockedModal');
    m.style.opacity = '0'; m.style.pointerEvents = 'none';
    document.getElementById('lockedModalBox').style.transform = 'scale(0.95)';
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeLockedModal(); });

/* ── Restore saved preferences on load ── */
(function() {
    var theme  = localStorage.getItem('sh_theme') || 'light';
    var accent = localStorage.getItem('sh_accent') || '#1a5f7a';
    var fs     = parseInt(localStorage.getItem('sh_font_size') || '16', 10);

    selectThemeCard(theme);
    document.getElementById('fsValue').textContent = fs + 'px';
    document.querySelectorAll('.accent-swatch').forEach(function(sw) {
        if (sw.dataset.color === accent) sw.classList.add('selected');
    });
    if (localStorage.getItem('sh_reduce_motion') === 'true') document.getElementById('reduceMotion').checked = true;
    if (localStorage.getItem('sh_high_contrast') === 'true') document.getElementById('highContrast').checked = true;
})();

/* ── Theme ── */
function selectTheme(v) {
    localStorage.setItem('sh_theme', v);
    var dark = v === 'auto' ? window.matchMedia('(prefers-color-scheme: dark)').matches : v === 'dark';
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    selectThemeCard(v);
}
function selectThemeCard(v) {
    ['light','dark','auto'].forEach(function(k) {
        var el = document.getElementById('tc-' + k);
        if (el) el.classList.toggle('selected', k === v);
    });
}

/* ── Accent ── */
function selectAccent(color, el) {
    localStorage.setItem('sh_accent', color);
    document.documentElement.style.setProperty('--primary', color);
    document.querySelectorAll('.accent-swatch').forEach(function(sw) { sw.classList.remove('selected'); });
    if (el) el.classList.add('selected');
}

/* ── Font size ── */
function changeFontSize(delta) {
    var cur = parseInt(localStorage.getItem('sh_font_size') || '16', 10);
    var nxt = Math.min(22, Math.max(12, cur + delta));
    localStorage.setItem('sh_font_size', nxt);
    document.documentElement.style.setProperty('font-size', nxt + 'px');
    document.getElementById('fsValue').textContent = nxt + 'px';
}

/* ── Accessibility ── */
function applyMotion(on)   { localStorage.setItem('sh_reduce_motion', on); document.documentElement.style.setProperty('--transition-speed', on ? '0s' : ''); }
function applyContrast(on) { localStorage.setItem('sh_high_contrast', on); document.documentElement.setAttribute('data-high-contrast', on ? 'true' : 'false'); }
</script>
</body>
</html>
