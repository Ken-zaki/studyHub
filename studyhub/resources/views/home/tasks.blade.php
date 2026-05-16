@extends('layouts.app')
@section('title', 'Tasks – StudyHub')
@php $activeNav = 'tasks'; @endphp
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/tasks.css') }}">
@endpush

@section('content')
    @include('layouts.sidebar')

    <main class="main-content">

        {{-- ── CENTER COLUMN ─────────────────────────────────────────── --}}
        <div class="center-column">

            {{-- Task Manager Card --}}
            <div class="card" id="taskManagerCard">

                {{-- Card Header --}}
                <div class="card-header" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
                    <h2 class="card-title" style="margin:0;flex:1;min-width:0;">
                        Task Manager
                        <span id="taskCountBadge"
                            style="display:inline-flex;align-items:center;justify-content:center;
                                     min-width:22px;height:22px;padding:0 7px;
                                     border-radius:99px;font-size:12px;font-weight:700;
                                     background:var(--primary,#1a5f7a);color:#fff;
                                     vertical-align:middle;margin-left:8px;">0</span>
                    </h2>

                    {{-- Filter Controls --}}
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <select id="taskSort"
                            style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);
                                       font-size:13px;font-family:inherit;color:var(--text-secondary);
                                       background:var(--bg-main);cursor:pointer;outline:none;">
                            <option value="created">Sort: Newest</option>
                            <option value="due">Sort: Due Date</option>
                            <option value="priority">Sort: Priority</option>
                            <option value="label">Sort: Subject</option>
                        </select>

                        <select id="taskFilterPriority"
                            style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);
                                       font-size:13px;font-family:inherit;color:var(--text-secondary);
                                       background:var(--bg-main);cursor:pointer;outline:none;">
                            <option value="all">All Priorities</option>
                            <option value="high">🔴 High</option>
                            <option value="medium">🟡 Medium</option>
                            <option value="low">🟢 Low</option>
                        </select>

                        {{--
                            FR-4.4: Progress monitoring with To-do / In Progress / Done.
                            CHANGE: Replaced "Active / Completed" (2 states) with the
                            three states specified in FR-4.4. "All Tasks" still available.
                            tasks.js must filter tasks by task.status value.
                        --}}
                        <select id="taskFilterStatus"
                            style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);
                                       font-size:13px;font-family:inherit;color:var(--text-secondary);
                                       background:var(--bg-main);cursor:pointer;outline:none;">
                            <option value="all">All Tasks</option>
                            <option value="todo">To-do</option>
                            <option value="in_progress">In Progress</option>
                            <option value="done">Done</option>
                        </select>
                    </div>
                </div>

                {{-- Progress Bar (FR-4.4) --}}
                {{--
                    Shows completion ratio across the current filter.
                    tasks.js calculates: done / total and animates the bar.
                --}}
                <div id="taskProgress" style="display:none;margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <span style="font-size:12px;font-weight:600;color:var(--text-secondary);">Progress</span>
                        <span id="taskProgressLabel" style="font-size:12px;color:var(--text-light);">0 / 0 done</span>
                    </div>
                    <div style="height:6px;border-radius:99px;background:var(--border);overflow:hidden;">
                        <div id="taskProgressBar"
                            style="height:100%;width:0%;border-radius:99px;
                                    background:linear-gradient(90deg,var(--primary,#1a5f7a),var(--secondary,#2a9d8f));
                                    transition:width .4s ease;">
                        </div>
                    </div>
                </div>

                {{-- Subject Chips (FR-4.5) --}}
                {{--
                    CHANGE: Renamed from "Label Chips" to "Subject Chips".
                    FR-4.5 calls these "subject tags" — keeping the label consistent.
                    tasks.js renders one chip per unique task.label value;
                    clicking a chip filters the list to that subject only.
                --}}
                <div id="taskLabelChips" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;min-height:4px;">
                </div>

                {{-- Status tab strip (FR-4.4) --}}
                {{--
                    NEW: Visual kanban-style tab strip so users can switch between
                    To-do / In Progress / Done with one click, mirroring the dropdown filter.
                    tasks.js should sync this strip with #taskFilterStatus.
                --}}
                <div id="statusTabStrip"
                    style="display:flex;gap:4px;margin-bottom:16px;
                           border-bottom:2px solid var(--border);padding-bottom:0;">
                    <button data-status="all" onclick="setStatusTab('all')" class="status-tab active"
                        style="padding:7px 14px;border:none;background:none;
                               font-size:13px;font-weight:600;cursor:pointer;
                               color:var(--primary,#1a5f7a);
                               border-bottom:2px solid var(--primary,#1a5f7a);
                               margin-bottom:-2px;transition:.15s;">
                        All
                    </button>
                    <button data-status="todo" onclick="setStatusTab('todo')" class="status-tab"
                        style="padding:7px 14px;border:none;background:none;
                               font-size:13px;font-weight:600;cursor:pointer;
                               color:var(--text-secondary);border-bottom:2px solid transparent;
                               margin-bottom:-2px;transition:.15s;">
                        To-do
                    </button>
                    <button data-status="in_progress" onclick="setStatusTab('in_progress')" class="status-tab"
                        style="padding:7px 14px;border:none;background:none;
                               font-size:13px;font-weight:600;cursor:pointer;
                               color:var(--text-secondary);border-bottom:2px solid transparent;
                               margin-bottom:-2px;transition:.15s;">
                        In Progress
                    </button>
                    <button data-status="done" onclick="setStatusTab('done')" class="status-tab"
                        style="padding:7px 14px;border:none;background:none;
                               font-size:13px;font-weight:600;cursor:pointer;
                               color:var(--text-secondary);border-bottom:2px solid transparent;
                               margin-bottom:-2px;transition:.15s;">
                        Done
                    </button>
                </div>

                {{-- Task List --}}
                {{--
                    tasks.js renders task rows here.
                    Each row must include:
                      - Checkbox to mark done (updates status → 'done')
                      - Title
                      - Status pill: To-do / In Progress / Done (FR-4.4)
                      - Priority badge: Low / Medium / High (FR-4.2)
                      - Due date (FR-4.3)
                      - Subject tag chip (FR-4.5)
                      - Expand arrow to show subtasks (FR-4.1)
                --}}
                <div id="taskList">
                    <div class="state-box" style="text-align:center;padding:40px 20px;color:var(--text-light);">
                        Loading tasks…
                    </div>
                </div>

            </div>{{-- /taskManagerCard --}}

        </div>{{-- /center-column --}}


        {{-- ── RIGHT SIDEBAR ──────────────────────────────────────────── --}}
        <aside class="right-sidebar">

            {{-- Quick Add Task (FR-4.1) --}}
            <div class="card" style="margin-bottom:20px;">
                <h3 class="card-title" style="margin-bottom:14px;">Quick Add</h3>
                <button onclick="openTaskModal()" class="btn-add-event"
                    style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16"
                        height="16">
                        <line x1="12" y1="5" x2="12" y2="19" />
                        <line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                    Add New Task
                </button>
            </div>

            {{-- Task Overview mini stats (FR-4.4) --}}
            {{--
                NEW CARD: Quick count of tasks by status in the sidebar,
                mirroring the dashboard. Populated by tasks.js.
            --}}
            <div class="card" style="margin-bottom:20px;">
                <h3 class="card-title" style="margin-bottom:12px;">Overview</h3>
                <div id="taskSidebarStats" style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;">
                    <div
                        style="text-align:center;padding:10px 4px;border-radius:10px;
                                background:var(--bg-main);border:1px solid var(--border);">
                        <div id="statTodo" style="font-size:20px;font-weight:700;color:var(--primary,#1a5f7a);">–</div>
                        <div style="font-size:10px;font-weight:600;color:var(--text-light);margin-top:2px;">
                            To-do</div>
                    </div>
                    <div
                        style="text-align:center;padding:10px 4px;border-radius:10px;
                                background:var(--bg-main);border:1px solid var(--border);">
                        <div id="statInProgress" style="font-size:20px;font-weight:700;color:#8b5cf6;">–</div>
                        <div style="font-size:10px;font-weight:600;color:var(--text-light);margin-top:2px;">
                            In Progress</div>
                    </div>
                    <div
                        style="text-align:center;padding:10px 4px;border-radius:10px;
                                background:var(--bg-main);border:1px solid var(--border);">
                        <div id="statDone" style="font-size:20px;font-weight:700;color:var(--secondary,#2a9d8f);">–</div>
                        <div style="font-size:10px;font-weight:600;color:var(--text-light);margin-top:2px;">
                            Done</div>
                    </div>
                </div>
            </div>

            {{-- Upcoming Deadlines (FR-2.2 / FR-4.3) --}}
            <div class="card">
                <h3 class="card-title" style="margin-bottom:14px;">Upcoming Deadlines</h3>
                <div id="deadlinesList">
                    <div style="text-align:center;padding:20px 0;color:var(--text-light);font-size:13px;">
                        Loading…
                    </div>
                </div>
            </div>

        </aside>{{-- /right-sidebar --}}

    </main>


    {{-- ══════════════════════════════════════════════════════════════════
         TASK MODAL (Add / Edit)
         FR-4.1: Task + subtasks
         FR-4.2: Priority (Low / Medium / High)
         FR-4.3: Due date visible
         FR-4.4: Status (To-do / In Progress / Done)
         FR-4.5: Subject tags
    ══════════════════════════════════════════════════════════════════ --}}
    <div id="taskModal" class="modal-overlay">
        <div class="modal-card" style="max-width:480px;width:100%;">

            <div class="modal-header">
                <h3 id="taskModalTitle" class="modal-title">Add Task</h3>
                <button id="btnTaskModalClose" class="modal-close" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18"
                        height="18">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>

            <div class="modal-body" style="display:flex;flex-direction:column;gap:16px;">

                {{-- Title (FR-4.1) --}}
                <div class="form-group">
                    <label class="form-label" for="taskTitle">
                        Task Title <span style="color:var(--accent)">*</span>
                    </label>
                    <input id="taskTitle" type="text" class="form-input" placeholder="What do you need to do?"
                        autocomplete="off">
                </div>

                {{-- Priority (FR-4.2) --}}
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <div style="display:flex;gap:8px;">
                        <label class="priority-option" data-p="high"
                            style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;
                                      padding:8px;border-radius:10px;border:1.5px solid var(--border);
                                      cursor:pointer;font-size:13px;font-weight:600;transition:.15s;">
                            <input type="radio" name="taskPriority" value="high" style="display:none">
                            🔴 High
                        </label>
                        <label class="priority-option" data-p="medium"
                            style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;
                                      padding:8px;border-radius:10px;border:1.5px solid var(--border);
                                      cursor:pointer;font-size:13px;font-weight:600;transition:.15s;">
                            <input type="radio" name="taskPriority" value="medium" style="display:none">
                            🟡 Medium
                        </label>
                        <label class="priority-option sel" data-p="low"
                            style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;
                                      padding:8px;border-radius:10px;border:1.5px solid var(--border);
                                      cursor:pointer;font-size:13px;font-weight:600;transition:.15s;">
                            <input type="radio" name="taskPriority" value="low" checked style="display:none">
                            🟢 Low
                        </label>
                    </div>
                </div>

                {{-- Status (FR-4.4) --}}
                {{--
                    CHANGE: Added Status field — previously missing from the modal entirely.
                    This is the core of FR-4.4: users must be able to set
                    To-do / In Progress / Done on each task.
                    tasks.js must:
                      - Read this value when saving (task.status)
                      - Pre-fill it when editing an existing task
                      - Update the task row's status pill after save
                --}}
                <div class="form-group">
                    <label class="form-label" for="taskStatus">Status</label>
                    <div style="display:flex;gap:8px;">
                        <label class="status-option" data-s="todo"
                            style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;
                                      padding:8px;border-radius:10px;border:1.5px solid var(--border);
                                      cursor:pointer;font-size:13px;font-weight:600;transition:.15s;"
                            onclick="setTaskStatus('todo')">
                            <input type="radio" name="taskStatus" value="todo" checked style="display:none">
                            📋 To-do
                        </label>
                        <label class="status-option" data-s="in_progress"
                            style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;
                                      padding:8px;border-radius:10px;border:1.5px solid var(--border);
                                      cursor:pointer;font-size:13px;font-weight:600;transition:.15s;"
                            onclick="setTaskStatus('in_progress')">
                            <input type="radio" name="taskStatus" value="in_progress" style="display:none">
                            🔄 In Progress
                        </label>
                        <label class="status-option" data-s="done"
                            style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;
                                      padding:8px;border-radius:10px;border:1.5px solid var(--border);
                                      cursor:pointer;font-size:13px;font-weight:600;transition:.15s;"
                            onclick="setTaskStatus('done')">
                            <input type="radio" name="taskStatus" value="done" style="display:none">
                            ✅ Done
                        </label>
                    </div>
                </div>

                {{-- Due Date & Time (FR-4.3) --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label" for="taskDueDate">Due Date</label>
                        <input id="taskDueDate" type="date" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="taskDueTime">Due Time</label>
                        <input id="taskDueTime" type="time" class="form-input">
                    </div>
                </div>

                {{-- Subject Tag (FR-4.5) --}}
                {{--
                    CHANGE: Renamed from "Label" to "Subject" to match FR-4.5
                    and align with the calendar's subject color system.
                    tasks.js can render a color dot next to this field if a matching
                    subject color exists in Supabase (user_subject_colors table).
                --}}
                <div class="form-group">
                    <label class="form-label" for="taskLabel">Subject tag</label>
                    <input id="taskLabel" type="text" class="form-input" placeholder="e.g. Math, Biology, History"
                        autocomplete="off">
                </div>

                {{-- Notes --}}
                <div class="form-group">
                    <label class="form-label" for="taskNotes">Notes</label>
                    <textarea id="taskNotes" class="form-input" rows="2" placeholder="Any extra details…"
                        style="resize:vertical;"></textarea>
                </div>

                {{-- ── Subtasks (FR-4.1) ───────────────────────────────── --}}
                {{--
                    NEW SECTION: FR-4.1 requires tasks AND subtasks.
                    Previously this was completely missing from the modal.

                    tasks.js must:
                      1. Save subtasks to a separate Supabase table:
                           subtasks (id, task_id FK, title, status, created_at)
                      2. Load existing subtasks when editing a task (by task.id)
                      3. addSubtask() appends a row to #subtaskList
                      4. Each subtask row: checkbox (done toggle) + text input + remove btn
                      5. On save: upsert subtasks, delete removed ones

                    The subtask rows are rendered by tasks.js into #subtaskList.
                    This blade only provides the shell + add button.
                --}}
                <div class="form-group" style="border-top:1px solid var(--border);padding-top:14px;margin-top:2px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                        <label class="form-label" style="margin:0;">Subtasks</label>
                        <button type="button" id="btnAddSubtask"
                            onclick="if(typeof addSubtask==='function') addSubtask();"
                            style="font-size:12px;font-weight:700;color:var(--primary,#1a5f7a);
                                   background:none;border:none;cursor:pointer;padding:0;
                                   display:flex;align-items:center;gap:4px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                width="13" height="13">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            Add subtask
                        </button>
                    </div>
                    {{--
                        tasks.js renders subtask rows here.
                        Each row structure (for reference):
                        <div class="subtask-row" data-id="...">
                          <input type="checkbox" class="subtask-check" ...>
                          <input type="text" class="subtask-title form-input" value="...">
                          <button class="subtask-remove">✕</button>
                        </div>
                    --}}
                    <div id="subtaskList" style="display:flex;flex-direction:column;gap:8px;min-height:4px;">
                        {{-- Populated by tasks.js --}}
                    </div>
                </div>
                {{-- /subtasks --}}

            </div>{{-- /modal-body --}}

            <div class="modal-footer" style="display:flex;align-items:center;gap:8px;margin-top:20px;">
                <button id="btnDelTask"
                    style="display:none;padding:9px 16px;border-radius:10px;border:1px solid #fca5a5;
                               background:#fff5f5;color:#dc2626;font-size:13px;font-weight:600;
                               cursor:pointer;transition:.15s;"
                    onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fff5f5'">
                    Delete
                </button>
                <div style="flex:1;"></div>
                <button id="btnTaskModalCancel" class="btn-secondary">Cancel</button>
                <button id="btnSaveTask" class="btn-primary">Save Task</button>
            </div>

        </div>
    </div>{{-- /taskModal --}}


    {{-- ══════════════════════════════════════════════════════════════════
         CONFIRM DELETE MODAL
    ══════════════════════════════════════════════════════════════════ --}}
    <div id="confirmModal" class="modal-overlay">
        <div class="modal-card" style="max-width:420px;width:100%;">

            <div class="modal-header">
                <h3 id="confirmTitle" class="modal-title">Confirm Delete</h3>
                <button id="btnConfirmClose" class="modal-close" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18"
                        height="18">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>

            <div class="modal-body">
                <p id="confirmBody" style="font-size:14px;color:var(--text-secondary);line-height:1.6;margin:0;"></p>
            </div>

            <div class="modal-footer" style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px;">
                <button id="btnConfirmCancel" class="btn-secondary">Cancel</button>
                <button id="btnConfirmDel"
                    style="padding:9px 18px;border-radius:10px;border:none;
                               background:linear-gradient(135deg,#dc2626,#b91c1c);
                               color:#fff;font-size:13px;font-weight:600;cursor:pointer;transition:.15s;">
                    Yes, Delete
                </button>
            </div>

        </div>
    </div>{{-- /confirmModal --}}


    {{-- ── Supabase config ───────────────────────────────────────────── --}}
    <script>
        const SB_URL = '{{ $supabaseUrl }}';
        const SB_ANON = '{{ $supabaseAnonKey }}';
        const SB_SVC = '{{ $supabaseSvcKey }}';
        const UID = '{{ $userId }}';
    </script>

    {{-- ── JS files ──────────────────────────────────────────────────── --}}
    <script src="{{ asset('js/studyhub-core.js') }}"></script>
    <script src="{{ asset('js/tasks.js') }}"></script>
    <script src="{{ asset('js/notifications.js') }}"></script>

    {{-- ── Status tab strip sync + status option highlight ─────────── --}}
    {{--
        setStatusTab() keeps the tab strip and the #taskFilterStatus dropdown in sync.
        setTaskStatus() highlights the selected status option in the modal.
        tasks.js may also call these directly.
    --}}
    <script>
        function setStatusTab(status) {
            // Sync dropdown
            const dropdown = document.getElementById('taskFilterStatus');
            if (dropdown) dropdown.value = status;

            // Sync tab strip highlight
            document.querySelectorAll('.status-tab').forEach(btn => {
                const active = btn.dataset.status === status;
                btn.style.color = active ? 'var(--primary,#1a5f7a)' : 'var(--text-secondary)';
                btn.style.borderBottom = active ? '2px solid var(--primary,#1a5f7a)' : '2px solid transparent';
            });

            // Trigger tasks.js filter if available
            if (typeof filterTasks === 'function') filterTasks();
        }

        function setTaskStatus(status) {
            // Highlight selected status option in the modal
            document.querySelectorAll('.status-option').forEach(opt => {
                const active = opt.dataset.s === status;
                opt.style.borderColor = active ? 'var(--primary,#1a5f7a)' : 'var(--border)';
                opt.style.background = active ? 'rgba(26,95,122,.08)' : 'transparent';
                opt.style.color = active ? 'var(--primary,#1a5f7a)' : 'var(--text-secondary)';
                const radio = opt.querySelector('input[type=radio]');
                if (radio) radio.checked = active;
            });
        }

        // Sync dropdown → tab strip on manual change
        document.addEventListener('DOMContentLoaded', function() {
            const dropdown = document.getElementById('taskFilterStatus');
            if (dropdown) {
                dropdown.addEventListener('change', () => setStatusTab(dropdown.value));
            }
        });
    </script>

    {{-- ── Top-bar injection ─────────────────────────────────────────── --}}
    <script>
        (function() {
            const bar = document.querySelector('.topbar-actions') ||
                document.querySelector('.top-bar .right') ||
                null;
            if (!bar) return;

            const bulkBarHTML = `
            <div id="bulkBar"
                 style="display:none;align-items:center;gap:10px;padding:6px 14px;
                        border-radius:10px;background:var(--bg-card);border:1px solid var(--border);
                        box-shadow:var(--shadow-sm);">
                <span id="bulkCount"
                    style="font-size:13px;font-weight:600;color:var(--text-primary);">0 tasks selected</span>
                <button id="btnBulkCancel"
                    style="padding:5px 12px;border-radius:8px;border:1px solid var(--border);
                           background:var(--bg-main);font-size:12px;font-weight:600;
                           color:var(--text-secondary);cursor:pointer;">Cancel</button>
                <button id="btnBulkDelete"
                    style="padding:5px 12px;border-radius:8px;border:none;
                           background:linear-gradient(135deg,#dc2626,#b91c1c);
                           color:#fff;font-size:12px;font-weight:600;cursor:pointer;">
                    Delete Selected</button>
            </div>`;

            const actionsHTML = `
            <div id="taskPageActions" style="display:flex;align-items:center;gap:8px;">
                ${bulkBarHTML}
                <button id="btnSelectMode"
                    style="display:flex;align-items:center;gap:6px;padding:8px 14px;
                           border-radius:10px;border:1px solid var(--border);
                           background:var(--bg-card);font-size:13px;font-weight:600;
                           color:var(--text-secondary);cursor:pointer;transition:.15s;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         style="width:15px;height:15px">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                    Select
                </button>
                <button id="btnAddTask"
                    onclick="if(typeof openTaskModal==='function') openTaskModal();"
                    style="display:flex;align-items:center;gap:6px;padding:8px 16px;
                           border-radius:10px;border:none;
                           background:linear-gradient(135deg,var(--primary,#1a5f7a),var(--primary-dark,#134d63));
                           color:#fff;font-size:13px;font-weight:600;cursor:pointer;
                           box-shadow:0 2px 8px rgba(26,95,122,.25);transition:.15s;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                         style="width:15px;height:15px">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add Task
                </button>
            </div>`;

            bar.insertAdjacentHTML('beforeend', actionsHTML);

            const style = document.createElement('style');
            style.textContent = `#bulkBar.visible { display:flex !important; }`;
            document.head.appendChild(style);
        })();
    </script>

@endsection
