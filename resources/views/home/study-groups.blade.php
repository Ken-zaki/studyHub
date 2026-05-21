<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Groups - StudyHub</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
    <link rel="stylesheet" href="{{ asset('css/studygroups.css') }}">
</head>

<body>
    @include('layouts.sidebar')

    <main class="main-content-simple sg-fullscreen">
        <div class="sg-layout">

            {{-- ══ GROUP LIST SIDEBAR ══ --}}
            <aside class="sg-sidebar">
                <div class="sg-sidebar-header">
                    <h2>Study Groups</h2>
                    <button class="btn-new-group" id="btnOpenModal" title="Create new group">+</button>
                </div>
                <div class="sg-group-search">
                    <input type="text" id="groupSearch" placeholder="Search groups…" oninput="filterGroups(this.value)">
                </div>
                <div class="sg-group-list" id="groupList">
                    @forelse($groups as $group)
                        <div class="sg-group-item" data-group-id="{{ $group->id }}"
                            data-privacy="{{ $group->is_private ? 'private' : 'public' }}"
                            data-name="{{ strtolower($group->name) }}"
                            onclick="openGroup('{{ $group->id }}', this)">
                            <div class="sg-group-avatar">
                                @if($group->photo)
                                    <img src="{{ $group->photo }}" alt="">
                                @else
                                    {{ strtoupper(substr($group->name, 0, 2)) }}
                                @endif
                            </div>
                            <div class="sg-group-item-wrap">
                                <div class="sg-group-name">{{ $group->name }}</div>
                                <div class="sg-group-meta">
                                    <span class="sg-group-subject">{{ $group->subject ?? 'General' }} · {{ $group->members_count }} members</span>
                                    <span class="privacy-badge {{ $group->is_private ? 'private' : 'public' }}">
                                        {{ $group->is_private ? '🔒' : '🌐' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="no-msg" style="margin-top:24px;">No groups yet.<br>Hit <strong>+</strong> to create one!</div>
                    @endforelse
                </div>
            </aside>

            {{-- ══ MAIN CHAT / CONTENT PANEL ══ --}}
            <section class="sg-chat" id="chatPanel">
                {{-- Empty state --}}
                <div class="sg-empty" id="chatEmpty">
                    <div class="sg-empty-icon">👥</div>
                    <p>Select a group to start collaborating</p>
                </div>

                {{-- CHAT HEADER --}}
                <div class="sg-chat-header" id="chatHeader" style="display:none;">
                    <div class="sg-chat-header-avatar" id="chatAvatar"></div>
                    <div class="sg-chat-header-info">
                        <div class="sg-chat-header-name" id="chatGroupName"></div>
                        <div class="sg-chat-header-members" id="chatGroupMembers"></div>
                    </div>
                    <div class="header-actions">
                        <div class="sg-settings-wrap" id="settingsWrap">
                            <button class="btn-settings" id="btnSettings" onclick="toggleSettings(event)" title="Group settings">⚙</button>
                            <div class="settings-dropdown" id="settingsDropdown">
                                {{-- Group banner --}}
                                <div class="sd-group-banner">
                                    <div class="sd-avatar-wrap" id="sdAvatarWrap" title="Change group photo">
                                        <div class="sd-avatar" id="sdAvatar"></div>
                                        <div class="sd-avatar-overlay">📷</div>
                                        <input type="file" id="groupPhotoInput" accept="image/*">
                                    </div>
                                    <div class="sd-group-title">
                                        <div class="sd-group-title-name" id="sdGroupName">—</div>
                                        <div class="sd-group-title-sub" id="sdGroupSub">—</div>
                                    </div>
                                </div>
                                {{-- Rename (admin only) --}}
                                <div class="sd-section" id="sdRenameSection">
                                    <div class="sd-section-label">Rename Group</div>
                                    <div class="sd-rename-row">
                                        <input type="text" class="sd-rename-input" id="sdRenameInput" placeholder="New name…">
                                        <button class="btn-sd-rename" onclick="renameGroup()">Save</button>
                                    </div>
                                </div>
                                {{-- Members --}}
                                <div class="sd-section">
                                    <div class="sd-section-label" style="display:flex;align-items:center;justify-content:space-between;">
                                        <span>Members</span>
                                        <button onclick="openMembersModal()" style="background:none;border:none;color:#6c63ff;font-size:0.7rem;cursor:pointer;font-weight:600;">See all →</button>
                                    </div>
                                    <div class="sd-members-list" id="sdMembersList">
                                        <div style="color:#4b5563;font-size:0.76rem;padding:4px 6px;">Loading…</div>
                                    </div>
                                </div>
                                {{-- Delete (admin only) --}}
                                <div class="sd-section" id="sdDeleteSection">
                                    <button class="btn-sd-delete" onclick="deleteGroup()">🗑️ Delete Group</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- TABS --}}
                <div class="sg-tabs" id="sgTabs" style="display:none;">
                    <button class="sg-tab active" id="tabMessages"  onclick="switchTab('messages')">💬 Chat</button>
                    <button class="sg-tab"         id="tabTasks"     onclick="switchTab('tasks')">✅ Tasks</button>
                    <button class="sg-tab"         id="tabResources" onclick="switchTab('resources')">📁 Resources</button>
                    <button class="sg-tab"         id="tabNotes"     onclick="switchTab('notes')">📝 Notes</button>
                    <button class="sg-tab"         id="tabCalendars" onclick="switchTab('calendars')">📅 Calendar</button>
                    {{-- Admin-only tab --}}
                    <button class="sg-tab admin-tab" id="tabAdmin"  onclick="switchTab('admin')">🛡️ Manage</button>
                </div>

                {{-- ── MESSAGES VIEW ── --}}
                {{-- FIX: display:flex with flex-direction:column; inner row for message list + thread --}}
                <div id="viewMessages" style="flex:1;display:none;flex-direction:column;overflow:hidden;">
                    <div style="display:flex;flex:1;overflow:hidden;min-height:0;">
                        {{-- Main message list --}}
                        <div style="flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0;">
                            <div class="sg-messages" id="messagesBox"></div>
                            {{-- Input --}}
                            <div class="sg-input-area" id="inputArea">
                                <div id="uploadPreview" class="sg-upload-preview"></div>
                                <div class="sg-input-toolbar">
                                    <input type="file" id="imageInput" accept="image/*" multiple style="display:none">
                                    <button class="sg-attach-btn" onclick="document.getElementById('imageInput').click()" title="Send image">🖼️</button>
                                    <input type="file" id="fileInput" multiple style="display:none">
                                    <button class="sg-attach-btn" onclick="document.getElementById('fileInput').click()" title="Attach file">📎</button>
                                    <textarea id="msgInput" rows="1" placeholder="Message the group…" onkeydown="handleEnter(event)" oninput="autoResize(this)"></textarea>
                                    <button class="sg-send-btn" id="sendBtn" onclick="sendMessage()">➤</button>
                                </div>
                            </div>
                        </div>
                        {{-- Thread panel (slides in from right INSIDE the messages view) --}}
                        <div class="sg-thread-panel" id="threadPanel">
                            <div class="thread-panel-header">
                                <h3>💬 Thread</h3>
                                <button class="btn-close-thread" onclick="closeThread()">✕</button>
                            </div>
                            <div class="thread-parent-msg" id="threadParentMsg">
                                <div class="thread-parent-sender" id="threadParentSender"></div>
                                <div class="thread-parent-text" id="threadParentText"></div>
                            </div>
                            <div class="thread-replies" id="threadReplies">
                                <div class="no-msg">No replies yet. Start the thread!</div>
                            </div>
                            <div class="thread-input-area">
                                <div class="thread-input-toolbar">
                                    <textarea id="threadInput" rows="1" placeholder="Reply in thread…"
                                              onkeydown="handleThreadEnter(event)"
                                              oninput="autoResize(this)"></textarea>
                                    <button class="thread-send-btn" onclick="sendThreadReply()">➤</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── TASKS VIEW ── --}}
                <div id="viewTasks" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
                    <div class="tasks-container" id="tasksContainer">
                        <div class="tasks-header-row">
                            <h3>📋 Group Tasks</h3>
                            <button class="btn-add-task" onclick="toggleTaskForm()">+ Add Task</button>
                        </div>
                        <div class="task-add-form" id="taskAddForm">
                            <input type="text" id="taskTitleInput" placeholder="Task title…">
                            <div class="task-form-row">
                                <select id="taskPriority">
                                    <option value="medium">Medium Priority</option>
                                    <option value="high">High Priority</option>
                                    <option value="low">Low Priority</option>
                                </select>
                                <input type="date" id="taskDueDate">
                            </div>
                            <input type="text" id="taskAssignee" placeholder="Assign to (optional member name)…">
                            <div class="task-form-actions">
                                <button class="btn-task-cancel" onclick="toggleTaskForm()">Cancel</button>
                                <button class="btn-task-save" onclick="saveTask()">Save Task</button>
                            </div>
                        </div>
                        <div id="tasksList">
                            <div class="no-msg" style="padding:30px 0;">Loading tasks…</div>
                        </div>
                    </div>
                </div>

                {{-- ── RESOURCES VIEW ── --}}
                <div id="viewResources" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
                    <div class="resources-container" id="resourcesContainer">
                        <div class="resources-header-row">
                            <h3>📁 Group Resources</h3>
                            <button class="btn-upload-resource" onclick="document.getElementById('resourceFileInput').click()">
                                ↑ Upload File
                            </button>
                            <input type="file" id="resourceFileInput" multiple style="display:none" onchange="uploadResources(this.files)">
                        </div>
                        <div class="resources-filter">
                            <button class="filter-pill active" onclick="filterResources('all', this)">All</button>
                            <button class="filter-pill" onclick="filterResources('image', this)">🖼 Images</button>
                            <button class="filter-pill" onclick="filterResources('doc', this)">📄 Docs</button>
                            <button class="filter-pill" onclick="filterResources('pdf', this)">📑 PDFs</button>
                            <button class="filter-pill" onclick="filterResources('other', this)">📦 Other</button>
                        </div>
                        <div id="pinnedResourcesSection" style="display:none;">
                            <div class="pinned-section-title">📌 Pinned</div>
                            <div class="resource-grid" id="pinnedResourcesGrid"></div>
                            <div style="margin-bottom:16px;"></div>
                        </div>
                        <div class="pinned-section-title">All Files</div>
                        <div class="resource-grid" id="resourcesGrid">
                            <div class="no-msg" style="grid-column:1/-1;padding:30px 0;">Loading resources…</div>
                        </div>
                    </div>
                </div>

                {{-- ── NOTES VIEW ── --}}
                <div id="viewNotes" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
                    <div class="notes-container">
                        <div class="notes-toolbar">
                            <div class="notes-toolbar-group">
                                <button class="btn-format" onclick="execFormat('bold')" title="Bold"><strong>B</strong></button>
                                <button class="btn-format" onclick="execFormat('italic')" title="Italic"><em>I</em></button>
                                <button class="btn-format" onclick="execFormat('underline')" title="Underline"><u>U</u></button>
                            </div>
                            <div class="notes-toolbar-sep"></div>
                            <div class="notes-toolbar-group">
                                <button class="btn-format" onclick="execFormat('formatBlock', 'h2')" title="Heading 2">H2</button>
                                <button class="btn-format" onclick="execFormat('formatBlock', 'h3')" title="Heading 3">H3</button>
                                <button class="btn-format" onclick="execFormat('insertUnorderedList')" title="Bullet list">• List</button>
                                <button class="btn-format" onclick="execFormat('insertOrderedList')" title="Numbered list">1. List</button>
                            </div>
                            <div class="notes-toolbar-sep"></div>
                            <div class="notes-toolbar-group">
                                <button class="btn-format" onclick="execFormat('insertHorizontalRule')" title="Divider">—</button>
                                <button class="btn-format" onclick="execFormat('removeFormat')" title="Clear formatting">✕</button>
                            </div>
                            <span class="notes-save-status" id="notesSaveStatus">Auto-save on</span>
                        </div>
                        <div class="notes-inner">
                            {{-- Notes list sidebar --}}
                            <div class="notes-sidebar">
                                <div class="notes-sidebar-header">
                                    <span>Notes</span>
                                    <button class="btn-new-note" onclick="createNewNote()" title="New note">+</button>
                                </div>
                                <div id="notesList"></div>
                            </div>
                            {{-- Note editor --}}
                            <div style="flex:1;display:flex;flex-direction:column;overflow:hidden;">
                                <input type="text" class="notes-title-input" id="noteTitleInput" placeholder="Note title…" oninput="scheduleNoteSave()">
                                <div class="notes-content-editor"
                                     id="noteEditor"
                                     contenteditable="true"
                                     data-placeholder="Start writing your notes here… Everyone in the group can see and edit this."
                                     oninput="scheduleNoteSave()"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── CALENDAR VIEW ── --}}
                <div id="viewCalendars" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
                    <div class="calendars-container" id="calendarsContainer">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                            <h3 style="font-family:'Crimson Pro',serif;font-size:1.2rem;color:#e8e6e1;margin:0;">📅 Shared Calendars</h3>
                        </div>
                        <div id="calendarsBox">
                            <div style="text-align:center;color:#6b7280;padding:60px 20px;">
                                <div style="font-size:2rem;margin-bottom:10px;">📅</div>
                                <p>Loading shared calendars…</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── ADMIN / MODERATION VIEW ── --}}
                <div id="viewAdmin" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
                    <div class="admin-container">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                            <h3 style="font-family:'Crimson Pro',serif;font-size:1.3rem;color:#e8e6e1;margin:0;">🛡️ Group Management</h3>
                            <span style="font-size:0.7rem;background:rgba(108,99,255,.15);color:#a78bfa;border:1px solid rgba(108,99,255,.25);padding:2px 8px;border-radius:5px;font-weight:600;">Admin</span>
                        </div>

                        {{-- Member Management --}}
                        <div class="admin-section">
                            <div class="admin-section-header">
                                <h4>👥 Members</h4>
                                <span id="adminMemberCount" style="font-size:0.75rem;color:#6b7280;"></span>
                            </div>
                            <div class="admin-section-body" id="adminMembersList">
                                <div class="no-msg">Loading members…</div>
                            </div>
                        </div>

                        {{-- Group Settings --}}
                        <div class="admin-section">
                            <div class="admin-section-header">
                                <h4>⚙️ Group Settings</h4>
                            </div>
                            <div class="admin-section-body" style="display:flex;flex-direction:column;gap:12px;">
                                <div>
                                    <div style="font-size:0.72rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">Group Name</div>
                                    <div style="display:flex;gap:8px;">
                                        <input type="text" id="adminRenameInput" placeholder="New group name…"
                                               style="flex:1;background:#0f1117;border:1px solid #2a2d3e;border-radius:8px;padding:8px 11px;color:#e8e6e1;font-family:'DM Sans',sans-serif;font-size:0.83rem;outline:none;">
                                        <button onclick="renameGroupFromAdmin()"
                                                style="padding:8px 14px;background:#6c63ff;border:none;border-radius:8px;color:#fff;font-size:0.8rem;font-weight:600;cursor:pointer;">
                                            Rename
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <div style="font-size:0.72rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">Group Photo</div>
                                    <button onclick="document.getElementById('adminPhotoInput').click()"
                                            style="display:flex;align-items:center;gap:7px;padding:8px 14px;background:#1e2030;border:1px solid #2a2d3e;border-radius:8px;color:#c4c0d8;font-size:0.8rem;cursor:pointer;transition:all .2s;"
                                            onmouseover="this.style.borderColor='#6c63ff'" onmouseout="this.style.borderColor='#2a2d3e'">
                                        📷 Change group photo
                                    </button>
                                    <input type="file" id="adminPhotoInput" accept="image/*" style="display:none">
                                </div>
                            </div>
                        </div>

                        {{-- Danger Zone --}}
                        <div class="admin-section danger-zone">
                            <div class="admin-section-header">
                                <h4>⚠️ Danger Zone</h4>
                            </div>
                            <div class="admin-section-body">
                                <p style="font-size:0.8rem;color:#9ca3af;margin:0 0 10px;">Deleting the group is permanent and cannot be undone. All messages, tasks, notes, and files will be lost.</p>
                                <button class="btn-sd-delete" onclick="deleteGroup()">🗑️ Delete This Group</button>
                            </div>
                        </div>
                    </div>
                </div>

            </section>
        </div>
    </main>

    {{-- ══ CREATE GROUP MODAL ══ --}}
    <div class="sg-modal-backdrop" id="modalBackdrop">
        <div class="sg-modal">
            <h3>Create Study Group</h3>
            <div class="sg-field">
                <label for="groupNameInput">Group Name</label>
                <input type="text" id="groupNameInput" placeholder="e.g. Calculus Squad">
            </div>
            <div class="sg-field">
                <label for="groupSubjectInput">Subject (optional)</label>
                <input type="text" id="groupSubjectInput" placeholder="e.g. Mathematics">
            </div>
            <div class="sg-field">
                <label>Privacy</label>
                <div class="privacy-toggle-wrap">
                    <label class="privacy-opt selected-public" id="optPublic" for="radioPublic">
                        <input type="radio" id="radioPublic" name="groupPrivacy" value="0" checked>
                        <span>🌐</span> Public
                    </label>
                    <label class="privacy-opt" id="optPrivate" for="radioPrivate">
                        <input type="radio" id="radioPrivate" name="groupPrivacy" value="1">
                        <span>🔒</span> Private
                    </label>
                </div>
                <div style="font-size:0.73rem;color:#6b7280;margin-top:6px;">
                    <div id="publicDesc">🌐 Anyone can discover and request to join this group</div>
                    <div id="privateDesc" style="display:none;">🔒 Only invited members can join this group</div>
                </div>
            </div>
            <div class="sg-field" id="friendsField" style="display:none;">
                <label>Add Friends</label>
                <div class="friend-list" id="friendList">
                    @forelse($friends as $friend)
                        <label class="friend-item" for="friend_{{ $friend['id'] }}">
                            <input type="checkbox" id="friend_{{ $friend['id'] }}" value="{{ $friend['id'] }}">
                            <div class="friend-avatar">
                                @if(!empty($friend['photo']))
                                    <img src="{{ $friend['photo'] }}" alt="">
                                @else
                                    {{ $friend['initials'] }}
                                @endif
                            </div>
                            <div>
                                <div class="friend-name">{{ $friend['name'] }}</div>
                                <div class="friend-username">@{{ $friend['username'] }}</div>
                            </div>
                        </label>
                    @empty
                        <div class="no-msg">No friends to add yet.</div>
                    @endforelse
                </div>
            </div>
            <div class="sg-modal-actions">
                <button class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button class="btn-create" onclick="createGroup()">Create Group</button>
            </div>
        </div>
    </div>

    {{-- ══ MEMBERS MODAL ══ --}}
    <div class="members-modal-backdrop" id="membersModalBackdrop">
        <div class="members-modal">
            <div class="members-modal-header">
                <h3 id="membersModalTitle">Members</h3>
                <button class="btn-close-modal" onclick="closeMembersModal()">✕</button>
            </div>
            <div class="members-full-list" id="membersFullList">
                <div style="color:#4b5563;font-size:0.82rem;text-align:center;padding:16px;">Loading…</div>
            </div>
        </div>
    </div>

    {{-- ══ LIGHTBOX ══ --}}
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <button class="lightbox-close">✕</button>
        <img src="" id="lightboxImg" alt="">
    </div>

    {{-- ══ TOAST ══ --}}
    <div class="sg-toast" id="sgToast"></div>

    <script>
        const SB_URL  = '{{ config('services.supabase.url') }}';
        const SB_ANON = '{{ config('services.supabase.anon_key') }}';
        const SB_SVC  = '{{ config('services.supabase.service_key') }}';
        const UID     = '{{ session('user_id') }}';
    </script>
    <script src="{{ asset('js/supabase-helpers.js') }}"></script>
    <script src="{{ asset('js/notifications.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof initNotifications === 'function') initNotifications();
        });
    </script>
    @include('layouts.admin_bar')

    <script>
        window.studyGroupData = {
            csrfToken: "{{ csrf_token() }}",
            userId: "{{ session('user_id') }}",
            firstGroupId: "{{ $groups->first()->id ?? '' }}",
            hasGroups: {{ $groups->isNotEmpty() ? 'true' : 'false' }}
        };
    </script>

    <script src="{{ asset('js/studygroups.js') }}"></script>
</body>
</html>
