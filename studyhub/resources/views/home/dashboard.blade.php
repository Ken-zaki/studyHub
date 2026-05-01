{{-- resources/views/home/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', 'Dashboard – StudyHub')

@php $activeNav = 'dashboard'; @endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('css/user_dashboard.css') }}">
@endpush

@section('content')

{{-- ── SHARED SIDEBAR + TOP BAR ─────────────────────────────────────────────── --}}
@include('layouts.sidebar')

{{-- ── CALENDAR TOP BAR ACTIONS (injected into the left side of top-bar) ──────
     The shared sidebar partial renders .top-bar with only right-side buttons.
     We absolutely-position our left-side buttons inside it via CSS/flex.
     Alternatively, if your layouts.app / sidebar partial has a @stack('topbar-left'),
     use that. Otherwise this div sits right after the top-bar and we use CSS to
     position it. The simplest approach: just render it and let dashboard.css handle it.
--}}
<div class="top-bar-left" id="calTopBarLeft">
    <button class="btn-add-event" id="btnAdd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Add Event
    </button>
    <button class="btn-select-mode" id="btnSelectMode">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>
        Select
    </button>
    <div class="bulk-bar" id="bulkBar">
        <span class="bulk-bar-count" id="bulkCount">0 selected</span>
        <button class="btn-bulk-cancel" id="btnBulkCancel">Cancel</button>
        <button class="btn-bulk-delete" id="btnBulkDelete">🗑 Delete Selected</button>
    </div>
</div>

{{-- ── MAIN ─────────────────────────────────────────────────────────────────── --}}
<main class="main-content">
    <div class="center-column">

        {{-- Calendar card (month + week in same card) --}}
        <div class="calendar-card">
            <div class="cal-header">
                <div class="cal-nav-group">
                    <button class="cal-nav-btn" id="btnPrev">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <h2 class="cal-month-title" id="calTitle"></h2>
                    <button class="cal-nav-btn" id="btnNext">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                    <button class="cal-today-btn" id="btnToday">Today</button>
                </div>
                <div class="cal-view-toggle">
                    <button class="view-btn active" data-view="month">Month</button>
                    <button class="view-btn"        data-view="week">Week</button>
                </div>
            </div>

            {{-- Month view --}}
            <div id="monthView">
                <div class="cal-weekdays">
                    <div class="cal-weekday">Sun</div><div class="cal-weekday">Mon</div>
                    <div class="cal-weekday">Tue</div><div class="cal-weekday">Wed</div>
                    <div class="cal-weekday">Thu</div><div class="cal-weekday">Fri</div>
                    <div class="cal-weekday">Sat</div>
                </div>
                <div class="cal-days" id="calDays">
                    <div class="state-box" style="grid-column:1/-1"><div class="spinner"></div>Loading…</div>
                </div>
            </div>

            {{-- Week view --}}
            <div id="weekView">
                <div id="weekSummaryBar" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;"></div>
                <div style="border:1px solid var(--border);border-radius:16px;overflow:hidden;">
                    <div id="weekGrid"></div>
                </div>
            </div>

        </div>{{-- /.calendar-card --}}

        {{-- Upcoming --}}
        <div class="upcoming-card">
            <div class="section-title">📅 Upcoming This Week</div>
            <div class="upcoming-list" id="upcomingList">
                <div class="state-box"><div class="spinner"></div>Loading…</div>
            </div>
        </div>

    </div>{{-- /.center-column --}}

    {{-- Right sidebar --}}
    <aside class="right-sidebar">
        <div class="widget-card">
            <div class="widget-title">⏰ Deadlines</div>
            <div id="deadlinesList"><div class="state-box"><div class="spinner"></div></div></div>
        </div>
        <div class="widget-card">
            <div class="widget-title">🗂️ Filter My Events</div>
            <div id="myCalendars"></div>
        </div>
        <div class="widget-card" id="allEventsWidget">
            <div class="widget-title" style="margin-bottom:12px">📋 All My Events</div>
            <input type="text" class="all-ev-search" id="allEvSearch" placeholder="Search events…">
            <div id="allEventsList"></div>
        </div>
    </aside>
</main>

{{-- ── DAY POPOVER ─────────────────────────────────────────────────────────── --}}
<div class="day-popover" id="dayPopover">
    <div class="popover-header">
        <div class="popover-date" id="popDate"></div>
        <button class="popover-close-btn" id="btnPopClose">✕</button>
    </div>
    <div class="popover-events" id="popEvents"></div>
    <button class="popover-add-btn" id="btnPopAdd">+ Add event on this day</button>
