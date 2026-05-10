/**
 * study-materials.js  –  Review Materials feature for Focus Mode
 * Fixed:
 *   - Delete now works (sends correct auth headers)
 *   - Back to Menu works after upload (event delegation on document)
 *   - PDF/DOCX viewer uses Google Docs Viewer (no blob/sandbox issues)
 *   - Viewer HTML built with DOM methods, no Blade syntax in JS strings
 *   - Old placeholder box removed (screen.innerHTML cleared on build)
 *   - Zoom applied to wrapper div, not the iframe transform directly
 */
(function () {
    "use strict";

    const $ = (id) => document.getElementById(id);
    const esc = (v) =>
        String(v)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;")
            .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    const csrf = () =>
        document.querySelector('meta[name="csrf-token"]')?.content ?? "";

    /* ── Toast ───────────────────────────────────────────────────── */
    function showToast(msg, dur = 2400) {
        let t = $("rmvToast");
        if (!t) {
            t = document.createElement("div");
            t.id = "rmvToast";
            t.className = "rmv-toast";
            document.body.appendChild(t);
        }
        t.textContent = msg;
        t.classList.add("show");
        clearTimeout(t._timer);
        t._timer = setTimeout(() => t.classList.remove("show"), dur);
    }

    /* ── Screen switcher ─────────────────────────────────────────── */
    function showScreen(id) {
        document.querySelectorAll(".screen").forEach((s) => s.classList.add("hidden"));
        const t = document.getElementById(id);
        if (t) t.classList.remove("hidden");
        if (window.FocusMode?.state) window.FocusMode.state.currentScreen = id;
        if (window.FocusMode?.el?.musicToggleBtn) {
            window.FocusMode.el.musicToggleBtn.classList.toggle("hidden", id === "screenMenu");
        }
    }

    /* ── State ───────────────────────────────────────────────────── */
    let materials = Array.isArray(window.__focusMaterials)
        ? [...window.__focusMaterials]
        : [];

    const viewer = {
        materialIndex: 0,
        notesMaterialIndex: 0,
        zoom: 1.0,
        notesDirty: {},
        notesCache: {},
    };

    /* ══════════════════════════════════════════════════════════════
       LIBRARY SCREEN
    ══════════════════════════════════════════════════════════════ */
    function buildLibraryScreen() {
        const screen = $("screenReview");
        if (!screen) return;

        // Clear everything — removes old placeholder/content-area box
        screen.innerHTML = "";

        // Heading
        const h = document.createElement("h2");
        h.className = "rm-heading";
        h.textContent = "Review Materials";
        screen.appendChild(h);

        // Header row
        const header = document.createElement("div");
        header.className = "rm-library-header";

        const label = document.createElement("span");
        label.className = "rm-library-label";
        label.textContent = "My Study Materials";
        header.appendChild(label);

        const uploadBtn = document.createElement("button");
        uploadBtn.className = "rm-upload-btn";
        uploadBtn.id = "rmUploadBtn";
        uploadBtn.type = "button";
        uploadBtn.textContent = "Upload new file";
        header.appendChild(uploadBtn);

        screen.appendChild(header);

        // Status text
        const status = document.createElement("div");
        status.className = "rm-upload-status";
        status.id = "rmUploadStatus";
        screen.appendChild(status);

        // Hidden file input
        const fileInput = document.createElement("input");
        fileInput.type = "file";
        fileInput.id = "rmFileInput";
        fileInput.className = "hidden";
        fileInput.accept = ".pdf,.doc,.docx,.ppt,.pptx";
        screen.appendChild(fileInput);

        // Grid
        const grid = document.createElement("div");
        grid.className = "rm-materials-grid";
        grid.id = "rmMaterialsGrid";
        screen.appendChild(grid);

        // Back button — data-target handled by our delegated listener
        const back = document.createElement("button");
        back.className = "back-btn";
        back.dataset.target = "screenMenu";
        back.style.marginTop = "2rem";
        back.textContent = "← Back to Menu";
        screen.appendChild(back);

        renderLibraryGrid();

        uploadBtn.addEventListener("click", () => fileInput.click());
        fileInput.addEventListener("change", handleLibraryUpload);
    }

    function renderLibraryGrid() {
        const grid = $("rmMaterialsGrid");
        if (!grid) return;
        grid.innerHTML = "";

        if (materials.length === 0) {
            const empty = document.createElement("p");
            empty.className = "rm-empty-state";
            empty.textContent = "No materials uploaded yet.";
            grid.appendChild(empty);
            return;
        }

        materials.forEach((m, i) => {
            const card = document.createElement("div");
            card.className = "rm-file-card";
            card.dataset.index = String(i);
            card.title = m.name;

            const del = document.createElement("button");
            del.className = "rm-file-card-delete";
            del.dataset.id = String(m.id);
            del.title = "Delete";
            del.type = "button";
            del.textContent = "✕";

            const name = document.createElement("span");
            name.className = "rm-file-card-name";
            name.textContent = m.name;

            card.appendChild(del);
            card.appendChild(name);
            grid.appendChild(card);

            card.addEventListener("click", (e) => {
                if (e.target === del) return;
                openViewer(i);
            });
            del.addEventListener("click", (e) => {
                e.stopPropagation();
                deleteMaterial(m.id);
            });
        });
    }

    async function handleLibraryUpload(e) {
        const file = e.target.files[0];
        if (!file) return;

        const statusEl = $("rmUploadStatus");
        if (statusEl) statusEl.textContent = "Uploading…";

        const fd = new FormData();
        fd.append("file", file);
        fd.append("screen", "screenReview");

        try {
            const res = await fetch("/focus-mode/materials", {
                method: "POST",
                headers: { "X-CSRF-TOKEN": csrf(), Accept: "application/json" },
                body: fd,
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message ?? "Upload failed");
            materials.push(data.material);
            renderLibraryGrid();
            if (statusEl) {
                statusEl.textContent = "✓ Uploaded!";
                setTimeout(() => { statusEl.textContent = ""; }, 2500);
            }
        } catch (err) {
            if (statusEl) statusEl.textContent = "✗ " + err.message;
        } finally {
            e.target.value = "";
        }
    }

    async function deleteMaterial(id) {
        if (!confirm("Remove this material?")) return;
        try {
            const res = await fetch("/focus-mode/materials/" + id, {
                method: "DELETE",
                headers: { "X-CSRF-TOKEN": csrf(), Accept: "application/json" },
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message ?? "Delete failed");
            }
            materials = materials.filter((m) => String(m.id) !== String(id));
            delete viewer.notesCache[id];
            delete viewer.notesDirty[id];
            renderLibraryGrid();
            showToast("Material removed.");
        } catch (err) {
            showToast("Could not delete: " + err.message);
        }
    }

    /* ══════════════════════════════════════════════════════════════
       VIEWER SCREEN
    ══════════════════════════════════════════════════════════════ */
    function buildViewerScreen() {
        let screen = $("screenMaterialViewer");
        if (!screen) {
            screen = document.createElement("div");
            screen.className = "screen hidden";
            screen.id = "screenMaterialViewer";
            document.querySelector(".fm-main")?.appendChild(screen);
        }
        screen.innerHTML = "";

        /* ── Sidebar strip ─────────────────────────────────────── */
        const sidebar = document.createElement("div");
        sidebar.className = "rmv-sidebar";
        const sideLabel = document.createElement("span");
        sideLabel.className = "rmv-sidebar-label";
        sideLabel.textContent = "The Sidebar";
        sidebar.appendChild(sideLabel);
        screen.appendChild(sidebar);

        /* ── Body ──────────────────────────────────────────────── */
        const body = document.createElement("div");
        body.className = "rmv-body";
        screen.appendChild(body);

        // Back button
        const backBtn = document.createElement("button");
        backBtn.className = "rmv-back-btn";
        backBtn.id = "rmvBackBtn";
        backBtn.type = "button";
        backBtn.textContent = "← Back to Materials";
        body.appendChild(backBtn);

        // Title
        const titleEl = document.createElement("p");
        titleEl.className = "rmv-title";
        titleEl.id = "rmvTitle";
        titleEl.textContent = "STUDY MATERIAL";
        body.appendChild(titleEl);

        // Panels row
        const panels = document.createElement("div");
        panels.className = "rmv-panels";
        body.appendChild(panels);

        /* ── Notes panel ───────────────────────────────────────── */
        const notesPanel = document.createElement("div");
        notesPanel.className = "rmv-notes-panel";
        panels.appendChild(notesPanel);

        const notesNav = document.createElement("div");
        notesNav.className = "rmv-notes-nav";

        const notesPrev = document.createElement("button");
        notesPrev.className = "rmv-nav-arrow";
        notesPrev.id = "rmvNotesPrev";
        notesPrev.type = "button";
        notesPrev.title = "Previous material";
        notesPrev.textContent = "<";

        const notesNext = document.createElement("button");
        notesNext.className = "rmv-nav-arrow";
        notesNext.id = "rmvNotesNext";
        notesNext.type = "button";
        notesNext.title = "Next material";
        notesNext.textContent = ">";

        notesNav.appendChild(notesPrev);
        notesNav.appendChild(notesNext);
        notesPanel.appendChild(notesNav);

        const notesCard = document.createElement("div");
        notesCard.className = "rmv-notes-card";

        const notesLabel = document.createElement("div");
        notesLabel.className = "rmv-notes-label";
        notesLabel.textContent = "Notes";
        notesCard.appendChild(notesLabel);

        const textarea = document.createElement("textarea");
        textarea.className = "rmv-notes-textarea";
        textarea.id = "rmvNotesTextarea";
        textarea.placeholder = "Write your notes here…";
        notesCard.appendChild(textarea);

        const saveBtn = document.createElement("button");
        saveBtn.className = "rmv-save-notes-btn";
        saveBtn.id = "rmvSaveNotesBtn";
        saveBtn.type = "button";
        saveBtn.textContent = "save notes";
        notesCard.appendChild(saveBtn);

        notesPanel.appendChild(notesCard);

        /* ── File panel ────────────────────────────────────────── */
        const filePanel = document.createElement("div");
        filePanel.className = "rmv-file-panel";
        panels.appendChild(filePanel);

        const fileNav = document.createElement("div");
        fileNav.className = "rmv-file-nav";

        const filePrev = document.createElement("button");
        filePrev.className = "rmv-nav-arrow";
        filePrev.id = "rmvFilePrev";
        filePrev.type = "button";
        filePrev.title = "Previous material";
        filePrev.textContent = "<";

        const fileNext = document.createElement("button");
        fileNext.className = "rmv-nav-arrow";
        fileNext.id = "rmvFileNext";
        fileNext.type = "button";
        fileNext.title = "Next material";
        fileNext.textContent = ">";

        fileNav.appendChild(filePrev);
        fileNav.appendChild(fileNext);
        filePanel.appendChild(fileNav);

        const fileCard = document.createElement("div");
        fileCard.className = "rmv-file-card";
        fileCard.id = "rmvFileCard";

        const placeholder = document.createElement("div");
        placeholder.className = "rmv-file-placeholder";
        placeholder.id = "rmvFilePlaceholder";
        placeholder.textContent = "study material";
        fileCard.appendChild(placeholder);

        const iframeWrap = document.createElement("div");
        iframeWrap.className = "rmv-iframe-wrap hidden";
        iframeWrap.id = "rmvIframeWrap";

        const iframe = document.createElement("iframe");
        iframe.id = "rmvIframe";
        iframe.title = "Study material viewer";
        iframe.setAttribute("allowfullscreen", "");
        iframeWrap.appendChild(iframe);
        fileCard.appendChild(iframeWrap);

        const loader = document.createElement("div");
        loader.className = "rmv-loader hidden";
        loader.id = "rmvLoader";
        loader.innerHTML = '<div class="rmv-loader-spinner"></div>';
        fileCard.appendChild(loader);

        // Zoom controls
        const zoomCtrl = document.createElement("div");
        zoomCtrl.className = "rmv-zoom-controls";
        zoomCtrl.innerHTML = `
            <button class="rmv-zoom-btn" id="rmvZoomOut" type="button" title="Zoom out">−</button>
            <span class="rmv-zoom-level" id="rmvZoomLevel">100%</span>
            <button class="rmv-zoom-btn" id="rmvZoomIn" type="button" title="Zoom in">+</button>
            <button class="rmv-zoom-btn" id="rmvZoomReset" type="button"
                title="Reset zoom" style="font-size:.7rem;width:auto;padding:0 .4rem">1:1</button>
        `;
        fileCard.appendChild(zoomCtrl);
        filePanel.appendChild(fileCard);

        bindViewerEvents();
    }

    function bindViewerEvents() {
        $("rmvBackBtn").addEventListener("click", () => {
            const m = materials[viewer.notesMaterialIndex];
            if (m && viewer.notesDirty[m.id]) saveNotes(m.id, false);
            showScreen("screenReview");
        });
        $("rmvNotesPrev").addEventListener("click", () => navigateNotes(-1));
        $("rmvNotesNext").addEventListener("click", () => navigateNotes(+1));
        $("rmvFilePrev").addEventListener("click",  () => navigateFile(-1));
        $("rmvFileNext").addEventListener("click",  () => navigateFile(+1));
        $("rmvNotesTextarea").addEventListener("input", () => {
            const m = materials[viewer.notesMaterialIndex];
            if (m) viewer.notesDirty[m.id] = true;
        });
        $("rmvSaveNotesBtn").addEventListener("click", () => {
            const m = materials[viewer.notesMaterialIndex];
            if (m) saveNotes(m.id, true);
        });
        $("rmvZoomIn").addEventListener("click",    () => applyZoom(viewer.zoom + 0.2));
        $("rmvZoomOut").addEventListener("click",   () => applyZoom(viewer.zoom - 0.2));
        $("rmvZoomReset").addEventListener("click", () => applyZoom(1.0));
    }

    /* ── Navigation ──────────────────────────────────────────────── */
    function openViewer(idx) {
        if (!materials.length) return;
        idx = Math.max(0, Math.min(idx, materials.length - 1));
        viewer.materialIndex      = idx;
        viewer.notesMaterialIndex = idx;
        viewer.zoom               = 1.0;
        buildViewerScreen();
        updateViewerNav();
        loadMaterialFile(idx);
        loadNotes(idx);
        showScreen("screenMaterialViewer");
    }

    function navigateNotes(dir) {
        const cur = materials[viewer.notesMaterialIndex];
        if (cur && viewer.notesDirty[cur.id]) saveNotes(cur.id, false);
        viewer.notesMaterialIndex = Math.max(0, Math.min(
            viewer.notesMaterialIndex + dir, materials.length - 1
        ));
        loadNotes(viewer.notesMaterialIndex);
        updateViewerNav();
    }

    function navigateFile(dir) {
        viewer.materialIndex = Math.max(0, Math.min(
            viewer.materialIndex + dir, materials.length - 1
        ));
        viewer.zoom = 1.0;
        loadMaterialFile(viewer.materialIndex);
        updateViewerNav();
    }

    function updateViewerNav() {
        const total = materials.length;
        const fi    = viewer.materialIndex;
        const ni    = viewer.notesMaterialIndex;
        const fp = $("rmvFilePrev"),  fn = $("rmvFileNext");
        const np = $("rmvNotesPrev"), nn = $("rmvNotesNext");
        if (fp) fp.disabled = fi <= 0;
        if (fn) fn.disabled = fi >= total - 1;
        if (np) np.disabled = ni <= 0;
        if (nn) nn.disabled = ni >= total - 1;
        const title = $("rmvTitle");
        if (title && materials[fi]) title.textContent = materials[fi].name.toUpperCase();
    }

    /* ── File loading ────────────────────────────────────────────── */
    function loadMaterialFile(idx) {
        const m = materials[idx];
        if (!m) return;

        const placeholder = $("rmvFilePlaceholder");
        const iframeWrap  = $("rmvIframeWrap");
        const iframe      = $("rmvIframe");
        const loader      = $("rmvLoader");

        if (placeholder) placeholder.classList.add("hidden");
        if (iframeWrap)  iframeWrap.classList.add("hidden");
        if (loader)      loader.classList.remove("hidden");

        const fileUrl = window.location.origin + "/focus-mode/materials/" + m.id + "/file";

        // Google Docs Viewer handles both PDF and Office formats
        const gdocsUrl = "https://docs.google.com/viewer?url=" +
            encodeURIComponent(fileUrl) + "&embedded=true";

        if (!iframe) return;
        iframe.src = "";

        setTimeout(() => {
            iframe.src = gdocsUrl;
            iframe.onload = () => {
                if (loader)     loader.classList.add("hidden");
                if (iframeWrap) iframeWrap.classList.remove("hidden");
                applyZoom(1.0);
            };
            // Safety fallback — show iframe even if onload is late
            setTimeout(() => {
                if (loader && !loader.classList.contains("hidden")) {
                    loader.classList.add("hidden");
                    if (iframeWrap) iframeWrap.classList.remove("hidden");
                }
            }, 12000);
        }, 60);
    }

    /* ── Zoom ────────────────────────────────────────────────────── */
    function applyZoom(z) {
        z = Math.max(0.4, Math.min(3.0, z));
        viewer.zoom = z;
        // Scale the wrapper so scroll position is preserved
        const wrap = $("rmvIframeWrap");
        if (wrap) {
            wrap.style.transformOrigin = "top left";
            wrap.style.transform = `scale(${z})`;
            // Adjust container height so parent scrolls properly
            wrap.style.width  = (100 / z) + "%";
            wrap.style.height = (100 / z) + "%";
        }
        const lvl = $("rmvZoomLevel");
        if (lvl) lvl.textContent = Math.round(z * 100) + "%";
    }

    /* ── Notes ───────────────────────────────────────────────────── */
    async function loadNotes(idx) {
        const m  = materials[idx];
        if (!m) return;
        const ta = $("rmvNotesTextarea");
        if (!ta) return;

        if (viewer.notesCache[m.id] !== undefined) {
            ta.value = viewer.notesCache[m.id];
            return;
        }
        ta.value = "";
        try {
            const res  = await fetch("/focus-mode/materials/" + m.id + "/notes", {
                headers: { Accept: "application/json" },
            });
            const data = await res.json();
            viewer.notesCache[m.id] = data.content ?? "";
            if (viewer.notesMaterialIndex === idx) {
                const t = $("rmvNotesTextarea");
                if (t) t.value = viewer.notesCache[m.id];
            }
        } catch (_) {}
    }

    async function saveNotes(materialId, showFeedback = true) {
        const ta      = $("rmvNotesTextarea");
        const btn     = $("rmvSaveNotesBtn");
        const content = ta?.value ?? "";
        if (btn) btn.classList.add("saving");
        try {
            const res = await fetch("/focus-mode/materials/" + materialId + "/notes", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrf(),
                    Accept: "application/json",
                },
                body: JSON.stringify({ content }),
            });
            if (!res.ok) throw new Error("Save failed");
            viewer.notesCache[materialId] = content;
            delete viewer.notesDirty[materialId];
            if (showFeedback) showToast("Notes saved ✓");
        } catch (err) {
            if (showFeedback) showToast("Could not save notes: " + err.message);
        } finally {
            if (btn) btn.classList.remove("saving");
        }
    }

    /* ══════════════════════════════════════════════════════════════
       INIT
    ══════════════════════════════════════════════════════════════ */
    function init() {
        buildLibraryScreen();

        // Single delegated listener on document covers:
        //  - Back to Menu (inside #screenReview, rebuilt on every upload)
        //  - Back to Materials (inside #screenMaterialViewer)
        // stopPropagation prevents focus-mode.js double-handling
        document.addEventListener("click", (e) => {
            const btn = e.target.closest("[data-target]");
            if (!btn) return;
            const inReview = btn.closest("#screenReview");
            const inViewer = btn.closest("#screenMaterialViewer");
            if (inReview || inViewer) {
                e.stopPropagation();
                showScreen(btn.dataset.target);
            }
        }, true); // capture phase so it fires before focus-mode.js bubble handlers
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();