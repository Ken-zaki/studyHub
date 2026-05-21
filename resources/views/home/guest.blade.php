<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse as Guest — StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
    <style>
        /* ── Hero banner matching the registered dashboard gradient style ── */
        .guest-hero {
            background: linear-gradient(135deg, var(--primary, #1a5f7a), #144d61);
            border-radius: 18px;
            padding: 28px 32px;
            color: white;
            margin-bottom: 24px;
        }
        .guest-hero h2 {
            font-family: 'Crimson Pro', serif;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .guest-hero p {
            font-size: 14px;
            opacity: 0.85;
            line-height: 1.6;
            max-width: 480px;
            margin-bottom: 18px;
        }
        .guest-hero-btns { display: flex; gap: 10px; flex-wrap: wrap; }
        .hero-btn-p {
            padding: 10px 22px; border-radius: 10px;
            background: white; color: var(--primary, #1a5f7a);
            font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 700;
            text-decoration: none; transition: opacity 0.18s;
        }
        .hero-btn-p:hover { opacity: 0.9; }
        .hero-btn-s {
            padding: 10px 22px; border-radius: 10px;
            background: transparent; color: white;
            border: 1.5px solid rgba(255,255,255,0.5);
            font-family: 'DM Sans', sans-serif; font-size: 14px; font-weight: 600;
            text-decoration: none; transition: border-color 0.18s;
        }
        .hero-btn-s:hover { border-color: white; }

        /* ── Browse cards (2-col, mirrors dashboard widgets) ── */
        .guest-browse-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 28px;
        }
        .browse-card {
            display: flex; flex-direction: column; gap: 14px; padding: 24px;
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 18px; text-decoration: none; color: var(--text-primary);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .browse-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(26,95,122,0.1);
        }
        .browse-card-icon {
            width: 48px; height: 48px; border-radius: 13px;
            display: flex; align-items: center; justify-content: center; font-size: 22px;
        }
        .browse-card-title {
            font-family: 'Crimson Pro', serif;
            font-size: 19px; font-weight: 700; margin-bottom: 5px;
        }
        .browse-card-desc {
            font-size: 13px; color: var(--text-secondary); line-height: 1.55;
        }
        .browse-card-footer {
            display: flex; align-items: center; justify-content: space-between;
            padding-top: 12px; border-top: 1px solid var(--border); margin-top: auto;
        }
        .browse-card-badge {
            font-size: 11px; font-weight: 700; color: var(--text-light);
            text-transform: uppercase; letter-spacing: 0.04em;
        }

        /* ── Feature grid (3-col, mirrors dashboard what you can do) ── */
        .guest-features-title {
            font-family: 'Crimson Pro', serif;
            font-size: 20px; font-weight: 700; color: var(--text-primary);
            margin-bottom: 14px;
        }
        .guest-features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 28px;
        }
        .feature-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 13px; padding: 16px;
        }
        .feature-card.locked { opacity: 0.55; }
        .feature-card-icon { font-size: 20px; margin-bottom: 8px; }
        .feature-card-title { font-size: 13px; font-weight: 700; color: var(--text-primary); margin-bottom: 4px; }
        .feature-card-desc  { font-size: 12px; color: var(--text-secondary); line-height: 1.5; }
        .feature-locked-tag {
            margin-top: 8px; font-size: 10px; font-weight: 700;
            color: var(--text-light); text-transform: uppercase; letter-spacing: 0.04em;
        }

        /* ── Stats row ── */
        .guest-stats-row {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;
            margin-bottom: 28px;
        }
        .guest-stat-box {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 14px; padding: 16px; text-align: center;
        }
        .guest-stat-num {
            font-family: 'Crimson Pro', serif;
            font-size: 22px; font-weight: 700; color: var(--text-primary);
        }
        .guest-stat-lbl { font-size: 12px; color: var(--text-secondary); margin-top: 3px; }

        @media (max-width: 680px) {
            .guest-browse-grid   { grid-template-columns: 1fr; }
            .guest-features-grid { grid-template-columns: 1fr 1fr; }
            .guest-stats-row     { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

@include('layouts.guest-sidebar', ['guestNav' => 'home'])

<main class="main-content" style="padding:28px 32px;">

    {{-- Hero --}}
    <div class="guest-hero">
        <h2>Welcome to StudyHub</h2>
        <p>Browse public content from the student community. Sign up for free to unlock the full experience — post, comment, join groups, and more.</p>
        <div class="guest-hero-btns">
            <a href="{{ route('signup') }}" class="hero-btn-p">Sign Up Free →</a>
            <a href="{{ route('login') }}"  class="hero-btn-s">Log In</a>
        </div>
    </div>

    {{-- Stats row --}}
    <div class="guest-stats-row">
        @foreach([['2,400+','Students'],['340+','Study Groups'],['1,800+','Resources'],['96%','Satisfaction']] as $s)
        <div class="guest-stat-box">
            <div class="guest-stat-num">{{ $s[0] }}</div>
            <div class="guest-stat-lbl">{{ $s[1] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Browse cards --}}
    <div class="guest-browse-grid">

        <a href="{{ route('guest.newsfeed') }}" class="browse-card">
            <div class="browse-card-icon" style="background:rgba(26,95,122,0.08);">📰</div>
            <div>
                <div class="browse-card-title">Public Newsfeed</div>
                <div class="browse-card-desc">See what students are sharing — study tips, questions, notes, and community posts.</div>
            </div>
            <div class="browse-card-footer">
                <span class="browse-card-badge">Read only</span>
                <span style="font-size:14px;">→</span>
            </div>
        </a>

        <a href="{{ route('guest.resources') }}" class="browse-card">
            <div class="browse-card-icon" style="background:rgba(245,158,66,0.10);">📚</div>
            <div>
                <div class="browse-card-title">Learning Resources</div>
                <div class="browse-card-desc">Download reviewers, notes, slides, and exercises shared by the student community.</div>
            </div>
            <div class="browse-card-footer">
                <span class="browse-card-badge">View &amp; Download</span>
                <span style="font-size:14px;">→</span>
            </div>
        </a>

    </div>

    {{-- Features grid --}}
    <div class="guest-features-title">What you can do</div>
    <div class="guest-features-grid">

        @foreach([
            ['👀','Browse Posts',       'Read public posts from the student community.',              false],
            ['⬇️','Download Resources', 'Access and download approved study materials for free.',     false],
            ['🔍','Search Materials',   'Filter resources by subject, type, and level.',              false],
            ['❤️','Like & Comment',     'Interact with posts and resources.',                         true ],
            ['✏️','Create Posts',       'Share your own study content and updates.',                  true ],
            ['👥','Study Groups',       'Join and create study groups with classmates.',              true ],
        ] as [$icon, $title, $desc, $locked])
        <div class="feature-card {{ $locked ? 'locked' : '' }}">
            <div class="feature-card-icon">{{ $icon }}</div>
            <div class="feature-card-title">{{ $title }}</div>
            <div class="feature-card-desc">{{ $desc }}</div>
            @if($locked)
            <div class="feature-locked-tag">🔒 Requires account</div>
            @endif
        </div>
        @endforeach

    </div>

</main>

</body>
</html>
