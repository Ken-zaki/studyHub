<?php
// ─────────────────────────────────────────────────────────────
//  FocusModeController.php
//  Place at: app/Http/Controllers/FocusModeController.php
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FocusModeController extends Controller
{
    // ─────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────

    /** Return the authenticated user's ID or null. */
    private function userId(): ?string
    {
        $id = session('user_id');
        return ($id && $id !== '') ? (string) $id : null;
    }

    /**
     * Load all decks for the current user, each with its flashcards nested.
     */
    private function loadDecks(string $userId): array
    {
        $decks = DB::table('focus_flashcard_decks')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'description', 'created_at'])
            ->map(fn ($row) => (array) $row)
            ->toArray();

        // Attach flashcards to each deck
        foreach ($decks as &$deck) {
            $deck['flashcards'] = DB::table('focus_flashcards')
                ->where('user_id', $userId)
                ->where('deck_id', $deck['id'])
                ->orderBy('created_at', 'asc')
                ->get(['id', 'deck_id', 'question', 'answer', 'created_at'])
                ->map(fn ($row) => (array) $row)
                ->toArray();
        }
        unset($deck);

        return $decks;
    }

    /**
     * Load all quizzes for the current user from the DB.
     * Rebuilds the nested 'options' array the JS expects.
     */
    private function loadQuizzes(string $userId): array
    {
        return DB::table('focus_quizzes')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'asc')
            ->get(['id', 'question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'explanation', 'created_at'])
            ->map(function ($row) {
                return [
                    'id'             => $row->id,
                    'question'       => $row->question,
                    'options'        => [
                        'A' => $row->option_a,
                        'B' => $row->option_b,
                        'C' => $row->option_c,
                        'D' => $row->option_d,
                    ],
                    'correct_option' => $row->correct_option,
                    'explanation'    => $row->explanation ?? '',
                    'created_at'     => $row->created_at,
                ];
            })
            ->toArray();
    }

    // ─────────────────────────────────────────────────────────
    //  Routes
    // ─────────────────────────────────────────────────────────

    /**
     * Display the Focus Mode page.
     * Decks (with nested flashcards) & quizzes come from DB (persist across logins).
     * Materials stay in session (ephemeral, per-browser, file-based).
     */
    public function index()
    {
        $userId = $this->userId();

        $decks   = $userId ? $this->loadDecks($userId)   : [];
        $quizzes = $userId ? $this->loadQuizzes($userId) : [];

        return view('home.focus-mode', [
            'materials' => session('focus_materials', []),
            'decks'     => $decks,
            'quizzes'   => $quizzes,
        ]);
    }

    /**
     * Save a completed focus session duration (AJAX).
     * Kept in session — these are transient stats shown on the profile page.
     */
    public function storeSession(Request $request)
    {
        $validated = $request->validate([
            'duration' => 'required|integer|min:1|max:86400',
        ]);

        if (!$this->userId()) {
            return response()->json(['message' => 'Not authenticated.'], 401);
        }

        session([
            'focus_session_count' => (int) session('focus_session_count', 0) + 1,
            'focus_total_seconds' => (int) session('focus_total_seconds', 0) + (int) $validated['duration'],
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Upload a study material file for the active focus session.
     * Materials are still session-based (they reference local files
     * whose URLs change per-device anyway).
     */
    public function storeMaterial(Request $request)
    {
        $validated = $request->validate([
            'material' => 'required|file|mimes:pdf,doc,docx,ppt,pptx|max:20480',
            'screen'   => 'required|string|in:screenReview,screenFlashcard,screenQuiz',
        ]);

        $userId   = $this->userId() ?: 'guest';
        $file     = $request->file('material');
        $origName = $file->getClientOriginalName();
        $safeName = Str::slug(pathinfo($origName, PATHINFO_FILENAME));
        $ext      = strtolower($file->getClientOriginalExtension());
        $fileName = now()->format('YmdHis') . '_' . $safeName . '_' . Str::random(8) . '.' . $ext;

        $directory = public_path('uploads/focus-mode/' . $userId);
        File::ensureDirectoryExists($directory);
        $file->move($directory, $fileName);

        $material = [
            'id'          => (string) Str::uuid(),
            'screen'      => $validated['screen'],
            'name'        => $origName,
            'url'         => asset('uploads/focus-mode/' . $userId . '/' . $fileName),
            'type'        => $ext,
            'uploaded_at' => now()->toDateTimeString(),
        ];

        $materials   = session('focus_materials', []);
        $materials[] = $material;
        session(['focus_materials' => array_slice($materials, -20)]);

        return response()->json([
            'status'    => 'ok',
            'material'  => $material,
            'materials' => session('focus_materials', []),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  Decks
    // ─────────────────────────────────────────────────────────

    /**
     * Create a new flashcard deck → persisted to DB.
     */
    public function storeDeck(Request $request)
    {
        $userId = $this->userId();

        if (!$userId) {
            return response()->json(['message' => 'Not authenticated.'], 401);
        }

        $validated = $request->validate([
            'name'        => 'required|string|min:1|max:120',
            'description' => 'nullable|string|max:250',
        ]);

        $id  = (string) Str::uuid();
        $now = now()->toDateTimeString();

        DB::table('focus_flashcard_decks')->insert([
            'id'          => $id,
            'user_id'     => $userId,
            'name'        => trim($validated['name']),
            'description' => trim((string) ($validated['description'] ?? '')),
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        return response()->json([
            'status' => 'ok',
            'deck'   => [
                'id'          => $id,
                'name'        => trim($validated['name']),
                'description' => trim((string) ($validated['description'] ?? '')),
                'created_at'  => $now,
                'flashcards'  => [],   // brand-new deck has no cards yet
            ],
            'decks'  => $this->loadDecks($userId),
        ]);
    }

    /**
     * Delete a deck and all its flashcards — only the owner may do this.
     */
    public function destroyDeck(string $id)
    {
        $userId = $this->userId();

        if (!$userId) {
            return response()->json(['message' => 'Not authenticated.'], 401);
        }

        // Verify ownership before touching anything
        $deck = DB::table('focus_flashcard_decks')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$deck) {
            return response()->json(['message' => 'Deck not found.'], 404);
        }

        // Delete all cards in the deck first, then the deck itself
        DB::table('focus_flashcards')
            ->where('deck_id', $id)
            ->where('user_id', $userId)
            ->delete();

        DB::table('focus_flashcard_decks')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json([
            'status' => 'ok',
            'decks'  => $this->loadDecks($userId),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  Flashcards  (now deck-scoped)
    // ─────────────────────────────────────────────────────────

    /**
     * Store a manually typed flashcard inside a specific deck → persisted to DB.
     */
    public function storeFlashcard(Request $request)
    {
        $userId = $this->userId();

        if (!$userId) {
            return response()->json(['message' => 'Not authenticated.'], 401);
        }

        $validated = $request->validate([
            'deck_id'  => 'required|string',
            'question' => 'required|string|min:1|max:400',
            'answer'   => 'required|string|min:1|max:1000',
        ]);

        // Verify the deck exists and belongs to this user
        $deck = DB::table('focus_flashcard_decks')
            ->where('id', $validated['deck_id'])
            ->where('user_id', $userId)
            ->first();

        if (!$deck) {
            return response()->json(['message' => 'Deck not found.'], 404);
        }

        $id  = (string) Str::uuid();
        $now = now()->toDateTimeString();

        DB::table('focus_flashcards')->insert([
            'id'         => $id,
            'user_id'    => $userId,
            'deck_id'    => $validated['deck_id'],
            'question'   => trim($validated['question']),
            'answer'     => trim($validated['answer']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $flashcard = [
            'id'         => $id,
            'deck_id'    => $validated['deck_id'],
            'question'   => trim($validated['question']),
            'answer'     => trim($validated['answer']),
            'created_at' => $now,
        ];

        // Return only the cards for this specific deck (what the slider needs)
        $deckFlashcards = DB::table('focus_flashcards')
            ->where('user_id', $userId)
            ->where('deck_id', $validated['deck_id'])
            ->orderBy('created_at', 'asc')
            ->get(['id', 'deck_id', 'question', 'answer', 'created_at'])
            ->map(fn ($row) => (array) $row)
            ->toArray();

        return response()->json([
            'status'     => 'ok',
            'flashcard'  => $flashcard,
            'flashcards' => $deckFlashcards,
        ]);
    }

    /**
     * Delete a flashcard — only the owner can delete their own card.
     */
    public function destroyFlashcard(string $id)
    {
        $userId = $this->userId();

        if (!$userId) {
            return response()->json(['message' => 'Not authenticated.'], 401);
        }

        // Fetch the card first so we know its deck_id for the response
        $card = DB::table('focus_flashcards')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$card) {
            return response()->json(['message' => 'Flashcard not found.'], 404);
        }

        $deckId = $card->deck_id;

        DB::table('focus_flashcards')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->delete();

        // Return the remaining cards for the same deck
        $remaining = DB::table('focus_flashcards')
            ->where('user_id', $userId)
            ->where('deck_id', $deckId)
            ->orderBy('created_at', 'asc')
            ->get(['id', 'deck_id', 'question', 'answer', 'created_at'])
            ->map(fn ($row) => (array) $row)
            ->toArray();

        return response()->json([
            'status'     => 'ok',
            'flashcards' => $remaining,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  Quizzes  (unchanged from original)
    // ─────────────────────────────────────────────────────────

    /**
     * Store a manually typed quiz question → persisted to DB.
     */
    public function storeQuiz(Request $request)
    {
        $userId = $this->userId();

        if (!$userId) {
            return response()->json(['message' => 'Not authenticated.'], 401);
        }

        $validated = $request->validate([
            'question'       => 'required|string|min:1|max:500',
            'option_a'       => 'required|string|min:1|max:400',
            'option_b'       => 'required|string|min:1|max:400',
            'option_c'       => 'required|string|min:1|max:400',
            'option_d'       => 'required|string|min:1|max:400',
            'correct_option' => 'required|string|in:A,B,C,D',
            'explanation'    => 'nullable|string|max:1000',
        ]);

        $id  = (string) Str::uuid();
        $now = now()->toDateTimeString();

        DB::table('focus_quizzes')->insert([
            'id'             => $id,
            'user_id'        => $userId,
            'question'       => trim($validated['question']),
            'option_a'       => trim($validated['option_a']),
            'option_b'       => trim($validated['option_b']),
            'option_c'       => trim($validated['option_c']),
            'option_d'       => trim($validated['option_d']),
            'correct_option' => $validated['correct_option'],
            'explanation'    => trim((string) ($validated['explanation'] ?? '')),
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        $quiz = [
            'id'             => $id,
            'question'       => trim($validated['question']),
            'options'        => [
                'A' => trim($validated['option_a']),
                'B' => trim($validated['option_b']),
                'C' => trim($validated['option_c']),
                'D' => trim($validated['option_d']),
            ],
            'correct_option' => $validated['correct_option'],
            'explanation'    => trim((string) ($validated['explanation'] ?? '')),
            'created_at'     => $now,
        ];

        return response()->json([
            'status'  => 'ok',
            'quiz'    => $quiz,
            'quizzes' => $this->loadQuizzes($userId),
        ]);
    }

    /**
     * Delete a quiz question — only the owner can delete their own question.
     */
    public function destroyQuiz(string $id)
    {
        $userId = $this->userId();

        if (!$userId) {
            return response()->json(['message' => 'Not authenticated.'], 401);
        }

        $deleted = DB::table('focus_quizzes')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Quiz question not found.'], 404);
        }

        return response()->json([
            'status'  => 'ok',
            'quizzes' => $this->loadQuizzes($userId),
        ]);
    }
}