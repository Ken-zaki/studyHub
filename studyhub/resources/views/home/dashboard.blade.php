{{-- resources/views/home/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', 'Dashboard – StudyHub')

@php $activeNav = 'dashboard'; @endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/user_dashboard.css') }}">
@endpush

@section('content')

    @include('layouts.sidebar')

    {{-- ── MAIN ──────────────────────────────────────────────────────────────── --}}
    <main class="main-content">
        <div class="center-column">

            {{-- Calendar card --}}
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
                        <button class="view-btn active" data-view="month">Month</button>
                        <button class="view-btn" data-view="week">Week</button>
                        <button class="view-btn" data-view="day">Day</button>
                    </div>
                </div>

                {{-- Month view --}}
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

                {{-- Week view --}}
                <div id="weekView" style="display:none;">
                    <div id="weekSummaryBar" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;"></div>
                    <div id="weekGrid"></div>
                </div>

                {{-- Day view --}}
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

            {{-- ── CALENDAR SHARING ─────────────────────────────────────────────── --}}
            <div class="upcoming-card">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                    <div class="section-title" style="margin: 0;">📤 Share Calendar</div>
                    <button class="btn-share-calendar" id="btnManageSharing" onclick="openShareModal()" title="Share your calendar with friends" style="background: #6c63ff; color: white; border: none; padding: 6px 14px; border-radius: 8px; cursor: pointer; font-size: 0.87rem; font-weight: 600;">+ Share</button>
                </div>
                <div id="sharingStatus" style="display: flex; flex-direction: column; gap: 10px;">
                    <div style="color: #6b7280; font-size: 0.85rem; text-align: center; padding: 12px;">Loading sharing status…</div>
                </div>
            </div>

            {{-- Shared Calendars Section --}}
            <div class="upcoming-card" id="sharedCalendarsCard" style="display: none;">
                <div class="section-title">👥 Friends' Calendars</div>
                <div id="sharedCalendarsList" style="display: flex; flex-direction: column; gap: 10px;">
                    <div style="color: #6b7280; font-size: 0.85rem; text-align: center; padding: 12px;">Loading…</div>
                </div>
            </div>

            {{-- ── TASK MANAGER ─────────────────────────────────────────────────── --}}
            <div class="calendar-card" id="taskManagerCard">
                <div class="cal-header"
                    style="border-bottom:1px solid var(--border);padding-bottom:16px;margin-bottom:16px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:18px;">✅</span>
                        <h2 style="font-size:17px;font-weight:600;margin:0;color:var(--text-primary)">Task Manager</h2>
                        <span class="task-count-badge" id="taskCountBadge">0</span>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <select class="task-filter-select" id="taskSort">
                            <option value="due">Sort: Due Date</option>
                            <option value="priority">Sort: Priority</option>
                            <option value="label">Sort: Label</option>
                            <option value="created">Sort: Created</option>
                        </select>
                        <select class="task-filter-select" id="taskFilterPriority">
                            <option value="all">All Priorities</option>
                            <option value="high">🔴 High</option>
                            <option value="medium">🟡 Medium</option>
                            <option value="low">🟢 Low</option>
                        </select>
                        <select class="task-filter-select" id="taskFilterStatus">
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="all">All Tasks</option>
                        </select>
                    </div>
                </div>

                {{-- Progress bar --}}
                <div id="taskProgress" style="margin-bottom:16px;display:none;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <span style="font-size:12px;color:var(--text-secondary);">Progress</span>
                        <span style="font-size:12px;font-weight:600;color:var(--text-primary);" id="taskProgressLabel">0 / 0
                            done</span>
                    </div>
                    <div style="height:6px;background:var(--border);border-radius:99px;overflow:hidden;">
                        <div id="taskProgressBar"
                            style="height:100%;background:#0f766e;border-radius:99px;width:0%;transition:width .4s ease;">
                        </div>
                    </div>
                </div>

                {{-- Label filter chips --}}
                <div id="taskLabelChips" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;"></div>

                {{-- Task list --}}
                <div id="taskList">
                    <div class="state-box">
                        <div class="spinner"></div>Loading…
                    </div>
                </div>
            </div>

        </div>{{-- /.center-column --}}

        {{-- Right sidebar (dashboard widgets only) --}}
        <aside class="right-sidebar">
            <div class="widget-card">
                <div class="widget-title">⏰ Deadlines</div>
                <div id="deadlinesList">
                    <div class="state-box">
                        <div class="spinner"></div>
                    </div>
                </div>
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
                <button class="popover-add-btn" id="btnPopAdd" style="margin-top:0;width:auto;padding:6px 12px;">+
                    Add</button>
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
                <div class="form-group">
                    <label class="form-label">Title <span style="color:#dc2626">*</span></label>
                    <input type="text" class="form-input" id="evTitle" placeholder="Event title…">
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select class="form-input" id="evCat" onchange="updateModalFields()">
                        <option value="class">📗 Class</option>
                        <option value="group">👥 Study Group</option>
                        <option value="event">📅 Event</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date <span style="color:#dc2626">*</span></label>
                    <input type="date" class="form-input" id="evDate">
                </div>
                <div id="timeFieldClass" class="form-group" style="display:none;">
                    <label class="form-label">Start Time <span style="color:#dc2626">*</span></label>
                    <input type="time" class="form-input" id="evTimeStart">
                    <label class="form-label" style="margin-top:8px;">End Time <span
                            style="color:#dc2626">*</span></label>
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
                <div class="form-group">
                    <label class="form-label">Description (optional)</label>
                    <textarea class="form-input" id="evDesc" rows="2" placeholder="Notes…" style="resize:vertical;"></textarea>
                </div>
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
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <div style="display:flex;gap:8px;margin-top:4px;">
                        <label class="priority-option" data-p="low">
                            <input type="radio" name="taskPriority" value="low" checked style="display:none;">
                            <span>🟢 Low</span>
                        </label>
                        <label class="priority-option sel" data-p="medium">
                            <input type="radio" name="taskPriority" value="medium" style="display:none;">
                            <span>🟡 Medium</span>
                        </label>
                        <label class="priority-option" data-p="high">
                            <input type="radio" name="taskPriority" value="high" style="display:none;">
                            <span>🔴 High</span>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Due Date (optional)</label>
                    <input type="date" class="form-input" id="taskDueDate">
                </div>
                <div class="form-group">
                    <label class="form-label">Due Time (optional)</label>
                    <input type="time" class="form-input" id="taskDueTime">
                </div>
                <div class="form-group">
                    <label class="form-label">Label / Tag (optional)</label>
                    <input type="text" class="form-input" id="taskLabel" placeholder="e.g. Math, Project, Reading…">
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
        const SB_URL = '{{ config('services.supabase.url') }}';
        const SB_ANON = '{{ config('services.supabase.anon_key') }}';
        const SB_SVC = '{{ config('services.supabase.service_key') }}';
        const UID = '{{ session('user_id') }}';
    </script>
    <script src="{{ asset('js/user_dashboard.js') }}"></script>
    <script src="{{ asset('js/notifications.js') }}"></script>

    {{-- ── CALENDAR SHARING MODAL ───────────────────────────────────────────── --}}
    <div class="modal" id="shareCalendarModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;">
        <div class="modal-content" style="width: 90%; max-width: 500px; background: var(--bg-secondary); border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--border);">
                <h3 style="margin: 0; color: var(--text-primary);">Share Your Calendar</h3>
                <button type="button" class="close-modal" onclick="closeShareModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-secondary);">✕</button>
            </div>
            <div class="modal-body" style="padding: 20px; max-height: 500px; overflow-y: auto;">

                {{-- Tabs for Different Sections --}}
                <div style="display: flex; gap: 0; margin-bottom: 20px; border-bottom: 2px solid var(--border);">
                    <button class="tab-btn active" onclick="switchShareTab('friends')" data-tab="friends" style="flex: 1; padding: 12px 10px; background: none; border: none; border-bottom: 3px solid #6c63ff; color: var(--text-primary); cursor: pointer; font-weight: 600; font-size: 0.9rem;">👤 Friends</button>
                    <button class="tab-btn" onclick="switchShareTab('groups')" data-tab="groups" style="flex: 1; padding: 12px 10px; background: none; border: none; color: #6b7280; cursor: pointer; font-weight: 600; font-size: 0.9rem;">👥 Groups</button>
                    <button class="tab-btn" onclick="switchShareTab('requests')" data-tab="requests" style="flex: 1; padding: 12px 10px; background: none; border: none; color: #6b7280; cursor: pointer; font-weight: 600; font-size: 0.9rem;">📬 Requests <span id="pendingCount" style="background: #ef4444; color: white; border-radius: 50%; width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; margin-left: 4px;">0</span></button>
                </div>

                {{-- Tab 1: Share with Friends --}}
                <div id="shareFriendsTab" class="share-tab">
                    <div style="margin-bottom: 12px; font-size: 0.85rem; color: #6b7280;">Select a friend to share your calendar with</div>
                    <div id="friendsList" style="display: flex; flex-direction: column; gap: 10px; max-height: 300px; overflow-y: auto;">
                        <div style="text-align: center; padding: 30px 20px; color: #6b7280;">
                            <div style="font-size: 3rem; margin-bottom: 8px;">⏳</div>
                            <div>Loading friends…</div>
                        </div>
                    </div>
                </div>

                {{-- Tab 2: Share with Study Groups --}}
                <div id="shareGroupsTab" class="share-tab" style="display: none;">
                    <div style="margin-bottom: 12px; font-size: 0.85rem; color: #6b7280;">Share your calendar with study groups</div>
                    <div id="groupsList" style="display: flex; flex-direction: column; gap: 10px; max-height: 300px; overflow-y: auto;">
                        <div style="text-align: center; padding: 30px 20px; color: #6b7280;">
                            <div style="font-size: 3rem; margin-bottom: 8px;">⏳</div>
                            <div>Loading study groups…</div>
                        </div>
                    </div>
                </div>

                {{-- Tab 3: Share Requests --}}
                <div id="shareRequestsTab" class="share-tab" style="display: none;">
                    <div style="margin-bottom: 12px; font-size: 0.85rem; color: #6b7280;">Calendar sharing requests from friends</div>
                    <div id="shareRequestsList" style="display: flex; flex-direction: column; gap: 10px; max-height: 300px; overflow-y: auto;">
                        <div style="text-align: center; padding: 30px 20px; color: #6b7280;">
                            <div style="font-size: 3rem; margin-bottom: 8px;">✨</div>
                            <div>No pending requests</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- ── CALENDAR SHARING SCRIPTS ─────────────────────────────────────────── --}}
    <script>
        // Sharing modal functions
        function openShareModal() {
            const modal = document.getElementById('shareCalendarModal');
            modal.style.display = 'flex';
            console.log('[Calendar Sharing] Modal opened');

            // Load initial data
            loadFriendsForSharing();
            loadStudyGroups();
            loadShareRequests();
        }

        function closeShareModal() {
            document.getElementById('shareCalendarModal').style.display = 'none';
            console.log('[Calendar Sharing] Modal closed');
        }

        function switchShareTab(tabName) {
            console.log('[Calendar Sharing] Switching to tab:', tabName);

            // Hide all tabs
            document.querySelectorAll('.share-tab').forEach(tab => tab.style.display = 'none');
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.style.borderBottomColor = 'transparent';
                btn.style.color = '#6b7280';
            });

            // Show selected tab
            const tabElement = document.getElementById('share' +
                (tabName === 'friends' ? 'Friends' :
                 tabName === 'groups' ? 'Groups' :
                 'Requests') + 'Tab');
            if (tabElement) {
                tabElement.style.display = 'block';
            }

            const tabBtn = document.querySelector(`[data-tab="${tabName}"]`);
            if (tabBtn) {
                tabBtn.style.borderBottomColor = '#6c63ff';
                tabBtn.style.color = 'var(--text-primary)';
            }
        }

        function loadFriendsForSharing() {
            console.log('[Calendar Sharing] Loading friends...');
            fetch('/calendar/sharing/friends')
                .then(r => {
                    console.log('[Calendar Sharing] Friends response status:', r.status);
                    return r.json();
                })
                .then(data => {
                    console.log('[Calendar Sharing] Friends data:', data);

                    if (data.error) {
                        throw new Error(data.error);
                    }

                    if (!data.friends || data.friends.length === 0) {
                        document.getElementById('friendsList').innerHTML = '<div style="text-align: center; padding: 30px 20px; color: #6b7280;"><div style="font-size: 3rem; margin-bottom: 8px;">👥</div>No friends to share with yet</div>';
                        return;
                    }

                    let html = '';
                    data.friends.forEach(friend => {
                        const sharingStatus = friend.sharing_status || 'none';
                        const statusColor = sharingStatus === 'active' ? '#10b981' : sharingStatus === 'pending' ? '#f59e0b' : '#d1d5db';
                        const statusText = sharingStatus === 'active' ? 'Sharing' : sharingStatus === 'pending' ? 'Pending' : 'Share';
                        const isDisabled = sharingStatus !== 'none';

                        html += `
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--input-bg);">
                                <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                                    ${friend.photo ? `<img src="${friend.photo}" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">` : `<div style="width: 40px; height: 40px; border-radius: 50%; background: #6c63ff; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700;">${friend.initials}</div>`}
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--text-primary);">${friend.name}</div>
                                        <div style="font-size: 0.75rem; color: #6b7280;">@${friend.username}</div>
                                    </div>
                                </div>
                                <button onclick="requestCalendarShare('${friend.id}')" ${isDisabled ? 'disabled' : ''} style="background: ${statusColor}; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: ${isDisabled ? 'not-allowed' : 'pointer'}; font-size: 0.8rem; font-weight: 600; opacity: ${isDisabled ? '0.6' : '1'}; transition: opacity 0.2s;">
                                    ${statusText}
                                </button>
                            </div>
                        `;
                    });

                    document.getElementById('friendsList').innerHTML = html;
                })
                .catch(err => {
                    console.error('[Calendar Sharing] Error loading friends:', err);
                    document.getElementById('friendsList').innerHTML = '<div style="color: #ef4444; padding: 20px; text-align: center;"><div style="font-size: 3rem; margin-bottom: 8px;">⚠️</div>Failed to load friends: ' + err.message + '</div>';
                });
        }

        function loadStudyGroups() {
            console.log('[Calendar Sharing] Loading study groups...');
            fetch('/study-groups/api/groups')
                .then(r => {
                    console.log('[Calendar Sharing] Study groups response status:', r.status);
                    if (!r.ok) throw new Error('Failed to fetch groups');
                    return r.json();
                })
                .then(data => {
                    console.log('[Calendar Sharing] Study groups data:', data);
                    renderStudyGroupsList(data.groups || []);
                })
                .catch(err => {
                    console.error('[Calendar Sharing] Error loading study groups:', err);
                    document.getElementById('groupsList').innerHTML = '<div style="text-align: center; padding: 30px 20px; color: #6b7280;"><div style="font-size: 3rem; margin-bottom: 8px;">👥</div>No study groups found or error loading groups</div>';
                });
        }

        function renderStudyGroupsList(groups) {
            if (!groups || groups.length === 0) {
                document.getElementById('groupsList').innerHTML = '<div style="text-align: center; padding: 30px 20px; color: #6b7280;"><div style="font-size: 3rem; margin-bottom: 8px;">📚</div>No study groups yet</div>';
                return;
            }

            let html = '';
            groups.forEach(group => {
                html += `
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--input-bg);">
                        <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                            ${group.photo ? `<img src="${group.photo}" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover;">` : `<div style="width: 40px; height: 40px; border-radius: 8px; background: #8b5cf6; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem;">📚</div>`}
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: var(--text-primary);">${group.name}</div>
                                <div style="font-size: 0.75rem; color: #6b7280;">${group.is_private === '1' ? '🔒 Private' : '🌐 Public'} • ${group.member_count || 0} member${group.member_count !== 1 ? 's' : ''}</div>
                            </div>
                        </div>
                        <button onclick="shareCalendarWithGroup('${group.id}')" style="background: #8b5cf6; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600;">Share</button>
                    </div>
                `;
            });

            document.getElementById('groupsList').innerHTML = html;
        }

        function requestCalendarShare(friendId) {
            console.log('[Calendar Sharing] Requesting share with friend:', friendId);
            fetch(`/calendar/sharing/request/${friendId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                }
            })
                .then(r => {
                    console.log('[Calendar Sharing] Request share response status:', r.status);
                    return r.json();
                })
                .then(data => {
                    console.log('[Calendar Sharing] Request share response:', data);
                    if (data.success) {
                        loadFriendsForSharing();
                        loadSharingStatus();
                        alert('✅ Share request sent!');
                    } else {
                        alert('❌ Error: ' + (data.error || 'Failed to send request'));
                    }
                })
                .catch(err => {
                    console.error('[Calendar Sharing] Error requesting share:', err);
                    alert('❌ Error: ' + err.message);
                });
        }

        function shareCalendarWithGroup(groupId) {
            console.log('[Calendar Sharing] Sharing with group:', groupId);
            const message = prompt('Add a message (optional):', '');
            if (message === null) return; // User cancelled

            fetch(`/calendar/sharing/group/${groupId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ message: message || '' })
            })
                .then(r => {
                    console.log('[Calendar Sharing] Group share response status:', r.status);
                    return r.json();
                })
                .then(data => {
                    console.log('[Calendar Sharing] Group share response:', data);
                    if (data.success) {
                        alert('✅ Calendar shared with the study group!');
                        loadSharingStatus();
                    } else {
                        alert('❌ Error: ' + (data.error || 'Failed to share'));
                    }
                })
                .catch(err => {
                    console.error('[Calendar Sharing] Error sharing with group:', err);
                    alert('❌ Error: ' + err.message);
                });
        }

        function loadShareRequests() {
            console.log('[Calendar Sharing] Loading share requests...');
            fetch('/calendar/sharing/requests')
                .then(r => {
                    console.log('[Calendar Sharing] Requests response status:', r.status);
                    return r.json();
                })
                .then(data => {
                    console.log('[Calendar Sharing] Requests data:', data);
                    const requests = data.requests || [];
                    document.getElementById('pendingCount').textContent = requests.length;

                    if (requests.length === 0) {
                        document.getElementById('shareRequestsList').innerHTML = '<div style="text-align: center; padding: 30px 20px; color: #6b7280;"><div style="font-size: 3rem; margin-bottom: 8px;">✨</div>No pending requests</div>';
                        return;
                    }

                    let html = '';
                    requests.forEach(req => {
                        html += `
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--input-bg);">
                                <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                                    ${req.requester_photo ? `<img src="${req.requester_photo}" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">` : `<div style="width: 40px; height: 40px; border-radius: 50%; background: #6c63ff; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700;">${req.requester_name.substring(0, 2).toUpperCase()}</div>`}
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--text-primary);">${req.requester_name}</div>
                                        <div style="font-size: 0.75rem; color: #6b7280;">@${req.requester_username}</div>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 6px;">
                                    <button onclick="acceptShareRequest('${req.id}')" style="background: #10b981; color: white; border: none; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 600;">Accept</button>
                                    <button onclick="rejectShareRequest('${req.id}')" style="background: #ef4444; color: white; border: none; padding: 6px 10px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 600;">Reject</button>
                                </div>
                            </div>
                        `;
                    });

                    document.getElementById('shareRequestsList').innerHTML = html;
                })
                .catch(err => {
                    console.error('[Calendar Sharing] Error loading requests:', err);
                    document.getElementById('shareRequestsList').innerHTML = '<div style="color: #ef4444; padding: 20px; text-align: center;">Failed to load requests</div>';
                });
        }

        function acceptShareRequest(requestId) {
            console.log('[Calendar Sharing] Accepting request:', requestId);
            fetch(`/calendar/sharing/requests/${requestId}/accept`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            })
                .then(r => r.json())
                .then(data => {
                    console.log('[Calendar Sharing] Accept response:', data);
                    if (data.success) {
                        loadShareRequests();
                        loadSharedCalendars();
                        loadSharingStatus();
                    } else {
                        alert('Error accepting request: ' + (data.error || 'Unknown'));
                    }
                })
                .catch(err => console.error('[Calendar Sharing] Error accepting:', err));
        }

        function rejectShareRequest(requestId) {
            console.log('[Calendar Sharing] Rejecting request:', requestId);
            fetch(`/calendar/sharing/requests/${requestId}/reject`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            })
                .then(r => r.json())
                .then(data => {
                    console.log('[Calendar Sharing] Reject response:', data);
                    if (data.success) {
                        loadShareRequests();
                        loadSharingStatus();
                    } else {
                        alert('Error rejecting request');
                    }
                })
                .catch(err => console.error('[Calendar Sharing] Error rejecting:', err));
        }

        function loadSharingStatus() {
            console.log('[Calendar Sharing] Loading sharing status...');
            fetch('/calendar/sharing/friends')
                .then(r => r.json())
                .then(data => {
                    console.log('[Calendar Sharing] Status data:', data);
                    const friends = data.friends || [];
                    const activeShares = friends.filter(f => f.sharing_status === 'active');
                    const pendingRequests = friends.filter(f => f.sharing_status === 'pending');

                    let html = '';
                    if (activeShares.length === 0 && pendingRequests.length === 0) {
                        html = '<div style="color: #6b7280; font-size: 0.85rem; text-align: center; padding: 16px;">No active shares or pending requests</div>';
                    } else {
                        if (activeShares.length > 0) {
                            html += '<div style="font-size: 0.85rem; font-weight: 600; color: #10b981; margin-bottom: 12px;">📤 Sharing Calendar With:</div>';
                            activeShares.forEach(f => {
                                html += `
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: rgba(16, 185, 129, 0.1); border-radius: 6px; margin-bottom: 8px;">
                                        <span style="font-size: 0.85rem; color: var(--text-primary);">${f.name}</span>
                                        <button onclick="revokeShare('${f.id}')" style="background: #ef4444; color: white; border: none; padding: 3px 8px; border-radius: 4px; cursor: pointer; font-size: 0.7rem; font-weight: 600;">Revoke</button>
                                    </div>
                                `;
                            });
                        }
                        if (pendingRequests.length > 0) {
                            html += '<div style="font-size: 0.85rem; font-weight: 600; color: #f59e0b; margin-top: 12px;">⏳ Pending Requests:</div>';
                            pendingRequests.forEach(f => {
                                html += `<div style="font-size: 0.85rem; color: #6b7280; padding: 8px 12px; background: rgba(245, 158, 11, 0.1); border-radius: 4px; margin-bottom: 6px;">${f.name}</div>`;
                            });
                        }
                    }

                    document.getElementById('sharingStatus').innerHTML = html;
                })
                .catch(err => {
                    console.error('[Calendar Sharing] Error loading status:', err);
                });
        }

        function loadSharedCalendars() {
            console.log('[Calendar Sharing] Loading shared calendars...');
            fetch('/calendar/sharing/calendars')
                .then(r => r.json())
                .then(data => {
                    console.log('[Calendar Sharing] Shared calendars data:', data);
                    const calendars = data.calendars || [];
                    const card = document.getElementById('sharedCalendarsCard');

                    if (calendars.length === 0) {
                        card.style.display = 'none';
                        return;
                    }

                    card.style.display = 'block';
                    let html = '';
                    calendars.forEach(cal => {
                        const eventCount = (cal.events || []).length;
                        html += `
                            <div style="padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--input-bg);">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                    <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                                        ${cal.owner_photo ? `<img src="${cal.owner_photo}" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">` : `<div style="width: 36px; height: 36px; border-radius: 50%; background: #6c63ff; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem;">${cal.owner_name.substring(0, 1)}</div>`}
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: var(--text-primary);">${cal.owner_name}'s Calendar</div>
                                            <div style="font-size: 0.8rem; color: #6b7280;">${eventCount} event${eventCount !== 1 ? 's' : ''}</div>
                                        </div>
                                    </div>
                                    <button onclick="revokeReceivedShare('${cal.owner_id}')" style="background: #ef4444; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem; font-weight: 600;">Remove</button>
                                </div>
                            </div>
                        `;
                    });

                    document.getElementById('sharedCalendarsList').innerHTML = html;
                })
                .catch(err => console.error('[Calendar Sharing] Error loading shared calendars:', err));
        }

        function revokeShare(friendId) {
            if (confirm('Stop sharing your calendar with this friend?')) {
                console.log('[Calendar Sharing] Revoking share with:', friendId);
                fetch(`/calendar/sharing/revoke/${friendId}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            loadFriendsForSharing();
                            loadSharingStatus();
                        } else {
                            alert('Error: ' + (data.error || 'Unknown'));
                        }
                    })
                    .catch(err => console.error('[Calendar Sharing] Error revoking:', err));
            }
        }

        function revokeReceivedShare(ownerId) {
            if (confirm('Stop viewing this friend\'s calendar?')) {
                console.log('[Calendar Sharing] Revoking received share from:', ownerId);
                fetch(`/calendar/sharing/revoke-received/${ownerId}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            loadSharedCalendars();
                            loadSharingStatus();
                        } else {
                            alert('Error: ' + (data.error || 'Unknown'));
                        }
                    })
                    .catch(err => console.error('[Calendar Sharing] Error revoking received:', err));
            }
        }

        // Load sharing data on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('[Calendar Sharing] Initializing on page load');
            loadSharingStatus();
            loadSharedCalendars();
        });

        // Close modal when clicking outside
        document.getElementById('shareCalendarModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeShareModal();
        });
    </script>

    {{-- ── INJECT CALENDAR ACTIONS INTO THE SIDEBAR TOP-BAR ───────────────────
     The sidebar partial always renders .top-bar with notification + avatar
     on the right. We move our calendar action buttons INTO the left side
     of that same top-bar so there's only ever ONE top-bar on the page.
──────────────────────────────────────────────────────────────────────────── --}}
    <script>
        (function() {
            const topBar = document.querySelector('.top-bar');
            if (!topBar) return;

            // Build the actions container
            const actionsEl = document.createElement('div');
            actionsEl.className = 'top-bar-left';
            actionsEl.id = 'calTopBarLeft';
            actionsEl.innerHTML = `
        <button class="btn-add-event" id="btnAdd">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:15px;height:15px">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add Event
        </button>
        <button class="btn-add-event" id="btnAddTask" style="background:var(--accent,#dc2626);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:15px;height:15px">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add Task
        </button>
        <button class="btn-select-mode" id="btnSelectMode">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px">
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
    `;

            // Insert before the right-side items (notification bell etc.)
            const topBarRight = topBar.querySelector('.top-bar-right');
            if (topBarRight) {
                topBar.insertBefore(actionsEl, topBarRight);
            } else {
                topBar.insertBefore(actionsEl, topBar.firstChild);
            }
        })();
    </script>

@endsection
