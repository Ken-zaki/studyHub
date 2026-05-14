// flashcards-decks.js — registers deck UI/behavior with FocusMode
(function () {
    "use strict";
    function init(state, el) {
        const api = window.FocusMode || {};
        const getCsrfToken = api.getCsrfToken || (()=>"");
        const escHtml = api.escHtml || ((v)=>String(v));
        const renderFlashcardSlider = api.renderFlashcardSlider || (()=>{});

        // Show deck browser when entering flashcard screen
        function showDeckBrowser() {
            state.activeDeckId = null;
            el.deckBrowser?.classList.remove("hidden");
            el.deckContent?.classList.add("hidden");
            if (el.flashcardScreenBackBtn) el.flashcardScreenBackBtn.dataset.target = "screenMenu";
            renderDeckGrid();
        }

        function showDeckContent(deckId) {
            const deck = state.decks.find((d) => d.id === deckId);
            if (!deck) return;
            state.activeDeckId = deckId;
            state.flashcardIndex = 0;

            el.deckBrowser?.classList.add("hidden");
            el.deckContent?.classList.remove("hidden");

            if (el.deckContentTitle) el.deckContentTitle.textContent = deck.name;
            if (el.deckContentDesc)  el.deckContentDesc.textContent  = deck.description || "";

            if (el.flashcardScreenBackBtn) el.flashcardScreenBackBtn.classList.add("hidden");

            renderFlashcardSlider(deck.flashcards || []);
        }

        function renderDeckGrid() {
            if (!el.deckGrid) return;
            if (!state.decks || !state.decks.length) {
                el.deckGrid.innerHTML = `<div class="deck-empty-state">No Decks Available Yet<br><span>Create a deck above to get started</span></div>`;
                return;
            }
            el.deckGrid.innerHTML = state.decks.map((d) => `
                <div class="deck-card" data-deck-id="${d.id}" role="button" tabindex="0" aria-label="Open deck ${escHtml(d.name)}">
                    <div class="deck-card-accent"></div>
                    <div class="deck-card-body">
                        <div class="deck-card-name">${escHtml(d.name)}</div>
                        <div class="deck-card-count">${(d.flashcards || []).length} card${(d.flashcards || []).length !== 1 ? "s" : ""}</div>
                        ${d.description ? `<div class="deck-card-desc">${escHtml(d.description)}</div>` : ""}
                    </div>
                    <button class="deck-delete-btn" data-deck-id="${d.id}" aria-label="Delete deck" title="Delete deck">🗑</button>
                </div>`).join("");

            // Attach handlers
            el.deckGrid.querySelectorAll(".deck-card").forEach((card) => {
                card.addEventListener("click", (e) => {
                    if (e.target.classList.contains("deck-delete-btn")) return;
                    showDeckContent(String(card.dataset.deckId));
                });
                card.addEventListener("keydown", (e) => { if (e.key === "Enter" || e.key === " ") showDeckContent(String(card.dataset.deckId)); });
            });
            el.deckGrid.querySelectorAll(".deck-delete-btn").forEach((btn) => {
                btn.addEventListener("click", async (e) => {
                    e.stopPropagation();
                    if (!confirm("Delete this deck and all its flashcards?")) return;
                    await handleDeckDelete(String(btn.dataset.deckId));
                });
            });
        }

        // Modal handling
        function openDeckModal() {
            el.deckModalOverlay?.classList.remove("hidden");
            el.deckModalOverlay?.classList.add("open");
            el.deckNameInput && (el.deckNameInput.value = "");
            setDeckStatus("");
            setTimeout(() => el.deckNameInput?.focus(), 60);
        }
        function closeDeckModal() {
            el.deckModalOverlay?.classList.add("hidden");
            el.deckModalOverlay?.classList.remove("open");
            el.deckNameInput && (el.deckNameInput.value = "");
            setDeckStatus("");
        }

        el.deckCreateBtn?.addEventListener("click", openDeckModal);
        el.deckCancelBtn?.addEventListener("click", closeDeckModal);
        el.deckModalOverlay?.addEventListener("click", (e)=>{ if (e.target === el.deckModalOverlay) closeDeckModal(); });
        document.addEventListener("keydown", (e)=>{ if (e.key === "Escape" && !el.deckModalOverlay?.classList.contains("hidden")) closeDeckModal(); });
        el.deckSaveBtn?.addEventListener("click", handleDeckCreate);

        function setDeckStatus(msg, isError = false) {
            if (!el.deckStatus) return;
            el.deckStatus.textContent = msg;
            el.deckStatus.classList.toggle("error", isError);
        }

        async function handleDeckCreate() {
            const name = (el.deckNameInput?.value || "").trim();
            if (!name) { setDeckStatus("Please enter a deck name.", true); return; }
            try {
                state.deckBusy = true;
                if (el.deckSaveBtn) el.deckSaveBtn.disabled = true;
                setDeckStatus("Creating…");
                let created = false;
                try {
                    const res = await fetch("/focus-mode/decks", {
                        method: "POST",
                        headers: { "X-CSRF-TOKEN": getCsrfToken(), Accept: "application/json", "Content-Type": "application/json" },
                        body: JSON.stringify({ name }),
                    });
                    const payload = await res.json().catch(()=>null);
                    if (res.ok && payload) {
                        state.decks = payload.decks || [payload.deck, ...state.decks];
                        // set the active deck to the newly created server deck
                        const newDeck = payload.deck || (Array.isArray(payload.decks) ? payload.decks[0] : null);
                        if (newDeck && newDeck.id) state.activeDeckId = String(newDeck.id);
                        created = true;
                    }
                } catch (networkErr) {
                    // network error — we'll fall through to offline creation
                }

                if (!created) {
                    // Fallback: create a transient local deck so UX works offline
                    const localDeck = { id: `local-${Date.now()}`, name, description: "", flashcards: [] };
                    state.decks = [localDeck, ...(state.decks || [])];
                    state.activeDeckId = localDeck.id;
                    setDeckStatus("Deck created (offline)");
                } else {
                    setDeckStatus("Deck created!");
                }

                setTimeout(closeDeckModal, 600);
                renderDeckGrid();
            } catch (err) {
                // If something unexpected fails, show error but keep UI usable
                setDeckStatus(err.message || "Failed.", true);
            } finally {
                state.deckBusy = false;
                if (el.deckSaveBtn) el.deckSaveBtn.disabled = false;
            }
        }

        async function handleDeckDelete(deckId) {
            try {
                const res = await fetch(`/focus-mode/decks/${deckId}`, {
                    method: "DELETE",
                    headers: { "X-CSRF-TOKEN": getCsrfToken(), Accept: "application/json" },
                });
                const payload = await res.json();
                if (!res.ok) throw new Error(payload?.message || "Failed to delete deck.");
                state.decks = payload.decks || state.decks.filter((d) => d.id !== deckId);
                renderDeckGrid();
            } catch (err) {
                alert(err.message || "Failed to delete deck.");
            }
        }

        // Back to deck browser from inside a deck
        el.deckBackBtn?.addEventListener("click", () => { el.flashcardScreenBackBtn?.classList.remove("hidden"); showDeckBrowser(); });

        // Ensure flashcard screen shows deck browser by default
        // If focus-mode calls showScreen('screenFlashcard') and expects the deck browser,
        // the existing focus-mode code calls showDeckBrowser(); mimic that by
        // registering a small hook: when screen changes to screenFlashcard, show deck browser
        // (we rely on state.currentScreen changes by focus-mode). Use a MutationObserver style
        // simple poll to respond to initial load and subsequent changes.
        let lastScreen = state.currentScreen;
        setInterval(() => {
            if (state.currentScreen !== lastScreen) {
                lastScreen = state.currentScreen;
                if (lastScreen === 'screenFlashcard') showDeckBrowser();
            }
        }, 300);

        // Initial render
        if (state.currentScreen === 'screenFlashcard') showDeckBrowser();
    }

    // Register with FocusMode if available, or wait for it
    if (window.FocusMode && typeof window.FocusMode.register === 'function') {
        window.FocusMode.register('decks', init);
    } else {
        // wait briefly and try again
        setTimeout(()=>{ window.FocusMode && window.FocusMode.register && window.FocusMode.register('decks', init); }, 300);
    }
})();