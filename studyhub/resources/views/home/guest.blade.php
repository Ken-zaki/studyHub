<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse as Guest — StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
</head>
<body>

@include('layouts.guest-sidebar', ['guestNav' => 'home'])

<main class="main-content" style="padding: 28px 32px;">

    <header class="page-header" style="margin-bottom: 28px;">
        <h1 class="page-title">Welcome to StudyHub</h1>
        <p class="page-subtitle">Browse public content or sign up to unlock the full experience</p>
    </header>

    {{-- Browse cards --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:32px;">

        <a href="{{ route('guest.newsfeed') }}"
           style="display:flex;flex-direction:column;gap:14px;padding:24px;
                  background:var(--bg-card);border:1px solid var(--border);border-radius:18px;
                  text-decoration:none;color:var(--text-primary);transition:all 0.2s;"
           onmouseover="this.style.borderColor='var(--primary)';this.style.boxShadow='0 4px 20px rgba(26,95,122,0.1)';"
           onmouseout="this.style.borderColor='var(--border)';this.style.boxShadow='none';">
            <div style="width:48px;height:48px;border-radius:13px;font-size:22px;
                        background:rgba(26,95,122,0.08);display:flex;align-items:center;justify-content:center;">
                📰
            </div>
            <div>
                <div style="font-family:'Crimson Pro',serif;font-size:19px;font-weight:700;
                             margin-bottom:5px;">Public Newsfeed</div>
                <div style="font-size:13px;color:var(--text-secondary);line-height:1.55;">
                    See what students are sharing — study tips, questions, notes, and community posts.
                </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;
                        padding-top:12px;border-top:1px solid var(--border);margin-top:auto;">
                <span style="font-size:11px;font-weight:700;color:var(--text-light);
                              text-transform:uppercase;letter-spacing:0.04em;">Read only</span>
                <span style="font-size:14px;">→</span>
            </div>
        </a>

        <a href="{{ route('guest.resources') }}"
           style="display:flex;flex-direction:column;gap:14px;padding:24px;
                  background:var(--bg-card);border:1px solid var(--border);border-radius:18px;
                  text-decoration:none;color:var(--text-primary);transition:all 0.2s;"
           onmouseover="this.style.borderColor='var(--primary)';this.style.boxShadow='0 4px 20px rgba(26,95,122,0.1)';"
           onmouseout="this.style.borderColor='var(--border)';this.style.boxShadow='none';">
            <div style="width:48px;height:48px;border-radius:13px;font-size:22px;
                        background:rgba(245,158,66,0.10);display:flex;align-items:center;justify-content:center;">
                📚
            </div>
            <div>
                <div style="font-family:'Crimson Pro',serif;font-size:19px;font-weight:700;
                             margin-bottom:5px;">Learning Resources</div>
                <div style="font-size:13px;color:var(--text-secondary);line-height:1.55;">
                    Download reviewers, notes, slides, and exercises shared by the student community.
                </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;
                        padding-top:12px;border-top:1px solid var(--border);margin-top:auto;">
                <span style="font-size:11px;font-weight:700;color:var(--text-light);
                              text-transform:uppercase;letter-spacing:0.04em;">View &amp; Download</span>
                <span style="font-size:14px;">→</span>
            </div>
        </a>

    </div>

    {{-- What you can do --}}
    <div style="margin-bottom:32px;">
        <h2 style="font-family:'Crimson Pro',serif;font-size:20px;font-weight:700;
                    color:var(--text-primary);margin-bottom:14px;">What you can do</h2>

        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">

            @foreach([
                ['👀','Browse Posts',      'Read public posts from the student community.',              false],
                ['⬇️','Download Resources','Access and download approved study materials for free.',     false],
                ['🔍','Search Materials',  'Filter resources by subject, type, and level.',              false],
                ['❤️','Like & Comment',    'Interact with posts and resources.',                         true],
                ['✏️','Create Posts',      'Share your own study content and updates.',                  true],
                ['👥','Study Groups',      'Join and create study groups with classmates.',              true],
            ] as [$icon, $title, $desc, $locked])
            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:13px;
                         padding:16px;{{ $locked ? 'opacity:0.5;' : '' }}">
                <div style="font-size:20px;margin-bottom:8px;">{{ $icon }}</div>
                <div style="font-size:13px;font-weight:700;color:var(--text-primary);margin-bottom:4px;">
                    {{ $title }}
                </div>
                <div style="font-size:12px;color:var(--text-secondary);line-height:1.5;">
                    {{ $desc }}
                </div>
                @if($locked)
                <div style="margin-top:8px;font-size:10px;font-weight:700;color:var(--text-light);
                              text-transform:uppercase;letter-spacing:0.04em;">
                    🔒 Requires account
                </div>
                @endif
            </div>
            @endforeach

        </div>
    </div>

</main>

</body>
</html>
