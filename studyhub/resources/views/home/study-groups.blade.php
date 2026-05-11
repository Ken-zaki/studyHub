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
    <style>
        .sg-layout {
            display: flex;
            height: 100vh;
            overflow: hidden;
            background: #0f1117;
            color: #e8e6e1;
            font-family: 'DM Sans', sans-serif;
        }
        /* ── SIDEBAR ── */
        .sg-sidebar {
            width: 280px;
            min-width: 280px;
            background: #161820;
            border-right: 1px solid #252830;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .sg-sidebar-header {
            padding: 20px 18px 14px;
            border-bottom: 1px solid #252830;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .sg-sidebar-header h2 {
            font-family: 'Crimson Pro', serif;
            font-size: 1.35rem;
            font-weight: 700;
            color: #e8e6e1;
            margin: 0;
        }
        .btn-new-group {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: #6c63ff;
            border: none;
            color: #fff;
            font-size: 1.3rem;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background .2s, transform .15s;
        }
        .btn-new-group:hover { background: #5a52e0; transform: scale(1.08); }
        .sg-group-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px 8px;
        }
        .sg-group-list::-webkit-scrollbar { width: 3px; }
        .sg-group-list::-webkit-scrollbar-thumb { background: #2a2d3e; border-radius: 4px; }
        .sg-group-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 10px;
            border-radius: 10px;
            cursor: pointer;
            transition: background .15s;
            border-left: 3px solid transparent;
        }
        .sg-group-item:hover { background: #1e2030; }
        .sg-group-item.active { background: #1e2030; border-left-color: #6c63ff; padding-left: 7px; }
        .sg-group-avatar {
            width: 42px; height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #6c63ff, #a78bfa);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; font-weight: 700; color: #fff;
            flex-shrink: 0;
        }
        .sg-group-info { flex: 1; min-width: 0; }
        .sg-group-name {
            font-size: 0.88rem; font-weight: 600; color: #e8e6e1;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .sg-group-subject { font-size: 0.74rem; color: #6b7280; margin-top: 1px; }
        /* ── CHAT ── */
        .sg-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #0f1117;
            overflow: hidden;
        }
        .sg-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #4b5563;
            gap: 12px;
        }
        .sg-empty-icon { font-size: 3rem; }
        .sg-empty p { font-size: 0.9rem; }
        .sg-chat-header {
            padding: 14px 20px;
            border-bottom: 1px solid #252830;
            display: flex;
            align-items: center;
            gap: 14px;
            background: #161820;
        }
        .sg-chat-header-avatar {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #6c63ff, #a78bfa);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; font-weight: 700; color: #fff;
            flex-shrink: 0;
        }
        .sg-chat-header-info { flex: 1; }
        .sg-chat-header-name { font-weight: 600; font-size: 0.95rem; color: #e8e6e1; }
        .sg-chat-header-members { font-size: 0.74rem; color: #6b7280; }
        .sg-chat-header-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .btn-delete-group {
            padding: 6px 12px;
            background: #ef4444;
            border: none;
            border-radius: 6px;
            color: #fff;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background .2s;
            font-weight: 500;
        }
        .btn-delete-group:hover { background: #dc2626; }
        /* ── MESSAGES ── */
        .sg-messages {
            flex: 1;
            overflow-y: auto;
            padding: 18px 22px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .sg-messages::-webkit-scrollbar { width: 4px; }
        .sg-messages::-webkit-scrollbar-thumb { background: #2a2d3e; border-radius: 4px; }
        .date-sep {
            text-align: center; font-size: 0.72rem; color: #4b5563;
            position: relative; margin: 4px 0;
        }
        .date-sep::before, .date-sep::after {
            content: ''; position: absolute; top: 50%; width: 40%; height: 1px; background: #252830;
        }
        .date-sep::before { left: 0; } .date-sep::after { right: 0; }
        .msg-row { display: flex; gap: 10px; align-items: flex-end; }
        .msg-row.own { flex-direction: row-reverse; }
        .msg-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, #6c63ff, #a78bfa);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.7rem; font-weight: 700; color: #fff;
            flex-shrink: 0; overflow: hidden;
        }
        .msg-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .msg-body { max-width: 68%; }
        .msg-sender { font-size: 0.72rem; color: #6b7280; margin-bottom: 3px; padding-left: 2px; }
        .msg-row.own .msg-sender { text-align: right; padding-right: 2px; }
        .msg-bubble {
            background: #1e2030; border-radius: 14px 14px 14px 4px;
            padding: 9px 14px; font-size: 0.875rem; line-height: 1.5;
            color: #e8e6e1; word-break: break-word;
        }
        .msg-row.own .msg-bubble {
            background: #6c63ff; border-radius: 14px 14px 4px 14px; color: #fff;
        }
        .msg-time { font-size: 0.68rem; color: #4b5563; margin-top: 4px; padding-left: 2px; }
        .msg-row.own .msg-time { text-align: right; padding-right: 2px; }
        .msg-image {
            max-width: 220px; border-radius: 10px; margin-top: 4px;
            cursor: pointer; border: 1px solid #2a2d3e; display: block;
        }
        .msg-file {
            display: flex; align-items: center; gap: 8px;
            background: #252830; border-radius: 8px; padding: 8px 12px;
            margin-top: 4px; font-size: 0.8rem; text-decoration: none;
            color: #c4c0d8; border: 1px solid #2e3040; transition: background .15s;
        }
        .msg-file:hover { background: #2e3040; }
        .msg-file-icon { font-size: 1.2rem; }
        .msg-file-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .msg-file-size { color: #4b5563; font-size: 0.72rem; }
        /* ── INPUT ── */
        .sg-input-area { padding: 14px 18px; border-top: 1px solid #252830; background: #161820; }
        .sg-input-toolbar {
            display: flex; align-items: center; gap: 6px;
            background: #0f1117; border: 1px solid #2a2d3e;
            border-radius: 14px; padding: 8px 12px;
        }
        .sg-input-toolbar textarea {
            flex: 1; background: transparent; border: none; outline: none;
            resize: none; color: #e8e6e1; font-family: 'DM Sans', sans-serif;
            font-size: 0.875rem; line-height: 1.4; max-height: 120px;
            min-height: 22px; overflow-y: auto;
        }
        .sg-input-toolbar textarea::placeholder { color: #4b5563; }
        .sg-attach-btn {
            width: 32px; height: 32px; border: none; background: none;
            color: #6b7280; cursor: pointer; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; transition: color .15s, background .15s;
        }
        .sg-attach-btn:hover { color: #a78bfa; background: #1e2030; }
        .sg-send-btn {
            width: 34px; height: 34px; background: #6c63ff; border: none;
            border-radius: 9px; color: #fff; font-size: 1rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background .2s, transform .15s; flex-shrink: 0;
        }
        .sg-send-btn:hover { background: #5a52e0; transform: scale(1.05); }
        .sg-send-btn:disabled { background: #2a2d3e; cursor: not-allowed; transform: none; }
        .sg-upload-preview { display: flex; flex-wrap: wrap; gap: 8px; padding: 8px 0 2px; }
        .sg-preview-item {
            display: inline-flex; align-items: center; gap: 6px;
            background: #1e2030; border-radius: 8px; padding: 5px 10px 5px 7px;
            font-size: 0.76rem; color: #c4c0d8; max-width: 180px;
        }
        .sg-preview-item img { width: 30px; height: 30px; border-radius: 5px; object-fit: cover; }
        .sg-preview-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sg-preview-remove {
            background: none; border: none; color: #6b7280;
            cursor: pointer; font-size: 0.9rem; padding: 0; margin-left: 2px;
        }
        .sg-preview-remove:hover { color: #ef4444; }
        /* ── MODAL ── */
        .sg-modal-backdrop {
            position: fixed; inset: 0; background: rgba(0,0,0,.65);
            backdrop-filter: blur(4px); z-index: 100;
            display: none; align-items: center; justify-content: center;
        }
        .sg-modal-backdrop.open { display: flex; }
        .sg-modal {
            background: #161820; border: 1px solid #252830;
            border-radius: 18px; padding: 28px 26px; width: 440px;
            max-width: 95vw; box-shadow: 0 24px 60px rgba(0,0,0,.5);
        }
        .sg-modal h3 {
            font-family: 'Crimson Pro', serif; font-size: 1.4rem;
            font-weight: 700; margin: 0 0 20px; color: #e8e6e1;
        }
        .sg-modal label {
            display: block; font-size: 0.78rem; font-weight: 600;
            color: #9ca3af; margin-bottom: 5px;
            text-transform: uppercase; letter-spacing: .04em;
        }
        .sg-modal input[type="text"] {
            width: 100%; background: #0f1117; border: 1px solid #2a2d3e;
            border-radius: 10px; padding: 10px 13px; color: #e8e6e1;
            font-size: 0.9rem; font-family: 'DM Sans', sans-serif;
            outline: none; box-sizing: border-box; transition: border-color .2s;
        }
        .sg-modal input[type="text"]:focus { border-color: #6c63ff; }
        .sg-field { margin-bottom: 18px; }
        .friend-list {
            display: flex; flex-direction: column; gap: 6px;
            max-height: 200px; overflow-y: auto;
        }
        .friend-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 10px; border-radius: 9px;
            cursor: pointer; transition: background .15s; user-select: none;
        }
        .friend-item:hover { background: #1e2030; }
        .friend-item input[type="checkbox"] { accent-color: #6c63ff; width: 15px; height: 15px; }
        .friend-avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: linear-gradient(135deg, #6c63ff, #a78bfa);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; font-weight: 700; color: #fff;
            overflow: hidden; flex-shrink: 0;
        }
        .friend-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .friend-name { font-size: 0.87rem; color: #e8e6e1; }
        .friend-username { font-size: 0.74rem; color: #6b7280; }
        .sg-modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 22px; }
        .btn-cancel {
            padding: 9px 18px; background: none; border: 1px solid #2a2d3e;
            border-radius: 9px; color: #9ca3af; font-size: 0.86rem;
            cursor: pointer; transition: background .15s;
        }
        .btn-cancel:hover { background: #1e2030; }
        .btn-create {
            padding: 9px 22px; background: #6c63ff; border: none;
            border-radius: 9px; color: #fff; font-size: 0.86rem;
            font-weight: 600; cursor: pointer; transition: background .2s;
        }
        .btn-create:hover { background: #5a52e0; }
        /* ── LIGHTBOX ── */
        .lightbox {
            position: fixed; inset: 0; background: rgba(0,0,0,.88);
            z-index: 200; display: none; align-items: center; justify-content: center;
        }
        .lightbox.open { display: flex; }
        .lightbox img { max-width: 90vw; max-height: 90vh; border-radius: 10px; }
        .lightbox-close {
            position: absolute; top: 20px; right: 28px;
            color: #fff; font-size: 2rem; cursor: pointer;
            background: none; border: none;
        }
        .no-msg { color: #4b5563; font-size: 0.83rem; text-align: center; padding: 12px; }
    </style>
</head>
<body>

@include('layouts.sidebar')

<main class="main-content-simple" style="padding:0; overflow:hidden;">
<div class="sg-layout">

    {{-- GROUP LIST SIDEBAR --}}
    <aside class="sg-sidebar">
        <div class="sg-sidebar-header">
            <h2>Study Groups</h2>
            <button class="btn-new-group" id="btnOpenModal" title="Create new group">+</button>
        </div>
        <div class="sg-group-list" id="groupList">
            @forelse($groups as $group)
                <div class="sg-group-item"
                     data-group-id="{{ $group->id }}"
                     onclick="openGroup('{{ $group->id }}', this)">
                    <div class="sg-group-avatar">{{ strtoupper(substr($group->name, 0, 2)) }}</div>
                    <div class="sg-group-info">
                        <div class="sg-group-name">{{ $group->name }}</div>
                        <div class="sg-group-subject">{{ $group->subject ?? 'General' }} · {{ $group->members_count }} members</div>
                    </div>
                </div>
            @empty
                <div class="no-msg" style="margin-top:24px;">No groups yet.<br>Hit <strong>+</strong> to create one!</div>
            @endforelse
        </div>
    </aside>

    {{-- CHAT PANEL --}}
    <section class="sg-chat" id="chatPanel">
        <div class="sg-empty" id="chatEmpty">
            <div class="sg-empty-icon">👥</div>
            <p>Select a group to start chatting</p>
        </div>

        <div class="sg-chat-header" id="chatHeader" style="display:none;">
            <div class="sg-chat-header-avatar" id="chatAvatar"></div>
            <div class="sg-chat-header-info">
                <div class="sg-chat-header-name" id="chatGroupName"></div>
                <div class="sg-chat-header-members" id="chatGroupMembers"></div>
            </div>
            <div class="sg-chat-header-actions">
                <button class="btn-delete-group" onclick="deleteGroup()" title="Delete this group">Delete</button>
            </div>
        </div>

        <div class="sg-messages" id="messagesBox" style="display:none;"></div>

        <div class="sg-input-area" id="inputArea" style="display:none;">
            <div id="uploadPreview" class="sg-upload-preview"></div>
            <div class="sg-input-toolbar">
                <input type="file" id="imageInput" accept="image/*" multiple style="display:none">
                <button class="sg-attach-btn" onclick="document.getElementById('imageInput').click()" title="Send image">🖼️</button>
                <input type="file" id="fileInput" multiple style="display:none">
                <button class="sg-attach-btn" onclick="document.getElementById('fileInput').click()" title="Attach file">📎</button>
                <textarea id="msgInput" rows="1" placeholder="Message…" onkeydown="handleEnter(event)"></textarea>
                <button class="sg-send-btn" id="sendBtn" onclick="sendMessage()">➤</button>
            </div>
        </div>
    </section>

</div>
</main>

{{-- CREATE GROUP MODAL --}}
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
            <label>Add Friends</label>
            <div class="friend-list" id="friendList">
                {{-- FIX: $friends is now an array of arrays, not Eloquent objects --}}
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

{{-- LIGHTBOX --}}
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close">✕</button>
    <img src="" id="lightboxImg" alt="">
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

{{-- FIX: Use session('user_id') instead of auth()->id() which is always null in this app --}}
const ME = @json(session('user_id'));

let activeGroupId = null;
let pollInterval  = null;
let pendingFiles  = [];
let lastMsgCount  = 0;

// ── OPEN GROUP ────────────────────────────────────────────────
function openGroup(groupId, el) {
    document.querySelectorAll('.sg-group-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
    activeGroupId = groupId;
    lastMsgCount  = 0;

    document.getElementById('chatEmpty').style.display   = 'none';
    document.getElementById('chatHeader').style.display  = 'flex';
    document.getElementById('messagesBox').style.display = 'flex';
    document.getElementById('inputArea').style.display   = 'block';

    const name    = el.querySelector('.sg-group-name').textContent;
    const subject = el.querySelector('.sg-group-subject').textContent;
    document.getElementById('chatAvatar').textContent       = name.substring(0, 2).toUpperCase();
    document.getElementById('chatGroupName').textContent    = name;
    document.getElementById('chatGroupMembers').textContent = subject;

    loadMessages(true);
    clearInterval(pollInterval);
    pollInterval = setInterval(() => loadMessages(false), 3000);
}

// ── LOAD MESSAGES ─────────────────────────────────────────────
function loadMessages(scrollToBottom) {
    if (!activeGroupId) return;
    fetch(`/study-groups/${activeGroupId}/messages`)
        .then(r => r.json())
        .then(data => {
            if (!scrollToBottom && data.messages.length === lastMsgCount) return;
            lastMsgCount = data.messages.length;
            renderMessages(data.messages, scrollToBottom);
        })
        .catch(() => {});
}

function renderMessages(messages, scroll) {
    const box = document.getElementById('messagesBox');
    let html = '';
    let lastDate = '';

    messages.forEach(m => {
        const d    = new Date(m.created_at);
        const date = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        if (date !== lastDate) {
            html += `<div class="date-sep">${date}</div>`;
            lastDate = date;
        }

        const isOwn    = ME && String(m.user_id) === String(ME);
        const initials = (m.sender_first || '?').charAt(0).toUpperCase();
        const name     = isOwn ? 'You' : `${m.sender_first || ''} ${m.sender_last || ''}`.trim();
        const time     = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        const avatar   = m.sender_photo
            ? `<img src="${escHtml(m.sender_photo)}" alt="">`
            : initials;

        let content = '';
        if (m.message && m.message.trim()) {
            content += `<div class="msg-bubble">${escHtml(m.message)}</div>`;
        }
        (m.attachments || []).forEach(a => {
            if (a.type === 'image') {
                content += `<img class="msg-image" src="${escHtml(a.url)}" alt="${escHtml(a.name)}" onclick="openLightbox('${escHtml(a.url)}')">`;
            } else {
                const ext = a.name.split('.').pop().toUpperCase();
                const sz  = a.size ? formatBytes(a.size) : '';
                content += `<a class="msg-file" href="${escHtml(a.url)}" target="_blank" download>
                    <span class="msg-file-icon">${fileIcon(ext)}</span>
                    <span class="msg-file-name">${escHtml(a.name)}</span>
                    <span class="msg-file-size">${sz}</span>
                </a>`;
            }
        });

        html += `
        <div class="msg-row ${isOwn ? 'own' : ''}">
            <div class="msg-avatar">${avatar}</div>
            <div class="msg-body">
                <div class="msg-sender">${escHtml(name)}</div>
                ${content}
                <div class="msg-time">${time}</div>
            </div>
        </div>`;
    });

    box.innerHTML = html;
    if (scroll) box.scrollTop = box.scrollHeight;
}

// ── SEND MESSAGE ──────────────────────────────────────────────
function sendMessage() {
    if (!activeGroupId) return;
    const text = document.getElementById('msgInput').value.trim();
    if (!text && pendingFiles.length === 0) return;

    const btn = document.getElementById('sendBtn');
    btn.disabled = true;

    const formData = new FormData();
    formData.append('_token', CSRF);
    formData.append('message', text);
    pendingFiles.forEach(pf => formData.append('attachments[]', pf.file));

    fetch(`/study-groups/${activeGroupId}/messages`, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(() => {
        document.getElementById('msgInput').value = '';
        pendingFiles = [];
        document.getElementById('uploadPreview').innerHTML = '';
        loadMessages(true);
    })
    .catch(() => alert('Failed to send message.'))
    .finally(() => { btn.disabled = false; });
}

function handleEnter(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

// ── FILE PICKERS ──────────────────────────────────────────────
document.getElementById('imageInput').addEventListener('change', function () {
    addFiles(this.files, 'image');
    this.value = '';
});
document.getElementById('fileInput').addEventListener('change', function () {
    addFiles(this.files, 'file');
    this.value = '';
});

function addFiles(fileList, type) {
    Array.from(fileList).forEach(f => {
        const id = Math.random().toString(36).slice(2);
        const pf = { file: f, type, id };
        if (type === 'image') pf.previewUrl = URL.createObjectURL(f);
        pendingFiles.push(pf);
        const div = document.createElement('div');
        div.className = 'sg-preview-item';
        div.id = 'prev_' + id;
        if (type === 'image') {
            div.innerHTML = `<img src="${pf.previewUrl}" alt=""><span class="sg-preview-name">${escHtml(f.name)}</span><button class="sg-preview-remove" onclick="removeFile('${id}')">✕</button>`;
        } else {
            const ext = f.name.split('.').pop().toUpperCase();
            div.innerHTML = `<span>${fileIcon(ext)}</span><span class="sg-preview-name">${escHtml(f.name)}</span><button class="sg-preview-remove" onclick="removeFile('${id}')">✕</button>`;
        }
        document.getElementById('uploadPreview').appendChild(div);
    });
}

function removeFile(id) {
    pendingFiles = pendingFiles.filter(f => f.id !== id);
    const el = document.getElementById('prev_' + id);
    if (el) el.remove();
}

// ── CREATE GROUP ──────────────────────────────────────────────
document.getElementById('btnOpenModal').onclick = () => {
    document.getElementById('groupNameInput').value    = '';
    document.getElementById('groupSubjectInput').value = '';
    document.querySelectorAll('#friendList input[type="checkbox"]').forEach(c => c.checked = false);
    
    // Load friends dynamically via AJAX
    loadFriendsForModal();
    
    document.getElementById('modalBackdrop').classList.add('open');
};

function loadFriendsForModal() {
    const friendListDiv = document.getElementById('friendList');
    friendListDiv.innerHTML = '<div style="padding: 10px; color: #9ca3af;">Loading friends...</div>';
    
    fetch('/study-groups/api/friends')
        .then(r => r.json())
        .then(data => {
            if (!data.friends || data.friends.length === 0) {
                friendListDiv.innerHTML = '<div class="no-msg">No friends to add yet.</div>';
                return;
            }
            
            let html = '';
            data.friends.forEach(friend => {
                const photoHtml = friend.photo 
                    ? `<img src="${escHtml(friend.photo)}" alt="">` 
                    : friend.initials;
                
                html += `
                    <label class="friend-item" for="friend_${friend.id}">
                        <input type="checkbox" id="friend_${friend.id}" value="${friend.id}">
                        <div class="friend-avatar">
                            ${photoHtml}
                        </div>
                        <div>
                            <div class="friend-name">${escHtml(friend.name)}</div>
                            <div class="friend-username">@${escHtml(friend.username || 'friend')}</div>
                        </div>
                    </label>
                `;
            });
            
            friendListDiv.innerHTML = html;
        })
        .catch(err => {
            console.error('Failed to load friends:', err);
            friendListDiv.innerHTML = '<div class="no-msg">Failed to load friends.</div>';
        });
}

function closeModal() {
    document.getElementById('modalBackdrop').classList.remove('open');
}
document.getElementById('modalBackdrop').addEventListener('click', function (e) {
    if (e.target === this) closeModal();
});

function createGroup() {
    const name    = document.getElementById('groupNameInput').value.trim();
    const subject = document.getElementById('groupSubjectInput').value.trim();
    if (!name) { alert('Please enter a group name.'); return; }

    const members = Array.from(
        document.querySelectorAll('#friendList input[type="checkbox"]:checked')
    ).map(c => c.value);

    fetch('/study-groups', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ name, subject, members })
    })
    .then(r => r.json())
    .then(data => {
        if (data.group) {
            closeModal();
            const noMsg = document.querySelector('#groupList .no-msg');
            if (noMsg) noMsg.remove();

            const div = document.createElement('div');
            div.className = 'sg-group-item';
            div.dataset.groupId = data.group.id;
            div.setAttribute('onclick', `openGroup('${data.group.id}', this)`);
            div.innerHTML = `
                <div class="sg-group-avatar">${data.group.name.substring(0,2).toUpperCase()}</div>
                <div class="sg-group-info">
                    <div class="sg-group-name">${escHtml(data.group.name)}</div>
                    <div class="sg-group-subject">${escHtml(data.group.subject || 'General')} · 1 member</div>
                </div>`;
            document.getElementById('groupList').prepend(div);
            openGroup(data.group.id, div);
        } else {
            alert(data.error || 'Failed to create group.');
        }
    })
    .catch(() => alert('Failed to create group.'));
}

function deleteGroup() {
    if (!activeGroupId) return;
    
    if (!confirm('Are you sure you want to delete this group? This action cannot be undone.')) {
        return;
    }

    fetch(`/study-groups/${activeGroupId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Remove group from list
            const groupEl = document.querySelector(`[data-group-id="${activeGroupId}"]`);
            if (groupEl) groupEl.remove();
            
            // Reset chat panel
            activeGroupId = null;
            clearInterval(pollInterval);
            document.getElementById('chatEmpty').style.display = 'flex';
            document.getElementById('chatHeader').style.display = 'none';
            document.getElementById('messagesBox').style.display = 'none';
            document.getElementById('inputArea').style.display = 'none';
            
            // Show empty message if no groups left
            if (document.querySelectorAll('.sg-group-item').length === 0) {
                const noMsg = document.createElement('div');
                noMsg.className = 'no-msg';
                noMsg.style.marginTop = '24px';
                noMsg.textContent = 'No groups yet. Hit + to create one!';
                document.getElementById('groupList').appendChild(noMsg);
            }
        } else {
            alert(data.error || 'Failed to delete group.');
        }
    })
    .catch(err => {
        console.error('Delete failed:', err);
        alert('Failed to delete group.');
    });
}

// ── LIGHTBOX ──────────────────────────────────────────────────
function openLightbox(url) {
    document.getElementById('lightboxImg').src = url;
    document.getElementById('lightbox').classList.add('open');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
}

// ── HELPERS ───────────────────────────────────────────────────
function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function formatBytes(b) {
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(1) + ' MB';
}
function fileIcon(ext) {
    const m = { PDF:'📄', DOC:'📝', DOCX:'📝', XLS:'📊', XLSX:'📊', PPT:'📑', PPTX:'📑', ZIP:'🗜️', RAR:'🗜️', MP4:'🎥', MP3:'🎵' };
    return m[ext] || '📁';
}

// Auto-open first group
@if($groups->isNotEmpty())
window.addEventListener('DOMContentLoaded', () => {
    const first = document.querySelector('.sg-group-item');
    if (first) openGroup('{{ $groups->first()->id }}', first);
});
@endif
</script>

</body>
</html>