</div>

{{-- ── EVENT MODAL ─────────────────────────────────────────────────────────── --}}
<div class="modal-overlay" id="eventModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle">Add Event</span>
            <button class="modal-close" id="btnModalClose">✕</button>
        </div>
        <div class="form-group">
            <label class="form-label">Category</label>
            <select class="form-select" id="evCat" onchange="updateModalFields()">
                <option value="todo">📌 To Do</option>
                <option value="class">📗 Class Schedule</option>
                <option value="group">👥 Study Group</option>
                <option value="event">📅 Event</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Title *</label>
            <input type="text" class="form-input" id="evTitle" placeholder="e.g. Math Assignment Due" maxlength="120">
        </div>
        <div class="form-group">
            <label class="form-label">Date *</label>
            <input type="date" class="form-input" id="evDate">
        </div>

        {{-- TODO: deadline time --}}
        <div id="timeFieldTodo" class="form-group" style="display:none;">
            <label class="form-label">Deadline Time <span style="font-weight:400;color:var(--text-light)">(optional)</span></label>
            <input type="time" class="form-input" id="evTimeTodo">
        </div>

        {{-- CLASS: start + end --}}
        <div id="timeFieldClass" style="display:none;">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Start Time *</label>
                    <input type="time" class="form-input" id="evTimeStart">
                </div>
                <div class="form-group">
                    <label class="form-label">End Time *</label>
                    <input type="time" class="form-input" id="evTimeEnd">
                </div>
            </div>
        </div>

        {{-- GROUP --}}
        <div id="timeFieldGroup" style="display:none;">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Start Time *</label>
                    <input type="time" class="form-input" id="evTimeStartGroup">
                </div>
                <div class="form-group">
                    <label class="form-label">End Time <span style="font-weight:400;color:var(--text-light)">(optional)</span></label>
                    <input type="time" class="form-input" id="evTimeEndGroup">
                </div>
            </div>
        </div>

        {{-- EVENT --}}
        <div id="timeFieldEvent" style="display:none;">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Start Time <span style="font-weight:400;color:var(--text-light)">(optional)</span></label>
                    <input type="time" class="form-input" id="evTimeStartEvent">
                </div>
                <div class="form-group">
                    <label class="form-label">End Time <span style="font-weight:400;color:var(--text-light)">(optional)</span></label>
                    <input type="time" class="form-input" id="evTimeEndEvent">
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea class="form-textarea" id="evDesc" placeholder="Room, link, notes…"></textarea>
        </div>
        <div class="form-group">
            <label class="recur-toggle">
                <input type="checkbox" id="evRecur"> Repeating event (weekly)
            </label>
            <div id="recurOpts" style="display:none;margin-top:10px">
                <div class="form-label" style="margin-bottom:6px">Repeat on</div>
                <div class="recur-days-group">
                    @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                        <button type="button" class="rday" data-d="{{ $day }}">{{ $day }}</button>
                    @endforeach
                </div>
                <div style="margin-top:10px">
                    <label class="form-label">Repeat until</label>
                    <input type="date" class="form-input" id="evRecurEnd">
                </div>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn-delete-ev" id="btnDelEv">Delete</button>
            <div class="modal-right">
                <button class="btn-cancel" id="btnModalCancel">Cancel</button>
                <button class="btn-save" id="btnSaveEv">Save</button>
            </div>
        </div>
    </div>
</div>

{{-- ── CONFIRM DELETE MODAL ─────────────────────────────────────────────────── --}}
<div class="modal-overlay" id="confirmModal">
    <div class="modal confirm-modal">
        <div class="modal-header">
            <span class="modal-title" id="confirmTitle">Delete</span>
            <button class="modal-close" id="btnConfirmClose">✕</button>
        </div>
        <p class="confirm-body" id="confirmBody"></p>
        <div class="modal-actions" style="justify-content:flex-end">
            <div class="modal-right">
                <button class="btn-cancel" id="btnConfirmCancel">Cancel</button>
                <button class="btn-confirm-del" id="btnConfirmDel">Yes, Delete</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
{{-- Pass server-side config to JS safely --}}
<script>
    window.DASHBOARD_CONFIG = {
        supabaseUrl:     @json($supabaseUrl),
        supabaseAnonKey: @json($supabaseAnonKey),
        supabaseSvcKey:  @json($supabaseSvcKey),
        userId:          @json($userId),
    };
</script>
<script src="{{ asset('js/user_dashboard.js') }}"></script>
@endpush
