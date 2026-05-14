/**
 * study-materials.js  –  Review Materials feature for Focus Mode
 *
 * Viewer: full-bleed iframe fills the entire screen.
 * - Top bar (back + title + nav) floats as a gradient overlay
 * - Notes panel slides in/out from the left edge
 * - Zoom controls sit bottom-right as a floating overlay
 *
 * Key fix: #screenMaterialViewer no longer forces display:flex in CSS,
 * so the app's normal .hidden / show pattern works correctly.
 */
(function () {
    "use strict";

    const $ = (id) => document.getElementById(id);
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
            window.FocusMode.el.musicToggleBtn.classList.toggle(
                "hidden", id === "screenMenu"
            );
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
        notesOpen: false,
    };

    /* ══════════════════════════════════════════════════════════════
       LIBRARY SCREEN
    ══════════════════════════════════════════════════════════════ */
    function buildLibraryScreen() {
        const screen = $("screenReview");
        if (!screen) return;

        screen.innerHTML = "";

        const h = document.createElement("h2");
        h.className = "rm-heading";
        h.textContent = "Review Materials";
        screen.appendChild(h);

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

        const status = document.createElement("div");
        status.className = "rm-upload-status";
        status.id = "rmUploadStatus";
        screen.appendChild(status);

        const fileInput = document.createElement("input");
        fileInput.type = "file";
        fileInput.id = "rmFileInput";
        fileInput.className = "hidden";
        fileInput.accept = ".pdf,.doc,.docx,.ppt,.pptx";
        screen.appendChild(fileInput);

        const grid = document.createElement("div");
        grid.className = "rm-materials-grid";
        grid.id = "rmMaterialsGrid";
        screen.appendChild(grid);

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
            // Card wrapper
            const card = document.createElement("div");
            card.className = "rm-file-card";
            card.dataset.index = String(i);
            card.title = m.name; // full name shown on hover as native tooltip

            // Left accent bar
            const accent = document.createElement("div");
            accent.className = "rm-file-card-accent";

            // Card body
            const body = document.createElement("div");
            body.className = "rm-file-card-body";

            const name = document.createElement("span");
            name.className = "rm-file-card-name";
            name.textContent = m.name;

            const type = document.createElement("span");
            type.className = "rm-file-card-type";
            type.textContent = (m.type || "file").toUpperCase();

            body.appendChild(name);
            body.appendChild(type);

            // Delete button
            const del = document.createElement("button");
            del.className = "rm-file-card-delete";
            del.dataset.id = String(m.id);
            del.title = "Delete";
            del.type = "button";
            del.textContent = "✕";

            card.appendChild(accent);
            card.appendChild(body);
            card.appendChild(del);
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
        fd.append("material", file);
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
       VIEWER SCREEN  –  full-bleed layout
    ══════════════════════════════════════════════════════════════ */
    function buildViewerScreen() {
        let screen = $("screenMaterialViewer");
        if (!screen) {
            screen = document.createElement("div");
            screen.className = "screen hidden";
            screen.id = "screenMaterialViewer";
            document.querySelector(".fm-main")?.appendChild(screen);
        }
        // Clear previous content but keep the element itself (and its classes)
        screen.innerHTML = "";

        /* 1. Full-bleed iframe (lowest layer) */
        const iframeWrap = document.createElement("div");
        iframeWrap.className = "rmv-iframe-fullscreen hidden";
        iframeWrap.id = "rmvIframeWrap";

        const iframe = document.createElement("iframe");
        iframe.id = "rmvIframe";
        iframe.title = "Study material viewer";
        iframe.setAttribute("allowfullscreen", "");
        iframeWrap.appendChild(iframe);
        screen.appendChild(iframeWrap);

        /* 2. Loader (covers full screen while loading) */
        const loader = document.createElement("div");
        loader.className = "rmv-loader hidden";
        loader.id = "rmvLoader";
        loader.innerHTML = '<div class="rmv-loader-spinner"></div>';
        screen.appendChild(loader);

        /* 3. Placeholder (before any file is loaded) */
        const placeholder = document.createElement("div");
        placeholder.className = "rmv-file-placeholder";
        placeholder.id = "rmvFilePlaceholder";
        placeholder.textContent = "Select a study material";
        screen.appendChild(placeholder);

        /* 4. Top bar overlay */
        const topbar = document.createElement("div");
        topbar.className = "rmv-topbar";

        const backBtn = document.createElement("button");
        backBtn.className = "rmv-back-btn";
        backBtn.id = "rmvBackBtn";
        backBtn.type = "button";
        backBtn.textContent = "← Materials";
        topbar.appendChild(backBtn);

        const titleEl = document.createElement("p");
        titleEl.className = "rmv-title";
        titleEl.id = "rmvTitle";
        titleEl.textContent = "STUDY MATERIAL";
        topbar.appendChild(titleEl);

        const fileNav = document.createElement("div");
        fileNav.className = "rmv-file-nav";

        const filePrev = document.createElement("button");
        filePrev.className = "rmv-nav-arrow";
        filePrev.id = "rmvFilePrev";
        filePrev.type = "button";
        filePrev.title = "Previous material";
        filePrev.textContent = "‹";

        const fileNext = document.createElement("button");
        fileNext.className = "rmv-nav-arrow";
        fileNext.id = "rmvFileNext";
        fileNext.type = "button";
        fileNext.title = "Next material";
        fileNext.textContent = "›";

        fileNav.appendChild(filePrev);
        fileNav.appendChild(fileNext);
        topbar.appendChild(fileNav);
        screen.appendChild(topbar);

        /* 5. Notes panel (slide-in from left) */
        const notesPanel = document.createElement("div");
        notesPanel.className = "rmv-notes-panel";
        notesPanel.id = "rmvNotesPanel";

        const toggle = document.createElement("div");
        toggle.className = "rmv-notes-toggle";
        toggle.id = "rmvNotesToggle";
        toggle.textContent = "Notes";
        notesPanel.appendChild(toggle);

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
        screen.appendChild(notesPanel);

        /* 6. Zoom controls (bottom-right overlay) */
        const zoomCtrl = document.createElement("div");
        zoomCtrl.className = "rmv-zoom-controls";
        zoomCtrl.innerHTML = `
            <button class="rmv-zoom-btn" id="rmvZoomOut" type="button" title="Zoom out">−</button>
            <span class="rmv-zoom-level" id="rmvZoomLevel">100%</span>
            <button class="rmv-zoom-btn" id="rmvZoomIn" type="button" title="Zoom in">+</button>
            <button class="rmv-zoom-btn" id="rmvZoomReset" type="button"
                title="Reset zoom" style="font-size:.7rem;width:auto;padding:0 .4rem">1:1</button>
        `;
        screen.appendChild(zoomCtrl);

        bindViewerEvents();
    }

    function bindViewerEvents() {
        $("rmvBackBtn").addEventListener("click", () => {
            const m = materials[viewer.notesMaterialIndex];
            if (m && viewer.notesDirty[m.id]) saveNotes(m.id, false);
            showScreen("screenReview");
        });

        $("rmvNotesToggle").addEventListener("click", () => {
            viewer.notesOpen = !viewer.notesOpen;
            $("rmvNotesPanel").classList.toggle("open", viewer.notesOpen);
        });

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
        viewer.notesOpen          = false;

        // Build DOM first, then show screen so elements exist when we query them
        buildViewerScreen();
        updateViewerNav();
        loadMaterialFile(idx);
        loadNotes(idx);
        showScreen("screenMaterialViewer");
    }

    function navigateFile(dir) {
        // Save dirty notes before switching
        const cur = materials[viewer.materialIndex];
        if (cur && viewer.notesDirty[cur.id]) saveNotes(cur.id, false);

        viewer.materialIndex = Math.max(0, Math.min(
            viewer.materialIndex + dir, materials.length - 1
        ));
        // Notes always follow the file
        viewer.notesMaterialIndex = viewer.materialIndex;
        viewer.zoom = 1.0;
        loadMaterialFile(viewer.materialIndex);
        loadNotes(viewer.materialIndex);
        updateViewerNav();
    }

    function updateViewerNav() {
        const total = materials.length;
        const fi    = viewer.materialIndex;
        if ($("rmvFilePrev")) $("rmvFilePrev").disabled = fi <= 0;
        if ($("rmvFileNext")) $("rmvFileNext").disabled = fi >= total - 1;
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

        const fileUrl = m.url || (window.location.origin + "/focus-mode/materials/" + m.id + "/file");
        const isSameOrigin = fileUrl.startsWith(window.location.origin);
        const isPdf = (m.type || "").toLowerCase() === "pdf" || fileUrl.toLowerCase().endsWith('.pdf');

        if (!iframe) return;
        iframe.src = "";

        const finishLoad = () => {
            if (loader)     loader.classList.add("hidden");
            if (iframeWrap) iframeWrap.classList.remove("hidden");
            applyZoom(1.0);
        };

        // If the file is same-origin (served by this app) or a PDF, load directly in the iframe.
        // Google Docs Viewer cannot access localhost/private URLs, which caused "No preview available".
        if (isSameOrigin || isPdf) {
            setTimeout(() => {
                iframe.src = fileUrl;
                iframe.onload = finishLoad;
                // Fallback if onload doesn't fire
                setTimeout(() => { if (loader && !loader.classList.contains("hidden")) finishLoad(); }, 12000);
            }, 60);
            return;
        }

        // Otherwise attempt Google Docs Viewer for public remote URLs
        const gdocsUrl = "https://docs.google.com/viewer?url=" + encodeURIComponent(fileUrl) + "&embedded=true";
        setTimeout(() => {
            iframe.src = gdocsUrl;
            iframe.onload = finishLoad;
            setTimeout(() => { if (loader && !loader.classList.contains("hidden")) finishLoad(); }, 12000);
        }, 60);
    }

    /* ── Zoom ────────────────────────────────────────────────────── */
    function applyZoom(z) {
        z = Math.max(0.4, Math.min(3.0, z));
        viewer.zoom = z;
        const wrap = $("rmvIframeWrap");
        if (wrap) {
            wrap.style.transformOrigin = "top left";
            wrap.style.transform = `scale(${z})`;
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

        // Always clear so previous file's notes never bleed through
        ta.value = "";
        viewer.notesDirty[m.id] = false;

        // Serve from cache if already fetched this session
        if (viewer.notesCache[m.id] !== undefined) {
            ta.value = viewer.notesCache[m.id];
            return;
        }

        try {
            const url = window.location.origin + "/focus-mode/materials/" + m.id + "/notes";
            const res  = await fetch(url, {
                headers: {
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": csrf(),
                },
            });

            if (!res.ok) {
                // Non-fatal — start with empty notes rather than crashing
                viewer.notesCache[m.id] = "";
                return;
            }

            const data = await res.json();
            viewer.notesCache[m.id] = data.content ?? "";

            // Only update the textarea if the user hasn't navigated away
            if (viewer.notesMaterialIndex === idx) {
                const t = $("rmvNotesTextarea");
                if (t) t.value = viewer.notesCache[m.id];
            }
        } catch (_) {
            viewer.notesCache[m.id] = "";
        }
    }

    async function saveNotes(materialId, showFeedback = true) {
        const ta      = $("rmvNotesTextarea");
        const btn     = $("rmvSaveNotesBtn");
        const content = ta ? ta.value : "";

        if (btn) btn.classList.add("saving");

        try {
            const url = window.location.origin + "/focus-mode/materials/" + materialId + "/notes";
            const res = await fetch(url, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrf(),
                    "Accept": "application/json",
                },
                body: JSON.stringify({ content }),
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message ?? "Save failed (" + res.status + ")");
            }

            viewer.notesCache[materialId]  = content;
            viewer.notesDirty[materialId]  = false;
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

        // Delegated click for data-target buttons (Back to Menu, etc.)
        document.addEventListener("click", (e) => {
            const btn = e.target.closest("[data-target]");
            if (!btn) return;
            const inReview = btn.closest("#screenReview");
            const inViewer = btn.closest("#screenMaterialViewer");
            if (inReview || inViewer) {
                e.stopPropagation();
                showScreen(btn.dataset.target);
            }
        }, true);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();