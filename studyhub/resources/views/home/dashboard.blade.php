{{-- resources/views/home/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', 'Dashboard – StudyHub')

@php $activeNav = 'dashboard'; @endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('css/user_dashboard.css') }}">
@endpush

@section('content')

{{-- ── SHARED SIDEBAR + TOP BAR ──────────────────────────────────────────── --}}
@include('layouts.sidebar')

{{-- ── CALENDAR TOP BAR ACTIONS ─────────────────────────────────────────── --}}
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

{{-- ── MAIN ──────────────────────────────────────────────────────────────── --}}
<main class="main-content">
    <div class="center-column">

        {{-- Calendar card --}}
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
            <div id="weekView" style="display:none;">
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

{{-- ═══════════════════════════════════════════════════════════
     DAY POPOVER
═══════════════════════════════════════════════════════════ --}}
<div class="day-popover" id="dayPopover">
    <div class="popover-header">
        <div class="popover-date" id="popDate"></div>
        <div style="display:flex;gap:6px;align-items:center;">
            <button class="popover-add-btn" id="btnPopAdd">+ Add</button>
            <button class="popover-close-btn" id="btnPopClose">✕</button>
        </div>
    </div>
    <div class="popover-events" id="popEvents"></div>
</div>

{{-- ═══════════════════════════════════════════════════════════
     EVENT MODAL (Add / Edit)
═══════════════════════════════════════════════════════════ --}}
<div class="modal-overlay" id="eventModal">
    <div class="modal ev-modal">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle">Add Event</span>
            <button class="modal-close" id="btnModalClose">✕</button>
        </div>

        <div class="modal-body">
            {{-- Title --}}
            <div class="form-group">
                <label class="form-label">Title <span style="color:#dc2626">*</span></label>
                <input type="text" class="form-input" id="evTitle" placeholder="Event title…">
            </div>

            {{-- Category --}}
            <div class="form-group">
                <label class="form-label">Category</label>
                <select class="form-input" id="evCat" onchange="updateModalFields()">
                    <option value="todo">📌 To Do</option>
                    <option value="class">📗 Class</option>
                    <option value="group">👥 Study Group</option>
                    <option value="event">📅 Event</option>
                </select>
            </div>

            {{-- Date --}}
            <div class="form-group">
                <label class="form-label">Date <span style="color:#dc2626">*</span></label>
                <input type="date" class="form-input" id="evDate">
            </div>

            {{-- Time fields (shown per category) --}}
            <div id="timeFieldTodo" class="form-group">
                <label class="form-label">Due Time (optional)</label>
                <input type="time" class="form-input" id="evTimeTodo">
            </div>
            <div id="timeFieldClass" class="form-group" style="display:none;">
                <label class="form-label">Start Time <span style="color:#dc2626">*</span></label>
                <input type="time" class="form-input" id="evTimeStart">
                <label class="form-label" style="margin-top:8px;">End Time <span style="color:#dc2626">*</span></label>
                <input type="time" class="form-input" id="evTimeEnd">
            </div>
            <div id="timeFieldGroup" class="form-group" style="display:none;">
                <label class="form-label">Start Time <span style="color:#dc2626">*</span></label>
                <input type="time" class="form-input" id="evTimeStartGroup">
                <label class="form-label" style="margin-top:8px;">End Time (optional)</label>
                <input type="time" class="form-input" id="evTimeEndGroup">
            </div>
            <div id="timeFieldEvent" class="form-group" style="display:none;">
                <label class="form-label">Start Time (optional)</label>
                <input type="time" class="form-input" id="evTimeStartEvent">
                <label class="form-label" style="margin-top:8px;">End Time (optional)</label>
                <input type="time" class="form-input" id="evTimeEndEvent">
            </div>

            {{-- Description --}}
            <div class="form-group">
                <label class="form-label">Description (optional)</label>
                <textarea class="form-input" id="evDesc" rows="2" placeholder="Notes…" style="resize:vertical;"></textarea>
            </div>

            {{-- Recurring --}}
            <div class="form-group" style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" id="evRecur" style="width:16px;height:16px;cursor:pointer;">
                <label for="evRecur" class="form-label" style="margin:0;cursor:pointer;">Repeat / Recurring</label>
            </div>
            <div id="recurOpts" style="display:none;padding:12px;background:var(--bg-main);border-radius:10px;margin-top:4px;">
                <label class="form-label">Repeat on days</label>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;">
                    @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
                    <button type="button" class="rday" data-d="{{ $day }}">{{ $day }}</button>
                    @endforeach
                </div>
                <label class="form-label" style="margin-top:10px;">Repeat until (optional)</label>
                <input type="date" class="form-input" id="evRecurEnd" style="margin-top:4px;">
            </div>
        </div>

        <div class="modal-actions">
            <button class="btn-del-ev" id="btnDelEv" style="display:none;">🗑 Delete</button>
            <div style="display:flex;gap:8px;margin-left:auto;">
                <button class="btn-cancel" id="btnModalCancel">Cancel</button>
                <button class="btn-save" id="btnSaveEv">Save</button>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════
     CONFIRM DELETE MODAL
═══════════════════════════════════════════════════════════ --}}
<div class="modal-overlay" id="confirmModal">
    <div class="modal confirm-modal">
        <div class="modal-header">
            <span class="modal-title" id="confirmTitle">Delete Event</span>
            <button class="modal-close" id="btnConfirmClose">✕</button>
        </div>
        <div class="modal-body">
            <p id="confirmBody"></p>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" id="btnConfirmCancel">Cancel</button>
            <button class="btn-danger" id="btnConfirmDel">Yes, Delete</button>
        </div>
    </div>
</div>

{{-- ── CONFIG (must come before user_dashboard.js) ──────────────────────── --}}
<script>
    const SB_URL  = '{{ env("SUPABASE_URL") }}';
    const SB_ANON = '{{ env("SUPABASE_ANON_KEY") }}';
    const SB_SVC  = '{{ env("SUPABASE_SERVICE_KEY") }}';
    const UID     = '{{ session("user_id") }}';
</script>

<script src="{{ asset('js/user_dashboard.js') }}"></script>

@endsection
