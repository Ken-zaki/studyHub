(function () {
    "use strict";

    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const ME = window.studyGroupData.userId;

    let activeGroupId = null;
    let activeGroupData = {};
    let isGroupAdmin = false;
    let pollInterval = null;
    let pendingFiles = [];
    let lastMsgCount = 0;
    let messagesRequestToken = 0;
    let settingsOpen = false;
    let currentTab = "messages";
    let createGroupPending = false;
    let membersRequestToken = 0;

    let activeThreadMsgId = null;
    let threadPollInterval = null;
    let lastThreadCount = 0;

    let currentNoteId = null;
    let noteSaveTimeout = null;
    let notesList = [];

    let allResources = [];
    let resourceFilter = "all";

    /* ══════════════════════════════════════════
       HELPERS
    ══════════════════════════════════════════ */
    function escHtml(str) {
        return String(str ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }
    function formatBytes(b) {
        b = parseInt(b, 10) || 0;
        if (b < 1024) return b + " B";
        if (b < 1048576) return (b / 1024).toFixed(1) + " KB";
        return (b / 1048576).toFixed(1) + " MB";
    }
    function fileIcon(ext) {
        const m = {
            PDF: "📄",
            DOC: "📝",
            DOCX: "📝",
            XLS: "📊",
            XLSX: "📊",
            PPT: "📑",
            PPTX: "📑",
            ZIP: "🗜️",
            RAR: "🗜️",
            MP4: "🎥",
            MP3: "🎵",
            PNG: "🖼️",
            JPG: "🖼️",
            JPEG: "🖼️",
            GIF: "🖼️",
            TXT: "📃",
            CSV: "📊",
        };
        return m[(ext || "").toUpperCase()] || "📁";
    }
    function autoResize(el) {
        el.style.height = "auto";
        el.style.height = Math.min(el.scrollHeight, 120) + "px";
    }

    /* ══════════════════════════════════════════
       TOAST
    ══════════════════════════════════════════ */
    function showToast(msg, type = "") {
        const t = document.getElementById("sgToast");
        t.textContent = msg;
        t.className = "sg-toast show " + type;
        clearTimeout(t._t);
        t._t = setTimeout(() => t.classList.remove("show"), 3000);
    }

    /* ══════════════════════════════════════════
       OPEN GROUP
    ══════════════════════════════════════════ */
    function openGroup(groupId, el) {
        document
            .querySelectorAll(".sg-group-item")
            .forEach((i) => i.classList.remove("active"));
        el.classList.add("active");
        activeGroupId = groupId;
        lastMsgCount = 0;
        settingsOpen = false;
        currentTab = "messages";
        closeSettingsDropdown();

        const name = el.querySelector(".sg-group-name").textContent.trim();
        const subject = el
            .querySelector(".sg-group-subject")
            .textContent.trim();
        const privacy = el.dataset.privacy || "public";
        const avatarEl = el.querySelector(".sg-group-avatar img");
        const photo = avatarEl ? avatarEl.src : null;
        activeGroupData = { name, subject, privacy, photo };

        document.getElementById("chatEmpty").style.display = "none";
        document.getElementById("chatHeader").style.display = "flex";
        document.getElementById("sgTabs").style.display = "flex";

        const hAvatar = document.getElementById("chatAvatar");
        if (photo) {
            hAvatar.innerHTML = `<img src="${escHtml(photo)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`;
        } else {
            hAvatar.textContent = name.substring(0, 2).toUpperCase();
            hAvatar.style.background =
                "linear-gradient(135deg,#8b7355,#b08c67)";
        }

        const privBadge =
            privacy === "private"
                ? `<span class="header-privacy-badge private">🔒 Private</span>`
                : `<span class="header-privacy-badge public">🌐 Public</span>`;
        document.getElementById("chatGroupName").innerHTML =
            escHtml(name) + " " + privBadge;
        document.getElementById("chatGroupMembers").textContent = subject;

        syncSettingsDropdown().then((members) => {
            if (Array.isArray(members)) {
                isGroupAdmin = members.some(
                    (m) =>
                        String(m.id) === String(ME) &&
                        (m.is_owner || m.role === "admin"),
                );
                syncAdminUI();
            }
        });

        switchTab("messages");
        clearInterval(pollInterval);
        clearInterval(threadPollInterval);
    }

    /* ══════════════════════════════════════════
       ADMIN STATUS
    ══════════════════════════════════════════ */
    function syncAdminUI() {
        const adminTab = document.getElementById("tabAdmin");
        adminTab.classList.toggle("visible", isGroupAdmin);
        const renameSection = document.getElementById("sdRenameSection");
        const deleteSection = document.getElementById("sdDeleteSection");
        const sdAvatarWrap = document.getElementById("sdAvatarWrap");
        if (renameSection)
            renameSection.style.display = isGroupAdmin ? "" : "none";
        if (deleteSection)
            deleteSection.style.display = isGroupAdmin ? "" : "none";
        if (sdAvatarWrap)
            sdAvatarWrap.style.cursor = isGroupAdmin ? "pointer" : "default";
        document.getElementById("adminRenameInput").value =
            activeGroupData.name || "";
    }

    function filterGroups(query) {
        const q = query.toLowerCase().trim();
        document.querySelectorAll(".sg-group-item").forEach((el) => {
            const name = el.dataset.name || "";
            el.style.display = !q || name.includes(q) ? "" : "none";
        });
    }

    /* ══════════════════════════════════════════
       TAB SWITCHING
    ══════════════════════════════════════════ */
    function switchTab(tab) {
        currentTab = tab;
        const views = [
            "messages",
            "tasks",
            "resources",
            "notes",
            "calendars",
            "admin",
        ];
        views.forEach((v) => {
            const el = document.getElementById(
                "view" + v.charAt(0).toUpperCase() + v.slice(1),
            );
            if (el) el.style.display = "none";
        });
        document
            .querySelectorAll(".sg-tab")
            .forEach((t) => t.classList.remove("active"));
        const tabBtn = document.getElementById(
            "tab" + tab.charAt(0).toUpperCase() + tab.slice(1),
        );
        const viewEl = document.getElementById(
            "view" + tab.charAt(0).toUpperCase() + tab.slice(1),
        );
        if (tabBtn) tabBtn.classList.add("active");
        if (viewEl) viewEl.style.display = "flex";

        clearInterval(pollInterval);
        clearInterval(threadPollInterval);

        if (tab === "messages") {
            loadMessages(true);
            pollInterval = setInterval(() => loadMessages(false), 3000);
        } else if (tab === "tasks") loadTasks();
        else if (tab === "resources") loadResources();
        else if (tab === "notes") loadNotes();
        else if (tab === "calendars") loadGroupSharedCalendars();
        else if (tab === "admin") loadAdminPanel();
    }

    /* ══════════════════════════════════════════
       SETTINGS DROPDOWN
    ══════════════════════════════════════════ */
    function toggleSettings(e) {
        e.stopPropagation();
        settingsOpen = !settingsOpen;
        const btn = document.getElementById("btnSettings");
        const dropdown = document.getElementById("settingsDropdown");
        btn.classList.toggle("active", settingsOpen);
        if (settingsOpen) {
            const rect = btn.getBoundingClientRect();
            dropdown.style.top = rect.bottom + 8 + "px";
            dropdown.style.right = window.innerWidth - rect.right + "px";
            dropdown.style.left = "auto";
            dropdown.classList.add("open");
            syncSettingsDropdown();
        } else {
            dropdown.classList.remove("open");
        }
    }

    function closeSettingsDropdown() {
        settingsOpen = false;
        document.getElementById("settingsDropdown").classList.remove("open");
        document.getElementById("btnSettings").classList.remove("active");
    }

    document.addEventListener("click", function (e) {
        if (
            settingsOpen &&
            !document.getElementById("settingsWrap").contains(e.target)
        )
            closeSettingsDropdown();
    });

    function syncSettingsDropdown() {
        const { name, subject, photo } = activeGroupData;
        document.getElementById("sdGroupName").textContent = name || "—";
        document.getElementById("sdGroupSub").textContent = subject || "—";
        document.getElementById("sdRenameInput").value = name || "";
        const sdAvatar = document.getElementById("sdAvatar");
        if (photo) {
            sdAvatar.innerHTML = `<img src="${escHtml(photo)}" alt="">`;
        } else {
            sdAvatar.textContent = (name || "??").substring(0, 2).toUpperCase();
            sdAvatar.style.background =
                "linear-gradient(135deg,#8b7355,#b08c67)";
        }
        return loadGroupMembers();
    }

    function loadGroupMembers() {
        if (!activeGroupId) return Promise.resolve([]);
        const list = document.getElementById("sdMembersList");
        const token = ++membersRequestToken;
        if (!list.dataset.loaded)
            list.innerHTML =
                '<div style="color:#8a8174;font-size:0.76rem;padding:4px 6px;">Loading…</div>';
        return fetch(`/study-groups/${activeGroupId}/members`)
            .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
            .then((data) => {
                if (token !== membersRequestToken) return [];
                const members = data.members || [];
                list.dataset.loaded = "1";
                if (!members.length) {
                    list.innerHTML =
                        '<div style="color:#8a8174;font-size:0.76rem;padding:6px;">No members found.</div>';
                    return members;
                }
                list.innerHTML = members
                    .slice(0, 5)
                    .map((m) => memberRowHtml(m, "sd"))
                    .join("");
                return members;
            })
            .catch(() => {
                list.innerHTML =
                    '<div style="color:#f87171;font-size:0.76rem;padding:6px;">Failed to load.</div>';
                return [];
            });
    }

    function memberRowHtml(m, type) {
        const isMe = ME && String(m.id) === String(ME);
        const initials = (m.first_name || m.name || "?")
            .charAt(0)
            .toUpperCase();
        const avatar = m.photo
            ? `<img src="${escHtml(m.photo)}" alt="">`
            : initials;
        const name =
            `${m.first_name || ""} ${m.last_name || ""}`.trim() ||
            m.name ||
            "Member";
        const tag = isMe
            ? `<span class="member-tag you">You</span>`
            : m.is_owner
              ? `<span class="member-tag owner">Owner</span>`
              : "";
        if (type === "sd")
            return `<div class="sd-member-row"><div class="sd-member-avatar">${avatar}</div><span class="sd-member-name">${escHtml(name)}</span>${tag}</div>`;
        return `<div class="member-full-row"><div class="member-full-avatar">${avatar}</div><div style="flex:1;min-width:0;"><div class="member-full-name">${escHtml(name)}</div>${m.username ? `<div class="member-full-username">@${escHtml(m.username)}</div>` : ""}</div>${tag}</div>`;
    }

    function openMembersModal() {
        closeSettingsDropdown();
        document.getElementById("membersModalTitle").textContent =
            `Members of "${activeGroupData.name}"`;
        document.getElementById("membersModalBackdrop").classList.add("open");
        const full = document.getElementById("membersFullList");
        full.innerHTML =
            '<div style="color:#8a8174;font-size:0.82rem;text-align:center;padding:16px;">Loading…</div>';
        fetch(`/study-groups/${activeGroupId}/members`)
            .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
            .then((data) => {
                const members = data.members || [];
                full.innerHTML = members.length
                    ? members.map((m) => memberRowHtml(m, "full")).join("")
                    : '<div style="color:#8a8174;font-size:0.82rem;text-align:center;padding:16px;">No members.</div>';
            })
            .catch(() => {
                full.innerHTML =
                    '<div style="color:#f87171;font-size:0.82rem;text-align:center;padding:16px;">Failed to load.</div>';
            });
    }

    function closeMembersModal() {
        document
            .getElementById("membersModalBackdrop")
            .classList.remove("open");
    }
    document
        .getElementById("membersModalBackdrop")
        .addEventListener("click", function (e) {
            if (e.target === this) closeMembersModal();
        });

    /* ══════════════════════════════════════════
       RENAME / PHOTO / DELETE GROUP
    ══════════════════════════════════════════ */
    function renameGroup() {
        if (!activeGroupId || !isGroupAdmin) return;
        const n = document.getElementById("sdRenameInput").value.trim();
        if (!n) {
            showToast("Please enter a group name.", "error");
            return;
        }
        _doRename(n);
    }
    function renameGroupFromAdmin() {
        if (!activeGroupId || !isGroupAdmin) return;
        const n = document.getElementById("adminRenameInput").value.trim();
        if (!n) {
            showToast("Please enter a group name.", "error");
            return;
        }
        _doRename(n);
    }

    function _doRename(newName) {
        fetch(`/study-groups/${activeGroupId}`, {
            method: "PATCH",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": CSRF,
            },
            body: JSON.stringify({ name: newName }),
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.success || data.group) {
                    activeGroupData.name = newName;
                    const item = document.querySelector(
                        `[data-group-id="${activeGroupId}"]`,
                    );
                    if (item) {
                        item.querySelector(".sg-group-name").textContent =
                            newName;
                        item.dataset.name = newName.toLowerCase();
                    }
                    const privacy =
                        activeGroupData.privacy === "private"
                            ? `<span class="header-privacy-badge private">🔒 Private</span>`
                            : `<span class="header-privacy-badge public">🌐 Public</span>`;
                    document.getElementById("chatGroupName").innerHTML =
                        escHtml(newName) + " " + privacy;
                    document.getElementById("sdGroupName").textContent =
                        newName;
                    document.getElementById("adminRenameInput").value = newName;
                    showToast("Group renamed!", "success");
                } else {
                    showToast(data.error || "Failed to rename.", "error");
                }
            })
            .catch(() => showToast("Failed to rename.", "error"));
    }

    document
        .getElementById("sdAvatarWrap")
        .addEventListener("click", function () {
            if (isGroupAdmin)
                document.getElementById("groupPhotoInput").click();
        });

    function handlePhotoChange(file) {
        if (!file || !activeGroupId || !isGroupAdmin) return;
        const form = new FormData();
        form.append("_token", CSRF);
        form.append("photo", file);
        fetch(`/study-groups/${activeGroupId}/photo`, {
            method: "POST",
            body: form,
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.photo_url) {
                    const url = data.photo_url;
                    activeGroupData.photo = url;
                    document.getElementById("sdAvatar").innerHTML =
                        `<img src="${escHtml(url)}" alt="">`;
                    document.getElementById("chatAvatar").innerHTML =
                        `<img src="${escHtml(url)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`;
                    const sa = document.querySelector(
                        `[data-group-id="${activeGroupId}"] .sg-group-avatar`,
                    );
                    if (sa) sa.innerHTML = `<img src="${escHtml(url)}" alt="">`;
                    showToast("Photo updated!", "success");
                } else
                    showToast(data.error || "Failed to update photo.", "error");
            })
            .catch(() => showToast("Failed to upload photo.", "error"));
    }

    document
        .getElementById("groupPhotoInput")
        .addEventListener("change", function () {
            if (this.files.length) handlePhotoChange(this.files[0]);
            this.value = "";
        });
    document
        .getElementById("adminPhotoInput")
        .addEventListener("change", function () {
            if (this.files.length) handlePhotoChange(this.files[0]);
            this.value = "";
        });

    function deleteGroup() {
        if (!activeGroupId || !isGroupAdmin) {
            showToast("Only the group admin can delete this group.", "error");
            return;
        }
        if (!confirm("Delete this group? This cannot be undone.")) return;
        fetch(`/study-groups/${activeGroupId}`, {
            method: "DELETE",
            headers: { "X-CSRF-TOKEN": CSRF },
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.success) {
                    document
                        .querySelector(`[data-group-id="${activeGroupId}"]`)
                        ?.remove();
                    activeGroupId = null;
                    clearInterval(pollInterval);
                    clearInterval(threadPollInterval);
                    closeSettingsDropdown();
                    document.getElementById("chatEmpty").style.display = "flex";
                    document.getElementById("chatHeader").style.display =
                        "none";
                    document.getElementById("sgTabs").style.display = "none";
                    [
                        "viewMessages",
                        "viewTasks",
                        "viewResources",
                        "viewNotes",
                        "viewCalendars",
                        "viewAdmin",
                    ].forEach((id) => {
                        const el = document.getElementById(id);
                        if (el) el.style.display = "none";
                    });
                    showToast("Group deleted.", "success");
                    if (!document.querySelectorAll(".sg-group-item").length) {
                        const d = document.createElement("div");
                        d.className = "no-msg";
                        d.style.marginTop = "24px";
                        d.innerHTML =
                            "No groups yet. Hit <strong>+</strong> to create one!";
                        document.getElementById("groupList").appendChild(d);
                    }
                } else showToast(data.error || "Failed to delete.", "error");
            })
            .catch(() => showToast("Failed to delete.", "error"));
    }

    /* ══════════════════════════════════════════
       ADMIN PANEL
    ══════════════════════════════════════════ */
    function loadAdminPanel() {
        if (!activeGroupId) return;
        const list = document.getElementById("adminMembersList");
        list.innerHTML = '<div class="no-msg">Loading members…</div>';
        fetch(`/study-groups/${activeGroupId}/members`)
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((data) => {
                const members = data.members || [];
                document.getElementById("adminMemberCount").textContent =
                    members.length +
                    " member" +
                    (members.length !== 1 ? "s" : "");
                if (!members.length) {
                    list.innerHTML =
                        '<div class="no-msg">No members found.</div>';
                    return;
                }
                list.innerHTML = members
                    .map((m) => adminMemberRowHtml(m))
                    .join("");
            })
            .catch(() => {
                list.innerHTML =
                    '<div class="no-msg" style="color:#f87171;">Failed to load members.</div>';
            });
    }

    function adminMemberRowHtml(m) {
        const isMe = ME && String(m.id) === String(ME);
        const initials = (m.first_name || m.name || "?")
            .charAt(0)
            .toUpperCase();
        const avatar = m.photo
            ? `<img src="${escHtml(m.photo)}" alt="">`
            : initials;
        const name =
            `${m.first_name || ""} ${m.last_name || ""}`.trim() ||
            m.name ||
            "Member";
        let roleBadge = m.is_owner
            ? `<span class="role-badge owner">Owner</span>`
            : m.role === "admin"
              ? `<span class="role-badge admin">Admin</span>`
              : `<span class="role-badge member">Member</span>`;
        if (isMe) roleBadge += ` <span class="role-badge you">You</span>`;
        let actions = "";
        if (!isMe && !m.is_owner && isGroupAdmin) {
            const mid = escHtml(m.id),
                mname = escHtml(name);
            if (m.role !== "admin") {
                actions += `<button class="btn-admin-member btn-promote" onclick="promoteMember('${mid}','${mname}')" title="Make admin">⬆ Admin</button>`;
            } else {
                actions += `<button class="btn-admin-member btn-demote" onclick="demoteMember('${mid}','${mname}')" title="Remove admin">⬇ Member</button>`;
            }
            actions += `<button class="btn-admin-member btn-kick" onclick="kickMember('${mid}','${mname}')" title="Remove from group">✕ Remove</button>`;
        }
        return `<div class="admin-member-row" id="admin-member-${escHtml(m.id)}"><div class="admin-member-avatar">${avatar}</div><div class="admin-member-info"><div class="admin-member-name">${escHtml(name)}</div>${m.username ? `<div class="admin-member-username">@${escHtml(m.username)}</div>` : ""}</div><div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;">${roleBadge}<div class="admin-member-actions">${actions}</div></div></div>`;
    }

    function promoteMember(memberId, name) {
        if (!confirm(`Make ${name} an admin?`)) return;
        fetch(`/study-groups/${activeGroupId}/members/${memberId}/role`, {
            method: "PATCH",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": CSRF,
            },
            body: JSON.stringify({ role: "admin" }),
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.ok || data.success) {
                    loadAdminPanel();
                    showToast(`${name} is now an admin!`, "success");
                } else showToast(data.error || "Failed.", "error");
            })
            .catch(() => showToast("Failed to update role.", "error"));
    }
    function demoteMember(memberId, name) {
        if (!confirm(`Remove admin rights from ${name}?`)) return;
        fetch(`/study-groups/${activeGroupId}/members/${memberId}/role`, {
            method: "PATCH",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": CSRF,
            },
            body: JSON.stringify({ role: "member" }),
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.ok || data.success) {
                    loadAdminPanel();
                    showToast(`${name} is now a member.`);
                } else showToast(data.error || "Failed.", "error");
            })
            .catch(() => showToast("Failed to update role.", "error"));
    }
    function kickMember(memberId, name) {
        if (!confirm(`Remove ${name} from this group?`)) return;
        fetch(`/study-groups/${activeGroupId}/members/${memberId}`, {
            method: "DELETE",
            headers: { "X-CSRF-TOKEN": CSRF },
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.ok || data.success) {
                    loadAdminPanel();
                    loadGroupMembers();
                    showToast(`${name} removed from group.`, "success");
                } else showToast(data.error || "Failed.", "error");
            })
            .catch(() => showToast("Failed to remove member.", "error"));
    }

    /* ══════════════════════════════════════════
       MESSAGES
    ══════════════════════════════════════════ */
    function loadMessages(scrollToBottom) {
        if (!activeGroupId || currentTab !== "messages") return;
        const requestToken = ++messagesRequestToken;
        fetch(`/study-groups/${activeGroupId}/messages`)
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((data) => {
                if (requestToken !== messagesRequestToken) return;
                if (!scrollToBottom && data.messages.length === lastMsgCount)
                    return;
                lastMsgCount = data.messages.length;
                renderMessages(data.messages, scrollToBottom);
            })
            .catch(() => {});
    }

    function messageDateLabel(message) {
        return new Date(message.created_at).toLocaleDateString("en-US", {
            month: "short",
            day: "numeric",
        });
    }

    function messageHtml(m) {
        const d = new Date(m.created_at);
        const isOwn = ME && String(m.user_id) === String(ME);
        const initials = (m.sender_first || "?").charAt(0).toUpperCase();
        const name = isOwn
            ? "You"
            : `${m.sender_first || ""} ${m.sender_last || ""}`.trim();
        const time = d.toLocaleTimeString("en-US", {
            hour: "2-digit",
            minute: "2-digit",
        });
        const avatar = m.sender_photo
            ? `<img src="${escHtml(m.sender_photo)}" alt="">`
            : initials;

        let content = "";
        let isCalShare = false,
            calData = null;
        try {
            if (m.message && m.message.startsWith("{")) {
                const p = JSON.parse(m.message);
                if (p.type === "calendar_share") {
                    isCalShare = true;
                    calData = p;
                }
            }
        } catch (e) {}

        if (isCalShare && calData) {
            content += `<div class="msg-calendar-share"><div style="display:flex;align-items:center;gap:7px;font-weight:600;margin-bottom:4px;"><span>📅</span> Shared their calendar</div>${calData.message ? `<div style="font-size:0.8rem;color:#8a8174;margin-left:22px;">"${escHtml(calData.message)}"</div>` : ""}</div>`;
        } else if (m.message && m.message.trim()) {
            content += `<div class="msg-bubble">${escHtml(m.message)}</div>`;
        }

        (m.attachments || []).forEach((a) => {
            if (a.type === "image") {
                content += `<img class="msg-image" src="${escHtml(a.url)}" alt="${escHtml(a.name)}" onclick="openLightbox('${escHtml(a.url)}')">`;
            } else {
                const ext = (a.name || "").split(".").pop().toUpperCase();
                const sz = a.size ? formatBytes(a.size) : "";
                content += `<a class="msg-file" href="${escHtml(a.url)}" target="_blank" download><span>${fileIcon(ext)}</span><span class="msg-file-name">${escHtml(a.name)}</span><span class="msg-file-size">${sz}</span></a>`;
            }
        });

        const msgIdSafe = escHtml(m.id);
        const canDelete = isGroupAdmin || isOwn;
        const menuItems = [
            `<button class="msg-menu-item" type="button" onclick="copyMessageText('${msgIdSafe}')">📋 Copy text</button>`,
            `<button class="msg-menu-item" type="button" onclick="openThread('${msgIdSafe}');closeAllMenus();">💬 Open thread</button>`,
            canDelete
                ? `<button class="msg-menu-item danger" type="button" onclick="deleteMessage('${msgIdSafe}')">🗑 Delete message</button>`
                : "",
        ]
            .filter(Boolean)
            .join("");

        const deleteBtn = canDelete
            ? `<button class="msg-action-btn delete" type="button" title="Delete" onclick="deleteMessage('${msgIdSafe}');closeAllMenus();">🗑</button><div class="msg-action-sep"></div>`
            : "";

        return `<div class="msg-row ${isOwn ? "own" : ""}" id="msg-${msgIdSafe}" data-msg-id="${msgIdSafe}" data-sender="${escHtml(name)}" data-text="${escHtml((m.message || "").substring(0, 120))}" data-message-id="${msgIdSafe}">
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
                    <button class="msg-action-btn" type="button" title="More actions" onclick="toggleMsgMenu(event,'${msgIdSafe}')">⋮</button>
                    <div class="msg-action-menu" id="msgMenu_${msgIdSafe}">${menuItems}</div>
                </div>
            </div>
        </div>`;
    }

    function toggleMsgMenu(e, msgId) {
        e.stopPropagation();
        closeAllMenus();
        const menu = document.getElementById("msgMenu_" + msgId);
        if (menu) menu.classList.toggle("open");
    }
    function closeAllMenus() {
        document
            .querySelectorAll(".msg-action-menu.open")
            .forEach((m) => m.classList.remove("open"));
    }
    document.addEventListener("click", closeAllMenus);

    function copyMessageText(msgId) {
        const row = document.querySelector(
            `.msg-row[data-msg-id="${CSS.escape(String(msgId))}"]`,
        );
        if (!row) return;
        const text = row.querySelector(".msg-bubble")?.innerText || "";
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard
                .writeText(text)
                .then(() => showToast("Copied!", "success"))
                .catch(() => showToast("Failed to copy.", "error"));
        } else {
            const ta = document.createElement("textarea");
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand("copy");
                showToast("Copied!", "success");
            } catch (err) {
                showToast("Failed to copy.", "error");
            }
            ta.remove();
        }
        closeAllMenus();
    }

    function appendMessageIfNotExists(message) {
        const box = document.getElementById("messagesBox");
        const messageId = String(message.id);
        if (box.querySelector(`[data-message-id="${CSS.escape(messageId)}"]`))
            return;
        const dateLabel = messageDateLabel(message);
        const lastDateSep = box.querySelectorAll(".date-sep");
        const lastDateText = lastDateSep.length
            ? lastDateSep[lastDateSep.length - 1].textContent.trim()
            : "";
        const lastRow = [
            ...box.querySelectorAll(".msg-row[data-message-id]"),
        ].pop();
        if (!lastRow || lastDateText !== dateLabel) {
            box.insertAdjacentHTML(
                "beforeend",
                `<div class="date-sep">${escHtml(dateLabel)}</div>`,
            );
        }
        box.insertAdjacentHTML("beforeend", messageHtml(message));
    }

    function renderMessages(messages, scroll) {
        const box = document.getElementById("messagesBox");
        if (!messages.length) {
            box.innerHTML =
                '<div class="no-msg" style="margin-top:20px;">No messages yet. Say hello! 👋</div>';
            return;
        }
        if (scroll || !box.querySelector(".msg-row[data-message-id]")) {
            let html = "",
                lastDate = "";
            messages.forEach((m) => {
                const date = messageDateLabel(m);
                if (date !== lastDate) {
                    html += `<div class="date-sep">${escHtml(date)}</div>`;
                    lastDate = date;
                }
                html += messageHtml(m);
            });
            box.innerHTML = html;
            box.scrollTop = box.scrollHeight;
            return;
        }
        messages.forEach((m) => appendMessageIfNotExists(m));
    }

    function openThreadById(btnEl) {
        const row = btnEl.closest("[data-msg-id]");
        if (!row) return;
        openThread(row.dataset.msgId, row.dataset.sender, row.dataset.text);
    }

    function deleteMessage(btnEl) {
        if (!confirm("Delete this message?")) return;
        const msgId =
            typeof btnEl === "string"
                ? btnEl
                : btnEl?.dataset?.msgId ||
                  btnEl?.closest?.("[data-msg-id]")?.dataset?.msgId ||
                  "";
        if (!msgId) return;
        fetch(`/study-groups/${activeGroupId}/messages/${msgId}`, {
            method: "DELETE",
            headers: { "X-CSRF-TOKEN": CSRF },
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.ok || data.success) {
                    document.getElementById("msg-" + msgId)?.remove();
                    lastMsgCount = Math.max(0, lastMsgCount - 1);
                    showToast("Message deleted.");
                    closeAllMenus();
                } else showToast(data.error || "Failed to delete.", "error");
            })
            .catch(() => showToast("Failed to delete.", "error"));
    }

    /* ══════════════════════════════════════════
       SEND MESSAGE
    ══════════════════════════════════════════ */
    function sendMessage() {
        if (!activeGroupId) return;
        const text = document.getElementById("msgInput").value.trim();
        if (!text && pendingFiles.length === 0) return;
        const btn = document.getElementById("sendBtn");
        btn.disabled = true;
        const formData = new FormData();
        formData.append("_token", CSRF);
        formData.append("message", text);
        pendingFiles.forEach((pf) => formData.append("attachments[]", pf.file));
        fetch(`/study-groups/${activeGroupId}/messages`, {
            method: "POST",
            body: formData,
        })
            .then((r) =>
                r.ok
                    ? r.json()
                    : r.json().then((d) => Promise.reject(d.error || "Error")),
            )
            .then((data) => {
                const ta = document.getElementById("msgInput");
                ta.value = "";
                ta.style.height = "auto";
                pendingFiles = [];
                document.getElementById("uploadPreview").innerHTML = "";
                if (data && data.message) {
                    appendMessageIfNotExists(data.message);
                    lastMsgCount = document.querySelectorAll(
                        ".msg-row[data-message-id]",
                    ).length;
                    document.getElementById("messagesBox").scrollTop =
                        document.getElementById("messagesBox").scrollHeight;
                } else loadMessages(true);
            })
            .catch((err) => showToast(String(err), "error"))
            .finally(() => {
                btn.disabled = false;
            });
    }

    function handleEnter(e) {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    }

    document
        .getElementById("imageInput")
        .addEventListener("change", function () {
            addFiles(this.files, "image");
            this.value = "";
        });
    document
        .getElementById("fileInput")
        .addEventListener("change", function () {
            addFiles(this.files, "file");
            this.value = "";
        });

    function addFiles(fileList, type) {
        Array.from(fileList).forEach((f) => {
            const id = Math.random().toString(36).slice(2);
            const pf = { file: f, type, id };
            if (type === "image") pf.previewUrl = URL.createObjectURL(f);
            pendingFiles.push(pf);
            const div = document.createElement("div");
            div.className = "sg-preview-item";
            div.id = "prev_" + id;
            if (type === "image") {
                div.innerHTML = `<img src="${pf.previewUrl}" alt=""><span class="sg-preview-name">${escHtml(f.name)}</span><button class="sg-preview-remove" onclick="removeFile('${id}')">✕</button>`;
            } else {
                div.innerHTML = `<span>${fileIcon(f.name.split(".").pop().toUpperCase())}</span><span class="sg-preview-name">${escHtml(f.name)}</span><button class="sg-preview-remove" onclick="removeFile('${id}')">✕</button>`;
            }
            document.getElementById("uploadPreview").appendChild(div);
        });
    }

    function removeFile(id) {
        pendingFiles = pendingFiles.filter((f) => f.id !== id);
        document.getElementById("prev_" + id)?.remove();
    }

    /* ══════════════════════════════════════════
       THREADED MESSAGES
    ══════════════════════════════════════════ */
    function openThread(msgId, senderName, msgText) {
        // Sanitize empty/undefined values
        const safe = (v, fb) =>
            !v || String(v).trim() === "" || v === "undefined" || v === "null"
                ? fb
                : v;
        const sender = safe(senderName, "Member");
        const text = safe(msgText, "(image or attachment)");

        activeThreadMsgId = msgId;
        lastThreadCount = 0;
        document.getElementById("threadParentSender").textContent = sender;
        document.getElementById("threadParentText").textContent = text;
        document.getElementById("threadPanel").classList.add("open");
        loadThreadReplies(true);
        clearInterval(threadPollInterval);
        threadPollInterval = setInterval(() => loadThreadReplies(false), 3000);
    }

    function closeThread() {
        document.getElementById("threadPanel").classList.remove("open");
        clearInterval(threadPollInterval);
        activeThreadMsgId = null;
    }

    function loadThreadReplies(scroll) {
        if (!activeThreadMsgId || !activeGroupId) return;
        fetch(
            `/study-groups/${activeGroupId}/messages/${activeThreadMsgId}/replies`,
        )
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((data) => {
                const replies = data.replies || [];
                if (!scroll && replies.length === lastThreadCount) return;
                lastThreadCount = replies.length;
                renderThreadReplies(replies, scroll);
            })
            .catch(() => {});
    }

    function renderThreadReplies(replies, scroll) {
        const box = document.getElementById("threadReplies");
        if (!replies.length) {
            box.innerHTML =
                '<div class="no-msg">No replies yet. Start the thread!</div>';
            return;
        }
        let html = "";
        replies.forEach((r) => {
            const isOwn = ME && String(r.user_id) === String(ME);
            const name = isOwn
                ? "You"
                : `${r.sender_first || ""} ${r.sender_last || ""}`.trim() ||
                  "Member";
            const time = new Date(r.created_at).toLocaleTimeString("en-US", {
                hour: "2-digit",
                minute: "2-digit",
            });
            const initials = (r.sender_first || name || "?")
                .charAt(0)
                .toUpperCase();
            const avatar = r.sender_photo
                ? `<img src="${escHtml(r.sender_photo)}" alt="">`
                : initials;
            const canDel = isOwn || isGroupAdmin;
            const delBtn = canDel
                ? `<button class="btn-delete-reply" data-reply-id="${escHtml(r.id)}" onclick="deleteReply(this)" title="Delete reply">🗑</button>`
                : "";
            html += `<div class="thread-reply-row" id="reply-${escHtml(r.id)}"><div class="thread-reply-avatar">${avatar}</div><div class="thread-reply-body"><div class="thread-reply-meta"><span>${escHtml(name)}</span><span>${time}</span>${delBtn}</div><div class="thread-reply-text">${escHtml(r.message || "")}</div></div></div>`;
        });
        box.innerHTML = html;
        if (scroll) box.scrollTop = box.scrollHeight;
    }

    function deleteReply(btnEl) {
        if (!confirm("Delete this reply?")) return;
        const replyId = btnEl.dataset.replyId;
        fetch(
            `/study-groups/${activeGroupId}/messages/${activeThreadMsgId}/replies/${replyId}`,
            { method: "DELETE", headers: { "X-CSRF-TOKEN": CSRF } },
        )
            .then((r) => r.json())
            .then((data) => {
                if (data.ok || data.success) {
                    document.getElementById("reply-" + replyId)?.remove();
                    lastThreadCount = Math.max(0, lastThreadCount - 1);
                    loadMessages(false);
                } else showToast(data.error || "Failed.", "error");
            })
            .catch(() => showToast("Failed to delete reply.", "error"));
    }

    function sendThreadReply() {
        if (!activeThreadMsgId || !activeGroupId) return;
        const text = document.getElementById("threadInput").value.trim();
        if (!text) return;
        fetch(
            `/study-groups/${activeGroupId}/messages/${activeThreadMsgId}/replies`,
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": CSRF,
                },
                body: JSON.stringify({ message: text }),
            },
        )
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then(() => {
                const ta = document.getElementById("threadInput");
                ta.value = "";
                ta.style.height = "auto";
                loadThreadReplies(true);
                loadMessages(false);
            })
            .catch(() => showToast("Failed to send reply.", "error"));
    }

    function handleThreadEnter(e) {
        if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();
            sendThreadReply();
        }
    }

    /* ══════════════════════════════════════════
       TASKS
    ══════════════════════════════════════════ */
    function loadTasks() {
        if (!activeGroupId) return;
        fetch(`/study-groups/${activeGroupId}/tasks`)
            .then((r) => (r.ok ? r.json() : Promise.resolve({ tasks: [] })))
            .then((data) => renderTasks(data.tasks || []))
            .catch(() => renderTasks([]));
    }

    function renderTasks(tasks) {
        const container = document.getElementById("tasksList");
        if (!tasks.length) {
            container.innerHTML =
                '<div class="no-msg" style="padding:40px 0;">No tasks yet. Create one to get started! ✅</div>';
            return;
        }
        const pending = tasks.filter((t) => !t.completed),
            completed = tasks.filter((t) => t.completed);
        let html = "";
        if (pending.length) {
            html += `<div class="tasks-section-label">📋 To Do (${pending.length})</div>`;
            html += pending.map((t) => taskItemHtml(t)).join("");
        }
        if (completed.length) {
            html += `<div class="tasks-section-label" style="margin-top:16px;">✅ Completed (${completed.length})</div>`;
            html += completed.map((t) => taskItemHtml(t)).join("");
        }
        container.innerHTML = html;
    }

    function taskItemHtml(t) {
        const today = new Date().toISOString().split("T")[0];
        const overdue = t.due_date && t.due_date < today && !t.completed;
        const dueLabel = t.due_date
            ? `<span class="task-due ${overdue ? "overdue" : ""}">📅 ${t.due_date}</span>`
            : "";
        const assignee = t.assigned_to
            ? `<span class="task-assignee">👤 ${escHtml(t.assigned_to)}</span>`
            : "";
        const priority = t.priority || "medium";
        const tid = escHtml(String(t.id));
        const canDel =
            isGroupAdmin || (ME && String(t.created_by) === String(ME));
        const delBtn = canDel
            ? `<button class="btn-task-action danger" data-task-id="${tid}" onclick="deleteTask(this)" title="Delete">🗑️</button>`
            : "";
        return `<div class="task-item ${t.completed ? "done" : ""}" id="task-${tid}"><div class="task-checkbox ${t.completed ? "checked" : ""}" data-task-id="${tid}" data-completed="${!t.completed}" onclick="toggleTask(this)"></div><div class="task-content"><div class="task-title">${escHtml(t.title)}</div><div class="task-meta"><span class="task-badge priority-${priority}">${priority.charAt(0).toUpperCase() + priority.slice(1)}</span>${dueLabel}${assignee}</div></div><div class="task-item-actions">${delBtn}</div></div>`;
    }

    function toggleTaskForm() {
        const form = document.getElementById("taskAddForm");
        form.classList.toggle("open");
        if (form.classList.contains("open"))
            document.getElementById("taskTitleInput").focus();
    }

    function saveTask() {
        if (!activeGroupId) return;
        const title = document.getElementById("taskTitleInput").value.trim();
        const priority = document.getElementById("taskPriority").value;
        const dueDate = document.getElementById("taskDueDate").value;
        const assignee = document.getElementById("taskAssignee").value.trim();
        if (!title) {
            showToast("Please enter a task title.", "error");
            return;
        }
        fetch(`/study-groups/${activeGroupId}/tasks`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": CSRF,
            },
            body: JSON.stringify({
                title,
                priority,
                due_date: dueDate || null,
                assigned_to: assignee || null,
            }),
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then(() => {
                document.getElementById("taskTitleInput").value = "";
                document.getElementById("taskDueDate").value = "";
                document.getElementById("taskAssignee").value = "";
                document.getElementById("taskPriority").value = "medium";
                toggleTaskForm();
                loadTasks();
                showToast("Task created!", "success");
            })
            .catch(() => showToast("Failed to create task.", "error"));
    }

    function toggleTask(el) {
        const taskId = el.dataset.taskId,
            completed = el.dataset.completed === "true";
        fetch(`/study-groups/${activeGroupId}/tasks/${taskId}`, {
            method: "PATCH",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": CSRF,
            },
            body: JSON.stringify({ completed }),
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then(() => loadTasks())
            .catch(() => showToast("Failed to update task.", "error"));
    }
    function deleteTask(btnEl) {
        if (!confirm("Delete this task?")) return;
        const taskId = btnEl.dataset.taskId;
        fetch(`/study-groups/${activeGroupId}/tasks/${taskId}`, {
            method: "DELETE",
            headers: { "X-CSRF-TOKEN": CSRF },
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then(() => {
                loadTasks();
                showToast("Task deleted.");
            })
            .catch(() => showToast("Failed to delete task.", "error"));
    }

    /* ══════════════════════════════════════════
       RESOURCES
    ══════════════════════════════════════════ */
    function loadResources() {
        if (!activeGroupId) return;
        fetch(`/study-groups/${activeGroupId}/resources`)
            .then((r) => (r.ok ? r.json() : Promise.resolve({ resources: [] })))
            .then((data) => {
                allResources = data.resources || [];
                renderResources();
            })
            .catch(() => {
                allResources = [];
                renderResources();
            });
    }

    function renderResources() {
        const pinned = allResources.filter((r) => r.pinned);
        const all = allResources.filter((r) => {
            if (resourceFilter === "all") return true;
            const fname = (r.file_name || "").toLowerCase();
            if (resourceFilter === "image")
                return (
                    r.type === "image" ||
                    /\.(png|jpg|jpeg|gif|webp|bmp|svg)$/.test(fname)
                );
            if (resourceFilter === "pdf") return fname.endsWith(".pdf");
            if (resourceFilter === "doc")
                return /\.(doc|docx|txt|odt|rtf|md)$/.test(fname);
            if (resourceFilter === "other")
                return (
                    r.type !== "image" &&
                    !fname.endsWith(".pdf") &&
                    !/\.(doc|docx|txt|odt|rtf|md|png|jpg|jpeg|gif|webp|bmp|svg)$/.test(
                        fname,
                    )
                );
            return true;
        });
        const pinnedSection = document.getElementById("pinnedResourcesSection");
        const pinnedGrid = document.getElementById("pinnedResourcesGrid");
        const allGrid = document.getElementById("resourcesGrid");
        if (pinned.length) {
            pinnedSection.style.display = "block";
            pinnedGrid.innerHTML = pinned
                .map((r) => resourceCardHtml(r))
                .join("");
        } else {
            pinnedSection.style.display = "none";
        }
        allGrid.innerHTML = all.length
            ? all.map((r) => resourceCardHtml(r)).join("")
            : '<div class="no-msg" style="grid-column:1/-1;padding:40px 0;">No files yet. Upload the first one! 📁</div>';
    }

    function resourceCardHtml(r) {
        const fname = r.file_name || "File";
        const ext = fname.split(".").pop().toUpperCase();
        const isImage =
            r.type === "image" || /\.(png|jpg|jpeg|gif|webp)$/i.test(fname);
        const size = r.file_size ? formatBytes(r.file_size) : "";
        const up = r.uploader_name ? `by ${escHtml(r.uploader_name)}` : "";
        const rid = escHtml(String(r.id));
        const pinLabel = r.pinned ? "📌 Unpin" : "📌 Pin";

        let visual = "";
        if (isImage) {
            visual = `<div class="resource-thumbnail" onclick="openLightbox('${escHtml(r.file_url)}')" title="Click to preview"><img src="${escHtml(r.file_url)}" alt="${escHtml(fname)}" loading="lazy"><div class="resource-thumb-overlay">🔍</div></div>`;
        } else {
            visual = `<div class="resource-icon">${fileIcon(ext)}</div>`;
        }

        const previewBtn = isImage
            ? `<button class="btn-resource-action" onclick="openLightbox('${escHtml(r.file_url)}')" title="Preview">🔍 Preview</button>`
            : "";

        return `<div class="resource-card ${r.pinned ? "pinned" : ""}" id="resource-${rid}">
            ${r.pinned ? '<span class="resource-pin-icon">📌</span>' : ""}
            ${visual}
            <div class="resource-name" title="${escHtml(fname)}">${escHtml(fname)}</div>
            <div class="resource-meta">${size}</div>
            <div class="resource-uploader">${up}</div>
            <div class="resource-card-actions">
                ${previewBtn}
                <a class="btn-resource-action" href="${escHtml(r.file_url)}" target="_blank" download="${escHtml(fname)}">⬇ Download</a>
                <button class="btn-resource-action" data-resource-id="${rid}" data-pin="${!r.pinned}" onclick="togglePin(this)">${pinLabel}</button>
                <button class="btn-resource-action danger" data-resource-id="${rid}" onclick="deleteResource(this)">🗑 Delete</button>
            </div>
        </div>`;
    }

    function filterResources(type, btn) {
        resourceFilter = type;
        document
            .querySelectorAll(".filter-pill")
            .forEach((p) => p.classList.remove("active"));
        btn.classList.add("active");
        renderResources();
    }

    function uploadResources(files) {
        if (!files.length || !activeGroupId) return;
        const formData = new FormData();
        formData.append("_token", CSRF);
        Array.from(files).forEach((f) => formData.append("files[]", f));
        showToast("Uploading files…");
        fetch(`/study-groups/${activeGroupId}/resources`, {
            method: "POST",
            body: formData,
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then(() => {
                loadResources();
                showToast("Files uploaded!", "success");
            })
            .catch(() => showToast("Upload failed.", "error"));
        document.getElementById("resourceFileInput").value = "";
    }

    function togglePin(btnEl) {
        const resourceId = btnEl.dataset.resourceId,
            pin = btnEl.dataset.pin === "true";
        fetch(`/study-groups/${activeGroupId}/resources/${resourceId}/pin`, {
            method: "PATCH",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": CSRF,
            },
            body: JSON.stringify({ pinned: pin }),
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then(() => {
                loadResources();
                showToast(pin ? "Pinned!" : "Unpinned.", "success");
            })
            .catch(() => showToast("Failed to update pin.", "error"));
    }
    function deleteResource(btnEl) {
        if (!confirm("Delete this file?")) return;
        const resourceId = btnEl.dataset.resourceId;
        fetch(`/study-groups/${activeGroupId}/resources/${resourceId}`, {
            method: "DELETE",
            headers: { "X-CSRF-TOKEN": CSRF },
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then(() => {
                loadResources();
                showToast("File deleted.");
            })
            .catch(() => showToast("Failed to delete.", "error"));
    }

    /* ══════════════════════════════════════════
       NOTES
    ══════════════════════════════════════════ */
    function loadNotes() {
        if (!activeGroupId) return;
        fetch(`/study-groups/${activeGroupId}/notes`)
            .then((r) => (r.ok ? r.json() : Promise.resolve({ notes: [] })))
            .then((data) => {
                notesList = data.notes || [];
                renderNotesList();
                if (notesList.length) {
                    openNote(notesList[0].id);
                } else {
                    document.getElementById("noteTitleInput").value = "";
                    document.getElementById("noteEditor").innerHTML = "";
                    currentNoteId = null;
                }
            })
            .catch(() => {});
    }

    function renderNotesList() {
        const container = document.getElementById("notesList");
        if (!notesList.length) {
            container.innerHTML = '<div class="no-msg">No notes yet.</div>';
            return;
        }
        container.innerHTML = notesList
            .map((n) => {
                const nid = escHtml(String(n.id));
                return `<div class="note-list-item ${n.id === currentNoteId ? "active" : ""}" onclick="openNote('${nid}')"><div class="note-list-title">${escHtml(n.title || "Untitled")}</div><div class="note-list-meta">${new Date(n.updated_at || n.created_at).toLocaleDateString()}</div><button class="btn-delete-note" data-note-id="${nid}" onclick="event.stopPropagation();deleteNote(this)" title="Delete note">🗑</button></div>`;
            })
            .join("");
    }

    function openNote(noteId) {
        currentNoteId = noteId;
        const note = notesList.find((n) => String(n.id) === String(noteId));
        if (!note) return;
        document.getElementById("noteTitleInput").value = note.title || "";
        document.getElementById("noteEditor").innerHTML = note.content || "";
        renderNotesList();
    }

    function createNewNote() {
        if (!activeGroupId) return;
        fetch(`/study-groups/${activeGroupId}/notes`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": CSRF,
            },
            body: JSON.stringify({ title: "Untitled Note", content: "" }),
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then(() => loadNotes())
            .catch(() => showToast("Failed to create note.", "error"));
    }

    function deleteNote(btnEl) {
        if (!confirm("Delete this note?")) return;
        const noteId = btnEl.dataset.noteId;
        fetch(`/study-groups/${activeGroupId}/notes/${noteId}`, {
            method: "DELETE",
            headers: { "X-CSRF-TOKEN": CSRF },
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then(() => {
                if (String(currentNoteId) === String(noteId)) {
                    currentNoteId = null;
                    document.getElementById("noteTitleInput").value = "";
                    document.getElementById("noteEditor").innerHTML = "";
                }
                loadNotes();
                showToast("Note deleted.");
            })
            .catch(() => showToast("Failed to delete note.", "error"));
    }

    function scheduleNoteSave() {
        clearTimeout(noteSaveTimeout);
        document.getElementById("notesSaveStatus").textContent = "Saving…";
        document.getElementById("notesSaveStatus").className =
            "notes-save-status saving";
        noteSaveTimeout = setTimeout(saveCurrentNote, 1500);
    }

    function saveCurrentNote() {
        if (!activeGroupId || !currentNoteId) return;
        const title = document.getElementById("noteTitleInput").value;
        const content = document.getElementById("noteEditor").innerHTML;
        fetch(`/study-groups/${activeGroupId}/notes/${currentNoteId}`, {
            method: "PATCH",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": CSRF,
            },
            body: JSON.stringify({ title, content }),
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then(() => {
                document.getElementById("notesSaveStatus").textContent =
                    "✓ Saved";
                document.getElementById("notesSaveStatus").className =
                    "notes-save-status saved";
                const note = notesList.find(
                    (n) => String(n.id) === String(currentNoteId),
                );
                if (note) {
                    note.title = title;
                    note.updated_at = new Date().toISOString();
                    renderNotesList();
                }
                setTimeout(() => {
                    document.getElementById("notesSaveStatus").textContent =
                        "Auto-save on";
                    document.getElementById("notesSaveStatus").className =
                        "notes-save-status";
                }, 2000);
            })
            .catch(() => {
                document.getElementById("notesSaveStatus").textContent =
                    "Save failed";
                document.getElementById("notesSaveStatus").className =
                    "notes-save-status";
            });
    }

    function execFormat(cmd, val) {
        document.getElementById("noteEditor").focus();
        document.execCommand(cmd, false, val || null);
    }

    /* ══════════════════════════════════════════
       SHARED CALENDARS — full render with share button
    ══════════════════════════════════════════ */
    function loadGroupSharedCalendars() {
        if (!activeGroupId) return;

        // Patch container header to include Share button
        const container = document.getElementById("calendarsContainer");
        if (container) {
            let headerRow = container.querySelector(".calendars-header-row");
            if (!headerRow) {
                // Replace existing header div with our styled one
                const firstDiv = container.querySelector("div");
                if (firstDiv) {
                    const newHeader = document.createElement("div");
                    newHeader.className = "calendars-header-row";
                    newHeader.innerHTML = `<h3>📅 Shared Calendars</h3><button class="btn-share-calendar" onclick="shareMyCalendar()">📅 Share my calendar</button>`;
                    firstDiv.replaceWith(newHeader);
                }
            }
        }

        const box = document.getElementById("calendarsBox");
        if (!box) return;
        box.innerHTML = `<div class="cal-empty-state"><div class="cal-empty-icon">📅</div><p>Loading shared calendars…</p></div>`;

        fetch(`/study-groups/${activeGroupId}/shared-calendars`)
            .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
            .then((data) => renderCalendars(data.calendars || []))
            .catch(() => {
                box.innerHTML = `<div class="cal-empty-state"><div class="cal-empty-icon">⚠️</div><p>Failed to load calendars</p><small>Try switching tabs and coming back.</small></div>`;
            });
    }

    function renderCalendars(calendars) {
        const box = document.getElementById("calendarsBox");
        if (!box) return;

        if (!calendars || !calendars.length) {
            box.innerHTML = `<div class="cal-empty-state"><div class="cal-empty-icon">📅</div><p>No shared calendars yet</p><small>Share your calendar with the group so members can see your schedule.</small><button class="btn-share-calendar" style="margin-top:10px;" onclick="shareMyCalendar()">📅 Share my calendar</button></div>`;
            return;
        }

        let html = '<div class="cal-grid">';
        calendars.forEach((cal) => {
            const name = escHtml(cal.owner_name || "Member");
            const events = cal.events || [];
            const evCount = events.length;
            const initials = (cal.owner_name || "?").charAt(0).toUpperCase();
            const avatarHtml = cal.owner_photo
                ? `<img src="${escHtml(cal.owner_photo)}" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">`
                : `<div class="cal-member-avatar">${initials}</div>`;

            const visible = events.slice(0, 5);
            const hidden = events.slice(5);

            let eventsHtml = "";
            if (!evCount) {
                eventsHtml =
                    '<div class="cal-no-events">No upcoming events</div>';
            } else {
                visible.forEach((e) => {
                    const cat = (e.category || "other").toLowerCase();
                    const rawDate = e.event_date
                        ? new Date(
                              e.event_date + "T00:00:00",
                          ).toLocaleDateString("en-US", {
                              month: "short",
                              day: "numeric",
                              year: "numeric",
                          })
                        : "";
                    const time = e.event_time
                        ? e.event_time.substring(0, 5)
                        : "";
                    const label = rawDate + (time ? " · " + time : "");
                    const catLabel =
                        cat !== "other"
                            ? `<span class="cal-event-category">${escHtml(e.category)}</span>`
                            : "";
                    eventsHtml += `<div class="cal-event-row"><div class="cal-event-dot category-${escHtml(cat)}"></div><div class="cal-event-info"><div class="cal-event-title">${escHtml(e.title || "Event")}</div><div class="cal-event-time">${escHtml(label)}</div></div>${catLabel}</div>`;
                });
                if (hidden.length) {
                    eventsHtml += `<button class="cal-view-more" onclick="toggleCalHidden(this)">+ ${hidden.length} more event${hidden.length > 1 ? "s" : ""}</button><div class="cal-hidden-events" style="display:none;flex-direction:column;gap:7px;margin-top:4px;">`;
                    hidden.forEach((e) => {
                        const cat = (e.category || "other").toLowerCase();
                        const rawDate = e.event_date
                            ? new Date(
                                  e.event_date + "T00:00:00",
                              ).toLocaleDateString("en-US", {
                                  month: "short",
                                  day: "numeric",
                                  year: "numeric",
                              })
                            : "";
                        const time = e.event_time
                            ? e.event_time.substring(0, 5)
                            : "";
                        const label = rawDate + (time ? " · " + time : "");
                        const catLabel =
                            cat !== "other"
                                ? `<span class="cal-event-category">${escHtml(e.category)}</span>`
                                : "";
                        eventsHtml += `<div class="cal-event-row"><div class="cal-event-dot category-${escHtml(cat)}"></div><div class="cal-event-info"><div class="cal-event-title">${escHtml(e.title || "Event")}</div><div class="cal-event-time">${escHtml(label)}</div></div>${catLabel}</div>`;
                    });
                    eventsHtml += `</div>`;
                }
            }

            html += `<div class="cal-member-card"><div class="cal-member-header">${avatarHtml}<div class="cal-member-info"><div class="cal-member-name">${name}'s Calendar</div><div class="cal-member-count">${evCount} event${evCount !== 1 ? "s" : ""}</div></div><span class="cal-shared-badge">✓ Shared</span></div><div class="cal-events-list">${eventsHtml}</div></div>`;
        });
        html += "</div>";
        box.innerHTML = html;
    }

    function toggleCalHidden(btn) {
        const hidden = btn.nextElementSibling;
        if (!hidden) return;
        const isOpen =
            hidden.style.display !== "none" && hidden.style.display !== "";
        hidden.style.display = isOpen ? "none" : "flex";
        const count = hidden.querySelectorAll(".cal-event-row").length;
        btn.textContent = isOpen
            ? `+ ${count} more event${count > 1 ? "s" : ""}`
            : "− Show less";
    }

    function shareMyCalendar() {
        if (!activeGroupId) {
            showToast("No group selected.", "error");
            return;
        }
        const btn = document.querySelector(".btn-share-calendar");
        if (btn) {
            btn.disabled = true;
            btn.textContent = "Sharing…";
        }
        fetch(`/calendar/sharing/group/${activeGroupId}`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": CSRF,
            },
            body: JSON.stringify({ group_id: activeGroupId }),
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.ok || data.success) {
                    showToast(
                        "Your calendar is now shared with the group! 📅",
                        "success",
                    );
                    loadGroupSharedCalendars();
                } else {
                    showToast(
                        data.error || "Could not share calendar.",
                        "error",
                    );
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = "📅 Share my calendar";
                    }
                }
            })
            .catch(() => {
                showToast("Failed to share calendar.", "error");
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = "📅 Share my calendar";
                }
            });
    }

    /* ══════════════════════════════════════════
       CREATE GROUP MODAL
    ══════════════════════════════════════════ */
    document.getElementById("btnOpenModal").onclick = () => {
        document.getElementById("groupNameInput").value = "";
        document.getElementById("groupSubjectInput").value = "";
        document
            .querySelectorAll('#friendList input[type="checkbox"]')
            .forEach((c) => (c.checked = false));
        document.getElementById("radioPublic").checked = true;
        document.getElementById("optPublic").classList.add("selected-public");
        document
            .getElementById("optPublic")
            .classList.remove("selected-private");
        document
            .getElementById("optPrivate")
            .classList.remove("selected-public", "selected-private");
        document.getElementById("friendsField").style.display = "none";
        document.getElementById("publicDesc").style.display = "block";
        document.getElementById("privateDesc").style.display = "none";
        loadFriendsForModal();
        document.getElementById("modalBackdrop").classList.add("open");
    };

    document.querySelectorAll('input[name="groupPrivacy"]').forEach((radio) => {
        radio.addEventListener("change", function () {
            document
                .getElementById("optPublic")
                .classList.remove("selected-public", "selected-private");
            document
                .getElementById("optPrivate")
                .classList.remove("selected-public", "selected-private");
            if (this.value === "1") {
                document
                    .getElementById("optPrivate")
                    .classList.add("selected-private");
                document.getElementById("friendsField").style.display = "block";
                document.getElementById("publicDesc").style.display = "none";
                document.getElementById("privateDesc").style.display = "block";
            } else {
                document
                    .getElementById("optPublic")
                    .classList.add("selected-public");
                document.getElementById("friendsField").style.display = "none";
                document.getElementById("publicDesc").style.display = "block";
                document.getElementById("privateDesc").style.display = "none";
            }
        });
    });

    function loadFriendsForModal() {
        const fl = document.getElementById("friendList");
        fl.innerHTML =
            '<div style="padding:8px;color:#9ca3af;">Loading friends…</div>';
        fetch("/study-groups/api/friends")
            .then((r) => r.json())
            .then((data) => {
                if (!data.friends || !data.friends.length) {
                    fl.innerHTML =
                        '<div class="no-msg">No friends to add yet.</div>';
                    return;
                }
                fl.innerHTML = data.friends
                    .map(
                        (f) =>
                            `<label class="friend-item" for="friend_${escHtml(String(f.id))}"><input type="checkbox" id="friend_${escHtml(String(f.id))}" value="${escHtml(String(f.id))}"><div class="friend-avatar">${f.photo ? `<img src="${escHtml(f.photo)}" alt="">` : escHtml(f.initials || "?")}</div><div><div class="friend-name">${escHtml(f.name)}</div><div class="friend-username">@${escHtml(f.username || "friend")}</div></div></label>`,
                    )
                    .join("");
            })
            .catch(() => {
                fl.innerHTML =
                    '<div class="no-msg">Failed to load friends.</div>';
            });
    }

    function closeModal() {
        document.getElementById("modalBackdrop").classList.remove("open");
    }
    document
        .getElementById("modalBackdrop")
        .addEventListener("click", function (e) {
            if (e.target === this) closeModal();
        });

    function createGroup() {
        if (createGroupPending) return;
        const name = document.getElementById("groupNameInput").value.trim();
        const subject = document
            .getElementById("groupSubjectInput")
            .value.trim();
        const isPrivate = document.querySelector(
            'input[name="groupPrivacy"]:checked',
        ).value;
        if (!name) {
            showToast("Please enter a group name.", "error");
            return;
        }
        const members =
            isPrivate === "1"
                ? Array.from(
                      document.querySelectorAll(
                          '#friendList input[type="checkbox"]:checked',
                      ),
                  ).map((c) => c.value)
                : [];
        createGroupPending = true;
        const createBtn = document.querySelector("#modalBackdrop .btn-create");
        const restore = () => {
            createGroupPending = false;
            if (createBtn) createBtn.disabled = false;
        };
        if (createBtn) createBtn.disabled = true;
        fetch("/study-groups", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": CSRF,
            },
            body: JSON.stringify({
                name,
                subject,
                members,
                is_private: isPrivate,
            }),
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.group) {
                    closeModal();
                    document.querySelector("#groupList .no-msg")?.remove();
                    const privacy = isPrivate === "1" ? "private" : "public";
                    const badgeHtml =
                        privacy === "private"
                            ? `<span class="privacy-badge private">🔒</span>`
                            : `<span class="privacy-badge public">🌐</span>`;
                    const div = document.createElement("div");
                    div.className = "sg-group-item";
                    div.dataset.groupId = data.group.id;
                    div.dataset.privacy = privacy;
                    div.dataset.name = name.toLowerCase();
                    div.setAttribute(
                        "onclick",
                        `openGroup('${data.group.id}',this)`,
                    );
                    div.innerHTML = `<div class="sg-group-avatar">${name.substring(0, 2).toUpperCase()}</div><div class="sg-group-item-wrap"><div class="sg-group-name">${escHtml(name)}</div><div class="sg-group-meta"><span class="sg-group-subject">${escHtml(data.group.subject || "General")} · 1 member</span>${badgeHtml}</div></div>`;
                    document.getElementById("groupList").prepend(div);
                    openGroup(data.group.id, div);
                    showToast("Group created!", "success");
                } else {
                    showToast(data.error || "Failed to create group.", "error");
                }
            })
            .catch(() => showToast("Failed to create group.", "error"))
            .finally(restore);
    }

    /* ══════════════════════════════════════════
       LIGHTBOX
    ══════════════════════════════════════════ */
    function openLightbox(url) {
        document.getElementById("lightboxImg").src = url;
        document.getElementById("lightbox").classList.add("open");
    }
    function closeLightbox() {
        document.getElementById("lightbox").classList.remove("open");
    }

    /* ══════════════════════════════════════════
       AUTO-OPEN FIRST GROUP
    ══════════════════════════════════════════ */
    window.addEventListener("DOMContentLoaded", () => {
        if (window.studyGroupData.hasGroups) {
            const first = document.querySelector(".sg-group-item");
            if (first) openGroup(window.studyGroupData.firstGroupId, first);
        }
    });

    // Expose globals
    window.openGroup = openGroup;
    window.filterGroups = filterGroups;
    window.switchTab = switchTab;
    window.toggleSettings = toggleSettings;
    window.openMembersModal = openMembersModal;
    window.closeMembersModal = closeMembersModal;
    window.renameGroup = renameGroup;
    window.renameGroupFromAdmin = renameGroupFromAdmin;
    window.deleteGroup = deleteGroup;
    window.sendMessage = sendMessage;
    window.handleEnter = handleEnter;
    window.autoResize = autoResize;
    window.removeFile = removeFile;
    window.openThreadById = openThreadById;
    window.openThread = openThread;
    window.deleteMessage = deleteMessage;
    window.toggleMsgMenu = toggleMsgMenu;
    window.closeAllMenus = closeAllMenus;
    window.copyMessageText = copyMessageText;
    window.closeThread = closeThread;
    window.sendThreadReply = sendThreadReply;
    window.handleThreadEnter = handleThreadEnter;
    window.deleteReply = deleteReply;
    window.toggleTaskForm = toggleTaskForm;
    window.saveTask = saveTask;
    window.toggleTask = toggleTask;
    window.deleteTask = deleteTask;
    window.filterResources = filterResources;
    window.uploadResources = uploadResources;
    window.togglePin = togglePin;
    window.deleteResource = deleteResource;
    window.openNote = openNote;
    window.createNewNote = createNewNote;
    window.deleteNote = deleteNote;
    window.scheduleNoteSave = scheduleNoteSave;
    window.execFormat = execFormat;
    window.createGroup = createGroup;
    window.closeModal = closeModal;
    window.openLightbox = openLightbox;
    window.closeLightbox = closeLightbox;
    window.promoteMember = promoteMember;
    window.demoteMember = demoteMember;
    window.kickMember = kickMember;
    window.shareMyCalendar = shareMyCalendar;
    window.toggleCalHidden = toggleCalHidden;
})();
