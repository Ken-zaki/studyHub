{{-- resources/views/home/calendar.blade.php --}}
@extends('layouts.app')
@section('title', 'Calendar – StudyHub')
@php $activeNav = 'calendar'; @endphp
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/calendar.css') }}">
@endpush

@section('content')

    @include('layouts.sidebar')

    {{-- ── MAIN ──────────────────────────────────────────────────────────────── --}}
    <main class="main-content">
        <div class="center-column">

            {{-- Calendar card --}}
            {{--
                FR-3.1: Day / Week / Month view toggle already present — kept as-is.
                FR-3.2: "Add Event" button in top-bar injection below — kept as-is.
                FR-3.4: Deadlines widget in right sidebar surfaces exam/deadline events.
            --}}
            <div class="calendar-card">
                <div class="cal-header">
                    <div class="cal-nav-group">
                        <button class="cal-nav-btn" id="btnPrev">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6" />
                            </svg>
                        </button>
                        <h2 class="cal-month-title" id="calTitle"></h2>
                        <button class="cal-nav-btn" id="btnNext">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 18 15 12 9 6" />
                            </svg>
                        </button>
                        <button class="cal-today-btn" id="btnToday">Today</button>
                    </div>
                    <div class="cal-view-toggle">
                        {{-- FR-3.1: Day / Week / Month views --}}
                        <button class="view-btn" data-view="day">Day</button>
                        <button class="view-btn" data-view="week">Week</button>
                        <button class="view-btn active" data-view="month">Month</button>
                    </div>
                </div>

                {{-- Month view (FR-3.1) --}}
                <div id="monthView">
                    <div class="cal-weekdays">
                        <div class="cal-weekday">Sun</div>
                        <div class="cal-weekday">Mon</div>
                        <div class="cal-weekday">Tue</div>
                        <div class="cal-weekday">Wed</div>
                        <div class="cal-weekday">Thu</div>
                        <div class="cal-weekday">Fri</div>
                        <div class="cal-weekday">Sat</div>
                    </div>
                    <div class="cal-days" id="calDays">
                        <div class="state-box" style="grid-column:1/-1">
                            <div class="spinner"></div>Loading…
                        </div>
                    </div>
                </div>

                {{-- Week view (FR-3.1) --}}
                <div id="weekView" style="display:none;">
                    <div id="weekSummaryBar" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;"></div>
                    <div id="weekGrid"></div>
                </div>

                {{-- Day view (FR-3.1) --}}
                <div id="dayView" style="display:none;">
                    <div id="dayGrid"></div>
                </div>
            </div>

            {{-- Upcoming this week --}}
            <div class="upcoming-card">
                <div class="section-title">📅 Upcoming This Week</div>
                <div class="upcoming-list" id="upcomingList">
                    <div class="state-box">
                        <div class="spinner"></div>Loading…
                    </div>
                </div>
            </div>

        </div>{{-- /.center-column --}}

        {{-- ── RIGHT SIDEBAR ─────────────────────────────────────────────────── --}}
        <aside class="right-sidebar">

            {{-- My Subjects (FR-3.3) --}}
            {{--
                CHANGE: Renamed from "Filter My Events" to "My Subjects".
                "Filter My Events" didn't communicate color-coding to the user.
                "My Subjects" matches FR-3.3: "color-code their subjects."

                calendar.js must render each subject with:
                  - a colored circle (the subject's assigned color)
                  - the subject name
                  - a checkbox to show/hide events of that subject on the calendar
                  - a color picker (small swatch button) to change the subject color

                Subject colors should be saved to the user's profile in Supabase
                (table: user_subject_colors, columns: user_id, subject_name, color_hex)
                so the same colors appear on the dashboard's "My Subjects" card.
            --}}
            <div class="card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                    <div class="widget-title" style="margin:0;">My Subjects</div>
                    {{-- FR-3.3: Add subject button — calendar.js opens a small inline form --}}
                    <button id="btnAddSubject"
                        style="font-size:11px;font-weight:700;color:var(--primary,#1a5f7a);
                               background:none;border:none;cursor:pointer;padding:0;opacity:.85;"
                        onclick="openAddSubjectForm()">
                        + Add
                    </button>
                </div>
                <div id="myCalendars"></div>
            </div>

            {{-- All My Events --}}
            <div class="card" id="allEventsWidget" style="margin-top:16px;">
                <div class="widget-title" style="margin-bottom:12px;">All My Events</div>
                <input type="text" class="all-ev-search" id="allEvSearch" placeholder="Search events…">
                <div id="allEventsList"></div>
            </div>

            {{-- Deadlines (FR-3.4) --}}
            {{--
                FR-3.4: Exam and deadline tracker.
                calendar.js should filter events where category = 'exam' or 'deadline'
                and render them here sorted by date, showing days-remaining.
                CHANGE: Retitled from "⏰ Deadlines" to "Exams & Deadlines" to
                        clearly communicate the FR-3.4 scope.
            --}}
            <div class="card" style="margin-top:16px;">
                <div class="widget-title" style="margin-bottom:8px;">Exams &amp; Deadlines</div>
                <div id="deadlinesList">
                    <div class="state-box">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>

            {{-- Sync / Export (FR-3.5) --}}
            {{--
                NEW CARD: FR-3.5 — "Sync to own study planner."
                Previously had no UI at all.

                Minimum implementation:
                  - "Export as .ics" button → calls calendar.js exportToICS()
                    which builds an iCal file from the user's events and triggers download.
                  - Optionally: "Copy iCal link" for subscribing in Google/Apple Calendar.

                calendar.js needs:
                  function exportToICS() — fetches all user events from Supabase,
                  formats as RFC 5545 iCal string, triggers file download.
            --}}
            <div class="card" style="margin-top:16px;">
                <div class="widget-title" style="margin-bottom:10px;">Sync &amp; Export</div>
                <p style="font-size:12px;color:var(--text-light);margin:0 0 10px;">
                    Export your schedule to Google Calendar, Apple Calendar, or any app that supports .ics files.
                </p>
                <button id="btnExportICS"
                    onclick="if(typeof exportToICS==='function') exportToICS(); else alert('Export not available yet.');"
                    style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;
                           padding:9px 14px;border-radius:10px;border:1px solid var(--border);
                           background:var(--bg-main);font-size:13px;font-weight:600;
                           color:var(--text-secondary);cursor:pointer;transition:.15s;"
                    onmouseover="this.style.borderColor='var(--primary,#1a5f7a)';this.style.color='var(--primary,#1a5f7a)'"
                    onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-secondary)'">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"
                        height="15">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="7 10 12 15 17 10" />
                        <line x1="12" y1="15" x2="12" y2="3" />
                    </svg>
                    Export as .ics
                </button>
                <button id="btnCopyICalLink"
                    onclick="if(typeof copyICalLink==='function') copyICalLink(); else alert('iCal link not available yet.');"
                    style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;
                           margin-top:8px;padding:9px 14px;border-radius:10px;border:1px solid var(--border);
                           background:var(--bg-main);font-size:13px;font-weight:600;
                           color:var(--text-secondary);cursor:pointer;transition:.15s;"
                    onmouseover="this.style.borderColor='var(--primary,#1a5f7a)';this.style.color='var(--primary,#1a5f7a)'"
                    onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-secondary)'">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"
                        height="15">
                        <rect x="9" y="9" width="13" height="13" rx="2" />
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                    </svg>
                    Copy iCal link
                </button>
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
                <button class="popover-add-btn" id="btnPopAdd" style="margin-top:0;width:auto;padding:6px 12px;">+
                    Add</button>
                <button class="popover-close-btn" id="btnPopClose">✕</button>
            </div>
        </div>
        <div class="popover-events" id="popEvents"></div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
     EVENT MODAL (Add / Edit)
     FR-3.2: Add study sessions
     FR-3.3: Subject color applied to event via subject picker
     FR-3.4: "Exam" and "Deadline" added as event categories
     FR-3.6: Reminder toggle added inside modal
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

                {{-- Category (FR-3.4: added Exam + Deadline options) --}}
                {{--
                    CHANGE: Added "Exam" and "Deadline" categories so FR-3.4 events
                    appear in the Exams & Deadlines sidebar widget.
                    calendar.js updateModalFields() must handle the new values.
                --}}
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select class="form-input" id="evCat" onchange="updateModalFields()">
                        <option value="class">📗 Class</option>
                        <option value="group">👥 Study Group</option>
                        <option value="exam">📝 Exam</option>
                        <option value="deadline">⏰ Deadline</option>
                        <option value="event">📅 Other Event</option>
                    </select>
                </div>

                {{-- Subject (FR-3.3: color-coded subjects) --}}
                {{--
                    NEW FIELD: Links event to a subject so the correct color is applied
                    on the calendar. calendar.js must populate this <select> with the
                    user's subjects from Supabase (user_subject_colors table).
                    Selecting a subject auto-applies that subject's color to the event dot.
                --}}
                <div class="form-group">
                    <label class="form-label">Subject</label>
                    <select class="form-input" id="evSubject">
                        <option value="">— None —</option>
                        {{-- Populated dynamically by calendar.js --}}
                    </select>
                </div>

                {{-- Date --}}
                <div class="form-group">
                    <label class="form-label">Date <span style="color:#dc2626">*</span></label>
                    <input type="date" class="form-input" id="evDate">
                </div>

                {{-- Time fields (shown/hidden by category via updateModalFields()) --}}
                <div id="timeFieldClass" class="form-group" style="display:none;">
                    <label class="form-label">Start Time <span style="color:#dc2626">*</span></label>
                    <input type="time" class="form-input" id="evTimeStart">
                    <label class="form-label" style="margin-top:8px;">End Time (optional)</label>
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

                {{-- Reminder toggle (FR-3.6) --}}
                {{--
                    NEW FIELD: FR-3.6 — "receive reminder notifications."
                    When enabled, notifications.js (or a server-side job) should
                    send a push/email reminder before the event.

                    Minimum implementation in notifications.js / calendar.js:
                      - Save reminder_minutes to the event row in Supabase
                        (events table: add column reminder_minutes INT NULL).
                      - A background job or Supabase Edge Function checks
                        upcoming events and fires notifications.
                --}}
                <div class="form-group" style="border-top:1px solid var(--border);padding-top:12px;margin-top:4px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" id="evReminder" style="width:16px;height:16px;cursor:pointer;"
                            onchange="document.getElementById('reminderOpts').style.display=this.checked?'block':'none'">
                        <label for="evReminder" class="form-label" style="margin:0;cursor:pointer;">
                            🔔 Set reminder (FR-3.6)
                        </label>
                    </div>
                    <div id="reminderOpts" style="display:none;margin-top:10px;">
                        <label class="form-label">Remind me</label>
                        <select class="form-input" id="evReminderMinutes" style="margin-top:4px;">
                            <option value="10">10 minutes before</option>
                            <option value="30" selected>30 minutes before</option>
                            <option value="60">1 hour before</option>
                            <option value="1440">1 day before</option>
                        </select>
                    </div>
                </div>

                {{-- Recurring --}}
                <div class="form-group" style="display:flex;align-items:center;gap:8px;">
                    <input type="checkbox" id="evRecur" style="width:16px;height:16px;cursor:pointer;">
                    <label for="evRecur" class="form-label" style="margin:0;cursor:pointer;">Repeat / Recurring</label>
                </div>
                <div id="recurOpts"
                    style="display:none;padding:12px;background:var(--bg-main);border-radius:10px;margin-top:4px;">
                    <label class="form-label">Repeat on days</label>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;">
                        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                            <button type="button" class="rday"
                                data-d="{{ $day }}">{{ $day }}</button>
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
     TASK MODAL (Add / Edit Task)
     Tasks shown on the calendar still use the 3-status system (FR-4.4).
