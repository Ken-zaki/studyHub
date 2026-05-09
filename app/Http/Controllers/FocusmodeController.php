<?php
// ─────────────────────────────────────────────────────────────
//  FocusModeController.php  (updated — adds Quiz Set support)
//  Place at: app/Http/Controllers/FocusModeController.php
//
//  WHAT'S NEW vs the original:
//   • loadQuizSets()  — loads sets with nested questions
//   • storeQuizSet()  — POST /focus-mode/quiz-sets
//   • destroyQuizSet() — DELETE /focus-mode/quiz-sets/{id}
//   • storeQuiz()  — now accepts quiz_set_id and scopes the
//                    returned questions to that set
//   • index()  — now passes $quizSets to the view
//
//  DB MIGRATION NEEDED — run once:
//  ─────────────────────────────────────────────────────────
//  Schema::create('focus_quiz_sets', function (Blueprint $table) {
//      $table->string('id')->primary();
//      $table->string('user_id');
//      $table->string('title');
//      $table->string('description')->default('');
//      $table->timestamps();
//      $table->index('user_id');
//  });
//
//  Then add quiz_set_id to focus_quizzes:
//  Schema::table('focus_quizzes', function (Blueprint $table) {
//      $table->string('quiz_set_id')->nullable()->after('user_id');
//      $table->index('quiz_set_id');
//  });
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
     * Load all quiz sets for the current user, each with its questions nested.
     */
    private function loadQuizSets(string $userId): array
    {
        $sets = DB::table('focus_quiz_sets')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'description', 'created_at'])
            ->map(fn ($row) => (array) $row)
            ->toArray();

        foreach ($sets as &$set) {
            $set['questions'] = DB::table('focus_quizzes')
                ->where('user_id', $userId)
                ->where('quiz_set_id', $set['id'])
                ->orderBy('created_at', 'asc')
                ->get(['id', 'quiz_set_id', 'question', 'option_a', 'option_b',
                       'option_c', 'option_d', 'correct_option', 'explanation', 'created_at'])
                ->map(function ($row) {
                    return [
                        'id'             => $row->id,
                        'quiz_set_id'    => $row->quiz_set_id,
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
        unset($set);

        return $sets;
    }

    /**
     * Load global quizzes (not set-scoped) — kept for backward compat.
     */
    private function loadQuizzes(string $userId): array
    {
        return DB::table('focus_quizzes')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'asc')
            ->get(['id', 'question', 'option_a', 'option_b', 'option_c', 'option_d',
                   'correct_option', 'explanation', 'created_at'])
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
     */
    public function index()
    {
        $userId = $this->userId();

        $decks     = $userId ? $this->loadDecks($userId)    : [];
        $quizzes   = $userId ? $this->loadQuizzes($userId)  : [];
        $quizSets  = $userId ? $this->loadQuizSets($userId) : [];

        return view('home.focus-mode', [
            'materials' => session('focus_materials', []),
            'decks'     => $decks,
            'quizzes'   => $quizzes,   // kept for backward compat
            'quizSets'  => $quizSets,  // new — passed to blade bootstrap
        ]);
    }

    /**
     * Save a completed focus session duration (AJAX).
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
     * Upload a study material file.
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
    //  Decks  (unchanged)
    // ─────────────────────────────────────────────────────────

    public function storeDeck(Request $request)
    {
        $userId = $this->userId();
        if (!$userId) return response()->json(['message' => 'Not authenticated.'], 401);

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
                'flashcards'  => [],
            ],
            'decks'  => $this->loadDecks($userId),
        ]);
    }

    public function destroyDeck(string $id)
    {
        $userId = $this->userId();
        if (!$userId) return response()->json(['message' => 'Not authenticated.'], 401);

        $deck = DB::table('focus_flashcard_decks')
            ->where('id', $id)->where('user_id', $userId)->first();

        if (!$deck) return response()->json(['message' => 'Deck not found.'], 404);

        DB::table('focus_flashcards')->where('deck_id', $id)->where('user_id', $userId)->delete();
        DB::table('focus_flashcard_decks')->where('id', $id)->where('user_id', $userId)->delete();

        return response()->json(['status' => 'ok', 'decks' => $this->loadDecks($userId)]);
    }

    // ─────────────────────────────────────────────────────────
    //  Flashcards  (unchanged)
    // ─────────────────────────────────────────────────────────

    public function storeFlashcard(Request $request)
    {
        $userId = $this->userId();
        if (!$userId) return response()->json(['message' => 'Not authenticated.'], 401);

        $validated = $request->validate([
            'deck_id'  => 'required|string',
            'question' => 'required|string|min:1|max:400',
            'answer'   => 'required|string|min:1|max:1000',
        ]);

        $deck = DB::table('focus_flashcard_decks')
            ->where('id', $validated['deck_id'])->where('user_id', $userId)->first();

        if (!$deck) return response()->json(['message' => 'Deck not found.'], 404);

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

    public function destroyFlashcard(string $id)
    {
        $userId = $this->userId();
        if (!$userId) return response()->json(['message' => 'Not authenticated.'], 401);

        $card = DB::table('focus_flashcards')
            ->where('id', $id)->where('user_id', $userId)->first();

        if (!$card) return response()->json(['message' => 'Flashcard not found.'], 404);

        $deckId = $card->deck_id;
        DB::table('focus_flashcards')->where('id', $id)->where('user_id', $userId)->delete();

        $remaining = DB::table('focus_flashcards')
            ->where('user_id', $userId)
            ->where('deck_id', $deckId)
            ->orderBy('created_at', 'asc')
            ->get(['id', 'deck_id', 'question', 'answer', 'created_at'])
            ->map(fn ($row) => (array) $row)
            ->toArray();

        return response()->json(['status' => 'ok', 'flashcards' => $remaining]);
    }

    // ─────────────────────────────────────────────────────────
    //  Quiz Sets  (NEW)
    // ─────────────────────────────────────────────────────────

    /**
     * Create a new quiz set.
     * POST /focus-mode/quiz-sets
     */
    public function storeQuizSet(Request $request)
    {
        $userId = $this->userId();
        if (!$userId) return response()->json(['message' => 'Not authenticated.'], 401);

        $validated = $request->validate([
            'title'       => 'required|string|min:1|max:120',
            'description' => 'nullable|string|max:250',
        ]);

        $id  = (string) Str::uuid();
        $now = now()->toDateTimeString();

        DB::table('focus_quiz_sets')->insert([
            'id'          => $id,
            'user_id'     => $userId,
            'title'       => trim($validated['title']),
            'description' => trim((string) ($validated['description'] ?? '')),
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        return response()->json([
            'status'    => 'ok',
            'quiz_set'  => [
                'id'          => $id,
                'title'       => trim($validated['title']),
                'description' => trim((string) ($validated['description'] ?? '')),
                'created_at'  => $now,
                'questions'   => [],  // brand-new set has no questions yet
            ],
            'quiz_sets' => $this->loadQuizSets($userId),
        ]);
    }

    /**
     * Delete a quiz set and all its questions.
     * DELETE /focus-mode/quiz-sets/{id}
     */
    public function destroyQuizSet(string $id)
    {
        $userId = $this->userId();
        if (!$userId) return response()->json(['message' => 'Not authenticated.'], 401);

        $set = DB::table('focus_quiz_sets')
            ->where('id', $id)->where('user_id', $userId)->first();

        if (!$set) return response()->json(['message' => 'Quiz set not found.'], 404);

        // Delete all questions in the set, then the set itself
        DB::table('focus_quizzes')
            ->where('quiz_set_id', $id)
            ->where('user_id', $userId)
            ->delete();

        DB::table('focus_quiz_sets')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json([
            'status'    => 'ok',
            'quiz_sets' => $this->loadQuizSets($userId),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  Quizzes  (updated — now scoped to a quiz set)
    // ─────────────────────────────────────────────────────────

    /**
     * Store a quiz question.
     * Now accepts quiz_set_id and returns the set's questions.
     * POST /focus-mode/quizzes
     */
    public function storeQuiz(Request $request)
    {
        $userId = $this->userId();
        if (!$userId) return response()->json(['message' => 'Not authenticated.'], 401);

        $validated = $request->validate([
            'quiz_set_id'    => 'nullable|string',
            'question'       => 'required|string|min:1|max:500',
            'option_a'       => 'required|string|min:1|max:400',
            'option_b'       => 'required|string|min:1|max:400',
            'option_c'       => 'required|string|min:1|max:400',
            'option_d'       => 'required|string|min:1|max:400',
            'correct_option' => 'required|string|in:A,B,C,D',
            'explanation'    => 'nullable|string|max:1000',
        ]);

        $quizSetId = $validated['quiz_set_id'] ?? null;

        // If a set ID was supplied, verify it belongs to this user
        if ($quizSetId) {
            $set = DB::table('focus_quiz_sets')
                ->where('id', $quizSetId)
                ->where('user_id', $userId)
                ->first();

            if (!$set) {
                return response()->json(['message' => 'Quiz set not found.'], 404);
            }
        }

        $id  = (string) Str::uuid();
        $now = now()->toDateTimeString();

        DB::table('focus_quizzes')->insert([
            'id'             => $id,
            'user_id'        => $userId,
            'quiz_set_id'    => $quizSetId,
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
            'quiz_set_id'    => $quizSetId,
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

        // If scoped to a set, return only that set's questions (what the slider needs)
        if ($quizSetId) {
            $setQuestions = DB::table('focus_quizzes')
                ->where('user_id', $userId)
                ->where('quiz_set_id', $quizSetId)
                ->orderBy('created_at', 'asc')
                ->get(['id', 'quiz_set_id', 'question', 'option_a', 'option_b',
                       'option_c', 'option_d', 'correct_option', 'explanation', 'created_at'])
                ->map(function ($row) {
                    return [
                        'id'             => $row->id,
                        'quiz_set_id'    => $row->quiz_set_id,
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

            return response()->json([
                'status'    => 'ok',
                'quiz'      => $quiz,
                'questions' => $setQuestions,  // JS updates the active set's question list
            ]);
        }

        // Fallback: no set — return all global quizzes (backward compat)
        return response()->json([
            'status'  => 'ok',
            'quiz'    => $quiz,
            'quizzes' => $this->loadQuizzes($userId),
        ]);
    }

    /**
     * Delete a quiz question — owner only.
     * DELETE /focus-mode/quizzes/{id}
     */
    public function destroyQuiz(string $id)
    {
        $userId = $this->userId();
        if (!$userId) return response()->json(['message' => 'Not authenticated.'], 401);

        $quiz = DB::table('focus_quizzes')
            ->where('id', $id)->where('user_id', $userId)->first();

        if (!$quiz) return response()->json(['message' => 'Quiz question not found.'], 404);

        $quizSetId = $quiz->quiz_set_id ?? null;
        DB::table('focus_quizzes')->where('id', $id)->where('user_id', $userId)->delete();

        if ($quizSetId) {
            $remaining = DB::table('focus_quizzes')
                ->where('user_id', $userId)
                ->where('quiz_set_id', $quizSetId)
                ->orderBy('created_at', 'asc')
                ->get(['id', 'quiz_set_id', 'question', 'option_a', 'option_b',
                       'option_c', 'option_d', 'correct_option', 'explanation', 'created_at'])
                ->map(function ($row) {
                    return [
                        'id'             => $row->id,
                        'quiz_set_id'    => $row->quiz_set_id,
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

            return response()->json(['status' => 'ok', 'questions' => $remaining]);
        }

        return response()->json([
            'status'  => 'ok',
            'quizzes' => $this->loadQuizzes($userId),
        ]);
    }
}

