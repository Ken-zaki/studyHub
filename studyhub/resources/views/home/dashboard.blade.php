@extends('layouts.app')
@section('title', 'Dashboard – StudyHub')
@php $activeNav = 'dashboard'; @endphp
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/user_dashboard.css') }}">
@endpush

@section('content')
    @include('layouts.sidebar')

    <main class="main-content">

        {{-- ── CENTER COLUMN ─────────────────────────────────────────── --}}
        <div class="center-column">

            {{-- ── Metric Summary Row (FR-2.2, FR-2.5) ─────────────── --}}
            <div id="dashMetricRow" class="dash-metric-row" style="margin-bottom:20px;">
                {{-- Rendered by dashboard.js --}}
                <div class="dash-metric-card">
                    <div class="dash-metric-val" style="color:var(--accent,#ff6b6b);">–</div>
                    <div class="dash-metric-label">Tasks due today</div>
                    <div class="dash-metric-sub">–</div>
                </div>
                <div class="dash-metric-card">
                    <div class="dash-metric-val" style="color:var(--primary,#1a5f7a);">–</div>
                    <div class="dash-metric-label">Weekly progress</div>
                    <div class="dash-metric-sub">–</div>
                </div>
                <div class="dash-metric-card">
                    <div class="dash-metric-val" style="color:var(--secondary,#2a9d8f);">–</div>
                    <div class="dash-metric-label">Study time today</div>
                    <div class="dash-metric-sub">–</div>
                </div>
                <div class="dash-metric-card">
                    <div class="dash-metric-val" style="color:var(--primary,#1a5f7a);">–</div>
                    <div class="dash-metric-label">Active study groups</div>
                    <div class="dash-metric-sub">–</div>
                </div>
            </div>

            {{-- ── Two-column grid: Schedule + Tasks ────────────────── --}}
            <div class="dash-two-col" style="margin-bottom:20px;">

                {{-- Today's Schedule (FR-2.1) --}}
                <div class="card">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                        <h2 class="card-title" style="margin:0;">Today's Schedule</h2>
                        <a href="{{ url('/calendar') }}"
                            style="font-size:12px;font-weight:600;color:var(--primary,#1a5f7a);
                                   text-decoration:none;display:flex;align-items:center;gap:4px;
                                   transition:.15s;opacity:.85;"
                            onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.85'">
                            View calendar
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12"
                                height="12">
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                        </a>
                    </div>
                    <div id="todayScheduleList">
                        <div style="text-align:center;padding:32px 0;color:var(--text-light);font-size:13px;">
                            Loading schedule…
                        </div>
                    </div>
                </div>

                {{-- Upcoming Tasks (FR-2.2) --}}
                <div class="card">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                        <h2 class="card-title" style="margin:0;">Upcoming Tasks</h2>
                        <a href="{{ url('/tasks') }}"
                            style="font-size:12px;font-weight:600;color:var(--primary,#1a5f7a);
                                   text-decoration:none;display:flex;align-items:center;gap:4px;
                                   transition:.15s;opacity:.85;"
                            onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.85'">
                            View all
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12"
                                height="12">
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                        </a>
                    </div>
                    <div id="upcomingTasksList">
                        <div style="text-align:center;padding:32px 0;color:var(--text-light);font-size:13px;">
                            Loading tasks…
                        </div>
                    </div>
                </div>

            </div>

            {{-- ── Mini Calendar Preview Card ────────────────────────── --}}
            <div class="card" style="margin-bottom:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <h2 class="card-title" style="margin:0;">Calendar</h2>
                    <a href="{{ url('/calendar') }}"
                        style="font-size:13px;font-weight:600;color:var(--primary,#1a5f7a);
                              text-decoration:none;display:flex;align-items:center;gap:4px;
                              transition:.15s;opacity:.85;"
                        onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.85'">
                        Open Calendar
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13"
                            height="13">
                            <polyline points="9 18 15 12 9 6" />
                        </svg>
                    </a>
                </div>

                {{-- Mini calendar grid rendered by dashboard.js --}}
                <div id="dashMiniCal">
                    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;margin-bottom:4px;">
                        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d)
                            <div
                                style="text-align:center;font-size:10px;font-weight:700;
                                        color:var(--text-light);letter-spacing:.04em;padding:4px 0;">
                                {{ $d }}
                            </div>
                        @endforeach
                    </div>
                    <div id="dashMiniCalGrid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;">
                        <div
                            style="grid-column:1/-1;text-align:center;padding:24px 0;
                                    color:var(--text-light);font-size:13px;">
                            Loading calendar…
                        </div>
                    </div>
                </div>
            </div>

            {{-- Upcoming This Week --}}
            <div class="card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <h2 class="card-title" style="margin:0;">Upcoming This Week</h2>
                </div>
                {{-- Cap at ~5 items (~52px each) then scroll --}}
                <div id="upcomingList"
                    style="max-height:265px;overflow-y:auto;
                            scrollbar-width:thin;
                            scrollbar-color:var(--border) transparent;">
                    <div style="text-align:center;padding:40px 0;color:var(--text-light);font-size:13px;">
                        Loading…
                    </div>
                </div>
            </div>

        </div>{{-- /center-column --}}

        {{-- ── RIGHT SIDEBAR ──────────────────────────────────────────── --}}
        <aside class="right-sidebar">

            {{-- Task Summary Stats --}}
            <div class="card" style="margin-bottom:20px;">
                <h3 class="card-title" style="margin-bottom:14px;">Task Overview</h3>
                <div id="taskSummaryStats">
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px;">
                        <div
                            style="text-align:center;padding:12px 8px;border-radius:12px;
                                    background:var(--bg-main);border:1px solid var(--border);">
                            <div
                                style="font-size:22px;font-weight:700;color:var(--primary,#1a5f7a);
                                        font-family:'Crimson Pro',serif;">
                                –</div>
                            <div
                                style="font-size:11px;font-weight:600;color:var(--text-light);
                                        margin-top:2px;letter-spacing:.03em;">
                                Active</div>
                        </div>
                        <div
                            style="text-align:center;padding:12px 8px;border-radius:12px;
                                    background:var(--bg-main);border:1px solid var(--border);">
                            <div
                                style="font-size:22px;font-weight:700;color:var(--secondary,#2a9d8f);
                                        font-family:'Crimson Pro',serif;">
                                –</div>
                            <div
                                style="font-size:11px;font-weight:600;color:var(--text-light);
                                        margin-top:2px;letter-spacing:.03em;">
                                Done</div>
                        </div>
                        <div
                            style="text-align:center;padding:12px 8px;border-radius:12px;
                                    background:var(--bg-main);border:1px solid var(--border);">
                            <div
                                style="font-size:22px;font-weight:700;color:var(--accent,#ff6b6b);
                                        font-family:'Crimson Pro',serif;">
                                –</div>
                            <div
                                style="font-size:11px;font-weight:600;color:var(--text-light);
                                        margin-top:2px;letter-spacing:.03em;">
                                Overdue</div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Upcoming Deadlines (FR-2.2) --}}
            <div class="card" style="margin-bottom:20px;">
                <h3 class="card-title" style="margin-bottom:14px;">Deadlines</h3>
                <div id="deadlinesList">
                    <div style="text-align:center;padding:20px 0;color:var(--text-light);font-size:13px;">
                        Loading…
                    </div>
                </div>
            </div>

            {{-- Active Study Groups (FR-2.3) --}}
            <div class="card" style="margin-bottom:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                    <h3 class="card-title" style="margin:0;">Study Groups</h3>
                    <a href="{{ url('/study-groups') }}"
                        style="font-size:12px;font-weight:600;color:var(--primary,#1a5f7a);
                               text-decoration:none;opacity:.85;transition:.15s;"
                        onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.85'">
                        View all
                    </a>
                </div>
                <div id="activeStudyGroups">
                    <div style="text-align:center;padding:20px 0;color:var(--text-light);font-size:13px;">
                        Loading…
                    </div>
                </div>
            </div>

            {{-- Weekly Summary / Progress (FR-2.5) --}}
            <div class="card" style="margin-bottom:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                    <h3 class="card-title" style="margin:0;">Weekly Summary</h3>
                    <a href="{{ url('/progress') }}"
                        style="font-size:12px;font-weight:600;color:var(--primary,#1a5f7a);
                               text-decoration:none;opacity:.85;transition:.15s;"
                        onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.85'">
                        Full report
                    </a>
                </div>
                <div id="weeklySummary">
                    {{-- Rendered by dashboard.js --}}
                    <div class="dash-progress-wrap">
                        <div class="dash-progress-label">
                            <span>Tasks completed</span><span id="wsSummTaskVal">– / –</span>
                        </div>
                        <div class="dash-progress-bar">
                            <div class="dash-progress-fill" id="wsSummTaskBar"
                                style="width:0%;background:var(--primary,#1a5f7a);"></div>
                        </div>
                    </div>
                    <div class="dash-progress-wrap">
                        <div class="dash-progress-label">
                            <span>Study hours</span><span id="wsSummHoursVal">– / –</span>
                        </div>
                        <div class="dash-progress-bar">
                            <div class="dash-progress-fill" id="wsSummHoursBar"
                                style="width:0%;background:var(--secondary,#2a9d8f);"></div>
                        </div>
                    </div>
                    <div class="dash-progress-wrap">
                        <div class="dash-progress-label">
                            <span>Focus sessions</span><span id="wsSummFocusVal">– / –</span>
                        </div>
                        <div class="dash-progress-bar">
                            <div class="dash-progress-fill" id="wsSummFocusBar" style="width:0%;background:#8b5cf6;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- My Calendars --}}
            <div class="card">
                <h3 class="card-title" style="margin-bottom:14px;">My Calendars</h3>
                <div id="myCalendars">
                    <div style="text-align:center;padding:20px 0;color:var(--text-light);font-size:13px;">
                        Loading…
                    </div>
                </div>
            </div>

        </aside>{{-- /right-sidebar --}}

    </main>


    {{-- ── Supabase config ───────────────────────────────────────────── --}}
    <script>
        const SB_URL = '{{ $supabaseUrl }}';
        const SB_ANON = '{{ $supabaseAnonKey }}';
        const SB_SVC = '{{ $supabaseSvcKey }}';
        const UID = '{{ $userId }}';
    </script>

    {{-- ── JS files ──────────────────────────────────────────────────── --}}
    <script src="{{ asset('js/studyhub-core.js') }}"></script>
    <script src="{{ asset('js/user_dashboard.js') }}"></script>
    <script src="{{ asset('js/notifications.js') }}"></script>
    {{-- ── Top-bar: greeting + date + focus button (FR-2.4) ────────── --}}
    @php
        $hour = (int) now()->format('H');
        $tod = $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening');
        $dashFirstName = session('user_first_name', '');
    @endphp
    <script>
        (function() {
            const topBar = document.querySelector('.top-bar');
            if (!topBar) return;

            const left = document.createElement('div');
            left.className = 'top-bar-greeting';
            left.innerHTML =
                '<span class="dash-greeting">Good {{ $tod }}{{ $dashFirstName ? ', ' . $dashFirstName : '' }} 👋</span>' +
                '<span class="dash-topbar-date">' +
                new Date().toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                }) +
                '</span>';

            const focusBtn = document.createElement('a');
            focusBtn.href = '{{ route('focus-mode') }}';
            focusBtn.className = 'dash-focus-btn';
            focusBtn.title = 'Start a focus session';
            focusBtn.innerHTML =
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">' +
                '<polygon points="5 3 19 12 5 21 5 3"/>' +
                '</svg>Focus';

            topBar.prepend(left);
            topBar.insertBefore(focusBtn, topBar.children[1]);
        })();
    </script>

@endsection