═══════════════════════════════════════════════════════════ --}}
    <div class="modal-overlay" id="taskModal">
        <div class="modal ev-modal">
            <div class="modal-header">
                <span class="modal-title" id="taskModalTitle">Add Task</span>
                <button class="modal-close" id="btnTaskModalClose">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Task Title <span style="color:#dc2626">*</span></label>
                    <input type="text" class="form-input" id="taskTitle" placeholder="What needs to be done?">
                </div>

                {{-- Priority (FR-4.2) --}}
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <div style="display:flex;gap:8px;margin-top:4px;">
                        <label class="priority-option" data-p="low">
                            <input type="radio" name="taskPriority" value="low" checked style="display:none;">
                            <span>🟢 Low</span>
                        </label>
                        <label class="priority-option" data-p="medium">
                            <input type="radio" name="taskPriority" value="medium" style="display:none;">
                            <span>🟡 Medium</span>
                        </label>
                        <label class="priority-option" data-p="high">
                            <input type="radio" name="taskPriority" value="high" style="display:none;">
                            <span>🔴 High</span>
                        </label>
                    </div>
                </div>

                {{-- Status (FR-4.4) --}}
                {{--
                    CHANGE: Added status field with To-do / In Progress / Done
                    so tasks opened from the calendar can have their status updated.
                    calendar.js must save task.status to Supabase when saving.
                --}}
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-input" id="taskStatus">
                        <option value="todo">To-do</option>
                        <option value="in_progress">In Progress</option>
                        <option value="done">Done</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Due Date (optional)</label>
                    <input type="date" class="form-input" id="taskDueDate">
                </div>
                <div class="form-group">
                    <label class="form-label">Due Time (optional)</label>
                    <input type="time" class="form-input" id="taskDueTime">
                </div>

                {{-- Subject tag (FR-4.5) --}}
                {{--
                    CHANGE: Renamed from "Label / Tag" to "Subject" to align with FR-4.5
                    and the calendar's subject color system (FR-3.3).
                    The input keeps the same id="taskLabel" so calendar.js doesn't break.
                --}}
                <div class="form-group">
                    <label class="form-label">Subject tag (optional)</label>
                    <input type="text" class="form-input" id="taskLabel" placeholder="e.g. Math, Biology, History…">
                </div>

                <div class="form-group">
                    <label class="form-label">Notes (optional)</label>
                    <textarea class="form-input" id="taskNotes" rows="2" placeholder="Additional details…"
                        style="resize:vertical;"></textarea>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-del-ev" id="btnDelTask" style="display:none;">🗑 Delete</button>
                <div style="display:flex;gap:8px;margin-left:auto;">
                    <button class="btn-cancel" id="btnTaskModalCancel">Cancel</button>
                    <button class="btn-save" id="btnSaveTask">Save Task</button>
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
                <span class="modal-title" id="confirmTitle">Delete</span>
                <button class="modal-close" id="btnConfirmClose">✕</button>
            </div>
            <div class="modal-body">
                <p id="confirmBody" style="color:var(--text-secondary);line-height:1.6;"></p>
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" id="btnConfirmCancel">Cancel</button>
                <button class="btn-danger" id="btnConfirmDel">Yes, Delete</button>
            </div>
        </div>
    </div>

    {{-- ── CONFIG ────────────────────────────────────────────────────────────── --}}
    <script>
        const SB_URL = '{{ $supabaseUrl }}';
        const SB_ANON = '{{ $supabaseAnonKey }}';
        const SB_SVC = '{{ $supabaseSvcKey }}';
        const UID = '{{ $userId }}';
    </script>
    <script src="{{ asset('js/studyhub-core.js') }}"></script>
    <script src="{{ asset('js/calendar.js') }}"></script>
    <script src="{{ asset('js/notifications.js') }}"></script>

    {{-- ── INJECT CALENDAR ACTIONS INTO THE TOP-BAR ────────────────────────── --}}
    <script>
        (function() {
            const topBar = document.querySelector('.top-bar');
            if (!topBar) return;

            const actionsEl = document.createElement('div');
            actionsEl.className = 'top-bar-left';
            actionsEl.id = 'calTopBarLeft';
            actionsEl.innerHTML = `
                <button class="btn-add-event" id="btnAdd">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                         style="width:15px;height:15px">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add Event
                </button>
                <button class="btn-select-mode" id="btnSelectMode">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         style="width:15px;height:15px">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                    Select
                </button>
                <div class="bulk-bar" id="bulkBar">
                    <span class="bulk-bar-count" id="bulkCount">0 selected</span>
                    <button class="btn-bulk-cancel" id="btnBulkCancel">Cancel</button>
                    <button class="btn-bulk-delete" id="btnBulkDelete">🗑 Delete Selected</button>
                </div>
            `;

            const topBarRight = topBar.querySelector('.top-bar-right');
            if (topBarRight) {
                topBar.insertBefore(actionsEl, topBarRight);
            } else {
                topBar.insertBefore(actionsEl, topBar.firstChild);
            }
        })();
    </script>

    {{-- ── Recurring checkbox toggle (kept from original) ─────────────────── --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const recurCheck = document.getElementById('evRecur');
            if (recurCheck) {
                recurCheck.addEventListener('change', function() {
                    document.getElementById('recurOpts').style.display =
                        this.checked ? 'block' : 'none';
                });
            }
        });
    </script>

@endsection
