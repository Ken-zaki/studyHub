(function () {
    'use strict';

    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const ME   = window.studyGroupData.userId;

    let activeGroupId   = null;
    let activeGroupData = {};
    let isGroupAdmin    = false;
    let pollInterval    = null;
    let pendingFiles    = [];
    let lastMsgCount    = 0;
    let messagesRequestToken = 0;
    let settingsOpen    = false;
    let currentTab      = 'messages';
    let createGroupPending = false;
    let membersRequestToken = 0;

    // Thread state
    let activeThreadMsgId  = null;
    let threadPollInterval = null;
    let lastThreadCount    = 0;

    // Notes state
    let currentNoteId   = null;
    let noteSaveTimeout = null;
    let notesList       = [];

    // Resources state
    let allResources   = [];
    let resourceFilter = 'all';

    /* ══════════════════════════════════════════
       HELPERS
    ══════════════════════════════════════════ */
    function escHtml(str) {
        return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function formatBytes(b) {
        b = parseInt(b, 10) || 0;
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
        return (b / 1048576).toFixed(1) + ' MB';
    }
    function fileIcon(ext) {
        const m = { PDF: '📄', DOC: '📝', DOCX: '📝', XLS: '📊', XLSX: '📊', PPT: '📑', PPTX: '📑', ZIP: '🗜️', RAR: '🗜️', MP4: '🎥', MP3: '🎵', PNG: '🖼️', JPG: '🖼️', JPEG: '🖼️', GIF: '🖼️' };
        return m[(ext || '').toUpperCase()] || '📁';
    }
    function autoResize(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    }

    /* ══════════════════════════════════════════
       TOAST
    ══════════════════════════════════════════ */
    function showToast(msg, type = '') {
        const t = document.getElementById('sgToast');
        t.textContent = msg;
        t.className   = 'sg-toast show ' + type;
        setTimeout(() => t.classList.remove('show'), 3000);
    }

    /* ══════════════════════════════════════════
       OPEN GROUP
    ══════════════════════════════════════════ */
    function openGroup(groupId, el) {
        document.querySelectorAll('.sg-group-item').forEach(i => i.classList.remove('active'));
        el.classList.add('active');
        activeGroupId = groupId;
        lastMsgCount  = 0;
        settingsOpen  = false;
        currentTab    = 'messages';
        closeSettingsDropdown();

        const name     = el.querySelector('.sg-group-name').textContent.trim();
        const subject  = el.querySelector('.sg-group-subject').textContent.trim();
        const privacy  = el.dataset.privacy || 'public';
        const avatarEl = el.querySelector('.sg-group-avatar img');
        const photo    = avatarEl ? avatarEl.src : null;
        activeGroupData = { name, subject, privacy, photo };

        // Show panels
        document.getElementById('chatEmpty').style.display  = 'none';
        document.getElementById('chatHeader').style.display = 'flex';
        document.getElementById('sgTabs').style.display     = 'flex';

        // Header avatar
        const hAvatar = document.getElementById('chatAvatar');
        if (photo) {
            hAvatar.innerHTML = `<img src="${escHtml(photo)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`;
        } else {
            hAvatar.textContent  = name.substring(0, 2).toUpperCase();
            hAvatar.style.background = 'linear-gradient(135deg,#6c63ff,#a78bfa)';
        }

        const privBadge = privacy === 'private'
            ? `<span class="header-privacy-badge private">🔒 Private</span>`
            : `<span class="header-privacy-badge public">🌐 Public</span>`;
        document.getElementById('chatGroupName').innerHTML     = escHtml(name) + ' ' + privBadge;
        document.getElementById('chatGroupMembers').textContent = subject;

        // Load member list once; use the same response to determine admin status.
        syncSettingsDropdown().then((members) => {
            if (Array.isArray(members)) {
                isGroupAdmin = members.some(m => String(m.id) === String(ME) && (m.is_owner || m.role === 'admin'));
                syncAdminUI();
            }
        });

        switchTab('messages');
        clearInterval(pollInterval);
        clearInterval(threadPollInterval);
    }

    /* ══════════════════════════════════════════
       ADMIN STATUS
    ══════════════════════════════════════════ */
    function syncAdminUI() {
        const adminTab = document.getElementById('tabAdmin');
        // FIX: toggle 'visible' class so CSS display:flex kicks in
        adminTab.classList.toggle('visible', isGroupAdmin);

        const renameSection = document.getElementById('sdRenameSection');
        const deleteSection = document.getElementById('sdDeleteSection');
        const sdAvatarWrap  = document.getElementById('sdAvatarWrap');
        if (renameSection) renameSection.style.display = isGroupAdmin ? '' : 'none';
        if (deleteSection) deleteSection.style.display = isGroupAdmin ? '' : 'none';
        if (sdAvatarWrap)  sdAvatarWrap.style.cursor   = isGroupAdmin ? 'pointer' : 'default';

        document.getElementById('adminRenameInput').value = activeGroupData.name || '';
    }

    /* ══════════════════════════════════════════
       GROUP SEARCH
    ══════════════════════════════════════════ */
    function filterGroups(query) {
        const q = query.toLowerCase().trim();
        document.querySelectorAll('.sg-group-item').forEach(el => {
            const name = el.dataset.name || '';
            el.style.display = !q || name.includes(q) ? '' : 'none';
        });
    }

    /* ══════════════════════════════════════════
       TAB SWITCHING
    ══════════════════════════════════════════ */
    function switchTab(tab) {
        currentTab = tab;
        const views = ['messages', 'tasks', 'resources', 'notes', 'calendars', 'admin'];
        views.forEach(v => {
            const el = document.getElementById('view' + v.charAt(0).toUpperCase() + v.slice(1));
            if (el) el.style.display = 'none';
        });
        document.querySelectorAll('.sg-tab').forEach(t => t.classList.remove('active'));

        const tabBtn  = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
        const viewEl  = document.getElementById('view' + tab.charAt(0).toUpperCase() + tab.slice(1));
        if (tabBtn) tabBtn.classList.add('active');
        if (viewEl) viewEl.style.display = 'flex';

        clearInterval(pollInterval);
        clearInterval(threadPollInterval);

        if (tab === 'messages') {
            loadMessages(true);
            pollInterval = setInterval(() => loadMessages(false), 3000);
        } else if (tab === 'tasks') {
            loadTasks();
        } else if (tab === 'resources') {
            loadResources();
        } else if (tab === 'notes') {
            loadNotes();
        } else if (tab === 'calendars') {
            loadGroupSharedCalendars();
        } else if (tab === 'admin') {
            loadAdminPanel();
        }
    }

    /* ══════════════════════════════════════════
       SETTINGS DROPDOWN
    ══════════════════════════════════════════ */
    function toggleSettings(e) {
        e.stopPropagation();
        settingsOpen = !settingsOpen;
        const btn      = document.getElementById('btnSettings');
        const dropdown = document.getElementById('settingsDropdown');
        btn.classList.toggle('active', settingsOpen);
        if (settingsOpen) {
            const rect = btn.getBoundingClientRect();
            dropdown.style.top   = (rect.bottom + 8) + 'px';
            dropdown.style.right = (window.innerWidth - rect.right) + 'px';
            dropdown.style.left  = 'auto';
            dropdown.classList.add('open');
            syncSettingsDropdown();
        } else {
            dropdown.classList.remove('open');
        }
    }

    function closeSettingsDropdown() {
        settingsOpen = false;
        document.getElementById('settingsDropdown').classList.remove('open');
        document.getElementById('btnSettings').classList.remove('active');
    }

    document.addEventListener('click', function (e) {
        if (settingsOpen && !document.getElementById('settingsWrap').contains(e.target)) {
            closeSettingsDropdown();
        }
    });

    function syncSettingsDropdown() {
        const { name, subject, photo } = activeGroupData;
        document.getElementById('sdGroupName').textContent = name || '—';
        document.getElementById('sdGroupSub').textContent  = subject || '—';
        document.getElementById('sdRenameInput').value     = name || '';
        const sdAvatar = document.getElementById('sdAvatar');
        if (photo) {
            sdAvatar.innerHTML = `<img src="${escHtml(photo)}" alt="">`;
        } else {
            sdAvatar.textContent      = (name || '??').substring(0, 2).toUpperCase();
            sdAvatar.style.background = 'linear-gradient(135deg,#6c63ff,#a78bfa)';
        }
        return loadGroupMembers();
    }

    /* ══════════════════════════════════════════
       LOAD GROUP MEMBERS (settings sidebar)
    ══════════════════════════════════════════ */
    function loadGroupMembers() {
        if (!activeGroupId) return Promise.resolve([]);
        const list = document.getElementById('sdMembersList');
        const token = ++membersRequestToken;

        // Keep the current members visible while refreshing so group switching feels instant.
        if (!list.dataset.loaded) {
            list.innerHTML = '<div style="color:#4b5563;font-size:0.76rem;padding:4px 6px;">Loading…</div>';
        }

        return fetch(`/study-groups/${activeGroupId}/members`)
            .then(r => r.ok ? r.json() : Promise.reject(r.status))
            .then(data => {
                if (token !== membersRequestToken) return [];
                const members = data.members || [];
                list.dataset.loaded = '1';
                if (!members.length) { list.innerHTML = '<div style="color:#4b5563;font-size:0.76rem;padding:6px;">No members found.</div>'; return members; }
                list.innerHTML = members.slice(0, 5).map(m => memberRowHtml(m, 'sd')).join('');
                return members;
            })
            .catch(() => { list.innerHTML = '<div style="color:#f87171;font-size:0.76rem;padding:6px;">Failed to load.</div>'; return []; });
    }

    function memberRowHtml(m, type) {
        const isMe     = ME && String(m.id) === String(ME);
        const initials = (m.first_name || m.name || '?').charAt(0).toUpperCase();
        const avatar   = m.photo ? `<img src="${escHtml(m.photo)}" alt="">` : initials;
        const name     = `${m.first_name || ''} ${m.last_name || ''}`.trim() || m.name || 'Member';
        const tag      = isMe
            ? `<span class="member-tag you">You</span>`
            : m.is_owner ? `<span class="member-tag owner">Owner</span>` : '';

        if (type === 'sd') return `<div class="sd-member-row">
            <div class="sd-member-avatar">${avatar}</div>
            <span class="sd-member-name">${escHtml(name)}</span>${tag}</div>`;

        return `<div class="member-full-row">
            <div class="member-full-avatar">${avatar}</div>
            <div style="flex:1;min-width:0;">
                <div class="member-full-name">${escHtml(name)}</div>
                ${m.username ? `<div class="member-full-username">@${escHtml(m.username)}</div>` : ''}
            </div>${tag}</div>`;
    }

    /* ══════════════════════════════════════════
       MEMBERS MODAL
    ══════════════════════════════════════════ */
    function openMembersModal() {
        closeSettingsDropdown();
        document.getElementById('membersModalTitle').textContent = `Members of "${activeGroupData.name}"`;
        document.getElementById('membersModalBackdrop').classList.add('open');
        const full = document.getElementById('membersFullList');
        full.innerHTML = '<div style="color:#4b5563;font-size:0.82rem;text-align:center;padding:16px;">Loading…</div>';
        fetch(`/study-groups/${activeGroupId}/members`)
            .then(r => r.ok ? r.json() : Promise.reject(r.status))
            .then(data => {
                const members = data.members || [];
                full.innerHTML = members.length
                    ? members.map(m => memberRowHtml(m, 'full')).join('')
                    : '<div style="color:#4b5563;font-size:0.82rem;text-align:center;padding:16px;">No members.</div>';
            })
            .catch(() => { full.innerHTML = '<div style="color:#f87171;font-size:0.82rem;text-align:center;padding:16px;">Failed to load.</div>'; });
    }
    function closeMembersModal() { document.getElementById('membersModalBackdrop').classList.remove('open'); }
    document.getElementById('membersModalBackdrop').addEventListener('click', function (e) { if (e.target === this) closeMembersModal(); });

    /* ══════════════════════════════════════════
       RENAME / PHOTO / DELETE GROUP
    ══════════════════════════════════════════ */
    function renameGroup() {
        if (!activeGroupId || !isGroupAdmin) return;
        const newName = document.getElementById('sdRenameInput').value.trim();
        if (!newName) { showToast('Please enter a group name.', 'error'); return; }
        _doRename(newName);
    }
    function renameGroupFromAdmin() {
        if (!activeGroupId || !isGroupAdmin) return;
        const newName = document.getElementById('adminRenameInput').value.trim();
        if (!newName) { showToast('Please enter a group name.', 'error'); return; }
        _doRename(newName);
    }
    function _doRename(newName) {
        fetch(`/study-groups/${activeGroupId}`, {
            method:  'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify({ name: newName }),
        }).then(r => r.json()).then(data => {
            if (data.success || data.group) {
                activeGroupData.name = newName;
                const item = document.querySelector(`[data-group-id="${activeGroupId}"]`);
                if (item) { item.querySelector('.sg-group-name').textContent = newName; item.dataset.name = newName.toLowerCase(); }
                const privacy = activeGroupData.privacy === 'private'
                    ? `<span class="header-privacy-badge private">🔒 Private</span>`
                    : `<span class="header-privacy-badge public">🌐 Public</span>`;
                document.getElementById('chatGroupName').innerHTML = escHtml(newName) + ' ' + privacy;
                document.getElementById('sdGroupName').textContent = newName;
                document.getElementById('adminRenameInput').value  = newName;
                showToast('Group renamed!', 'success');
            } else { showToast(data.error || 'Failed to rename.', 'error'); }
        }).catch(() => showToast('Failed to rename.', 'error'));
    }

    document.getElementById('sdAvatarWrap').addEventListener('click', function () {
        if (isGroupAdmin) document.getElementById('groupPhotoInput').click();
    });

    function handlePhotoChange(file) {
        if (!file || !activeGroupId || !isGroupAdmin) return;
        const form = new FormData();
        form.append('_token', CSRF);
        form.append('photo', file);
        fetch(`/study-groups/${activeGroupId}/photo`, { method: 'POST', body: form })
            .then(r => r.json()).then(data => {
                if (data.photo_url) {
                    const url = data.photo_url;
                    activeGroupData.photo = url;
                    document.getElementById('sdAvatar').innerHTML   = `<img src="${escHtml(url)}" alt="">`;
                    document.getElementById('chatAvatar').innerHTML  = `<img src="${escHtml(url)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`;
                    const sa = document.querySelector(`[data-group-id="${activeGroupId}"] .sg-group-avatar`);
                    if (sa) sa.innerHTML = `<img src="${escHtml(url)}" alt="">`;
                    showToast('Photo updated!', 'success');
                } else showToast(data.error || 'Failed to update photo.', 'error');
            }).catch(() => showToast('Failed to upload photo.', 'error'));
    }

    document.getElementById('groupPhotoInput').addEventListener('change', function () { if (this.files.length) handlePhotoChange(this.files[0]); this.value = ''; });
    document.getElementById('adminPhotoInput').addEventListener('change', function () { if (this.files.length) handlePhotoChange(this.files[0]); this.value = ''; });

    function deleteGroup() {
        if (!activeGroupId || !isGroupAdmin) { showToast('Only the group admin can delete this group.', 'error'); return; }
        if (!confirm('Delete this group? This cannot be undone. All messages, tasks, notes, and files will be deleted.')) return;
        fetch(`/study-groups/${activeGroupId}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF } })
            .then(r => r.json()).then(data => {
                if (data.success) {
                    document.querySelector(`[data-group-id="${activeGroupId}"]`)?.remove();
                    activeGroupId = null;
                    clearInterval(pollInterval);
                    clearInterval(threadPollInterval);
                    closeSettingsDropdown();
                    document.getElementById('chatEmpty').style.display  = 'flex';
                    document.getElementById('chatHeader').style.display = 'none';
                    document.getElementById('sgTabs').style.display     = 'none';
                    ['viewMessages', 'viewTasks', 'viewResources', 'viewNotes', 'viewCalendars', 'viewAdmin']
                        .forEach(id => { const el = document.getElementById(id); if (el) el.style.display = 'none'; });
                    showToast('Group deleted.', 'success');
                    if (!document.querySelectorAll('.sg-group-item').length) {
                        const d = document.createElement('div');
                        d.className  = 'no-msg';
                        d.style.marginTop = '24px';
                        d.innerHTML  = 'No groups yet. Hit <strong>+</strong> to create one!';
                        document.getElementById('groupList').appendChild(d);
                    }
                } else showToast(data.error || 'Failed to delete.', 'error');
            }).catch(() => showToast('Failed to delete.', 'error'));
    }

    /* ══════════════════════════════════════════
       ADMIN PANEL
    ══════════════════════════════════════════ */
    function loadAdminPanel() {
        if (!activeGroupId) return;
        const list = document.getElementById('adminMembersList');
        list.innerHTML = '<div class="no-msg">Loading members…</div>';
        fetch(`/study-groups/${activeGroupId}/members`)
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(data => {
                const members = data.members || [];
                document.getElementById('adminMemberCount').textContent = members.length + ' member' + (members.length !== 1 ? 's' : '');
                if (!members.length) { list.innerHTML = '<div class="no-msg">No members found.</div>'; return; }
                list.innerHTML = members.map(m => adminMemberRowHtml(m)).join('');
            }).catch(() => { list.innerHTML = '<div class="no-msg" style="color:#f87171;">Failed to load members.</div>'; });
    }

    function adminMemberRowHtml(m) {
        const isMe     = ME && String(m.id) === String(ME);
        const initials = (m.first_name || m.name || '?').charAt(0).toUpperCase();
        const avatar   = m.photo ? `<img src="${escHtml(m.photo)}" alt="">` : initials;
        const name     = `${m.first_name || ''} ${m.last_name || ''}`.trim() || m.name || 'Member';

        let roleBadge = m.is_owner
            ? `<span class="role-badge owner">Owner</span>`
            : m.role === 'admin'
                ? `<span class="role-badge admin">Admin</span>`
                : `<span class="role-badge member">Member</span>`;

        if (isMe) roleBadge += ` <span class="role-badge you">You</span>`;

        let actions = '';
        if (!isMe && !m.is_owner && isGroupAdmin) {
            const mid   = escHtml(m.id);
            const mname = escHtml(name);
            if (m.role !== 'admin') {
                actions += `<button class="btn-admin-member btn-promote" onclick="promoteMember('${mid}','${mname}')" title="Make admin">⬆ Admin</button>`;
            } else {
                actions += `<button class="btn-admin-member btn-demote" onclick="demoteMember('${mid}','${mname}')" title="Remove admin">⬇ Member</button>`;
            }
            actions += `<button class="btn-admin-member btn-kick" onclick="kickMember('${mid}','${mname}')" title="Remove from group">✕ Remove</button>`;
        }

        return `<div class="admin-member-row" id="admin-member-${escHtml(m.id)}">
            <div class="admin-member-avatar">${avatar}</div>
            <div class="admin-member-info">
                <div class="admin-member-name">${escHtml(name)}</div>
                ${m.username ? `<div class="admin-member-username">@${escHtml(m.username)}</div>` : ''}
            </div>
            <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;">
                ${roleBadge}
                <div class="admin-member-actions">${actions}</div>
            </div>
        </div>`;
    }

    function promoteMember(memberId, name) {
        if (!confirm(`Make ${name} an admin of this group?`)) return;
        fetch(`/study-groups/${activeGroupId}/members/${memberId}/role`, {
            method:  'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify({ role: 'admin' }),
        }).then(r => r.json()).then(data => {
            if (data.ok || data.success) { loadAdminPanel(); showToast(`${name} is now an admin!`, 'success'); }
            else showToast(data.error || 'Failed.', 'error');
        }).catch(() => showToast('Failed to update role.', 'error'));
    }

    function demoteMember(memberId, name) {
        if (!confirm(`Remove admin rights from ${name}?`)) return;
        fetch(`/study-groups/${activeGroupId}/members/${memberId}/role`, {
            method:  'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify({ role: 'member' }),
        }).then(r => r.json()).then(data => {
            if (data.ok || data.success) { loadAdminPanel(); showToast(`${name} is now a member.`); }
            else showToast(data.error || 'Failed.', 'error');
        }).catch(() => showToast('Failed to update role.', 'error'));
    }

    function kickMember(memberId, name) {
        if (!confirm(`Remove ${name} from this group?`)) return;
        fetch(`/study-groups/${activeGroupId}/members/${memberId}`, {
            method:  'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF },
        }).then(r => r.json()).then(data => {
            if (data.ok || data.success) { loadAdminPanel(); loadGroupMembers(); showToast(`${name} removed from group.`, 'success'); }
            else showToast(data.error || 'Failed.', 'error');
        }).catch(() => showToast('Failed to remove member.', 'error'));
    }

    /* ══════════════════════════════════════════
       MESSAGES
    ══════════════════════════════════════════ */
    function loadMessages(scrollToBottom) {
        if (!activeGroupId || currentTab !== 'messages') return;
        const cacheKey = 'sg:' + activeGroupId;
        const requestToken = ++messagesRequestToken;
        fetch(`/study-groups/${activeGroupId}/messages`)
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(data => {
                if (requestToken !== messagesRequestToken) return;
                if (!scrollToBottom && data.messages.length === lastMsgCount) return;
                lastMsgCount = data.messages.length;
                renderMessages(data.messages, scrollToBottom);
                try { sessionStorage.setItem(cacheKey, JSON.stringify(data.messages)); } catch (e) { }
            }).catch(() => { });
    }

    function messageDateLabel(message) {
        return new Date(message.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    function messageHtml(m) {
        const d       = new Date(m.created_at);
        const isOwn   = ME && String(m.user_id) === String(ME);
        const initials = (m.sender_first || '?').charAt(0).toUpperCase();
        const name    = isOwn ? 'You' : `${m.sender_first || ''} ${m.sender_last || ''}`.trim();
        const time    = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        const avatar  = m.sender_photo ? `<img src="${escHtml(m.sender_photo)}" alt="">` : initials;

        let content = '';

        // Calendar share detection
        let isCalShare = false, calData = null;
        try {
            if (m.message && m.message.startsWith('{')) {
                const p = JSON.parse(m.message);
                if (p.type === 'calendar_share') { isCalShare = true; calData = p; }
            }
        } catch (e) { }

        if (isCalShare && calData) {
            content += `<div class="msg-calendar-share">
                <div style="display:flex;align-items:center;gap:7px;font-weight:600;margin-bottom:4px;">
                    <span>📅</span> Shared their calendar
                </div>
                ${calData.message ? `<div style="font-size:0.8rem;color:#9ca3af;margin-left:22px;">"${escHtml(calData.message)}"</div>` : ''}
            </div>`;
        } else if (m.message && m.message.trim()) {
            content += `<div class="msg-bubble">${escHtml(m.message)}</div>`;
        }

        (m.attachments || []).forEach(a => {
            if (a.type === 'image') {
                content += `<img class="msg-image" src="${escHtml(a.url)}" alt="${escHtml(a.name)}" onclick="openLightbox('${escHtml(a.url)}')">`;
            } else {
                const ext = (a.name || '').split('.').pop().toUpperCase();
                const sz  = a.size ? formatBytes(a.size) : '';
                content += `<a class="msg-file" href="${escHtml(a.url)}" target="_blank" download>
                    <span>${fileIcon(ext)}</span>
                    <span class="msg-file-name">${escHtml(a.name)}</span>
                    <span class="msg-file-size">${sz}</span>
                </a>`;
            }
        });

        const replyCount = parseInt(m.reply_count, 10) || 0;
        const msgIdSafe  = escHtml(m.id);
        const canDelete = isGroupAdmin || isOwn;
        const menuItems = [
            `<button class="msg-menu-item" type="button" onclick="copyMessageText('${msgIdSafe}')">📋 Copy text</button>`,
            `<button class="msg-menu-item" type="button" onclick="openThread('${msgIdSafe}'); closeAllMenus();">💬 Open thread</button>`,
            canDelete ? `<button class="msg-menu-item danger" type="button" onclick="deleteMessage('${msgIdSafe}')">🗑 Delete message</button>` : ''
        ].filter(Boolean).join('');

        const deleteBtn = canDelete
            ? `<button class="msg-action-btn delete" type="button" title="Delete" onclick="deleteMessage('${msgIdSafe}'); closeAllMenus();">🗑</button><div class="msg-action-sep"></div>`
            : '';

        return `<div class="msg-row ${isOwn ? 'own' : ''}" id="msg-${msgIdSafe}"
                      data-msg-id="${msgIdSafe}"
                      data-sender="${escHtml(name)}"
                      data-text="${escHtml((m.message || '').substring(0, 120))}"
                      data-message-id="${msgIdSafe}">
            <div class="msg-avatar">${avatar}</div>
            <div class="msg-body">
                <div class="msg-sender">${escHtml(name)} · ${time}</div>
                ${content}
            </div>
            <div class="msg-actions" data-msg-id="${msgIdSafe}">
                ${deleteBtn}
                <button class="msg-action-btn thread" type="button" title="Reply in thread" onclick="openThreadById(this)">💬</button>
                <div class="msg-action-sep"></div>
                <div style="position:relative;">
                    <button class="msg-action-btn" type="button" title="More actions" onclick="toggleMsgMenu(event, '${msgIdSafe}')">⋮</button>
                    <div class="msg-action-menu" id="msgMenu_${msgIdSafe}">
                        ${menuItems}
                    </div>
                </div>
            </div>
        </div>`;
    }

    function toggleMsgMenu(e, msgId) {
        e.stopPropagation();
        closeAllMenus();
        const menu = document.getElementById('msgMenu_' + msgId);
        if (menu) menu.classList.toggle('open');
    }

    function closeAllMenus() {
        document.querySelectorAll('.msg-action-menu.open').forEach(menu => menu.classList.remove('open'));
    }

    document.addEventListener('click', closeAllMenus);

    function copyMessageText(msgId) {
        const row = document.querySelector(`.msg-row[data-msg-id="${CSS.escape(String(msgId))}"]`);
        if (!row) return;
        const text = row.querySelector('.msg-bubble')?.innerText || '';
        if (!text) return;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => showToast('Copied!', 'success')).catch(() => showToast('Failed to copy.', 'error'));
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                showToast('Copied!', 'success');
            } catch (err) {
                showToast('Failed to copy.', 'error');
            }
            ta.remove();
        }
        closeAllMenus();
    }

    function appendMessageIfNotExists(message) {
        const box = document.getElementById('messagesBox');
        const messageId = String(message.id);
        if (box.querySelector(`[data-message-id="${CSS.escape(messageId)}"]`)) return;

        const dateLabel = messageDateLabel(message);
        const lastRow = [...box.querySelectorAll('.msg-row[data-message-id]')].pop();
        const lastDateSep = box.querySelectorAll('.date-sep');
        const lastDateText = lastDateSep.length ? lastDateSep[lastDateSep.length - 1].textContent.trim() : '';

        if (!lastRow || lastDateText !== dateLabel) {
            box.insertAdjacentHTML('beforeend', `<div class="date-sep">${escHtml(dateLabel)}</div>`);
        }

        box.insertAdjacentHTML('beforeend', messageHtml(message));
    }

    function renderMessages(messages, scroll) {
        const box = document.getElementById('messagesBox');
        if (!messages.length) {
            box.innerHTML = '<div class="no-msg" style="margin-top:20px;">No messages yet. Say hello! 👋</div>';
            return;
        }

        if (scroll || !box.querySelector('.msg-row[data-message-id]')) {
            let html = '', lastDate = '';
            messages.forEach(m => {
                const date = messageDateLabel(m);
                if (date !== lastDate) { html += `<div class="date-sep">${escHtml(date)}</div>`; lastDate = date; }
                html += messageHtml(m);
            });
            box.innerHTML = html;
            box.scrollTop = box.scrollHeight;
            return;
        }

        messages.forEach(m => appendMessageIfNotExists(m));
    }

    function openThreadById(btnEl) {
        const row = btnEl.closest('[data-msg-id]');
        if (!row) return;
        openThread(row.dataset.msgId, row.dataset.sender, row.dataset.text);
    }

    function deleteMessage(btnEl) {
        if (!confirm('Delete this message?')) return;
        const msgId = typeof btnEl === 'string'
            ? btnEl
            : (btnEl?.dataset?.msgId || btnEl?.closest?.('[data-msg-id]')?.dataset?.msgId || '');
        if (!msgId) return;
        fetch(`/study-groups/${activeGroupId}/messages/${msgId}`, {
            method:  'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF },
        }).then(r => r.json()).then(data => {
            if (data.ok || data.success) {
                document.getElementById('msg-' + msgId)?.remove();
                lastMsgCount = Math.max(0, lastMsgCount - 1);
                showToast('Message deleted.');
                closeAllMenus();
            } else showToast(data.error || 'Failed to delete.', 'error');
        }).catch(() => showToast('Failed to delete.', 'error'));
    }

    /* ══════════════════════════════════════════
       SEND MESSAGE
    ══════════════════════════════════════════ */
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
        fetch(`/study-groups/${activeGroupId}/messages`, { method: 'POST', body: formData })
            .then(r => r.ok ? r.json() : r.json().then(d => Promise.reject(d.error || 'Error')))
            .then((data) => {
                const ta = document.getElementById('msgInput');
                ta.value = ''; ta.style.height = 'auto';
                pendingFiles = [];
                document.getElementById('uploadPreview').innerHTML = '';
                if (data && data.message) {
                    appendMessageIfNotExists(data.message);
                    lastMsgCount = document.querySelectorAll('.msg-row[data-message-id]').length;
                    const box = document.getElementById('messagesBox');
                    box.scrollTop = box.scrollHeight;
                } else {
                    loadMessages(true);
                }
            })
            .catch(err => showToast(String(err), 'error'))
            .finally(() => { btn.disabled = false; });
    }

    function handleEnter(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    }

    document.getElementById('imageInput').addEventListener('change', function () { addFiles(this.files, 'image'); this.value = ''; });
    document.getElementById('fileInput').addEventListener('change', function () { addFiles(this.files, 'file'); this.value = ''; });

    function addFiles(fileList, type) {
        Array.from(fileList).forEach(f => {
            const id = Math.random().toString(36).slice(2);
            const pf = { file: f, type, id };
            if (type === 'image') pf.previewUrl = URL.createObjectURL(f);
            pendingFiles.push(pf);
            const div = document.createElement('div');
            div.className = 'sg-preview-item';
            div.id        = 'prev_' + id;
            if (type === 'image') {
                div.innerHTML = `<img src="${pf.previewUrl}" alt=""><span class="sg-preview-name">${escHtml(f.name)}</span><button class="sg-preview-remove" onclick="removeFile('${id}')">✕</button>`;
            } else {
                div.innerHTML = `<span>${fileIcon(f.name.split('.').pop().toUpperCase())}</span><span class="sg-preview-name">${escHtml(f.name)}</span><button class="sg-preview-remove" onclick="removeFile('${id}')">✕</button>`;
            }
            document.getElementById('uploadPreview').appendChild(div);
        });
    }

    function removeFile(id) {
        pendingFiles = pendingFiles.filter(f => f.id !== id);
        document.getElementById('prev_' + id)?.remove();
    }

    /* ══════════════════════════════════════════
       THREADED MESSAGES
    ══════════════════════════════════════════ */
    function openThread(msgId, senderName, msgText) {
        activeThreadMsgId = msgId;
        lastThreadCount   = 0;
        document.getElementById('threadParentSender').textContent = senderName || 'Member';
        document.getElementById('threadParentText').textContent   = msgText || '(no text)';
        document.getElementById('threadPanel').classList.add('open');
        loadThreadReplies(true);
        clearInterval(threadPollInterval);
        threadPollInterval = setInterval(() => loadThreadReplies(false), 3000);
    }

    function closeThread() {
        document.getElementById('threadPanel').classList.remove('open');
        clearInterval(threadPollInterval);
        activeThreadMsgId = null;
    }

    function loadThreadReplies(scroll) {
        if (!activeThreadMsgId || !activeGroupId) return;
        fetch(`/study-groups/${activeGroupId}/messages/${activeThreadMsgId}/replies`)
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(data => {
                const replies = data.replies || [];
                if (!scroll && replies.length === lastThreadCount) return;
                lastThreadCount = replies.length;
                renderThreadReplies(replies, scroll);
            }).catch(() => { });
    }

    function renderThreadReplies(replies, scroll) {
        const box = document.getElementById('threadReplies');
        if (!replies.length) {
            box.innerHTML = '<div class="no-msg">No replies yet. Start the thread!</div>';
            return;
        }
        let html = '';
        replies.forEach(r => {
            const isOwn    = ME && String(r.user_id) === String(ME);
            const name     = isOwn ? 'You' : `${r.sender_first || ''} ${r.sender_last || ''}`.trim() || 'Member';
            const time     = new Date(r.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            const initials = (r.sender_first || name || '?').charAt(0).toUpperCase();
            const avatar   = r.sender_photo ? `<img src="${escHtml(r.sender_photo)}" alt="">` : initials;
            const canDel   = isOwn || isGroupAdmin;
            const delBtn   = canDel
                ? `<button class="btn-delete-reply" data-reply-id="${escHtml(r.id)}" onclick="deleteReply(this)" title="Delete reply">🗑</button>`
                : '';
            html += `<div class="thread-reply-row" id="reply-${escHtml(r.id)}">
                <div class="thread-reply-avatar">${avatar}</div>
                <div class="thread-reply-body">
                    <div class="thread-reply-meta">
                        <span>${escHtml(name)}</span>
                        <span>${time}</span>
                        ${delBtn}
                    </div>
                    <div class="thread-reply-text">${escHtml(r.message || '')}</div>
                </div>
            </div>`;
        });
        box.innerHTML = html;
        if (scroll) box.scrollTop = box.scrollHeight;
    }

    function deleteReply(btnEl) {
        if (!confirm('Delete this reply?')) return;
        const replyId = btnEl.dataset.replyId;
        // FIX: use correct delete reply route
        fetch(`/study-groups/${activeGroupId}/messages/${activeThreadMsgId}/replies/${replyId}`, {
            method:  'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF },
        }).then(r => r.json()).then(data => {
            if (data.ok || data.success) {
                document.getElementById('reply-' + replyId)?.remove();
                lastThreadCount = Math.max(0, lastThreadCount - 1);
                loadMessages(false); // refresh reply count on parent
            } else showToast(data.error || 'Failed.', 'error');
        }).catch(() => showToast('Failed to delete reply.', 'error'));
    }

    function sendThreadReply() {
        if (!activeThreadMsgId || !activeGroupId) return;
        const text = document.getElementById('threadInput').value.trim();
        if (!text) return;
        fetch(`/study-groups/${activeGroupId}/messages/${activeThreadMsgId}/replies`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify({ message: text }),
        }).then(r => r.ok ? r.json() : Promise.reject())
          .then(() => {
              const ta = document.getElementById('threadInput');
              ta.value = ''; ta.style.height = 'auto';
              loadThreadReplies(true);
              loadMessages(false);
          }).catch(() => showToast('Failed to send reply.', 'error'));
    }

    function handleThreadEnter(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendThreadReply(); }
    }

    /* ══════════════════════════════════════════
       TASKS
    ══════════════════════════════════════════ */
    function loadTasks() {
        if (!activeGroupId) return;
        fetch(`/study-groups/${activeGroupId}/tasks`)
            .then(r => r.ok ? r.json() : Promise.resolve({ tasks: [] }))
            .then(data => renderTasks(data.tasks || []))
            .catch(() => renderTasks([]));
    }

    function renderTasks(tasks) {
        const container = document.getElementById('tasksList');
        if (!tasks.length) {
            container.innerHTML = '<div class="no-msg" style="padding:40px 0;">No tasks yet. Create one to get started! ✅</div>';
            return;
        }
        const pending   = tasks.filter(t => !t.completed);
        const completed = tasks.filter(t =>  t.completed);
        let html = '';
        if (pending.length) {
            html += `<div class="tasks-section-label">📋 To Do (${pending.length})</div>`;
            html += pending.map(t => taskItemHtml(t)).join('');
        }
        if (completed.length) {
            html += `<div class="tasks-section-label" style="margin-top:16px;">✅ Completed (${completed.length})</div>`;
            html += completed.map(t => taskItemHtml(t)).join('');
        }
        container.innerHTML = html;
    }

    function taskItemHtml(t) {
        const today    = new Date().toISOString().split('T')[0];
        const overdue  = t.due_date && t.due_date < today && !t.completed;
        const dueLabel = t.due_date ? `<span class="task-due ${overdue ? 'overdue' : ''}">📅 ${t.due_date}</span>` : '';
        const assignee = t.assigned_to ? `<span class="task-assignee">👤 ${escHtml(t.assigned_to)}</span>` : '';
        const priority = t.priority || 'medium';
        const tid      = escHtml(String(t.id));
        const canDel   = isGroupAdmin || (ME && String(t.created_by) === String(ME));
        const delBtn   = canDel ? `<button class="btn-task-action danger" data-task-id="${tid}" onclick="deleteTask(this)" title="Delete">🗑️</button>` : '';
        return `<div class="task-item ${t.completed ? 'done' : ''}" id="task-${tid}">
            <div class="task-checkbox ${t.completed ? 'checked' : ''}" data-task-id="${tid}" data-completed="${!t.completed}" onclick="toggleTask(this)"></div>
            <div class="task-content">
                <div class="task-title">${escHtml(t.title)}</div>
                <div class="task-meta">
                    <span class="task-badge priority-${priority}">${priority.charAt(0).toUpperCase() + priority.slice(1)}</span>
                    ${dueLabel}${assignee}
                </div>
            </div>
            <div class="task-item-actions">${delBtn}</div>
        </div>`;
    }

    function toggleTaskForm() {
        const form = document.getElementById('taskAddForm');
        form.classList.toggle('open');
        if (form.classList.contains('open')) document.getElementById('taskTitleInput').focus();
    }

    function saveTask() {
        if (!activeGroupId) return;
        const title    = document.getElementById('taskTitleInput').value.trim();
        const priority = document.getElementById('taskPriority').value;
        const dueDate  = document.getElementById('taskDueDate').value;
        const assignee = document.getElementById('taskAssignee').value.trim();
        if (!title) { showToast('Please enter a task title.', 'error'); return; }
        fetch(`/study-groups/${activeGroupId}/tasks`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify({ title, priority, due_date: dueDate || null, assigned_to: assignee || null }),
        }).then(r => r.ok ? r.json() : Promise.reject())
          .then(() => {
              document.getElementById('taskTitleInput').value = '';
              document.getElementById('taskDueDate').value    = '';
              document.getElementById('taskAssignee').value   = '';
              document.getElementById('taskPriority').value   = 'medium';
              toggleTaskForm();
              loadTasks();
              showToast('Task created!', 'success');
          }).catch(() => showToast('Failed to create task.', 'error'));
    }

    function toggleTask(el) {
        const taskId    = el.dataset.taskId;
        const completed = el.dataset.completed === 'true';
        fetch(`/study-groups/${activeGroupId}/tasks/${taskId}`, {
            method:  'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify({ completed }),
        }).then(r => r.ok ? r.json() : Promise.reject())
          .then(() => loadTasks())
          .catch(() => showToast('Failed to update task.', 'error'));
    }

    function deleteTask(btnEl) {
        if (!confirm('Delete this task?')) return;
        const taskId = btnEl.dataset.taskId;
        fetch(`/study-groups/${activeGroupId}/tasks/${taskId}`, {
            method:  'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF },
        }).then(r => r.ok ? r.json() : Promise.reject())
          .then(() => { loadTasks(); showToast('Task deleted.'); })
          .catch(() => showToast('Failed to delete task.', 'error'));
    }

    /* ══════════════════════════════════════════
       RESOURCES
    ══════════════════════════════════════════ */
    function loadResources() {
        if (!activeGroupId) return;
        fetch(`/study-groups/${activeGroupId}/resources`)
            .then(r => r.ok ? r.json() : Promise.resolve({ resources: [] }))
            .then(data => { allResources = data.resources || []; renderResources(); })
            .catch(() => { allResources = []; renderResources(); });
    }

    function renderResources() {
        const pinned = allResources.filter(r => r.pinned);
        const all    = allResources.filter(r => {
            if (resourceFilter === 'all') return true;
            const fname = (r.file_name || '').toLowerCase();
            if (resourceFilter === 'image') return r.type === 'image' || /\.(png|jpg|jpeg|gif|webp|bmp|svg)$/.test(fname);
            if (resourceFilter === 'pdf')   return fname.endsWith('.pdf');
            if (resourceFilter === 'doc')   return /\.(doc|docx|txt|odt|rtf|md)$/.test(fname);
            if (resourceFilter === 'other') {
                return r.type !== 'image'
                    && !fname.endsWith('.pdf')
                    && !/\.(doc|docx|txt|odt|rtf|md|png|jpg|jpeg|gif|webp|bmp|svg)$/.test(fname);
            }
            return true;
        });

        const pinnedSection = document.getElementById('pinnedResourcesSection');
        const pinnedGrid    = document.getElementById('pinnedResourcesGrid');
        const allGrid       = document.getElementById('resourcesGrid');

        if (pinned.length) {
            pinnedSection.style.display = 'block';
            pinnedGrid.innerHTML = pinned.map(r => resourceCardHtml(r)).join('');
        } else {
            pinnedSection.style.display = 'none';
        }

        allGrid.innerHTML = all.length
            ? all.map(r => resourceCardHtml(r)).join('')
            : '<div class="no-msg" style="grid-column:1/-1;padding:40px 0;">No files yet. Upload the first one! 📁</div>';
    }

    function resourceCardHtml(r) {
        const ext     = (r.file_name || '').split('.').pop().toUpperCase();
        const icon    = (r.type === 'image' || /\.(png|jpg|jpeg|gif|webp)$/i.test(r.file_name || '')) ? '🖼️' : fileIcon(ext);
        const pinIcon = r.pinned ? '<span class="resource-pin-icon">📌</span>' : '';
        const size    = r.file_size ? formatBytes(r.file_size) : '';
        const up      = r.uploader_name ? `by ${escHtml(r.uploader_name)}` : '';
        const rid     = escHtml(String(r.id));
        const pinLabel = r.pinned ? '📌 Unpin' : '📌 Pin';
        return `<div class="resource-card ${r.pinned ? 'pinned' : ''}" id="resource-${rid}">
            ${pinIcon}
            <div class="resource-icon">${icon}</div>
            <div class="resource-name">${escHtml(r.file_name || 'File')}</div>
            <div class="resource-meta">${size}</div>
            <div class="resource-uploader">${up}</div>
            <div class="resource-card-actions">
                <a class="btn-resource-action" href="${escHtml(r.file_url)}" target="_blank" download>⬇ Download</a>
                <button class="btn-resource-action" data-resource-id="${rid}" data-pin="${!r.pinned}" onclick="togglePin(this)">${pinLabel}</button>
                <button class="btn-resource-action danger" data-resource-id="${rid}" onclick="deleteResource(this)" style="color:#f87171;">🗑</button>
            </div>
        </div>`;
    }

    function filterResources(type, btn) {
        resourceFilter = type;
        document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        renderResources();
    }

    function uploadResources(files) {
        if (!files.length || !activeGroupId) return;
        const formData = new FormData();
        formData.append('_token', CSRF);
        Array.from(files).forEach(f => formData.append('files[]', f));
        showToast('Uploading files…');
        fetch(`/study-groups/${activeGroupId}/resources`, { method: 'POST', body: formData })
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(() => { loadResources(); showToast('Files uploaded!', 'success'); })
            .catch(() => showToast('Upload failed.', 'error'));
        document.getElementById('resourceFileInput').value = '';
    }

    function togglePin(btnEl) {
        const resourceId = btnEl.dataset.resourceId;
        const pin        = btnEl.dataset.pin === 'true';
        fetch(`/study-groups/${activeGroupId}/resources/${resourceId}/pin`, {
            method:  'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify({ pinned: pin }),
        }).then(r => r.ok ? r.json() : Promise.reject())
          .then(() => { loadResources(); showToast(pin ? 'Pinned!' : 'Unpinned.', 'success'); })
          .catch(() => showToast('Failed to update pin.', 'error'));
    }

    function deleteResource(btnEl) {
        if (!confirm('Delete this file?')) return;
        const resourceId = btnEl.dataset.resourceId;
        fetch(`/study-groups/${activeGroupId}/resources/${resourceId}`, {
            method:  'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF },
        }).then(r => r.ok ? r.json() : Promise.reject())
          .then(() => { loadResources(); showToast('File deleted.'); })
          .catch(() => showToast('Failed to delete.', 'error'));
    }

    /* ══════════════════════════════════════════
       NOTES
    ══════════════════════════════════════════ */
    function loadNotes() {
        if (!activeGroupId) return;
        fetch(`/study-groups/${activeGroupId}/notes`)
            .then(r => r.ok ? r.json() : Promise.resolve({ notes: [] }))
            .then(data => {
                notesList = data.notes || [];
                renderNotesList();
                if (notesList.length) {
                    openNote(notesList[0].id);
                } else {
                    document.getElementById('noteTitleInput').value  = '';
                    document.getElementById('noteEditor').innerHTML  = '';
                    currentNoteId = null;
                }
            }).catch(() => { });
    }

    function renderNotesList() {
        const container = document.getElementById('notesList');
        if (!notesList.length) {
            container.innerHTML = '<div class="no-msg">No notes yet.</div>';
            return;
        }
        container.innerHTML = notesList.map(n => {
            const nid = escHtml(String(n.id));
            return `<div class="note-list-item ${n.id === currentNoteId ? 'active' : ''}" onclick="openNote('${nid}')">
                <div class="note-list-title">${escHtml(n.title || 'Untitled')}</div>
                <div class="note-list-meta">${new Date(n.updated_at || n.created_at).toLocaleDateString()}</div>
                <button class="btn-delete-note" data-note-id="${nid}" onclick="event.stopPropagation();deleteNote(this)" title="Delete note">🗑</button>
            </div>`;
        }).join('');
    }

    function openNote(noteId) {
        currentNoteId = noteId;
        const note = notesList.find(n => String(n.id) === String(noteId));
        if (!note) return;
        document.getElementById('noteTitleInput').value = note.title || '';
        document.getElementById('noteEditor').innerHTML = note.content || '';
        renderNotesList();
    }

    function createNewNote() {
        if (!activeGroupId) return;
        fetch(`/study-groups/${activeGroupId}/notes`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify({ title: 'Untitled Note', content: '' }),
        }).then(r => r.ok ? r.json() : Promise.reject())
          .then(() => loadNotes())
          .catch(() => showToast('Failed to create note.', 'error'));
    }

    function deleteNote(btnEl) {
        if (!confirm('Delete this note?')) return;
        const noteId = btnEl.dataset.noteId;
        fetch(`/study-groups/${activeGroupId}/notes/${noteId}`, {
            method:  'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF },
        }).then(r => r.ok ? r.json() : Promise.reject())
          .then(() => {
              if (String(currentNoteId) === String(noteId)) {
                  currentNoteId = null;
                  document.getElementById('noteTitleInput').value = '';
                  document.getElementById('noteEditor').innerHTML = '';
              }
              loadNotes();
              showToast('Note deleted.');
          }).catch(() => showToast('Failed to delete note.', 'error'));
    }

    function scheduleNoteSave() {
        clearTimeout(noteSaveTimeout);
        document.getElementById('notesSaveStatus').textContent = 'Saving…';
        document.getElementById('notesSaveStatus').className   = 'notes-save-status saving';
        noteSaveTimeout = setTimeout(saveCurrentNote, 1500);
    }

    function saveCurrentNote() {
        if (!activeGroupId || !currentNoteId) return;
        const title   = document.getElementById('noteTitleInput').value;
        const content = document.getElementById('noteEditor').innerHTML;
        fetch(`/study-groups/${activeGroupId}/notes/${currentNoteId}`, {
            method:  'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify({ title, content }),
        }).then(r => r.ok ? r.json() : Promise.reject())
          .then(() => {
              document.getElementById('notesSaveStatus').textContent = '✓ Saved';
              document.getElementById('notesSaveStatus').className   = 'notes-save-status saved';
              const note = notesList.find(n => String(n.id) === String(currentNoteId));
              if (note) { note.title = title; note.updated_at = new Date().toISOString(); renderNotesList(); }
              setTimeout(() => {
                  document.getElementById('notesSaveStatus').textContent = 'Auto-save on';
                  document.getElementById('notesSaveStatus').className   = 'notes-save-status';
              }, 2000);
          }).catch(() => {
              document.getElementById('notesSaveStatus').textContent = 'Save failed';
              document.getElementById('notesSaveStatus').className   = 'notes-save-status';
          });
    }

    function execFormat(cmd, val) {
        document.getElementById('noteEditor').focus();
        document.execCommand(cmd, false, val || null);
    }

    /* ══════════════════════════════════════════
       SHARED CALENDARS
    ══════════════════════════════════════════ */
    function loadGroupSharedCalendars() {
        if (!activeGroupId) return;
        document.getElementById('calendarsBox').innerHTML = '<div style="text-align:center;color:#6b7280;padding:60px 20px;"><div style="font-size:2rem;margin-bottom:10px;">📅</div><p>Loading shared calendars…</p></div>';
        fetch(`/study-groups/${activeGroupId}/shared-calendars`)
            .then(r => r.json())
            .then(data => {
                const cals = data.calendars || [];
                if (!cals.length) {
                    document.getElementById('calendarsBox').innerHTML = `<div style="text-align:center;color:#6b7280;padding:60px 20px;">
                        <div style="font-size:2rem;margin-bottom:10px;">📅</div>
                        <p>No shared calendars yet</p>
                        <p style="font-size:0.8rem;margin-top:8px;color:#4b5563;">Members can share their calendars from their profile.</p>
                    </div>`;
                    return;
                }
                let html = '<div style="display:grid;gap:14px;">';
                cals.forEach(cal => {
                    const evCount    = (cal.events || []).length;
                    const avatarHtml = cal.owner_photo
                        ? `<img src="${escHtml(cal.owner_photo)}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">`
                        : `<div class="cal-member-avatar">${(cal.owner_name || '?').charAt(0).toUpperCase()}</div>`;
                    html += `<div class="cal-member-card">
                        <div class="cal-member-header">
                            ${avatarHtml}
                            <div>
                                <div class="cal-member-name">${escHtml(cal.owner_name)}'s Calendar</div>
                                <div class="cal-member-count">${evCount} event${evCount !== 1 ? 's' : ''}</div>
                            </div>
                        </div>
                        <div class="cal-events-list">
                            ${!evCount
                                ? '<div style="color:#4b5563;font-size:0.8rem;padding:8px 4px;">No upcoming events</div>'
                                : (cal.events || []).map(e => `
                                    <div class="cal-event-row">
                                        <div class="cal-event-dot"></div>
                                        <div>
                                            <div class="cal-event-title">${escHtml(e.title)}</div>
                                            <div class="cal-event-time">${escHtml(e.event_date)} ${escHtml(e.event_time || '')}</div>
                                        </div>
                                    </div>`).join('')
                            }
                        </div>
                    </div>`;
                });
                html += '</div>';
                document.getElementById('calendarsBox').innerHTML = html;
            }).catch(() => {
                document.getElementById('calendarsBox').innerHTML = '<div style="text-align:center;color:#ef4444;padding:40px 20px;"><p>Failed to load calendars</p></div>';
            });
    }

    /* ══════════════════════════════════════════
       CREATE GROUP MODAL
    ══════════════════════════════════════════ */
    document.getElementById('btnOpenModal').onclick = () => {
        document.getElementById('groupNameInput').value    = '';
        document.getElementById('groupSubjectInput').value = '';
        document.querySelectorAll('#friendList input[type="checkbox"]').forEach(c => c.checked = false);
        document.getElementById('radioPublic').checked = true;
        document.getElementById('optPublic').classList.add('selected-public');
        document.getElementById('optPublic').classList.remove('selected-private');
        document.getElementById('optPrivate').classList.remove('selected-public', 'selected-private');
        document.getElementById('friendsField').style.display = 'none';
        document.getElementById('publicDesc').style.display   = 'block';
        document.getElementById('privateDesc').style.display  = 'none';
        loadFriendsForModal();
        document.getElementById('modalBackdrop').classList.add('open');
    };

    document.querySelectorAll('input[name="groupPrivacy"]').forEach(radio => {
        radio.addEventListener('change', function () {
            document.getElementById('optPublic').classList.remove('selected-public', 'selected-private');
            document.getElementById('optPrivate').classList.remove('selected-public', 'selected-private');
            if (this.value === '1') {
                document.getElementById('optPrivate').classList.add('selected-private');
                document.getElementById('friendsField').style.display = 'block';
                document.getElementById('publicDesc').style.display   = 'none';
                document.getElementById('privateDesc').style.display  = 'block';
            } else {
                document.getElementById('optPublic').classList.add('selected-public');
                document.getElementById('friendsField').style.display = 'none';
                document.getElementById('publicDesc').style.display   = 'block';
                document.getElementById('privateDesc').style.display  = 'none';
            }
        });
    });

    function loadFriendsForModal() {
        const fl = document.getElementById('friendList');
        fl.innerHTML = '<div style="padding:8px;color:#9ca3af;">Loading friends…</div>';
        fetch('/study-groups/api/friends')
            .then(r => r.json())
            .then(data => {
                if (!data.friends || !data.friends.length) { fl.innerHTML = '<div class="no-msg">No friends to add yet.</div>'; return; }
                fl.innerHTML = data.friends.map(f => `
                    <label class="friend-item" for="friend_${escHtml(String(f.id))}">
                        <input type="checkbox" id="friend_${escHtml(String(f.id))}" value="${escHtml(String(f.id))}">
                        <div class="friend-avatar">${f.photo ? `<img src="${escHtml(f.photo)}" alt="">` : escHtml(f.initials || '?')}</div>
                        <div>
                            <div class="friend-name">${escHtml(f.name)}</div>
                            <div class="friend-username">@${escHtml(f.username || 'friend')}</div>
                        </div>
                    </label>`).join('');
            }).catch(() => { fl.innerHTML = '<div class="no-msg">Failed to load friends.</div>'; });
    }

    function closeModal() { document.getElementById('modalBackdrop').classList.remove('open'); }
    document.getElementById('modalBackdrop').addEventListener('click', function (e) { if (e.target === this) closeModal(); });

    function createGroup() {
        if (createGroupPending) return;

        const name      = document.getElementById('groupNameInput').value.trim();
        const subject   = document.getElementById('groupSubjectInput').value.trim();
        const isPrivate = document.querySelector('input[name="groupPrivacy"]:checked').value;
        if (!name) { showToast('Please enter a group name.', 'error'); return; }
        const members = isPrivate === '1'
            ? Array.from(document.querySelectorAll('#friendList input[type="checkbox"]:checked')).map(c => c.value)
            : [];

        createGroupPending = true;
        const createBtn = document.querySelector('#modalBackdrop .btn-create');
        const restoreCreateBtn = () => {
            createGroupPending = false;
            if (createBtn) createBtn.disabled = false;
        };

        if (createBtn) createBtn.disabled = true;

        fetch('/study-groups', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify({ name, subject, members, is_private: isPrivate }),
        }).then(r => r.json()).then(data => {
            if (data.group) {
                closeModal();
                document.querySelector('#groupList .no-msg')?.remove();
                const privacy   = isPrivate === '1' ? 'private' : 'public';
                const badgeHtml = privacy === 'private'
                    ? `<span class="privacy-badge private">🔒</span>`
                    : `<span class="privacy-badge public">🌐</span>`;
                const div = document.createElement('div');
                div.className       = 'sg-group-item';
                div.dataset.groupId = data.group.id;
                div.dataset.privacy = privacy;
                div.dataset.name    = name.toLowerCase();
                div.setAttribute('onclick', `openGroup('${data.group.id}', this)`);
                div.innerHTML = `
                    <div class="sg-group-avatar">${name.substring(0, 2).toUpperCase()}</div>
                    <div class="sg-group-item-wrap">
                        <div class="sg-group-name">${escHtml(name)}</div>
                        <div class="sg-group-meta">
                            <span class="sg-group-subject">${escHtml(data.group.subject || 'General')} · 1 member</span>
                            ${badgeHtml}
                        </div>
                    </div>`;
                document.getElementById('groupList').prepend(div);
                openGroup(data.group.id, div);
                showToast('Group created!', 'success');
            } else { showToast(data.error || 'Failed to create group.', 'error'); }
        }).catch(() => showToast('Failed to create group.', 'error'))
          .finally(restoreCreateBtn);
    }

    /* ══════════════════════════════════════════
       LIGHTBOX
    ══════════════════════════════════════════ */
    function openLightbox(url) { document.getElementById('lightboxImg').src = url; document.getElementById('lightbox').classList.add('open'); }
    function closeLightbox()   { document.getElementById('lightbox').classList.remove('open'); }

    /* ══════════════════════════════════════════
       AUTO-OPEN FIRST GROUP
    ══════════════════════════════════════════ */
    window.addEventListener('DOMContentLoaded', () => {
        if (window.studyGroupData.hasGroups) {
            const first = document.querySelector('.sg-group-item');
            if (first) openGroup(window.studyGroupData.firstGroupId, first);
        }
    });

    // Expose to global scope for inline HTML onclick handlers
    window.openGroup          = openGroup;
    window.filterGroups       = filterGroups;
    window.switchTab          = switchTab;
    window.toggleSettings     = toggleSettings;
    window.openMembersModal   = openMembersModal;
    window.closeMembersModal  = closeMembersModal;
    window.renameGroup        = renameGroup;
    window.renameGroupFromAdmin = renameGroupFromAdmin;
    window.deleteGroup        = deleteGroup;
    window.sendMessage        = sendMessage;
    window.handleEnter        = handleEnter;
    window.autoResize         = autoResize;
    window.removeFile         = removeFile;
    window.openThreadById     = openThreadById;
    window.deleteMessage      = deleteMessage;
    window.toggleMsgMenu      = toggleMsgMenu;
    window.closeAllMenus      = closeAllMenus;
    window.copyMessageText    = copyMessageText;
    window.closeThread        = closeThread;
    window.sendThreadReply    = sendThreadReply;
    window.handleThreadEnter  = handleThreadEnter;
    window.deleteReply        = deleteReply;
    window.toggleTaskForm     = toggleTaskForm;
    window.saveTask           = saveTask;
    window.toggleTask         = toggleTask;
    window.deleteTask         = deleteTask;
    window.filterResources    = filterResources;
    window.uploadResources    = uploadResources;
    window.togglePin          = togglePin;
    window.deleteResource     = deleteResource;
    window.openNote           = openNote;
    window.createNewNote      = createNewNote;
    window.deleteNote         = deleteNote;
    window.scheduleNoteSave   = scheduleNoteSave;
    window.execFormat         = execFormat;
    window.createGroup        = createGroup;
    window.closeModal         = closeModal;
    window.openLightbox       = openLightbox;
    window.closeLightbox      = closeLightbox;
    window.promoteMember      = promoteMember;
    window.demoteMember       = demoteMember;
    window.kickMember         = kickMember;

})();
