@php $activeNav = 'settings'; @endphp

@include('layouts.sidebar')

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings – StudyHub</title>
    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}">

</head>
<body>

<div class="settings-wrapper">

    {{-- ── LEFT MENU ── --}}
    <nav class="settings-menu">
        <div class="settings-menu-label">General</div>

        <button class="settings-menu-item active" data-panel="about" onclick="switchPanel('about', this)">
            <div class="settings-menu-icon">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <span>About</span>
        </button>

        <button class="settings-menu-item" data-panel="theme" onclick="switchPanel('theme', this)">
            <div class="settings-menu-icon">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            </div>
            <span>Theme</span>
        </button>

        <div class="settings-menu-label">Preferences</div>

        <button class="settings-menu-item" data-panel="notifications" onclick="switchPanel('notifications', this)">
            <div class="settings-menu-icon">
                <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
            </div>
            <span>Notifications</span>
        </button>

        <div class="settings-menu-label">Account</div>

        <button class="settings-menu-item" data-panel="account" onclick="switchPanel('account', this)">
            <div class="settings-menu-icon">
                <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <span>Account</span>
        </button>

        <button class="settings-menu-item" data-panel="privacy" onclick="switchPanel('privacy', this)">
            <div class="settings-menu-icon">
                <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <span>Privacy</span>
        </button>

        <div class="settings-menu-divider"></div>

        <a href="{{ route('profile') }}" class="settings-menu-item">
            <div class="settings-menu-icon">
                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </div>
            <span>Edit Profile</span>
        </a>
    </nav>

    {{-- ── MAIN CONTENT ── --}}
    <main class="settings-content">

        {{-- ════════ ABOUT PANEL ════════ --}}
        <div id="panel-about" class="settings-panel active">
            <div class="panel-header">
                <div class="panel-title">About StudyHub</div>
                <div class="panel-subtitle">Learn more about the platform and the people behind it</div>
            </div>

            <div class="about-desc-card">
                <p>StudyHub is a web-based platform that helps students organize their academic life, manage study schedules, track tasks and deadlines, and collaborate with peers. It brings together essential tools like a calendar planner, task manager, study groups, and a shared resource library into one centralized environment to improve productivity and learning efficiency.</p>
            </div>

            <div class="about-hero">
                <div class="about-hero-tagline">Study smarter, stay organized, and succeed together.</div>
                <div class="about-hero-mission">StudyHub aims to empower students by providing a centralized and structured digital study environment where they can manage their academic responsibilities, collaborate with others, and access shared learning resources—helping them stay organized, reduce stress, and achieve better academic outcomes.</div>
            </div>

            <div class="about-stats">
                <div class="stat-card">
                    <div class="stat-value" id="stat-students">2,400+</div>
                    <div class="stat-label">Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="stat-groups">340+</div>
                    <div class="stat-label">Study Groups</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="stat-resources">1,800+</div>
                    <div class="stat-label">Resources Shared</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">96%</div>
                    <div class="stat-label">Satisfaction Rate</div>
                </div>
            </div>

            <div class="section-title">Meet the Team</div>
            <div class="team-grid">
                @php
                    $team = [
                        ['initials' => 'AJ', 'name' => 'Alex Johnson', 'role' => 'Project Lead'],
                        ['initials' => 'MR', 'name' => 'Maria Reyes', 'role' => 'UI/UX Designer'],
                        ['initials' => 'KC', 'name' => 'Kyle Cruz', 'role' => 'Backend Developer'],
                        ['initials' => 'SL', 'name' => 'Sofia Lim', 'role' => 'Frontend Developer'],
                        ['initials' => 'DP', 'name' => 'Diego Perez', 'role' => 'Database Engineer'],
                        ['initials' => 'NK', 'name' => 'Nina Kim', 'role' => 'QA & Testing'],
                    ];
                @endphp
                @foreach($team as $member)
                <div class="team-card">
                    <div class="team-photo">{{ $member['initials'] }}</div>
                    <div class="team-name">{{ $member['name'] }}</div>
                    <div class="team-role">{{ $member['role'] }}</div>
                </div>
                @endforeach
            </div>

            <div class="section-title">FAQ</div>
            <div class="faq-list">
                @php
                    $faqs = [
                        ['q' => 'Is StudyHub free to use?', 'a' => 'Yes! StudyHub is completely free for all students. Simply sign up with your email and you\'ll have full access to all features including study groups, resources, and the focus timer.'],
                        ['q' => 'Can I join multiple study groups?', 'a' => 'Absolutely. You can join as many study groups as you like and even create your own. There\'s no limit to how many groups you can participate in.'],
                        ['q' => 'How do I share resources with my peers?', 'a' => 'Navigate to the Resources section from the sidebar. You can upload files, share links, and organize materials by subject. Your friends and study group members can access anything you share publicly.'],
                        ['q' => 'What is Focus Mode?', 'a' => 'Focus Mode is a distraction-free study timer based on the Pomodoro technique. It tracks your study sessions, lets you set goals, and helps you maintain a consistent study habit over time.'],
                        ['q' => 'How do I add friends on StudyHub?', 'a' => 'Go to the Friends section in the sidebar. You can search for other students by name or username and send them a friend request. Once accepted, you\'ll appear in each other\'s friend lists.'],
                        ['q' => 'Is my data kept private?', 'a' => 'We take your privacy seriously. Your personal data is stored securely and is never shared with third parties. You control who sees your profile and what you share on the platform.'],
                    ];
                @endphp
                @foreach($faqs as $i => $faq)
                <div class="faq-item" id="faq-{{ $i }}">
                    <button class="faq-question" onclick="toggleFaq({{ $i }})">
                        <span>{{ $faq['q'] }}</span>
                        <svg class="faq-chevron" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-inner">{{ $faq['a'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- ════════ THEME PANEL ════════ --}}
        <div id="panel-theme" class="settings-panel">
            <div class="panel-header">
                <div class="panel-title">Theme</div>
                <div class="panel-subtitle">Customize how StudyHub looks and feels</div>
            </div>

            <div class="section-title" style="font-size:16px;">Color Mode</div>
            <div class="theme-options">
                <div class="theme-option selected" id="theme-light" onclick="selectTheme('light', this)">
                    <div class="theme-preview theme-preview-light">
                        <div class="theme-mock-bar"></div>
                        <div class="theme-mock-card"></div>
                    </div>
                    <div class="theme-option-label">
                        <div>
                            <div class="theme-option-name">Light</div>
                            <div class="theme-option-desc">Clean and bright</div>
                        </div>
                        <div class="theme-check"><div class="theme-check-dot"></div></div>
                    </div>
                </div>

                <div class="theme-option" id="theme-dark" onclick="selectTheme('dark', this)">
                    <div class="theme-preview theme-preview-dark">
                        <div class="theme-mock-bar"></div>
                        <div class="theme-mock-card"></div>
                    </div>
                    <div class="theme-option-label">
                        <div>
                            <div class="theme-option-name">Dark</div>
                            <div class="theme-option-desc">Easy on the eyes</div>
                        </div>
                        <div class="theme-check"><div class="theme-check-dot"></div></div>
                    </div>
                </div>

                <div class="theme-option" id="theme-auto" onclick="selectTheme('auto', this)">
                    <div class="theme-preview theme-preview-auto">
                        <div class="theme-mock-bar"></div>
                        <div class="theme-mock-card"></div>
                    </div>
                    <div class="theme-option-label">
                        <div>
                            <div class="theme-option-name">Auto</div>
                            <div class="theme-option-desc">Follows your device</div>
                        </div>
                        <div class="theme-check"><div class="theme-check-dot"></div></div>
                    </div>
                </div>
            </div>

            <div class="section-title" style="font-size:16px;">Accent Color</div>
            <div class="theme-accent-section">
                <div class="accent-colors">
                    <div class="accent-color selected" style="background:#1a5f7a;" data-accent="#1a5f7a" onclick="selectAccent(this)" title="Ocean (default)"></div>
                    <div class="accent-color" style="background:#7c3aed;" data-accent="#7c3aed" onclick="selectAccent(this)" title="Purple"></div>
                    <div class="accent-color" style="background:#059669;" data-accent="#059669" onclick="selectAccent(this)" title="Emerald"></div>
                    <div class="accent-color" style="background:#dc2626;" data-accent="#dc2626" onclick="selectAccent(this)" title="Red"></div>
                    <div class="accent-color" style="background:#d97706;" data-accent="#d97706" onclick="selectAccent(this)" title="Amber"></div>
                    <div class="accent-color" style="background:#db2777;" data-accent="#db2777" onclick="selectAccent(this)" title="Pink"></div>
                    <div class="accent-color" style="background:#0891b2;" data-accent="#0891b2" onclick="selectAccent(this)" title="Cyan"></div>
                </div>
                <div style="margin-top:10px; font-size:12px; color:var(--text-light);">Accent color is applied to active nav items, buttons, and highlights.</div>
            </div>

            <div class="section-title" style="font-size:16px;">Text Size</div>
            <div class="font-size-row">
                <div>
                    <div class="font-size-label">Interface font size</div>
                    <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;">Adjusts text size across the platform</div>
                </div>
                <div class="font-size-controls">
                    <button class="font-size-btn" onclick="changeFontSize(-1)">−</button>
                    <div class="font-size-value" id="font-size-display">16px</div>
                    <button class="font-size-btn" onclick="changeFontSize(1)">+</button>
                </div>
            </div>
        </div>

        {{-- ════════ NOTIFICATIONS PANEL ════════ --}}
        <div id="panel-notifications" class="settings-panel">
            <div class="panel-header">
                <div class="panel-title">Notifications</div>
                <div class="panel-subtitle">Choose what you want to be notified about</div>
            </div>

            <div class="section-title" style="font-size:16px;">Activity</div>

            @php
                $notifToggles = [
                    ['id' => 'notif-friend-req', 'title' => 'Friend Requests', 'desc' => 'When someone sends you a friend request', 'default' => true],
                    ['id' => 'notif-friend-acc', 'title' => 'Friend Accepted', 'desc' => 'When someone accepts your friend request', 'default' => true],
                    ['id' => 'notif-post-like', 'title' => 'Post Reactions', 'desc' => 'When someone reacts to your newsfeed post', 'default' => true],
                    ['id' => 'notif-post-comment', 'title' => 'Comments', 'desc' => 'When someone comments on your post', 'default' => true],
                    ['id' => 'notif-group-invite', 'title' => 'Study Group Invites', 'desc' => 'When you are invited to join a study group', 'default' => true],
                    ['id' => 'notif-resource', 'title' => 'New Resources', 'desc' => 'When a friend shares a new resource', 'default' => false],
                    ['id' => 'notif-messages', 'title' => 'Messages', 'desc' => 'When you receive a new direct message', 'default' => true],
                ];
            @endphp

            @foreach($notifToggles as $toggle)
            <div class="settings-toggle-row">
                <div class="toggle-info">
                    <div class="toggle-title">{{ $toggle['title'] }}</div>
                    <div class="toggle-desc">{{ $toggle['desc'] }}</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="{{ $toggle['id'] }}" {{ $toggle['default'] ? 'checked' : '' }} onchange="saveToggle('{{ $toggle['id'] }}', this.checked)">
                    <div class="toggle-track"></div>
                </label>
            </div>
            @endforeach

            <div style="margin-top:20px;">
                <div class="section-title" style="font-size:16px;">Study Reminders</div>
                <div class="settings-toggle-row">
                    <div class="toggle-info">
                        <div class="toggle-title">Focus Session Reminders</div>
                        <div class="toggle-desc">Remind me to start my daily study session</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="notif-focus" checked onchange="saveToggle('notif-focus', this.checked)">
                        <div class="toggle-track"></div>
                    </label>
                </div>
                <div class="settings-toggle-row">
                    <div class="toggle-info">
                        <div class="toggle-title">Weekly Study Summary</div>
                        <div class="toggle-desc">Get a weekly recap of your study activity</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="notif-weekly" onchange="saveToggle('notif-weekly', this.checked)">
                        <div class="toggle-track"></div>
                    </label>
                </div>
            </div>
        </div>

        {{-- ════════ ACCOUNT PANEL ════════ --}}
        <div id="panel-account" class="settings-panel">
            <div class="panel-header">
                <div class="panel-title">Account</div>
                <div class="panel-subtitle">Manage your account information</div>
            </div>

            <div class="account-avatar-section">
                <div class="account-avatar">
                    @if(session('user_profile_photo'))
                        <img src="{{ session('user_profile_photo') }}" alt="Profile">
                    @else
                        {{ strtoupper(substr(session('user_first_name','U'),0,1) . substr(session('user_last_name',''),0,1)) }}
                    @endif
                </div>
                <div class="account-avatar-info">
                    <div class="account-avatar-name">{{ trim(session('user_first_name','') . ' ' . session('user_last_name','')) ?: session('user_username','StudyHub User') }}</div>
                    <div class="account-avatar-username">@{{ session('user_username', 'username') }}</div>
                    <a href="{{ route('profile') }}" class="account-avatar-btn">Edit Profile →</a>
                </div>
            </div>

            <div class="settings-field-group">
                <div class="settings-field">
                    <div class="settings-field-label">Full Name</div>
                    <div class="settings-field-value">{{ trim(session('user_first_name','') . ' ' . session('user_last_name','')) ?: '—' }}</div>
                    <a href="{{ route('profile') }}" class="settings-field-action">Edit</a>
                </div>
                <div class="settings-field">
                    <div class="settings-field-label">Username</div>
                    <div class="settings-field-value">@{{ session('user_username', '—') }}</div>
                    <a href="{{ route('profile') }}" class="settings-field-action">Edit</a>
                </div>
                <div class="settings-field">
                    <div class="settings-field-label">Student Type</div>
                    <div class="settings-field-value">
                        @php
                            $st = session('user_student_type','');
                            echo match(strtolower($st)) {
                                'high_school' => 'High School Student',
                                'college' => 'College Student',
                                default => 'Not set'
                            };
                        @endphp
                    </div>
                    <a href="{{ route('profile') }}" class="settings-field-action">Edit</a>
                </div>
                <div class="settings-field">
                    <div class="settings-field-label">Account Role</div>
                    <div class="settings-field-value">{{ ucfirst(session('user_role','student')) }}</div>
                    <span></span>
                </div>
            </div>

            <div class="section-title" style="font-size:16px; margin-top:8px;">Security</div>
            <div class="settings-field-group" style="margin-bottom:20px;">
                <div class="settings-field">
                    <div class="settings-field-label">Password</div>
                    <div class="settings-field-value">••••••••••</div>
                    <button class="settings-field-action">Change</button>
                </div>
                <div class="settings-field">
                    <div class="settings-field-label">Sessions</div>
                    <div class="settings-field-value">Active on this device</div>
                    <form method="POST" action="{{ route('logout') }}" style="margin:0">
                        @csrf
                        <button type="submit" class="settings-field-action" style="color:#dc2626;">Sign out</button>
                    </form>
                </div>
            </div>

            <div class="danger-zone">
                <div class="danger-title">⚠ Danger Zone</div>
                <div class="danger-desc">Deleting your account is permanent and cannot be undone. All your posts, resources, and group memberships will be removed.</div>
                <button class="danger-btn" onclick="if(confirm('Are you sure? This cannot be undone.')) alert('Please contact support to delete your account.')">Delete Account</button>
            </div>
        </div>

        {{-- ════════ PRIVACY PANEL ════════ --}}
        <div id="panel-privacy" class="settings-panel">
            <div class="panel-header">
                <div class="panel-title">Privacy</div>
                <div class="panel-subtitle">Control your visibility and data preferences</div>
            </div>

            <div class="privacy-info-box">
                🔒 StudyHub takes your privacy seriously. Your data is never sold or shared with third parties. These settings let you control who can see your information.
            </div>

            <div class="section-title" style="font-size:16px;">Profile Visibility</div>

            @php
                $privacyToggles = [
                    ['id' => 'priv-profile-public', 'title' => 'Public Profile', 'desc' => 'Allow anyone on StudyHub to view your profile', 'default' => true],
                    ['id' => 'priv-show-online', 'title' => 'Show Online Status', 'desc' => 'Let your friends see when you are online', 'default' => true],
                    ['id' => 'priv-show-posts', 'title' => 'Public Posts', 'desc' => 'Allow non-friends to see your newsfeed posts', 'default' => false],
                    ['id' => 'priv-show-friends', 'title' => 'Show Friends List', 'desc' => 'Let others see who you are friends with', 'default' => true],
                    ['id' => 'priv-allow-requests', 'title' => 'Allow Friend Requests', 'desc' => 'Let other students send you friend requests', 'default' => true],
                    ['id' => 'priv-show-resources', 'title' => 'Public Resources', 'desc' => 'Allow others to discover resources you\'ve shared', 'default' => true],
                ];
            @endphp

            @foreach($privacyToggles as $toggle)
            <div class="settings-toggle-row">
                <div class="toggle-info">
                    <div class="toggle-title">{{ $toggle['title'] }}</div>
                    <div class="toggle-desc">{{ $toggle['desc'] }}</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="{{ $toggle['id'] }}" {{ $toggle['default'] ? 'checked' : '' }} onchange="saveToggle('{{ $toggle['id'] }}', this.checked)">
                    <div class="toggle-track"></div>
                </label>
            </div>
            @endforeach
        </div>

    </main>

    {{-- ── RIGHT RAIL ── --}}
    <aside class="settings-right-rail">
        <div class="rail-section">
            <div class="rail-title">Platform Info</div>
            <div class="rail-item">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Version
                <span class="rail-version-badge">1.0.0</span>
            </div>
            <a href="#" class="rail-item">
                <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Privacy Policy
            </a>
            <a href="#" class="rail-item">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Terms of Service
            </a>
        </div>

        <div class="rail-section">
            <div class="rail-title">Contact</div>
            <a href="#" class="rail-item">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Support Center
            </a>
            <a href="#" class="rail-item">
                <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                Send Feedback
            </a>
        </div>

        <div class="rail-section">
            <div class="rail-title">Quick Links</div>
            <a href="{{ route('profile') }}" class="rail-item">
                <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Edit Profile
            </a>
            <a href="{{ route('focus-mode') }}" class="rail-item">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                Focus Mode
            </a>
        </div>
    </aside>

</div>

<script src="{{ asset('js/settings.js') }}"></script>

</body>
</html>
