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

                        <select id="taskFilterStatus"
                            style="padding:6px 10px;border-radius:8px;border:1px solid var(--border);
                                       font-size:13px;font-family:inherit;color:var(--text-secondary);
                                       background:var(--bg-main);cursor:pointer;outline:none;">
                            <option value="all">All Tasks</option>
                            <option value="to-do">To-do</option>
                            <option value="in-progress">In Progress</option>
                            <option value="done">Done</option>
                        </select>
                    </div>
                </div>

                {{-- Progress Bar (FR-4.4) --}}
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
                <div id="taskLabelChips" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;min-height:4px;">
                </div>

                {{-- Status tab strip (FR-4.4) --}}
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
                    <button data-status="to-do" onclick="setStatusTab('to-do')" class="status-tab"
                        style="padding:7px 14px;border:none;background:none;
                               font-size:13px;font-weight:600;cursor:pointer;
                               color:var(--text-secondary);border-bottom:2px solid transparent;
                               margin-bottom:-2px;transition:.15s;">
                        To-do
                    </button>
                    <button data-status="in-progress" onclick="setStatusTab('in-progress')" class="status-tab"
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
                <div id="taskList">
                    <div class="state-box" style="text-align:center;padding:40px 20px;color:var(--text-light);">
                        Loading tasks…
                    </div>
                </div>

            </div>{{-- /taskManagerCard --}}

        </div>{{-- /center-column --}}


        {{-- ── RIGHT SIDEBAR ──────────────────────────────────────────── --}}
        <aside class="right-sidebar">

            {{-- Quick Add Task --}}
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
            <div class="card" style="margin-bottom:20px;">
                <h3 class="card-title" style="margin-bottom:12px;">Overview</h3>
                <div id="taskSidebarStats" style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;">
                    <div
                        style="text-align:center;padding:10px 4px;border-radius:10px;
                                background:var(--bg-main);border:1px solid var(--border);">
                        <div id="statTodo" style="font-size:20px;font-weight:700;color:var(--primary,#1a5f7a);">–</div>
                        <div style="font-size:10px;font-weight:600;color:var(--text-light);margin-top:2px;">To-do</div>
                    </div>
                    <div
                        style="text-align:center;padding:10px 4px;border-radius:10px;
                                background:var(--bg-main);border:1px solid var(--border);">
                        <div id="statInProgress" style="font-size:20px;font-weight:700;color:#8b5cf6;">–</div>
                        <div style="font-size:10px;font-weight:600;color:var(--text-light);margin-top:2px;">In Progress
                        </div>
                    </div>
                    <div
                        style="text-align:center;padding:10px 4px;border-radius:10px;
                                background:var(--bg-main);border:1px solid var(--border);">
                        <div id="statDone" style="font-size:20px;font-weight:700;color:var(--secondary,#2a9d8f);">–</div>
                        <div style="font-size:10px;font-weight:600;color:var(--text-light);margin-top:2px;">Done</div>
                    </div>
                </div>
            </div>

            {{-- Upcoming Deadlines --}}
            <div class="card">
                <h3 class="card-title" style="margin-bottom:14px;">Upcoming Deadlines</h3>
                <div id="deadlinesList">
                    <div style="text-align:center;padding:20px 0;color:var(--text-light);font-size:13px;">
                        Loading…
                    </div>
                </div>
            </div>

            {{-- Subject Tags Card --}}
            <div class="card" id="subjectTagsCard" style="margin-bottom:20px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                    <h3 class="card-title" style="margin:0;">Subject Tags</h3>
                    <button id="btnAddSubjectTag"
                        style="display:flex;align-items:center;gap:5px;padding:5px 10px;
                               border-radius:8px;border:none;font-size:12px;font-weight:700;
                               background:var(--primary,#1a5f7a);color:#fff;cursor:pointer;transition:.15s;"
                        onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12"
                            height="12">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        Add Subject
                    </button>
                </div>
                <div id="subjectTagsList"
                    style="display:flex;flex-direction:column;gap:6px;
                           max-height:220px;overflow-y:auto;padding-right:2px;">
                    <div style="font-size:12px;color:var(--text-light);text-align:center;padding:18px 0;">
                        Loading subjects…
                    </div>
                </div>
            </div>

        </aside>{{-- /right-sidebar --}}

    </main>


    {{-- ══════════════════════════════════════════════════════════════════
         TASK MODAL (Add / Edit)
         Status is intentionally NOT here — it lives as an inline dropdown
         on each task row and is changed there directly (no modal needed).
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
                <div class="form-group">
                    <label class="form-label" for="taskLabelSelect">Subject tag</label>
                    <div style="position:relative;">
                        <select id="taskLabelSelect" class="form-input"
                            style="appearance:none;padding-right:32px;cursor:pointer;">
                            <option value="">— None —</option>
                            {{-- Populated by tasks.js loadSubjectsIntoSelect() --}}
                        </select>
                        <span id="taskLabelDot"
                            style="position:absolute;left:12px;top:50%;transform:translateY(-50%);
                                   width:9px;height:9px;border-radius:50%;background:transparent;
                                   pointer-events:none;display:none;"></span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14"
                            height="14"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);
                                   pointer-events:none;color:var(--text-secondary);">
                            <polyline points="6 9 12 15 18 9" />
                        </svg>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="form-group">
                    <label class="form-label" for="taskNotes">Notes</label>
                    <textarea id="taskNotes" class="form-input" rows="2" placeholder="Any extra details…"
                        style="resize:vertical;"></textarea>
                </div>

                {{-- Subtasks (FR-4.1) --}}
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
                    <div id="subtaskList" style="display:flex;flex-direction:column;gap:8px;min-height:4px;">
                        {{-- Populated by tasks.js --}}
                    </div>
                </div>

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


    {{-- ══════════════════════════════════════════════════════════════════
         SUBJECT TAG MODAL (Add / Edit)
    ══════════════════════════════════════════════════════════════════ --}}
    <div id="subjectModal" class="modal-overlay">
        <div class="modal-card" style="max-width:400px;width:100%;">

            <div class="modal-header">
                <h3 id="subjectModalTitle" class="modal-title">Add Subject</h3>
                <button id="btnSubjectModalClose" class="modal-close" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18"
                        height="18">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>

            <div class="modal-body" style="display:flex;flex-direction:column;gap:16px;">

                <div class="form-group">
                    <label class="form-label" for="subjectName">
                        Subject Name <span style="color:var(--accent)">*</span>
                    </label>
                    <input id="subjectName" type="text" class="form-input" placeholder="e.g. Math, Biology, History"
                        autocomplete="off">
                </div>

                <div class="form-group">
                    <label class="form-label">Color</label>
                    <div id="subjectColorPicker"
                        style="display:grid;grid-template-columns:repeat(8,1fr);gap:8px;margin-top:4px;">
                        {{-- Populated by tasks.js --}}
                    </div>
                    <input type="hidden" id="subjectColorValue" value="#9ca3af">
                </div>

                <div
                    style="padding:10px 14px;border-radius:10px;border:1px solid var(--border);
                            background:var(--bg-main);display:flex;align-items:center;gap:8px;">
                    <span style="font-size:12px;color:var(--text-secondary);">Preview:</span>
                    <span id="subjectPreviewChip"
                        style="padding:3px 10px;border-radius:99px;font-size:12px;font-weight:600;
                               background:rgba(156,163,175,.15);color:#9ca3af;
                               display:inline-flex;align-items:center;gap:5px;transition:.2s;">
                        <span id="subjectPreviewDot"
                            style="width:8px;height:8px;border-radius:50%;
                                   background:#9ca3af;display:inline-block;transition:.2s;"></span>
                        <span id="subjectPreviewName">Subject</span>
                    </span>
                </div>

            </div>{{-- /modal-body --}}

            <div class="modal-footer" style="display:flex;align-items:center;gap:8px;margin-top:20px;">
                <button id="btnDelSubject"
                    style="display:none;padding:9px 16px;border-radius:10px;
                           border:1px solid #fca5a5;background:#fff5f5;color:#dc2626;
                           font-size:13px;font-weight:600;cursor:pointer;transition:.15s;"
                    onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fff5f5'">
                    Delete
                </button>
                <div style="flex:1;"></div>
                <button id="btnSubjectModalCancel" class="btn-secondary">Cancel</button>
                <button id="btnSaveSubject" class="btn-primary">Save Subject</button>
            </div>

        </div>
    </div>{{-- /subjectModal --}}


    {{-- ── Supabase config ───────────────────────────────────────────── --}}
    <script>
        const SB_URL = '{{ $supabaseUrl }}';
        const SB_ANON = '{{ $supabaseAnonKey }}';
        const SB_SVC = '{{ $supabaseSvcKey }}';
        const UID = '{{ $userId }}';
    </script>

    {{-- ── JS files ──────────────────────────────────────────────────── --}}
    <script src="{{ asset('js/studyhub-core.js') }}"></script>
    <script src="{{ asset('js/notifications.js') }}"></script>
    <script src="{{ asset('js/tasks.js') }}"></script>

    {{-- ── Status tab strip sync ─────────────────────────────────────── --}}
    {{--
        setStatusTab() keeps the tab strip and the #taskFilterStatus dropdown in sync.
        Status is no longer set inside the modal — it lives as an inline dropdown
        on each task row. setTaskStatus() is therefore not needed here.
    --}}
    <script>
        function setStatusTab(status) {
            const dropdown = document.getElementById('taskFilterStatus');
            if (dropdown) dropdown.value = status;

            document.querySelectorAll('.status-tab').forEach(btn => {
                const active = btn.dataset.status === status;
                btn.style.color = active ? 'var(--primary,#1a5f7a)' : 'var(--text-secondary)';
                btn.style.borderBottom = active ? '2px solid var(--primary,#1a5f7a)' : '2px solid transparent';
            });

            if (typeof filterTasks === 'function') filterTasks();
        }

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
