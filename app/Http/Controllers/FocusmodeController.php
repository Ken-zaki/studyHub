<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FocusModeController extends Controller
{
    // ─────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────

    /**
     * Return the current user's ID as a string.
     * Checks Laravel Auth first, then falls back to session('user_id').
     * This covers both standard Auth middleware and session-based auth.
     */
    private function userId(): ?string
    {
        // Standard Laravel Auth (works with Sanctum, session guard, etc.)
        if (Auth::check()) {
            return (string) Auth::id();
        }

        // Fallback: session-based user_id (used by original implementation)
        $id = session('user_id');
        return ($id && $id !== '') ? (string) $id : null;
    }

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
                ->map(fn ($row) => [
                    'id'             => $row->id,
                    'quiz_set_id'    => $row->quiz_set_id,
                    'question'       => $row->question,
                    'options'        => [
                        'A' => $row->option_a, 'B' => $row->option_b,
                        'C' => $row->option_c, 'D' => $row->option_d,
                    ],
                    'correct_option' => $row->correct_option,
                    'explanation'    => $row->explanation ?? '',
                    'created_at'     => $row->created_at,
                ])
                ->toArray();
        }
        unset($set);

        return $sets;
    }

    private function loadQuizzes(string $userId): array
    {
        return DB::table('focus_quizzes')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'asc')
            ->get(['id', 'question', 'option_a', 'option_b', 'option_c', 'option_d',
                   'correct_option', 'explanation', 'created_at'])
            ->map(fn ($row) => [
                'id'             => $row->id,
                'question'       => $row->question,
                'options'        => [
                    'A' => $row->option_a, 'B' => $row->option_b,
                    'C' => $row->option_c, 'D' => $row->option_d,
                ],
                'correct_option' => $row->correct_option,
                'explanation'    => $row->explanation ?? '',
                'created_at'     => $row->created_at,
            ])
            ->toArray();
    }

    private function loadMaterials(string $userId, string $screen = 'screenReview'): array
    {
        return DB::table('focus_materials')
            ->where('user_id', $userId)
            ->where('screen', $screen)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'file_path', 'type', 'screen', 'created_at'])
            ->map(fn ($row) => [
                'id'         => $row->id,
                'name'       => $row->name,
                'file_path'  => $row->file_path,
                'type'       => $row->type,
                'screen'     => $row->screen,
                'url'        => asset($row->file_path),
                'created_at' => $row->created_at,
            ])
            ->toArray();
    }

    // ─────────────────────────────────────────────────────────
    //  Main page
    // ─────────────────────────────────────────────────────────

    public function index()
    {
        $userId = $this->userId();

        $materials = $userId ? $this->loadMaterials($userId, 'screenReview') : [];
        $decks     = $userId ? $this->loadDecks($userId)    : [];
        $quizzes   = $userId ? $this->loadQuizzes($userId)  : [];
        $quizSets  = $userId ? $this->loadQuizSets($userId) : [];

        return view('home.focus-mode', compact('materials', 'decks', 'quizzes', 'quizSets'));
    }

    // ─────────────────────────────────────────────────────────
    //  Session tracking
    // ─────────────────────────────────────────────────────────

    public function storeSession(Request $request): JsonResponse
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

    // ─────────────────────────────────────────────────────────
    //  Materials
    // ─────────────────────────────────────────────────────────

    /**
     * Upload and store a study material.
     * POST /focus-mode/materials
     */
    public function storeMaterial(Request $request): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return response()->json(['message' => 'Not authenticated.'], 401);
        }

        $validated = $request->validate([
            'file'   => 'required|file|mimes:pdf,doc,docx,ppt,pptx|max:20480',
            'screen' => 'nullable|string|in:screenReview,screenFlashcard,screenQuiz',
        ]);

        $file     = $request->file('file');
        $origName = $file->getClientOriginalName();
        $safeName = Str::slug(pathinfo($origName, PATHINFO_FILENAME));
        $ext      = strtolower($file->getClientOriginalExtension());
        $fileName = now()->format('YmdHis') . '_' . $safeName . '_' . Str::random(8) . '.' . $ext;
        $screen   = $validated['screen'] ?? 'screenReview';

        $directory = 'uploads/focus-mode/' . $userId;
        File::ensureDirectoryExists(public_path($directory));
        $file->move(public_path($directory), $fileName);

        $filePath = $directory . '/' . $fileName;
        $id       = (string) Str::uuid();
        $now      = now()->toDateTimeString();

        DB::table('focus_materials')->insert([
            'id'         => $id,
            'user_id'    => $userId,
            'screen'     => $screen,
            'name'       => $origName,
            'file_path'  => $filePath,
            'type'       => $ext,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'status'   => 'ok',
            'material' => [
                'id'         => $id,
                'name'       => $origName,
                'file_path'  => $filePath,
                'type'       => $ext,
                'screen'     => $screen,
                'url'        => asset($filePath),
                'created_at' => $now,
            ],
        ]);
    }

    /**
     * Delete a study material and its notes.
     * DELETE /focus-mode/materials/{id}
     */
    public function destroyMaterial(string $id): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return response()->json(['message' => 'Not authenticated.'], 401);
        }

        $material = DB::table('focus_materials')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$material) {
            return response()->json(['message' => 'Material not found.'], 404);
        }

        // Delete physical file
        $fullPath = public_path($material->file_path);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        // Delete notes then DB record
        DB::table('focus_material_notes')
            ->where('study_material_id', $id)
            ->where('user_id', $userId)
            ->delete();

        DB::table('focus_materials')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json(['status' => 'ok']);
    }

    /**
     * Serve the raw file for the in-page viewer.
     * GET /focus-mode/materials/{id}/file
     */
    public function serveMaterial(string $id)
    {
        $userId = $this->userId();
        if (!$userId) {
            abort(401);
        }

        $material = DB::table('focus_materials')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        abort_if(!$material, 404);

        $path = public_path($material->file_path);
        abort_unless(file_exists($path), 404);

        $mime = mime_content_type($path) ?: 'application/octet-stream';

        return response()->file($path, [
            'Content-Type'              => $mime,
            'Content-Disposition'       => 'inline; filename="' . basename($path) . '"',
            // Allow Google Docs Viewer to embed this file
            'Access-Control-Allow-Origin' => 'https://docs.google.com',
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  Notes
    // ─────────────────────────────────────────────────────────

    /**
     * GET /focus-mode/materials/{id}/notes
     */
    public function showNote(string $id): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return response()->json(['message' => 'Not authenticated.'], 401);
        }

        $note = DB::table('focus_material_notes')
            ->where('user_id', $userId)
            ->where('study_material_id', $id)
            ->first();

        return response()->json(['content' => $note?->content ?? '']);
    }

    /**
     * POST /focus-mode/materials/{id}/notes
     */
    public function saveNote(Request $request, string $id): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) {
            return response()->json(['message' => 'Not authenticated.'], 401);
        }

        $validated = $request->validate([
            'content' => 'nullable|string|max:65535',
        ]);

        $content = $validated['content'] ?? '';
        $now     = now()->toDateTimeString();

        $exists = DB::table('focus_material_notes')
            ->where('user_id', $userId)
            ->where('study_material_id', $id)
            ->exists();

        if ($exists) {
            DB::table('focus_material_notes')
                ->where('user_id', $userId)
                ->where('study_material_id', $id)
                ->update(['content' => $content, 'updated_at' => $now]);
        } else {
            DB::table('focus_material_notes')->insert([
                'user_id'           => $userId,
                'study_material_id' => $id,
                'content'           => $content,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        }

        return response()->json(['ok' => true, 'content' => $content]);
    }

    // ─────────────────────────────────────────────────────────
    //  Decks
    // ─────────────────────────────────────────────────────────

    public function storeDeck(Request $request): JsonResponse
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
            'deck'   => ['id' => $id, 'name' => trim($validated['name']),
                         'description' => trim((string)($validated['description'] ?? '')),
                         'created_at' => $now, 'flashcards' => []],
            'decks'  => $this->loadDecks($userId),
        ]);
    }

    public function destroyDeck(string $id): JsonResponse
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
    //  Flashcards
    // ─────────────────────────────────────────────────────────

    public function storeFlashcard(Request $request): JsonResponse
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
            'id'         => $id, 'user_id'  => $userId,
            'deck_id'    => $validated['deck_id'],
            'question'   => trim($validated['question']),
            'answer'     => trim($validated['answer']),
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $deckFlashcards = DB::table('focus_flashcards')
            ->where('user_id', $userId)->where('deck_id', $validated['deck_id'])
            ->orderBy('created_at', 'asc')
            ->get(['id', 'deck_id', 'question', 'answer', 'created_at'])
            ->map(fn ($r) => (array) $r)->toArray();

        return response()->json([
            'status'     => 'ok',
            'flashcard'  => ['id' => $id, 'deck_id' => $validated['deck_id'],
                             'question' => trim($validated['question']),
                             'answer'   => trim($validated['answer']), 'created_at' => $now],
            'flashcards' => $deckFlashcards,
        ]);
    }

    public function destroyFlashcard(string $id): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) return response()->json(['message' => 'Not authenticated.'], 401);

        $card = DB::table('focus_flashcards')
            ->where('id', $id)->where('user_id', $userId)->first();
        if (!$card) return response()->json(['message' => 'Flashcard not found.'], 404);

        $deckId = $card->deck_id;
        DB::table('focus_flashcards')->where('id', $id)->where('user_id', $userId)->delete();

        $remaining = DB::table('focus_flashcards')
            ->where('user_id', $userId)->where('deck_id', $deckId)
            ->orderBy('created_at', 'asc')
            ->get(['id', 'deck_id', 'question', 'answer', 'created_at'])
            ->map(fn ($r) => (array) $r)->toArray();

        return response()->json(['status' => 'ok', 'flashcards' => $remaining]);
    }

    // ─────────────────────────────────────────────────────────
    //  Quiz Sets
    // ─────────────────────────────────────────────────────────

    public function storeQuizSet(Request $request): JsonResponse
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
            'id'          => $id, 'user_id' => $userId,
            'title'       => trim($validated['title']),
            'description' => trim((string)($validated['description'] ?? '')),
            'created_at'  => $now, 'updated_at' => $now,
        ]);

        return response()->json([
            'status'    => 'ok',
            'quiz_set'  => ['id' => $id, 'title' => trim($validated['title']),
                            'description' => trim((string)($validated['description'] ?? '')),
                            'created_at' => $now, 'questions' => []],
            'quiz_sets' => $this->loadQuizSets($userId),
        ]);
    }

    public function destroyQuizSet(string $id): JsonResponse
    {
        $userId = $this->userId();
        if (!$userId) return response()->json(['message' => 'Not authenticated.'], 401);

        $set = DB::table('focus_quiz_sets')
            ->where('id', $id)->where('user_id', $userId)->first();
        if (!$set) return response()->json(['message' => 'Quiz set not found.'], 404);

        DB::table('focus_quizzes')->where('quiz_set_id', $id)->where('user_id', $userId)->delete();
        DB::table('focus_quiz_sets')->where('id', $id)->where('user_id', $userId)->delete();

        return response()->json(['status' => 'ok', 'quiz_sets' => $this->loadQuizSets($userId)]);
    }

    // ─────────────────────────────────────────────────────────
    //  Quizzes
    // ─────────────────────────────────────────────────────────

    public function storeQuiz(Request $request): JsonResponse
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
        if ($quizSetId) {
            $set = DB::table('focus_quiz_sets')
                ->where('id', $quizSetId)->where('user_id', $userId)->first();
            if (!$set) return response()->json(['message' => 'Quiz set not found.'], 404);
        }

        $id  = (string) Str::uuid();
        $now = now()->toDateTimeString();

        DB::table('focus_quizzes')->insert([
            'id'             => $id, 'user_id' => $userId,
            'quiz_set_id'    => $quizSetId,
            'question'       => trim($validated['question']),
            'option_a'       => trim($validated['option_a']),
            'option_b'       => trim($validated['option_b']),
            'option_c'       => trim($validated['option_c']),
            'option_d'       => trim($validated['option_d']),
            'correct_option' => $validated['correct_option'],
            'explanation'    => trim((string)($validated['explanation'] ?? '')),
            'created_at'     => $now, 'updated_at' => $now,
        ]);

        $quiz = [
            'id' => $id, 'quiz_set_id' => $quizSetId,
            'question' => trim($validated['question']),
            'options'  => ['A' => trim($validated['option_a']), 'B' => trim($validated['option_b']),
                           'C' => trim($validated['option_c']), 'D' => trim($validated['option_d'])],
            'correct_option' => $validated['correct_option'],
            'explanation'    => trim((string)($validated['explanation'] ?? '')),
            'created_at'     => $now,
        ];

        if ($quizSetId) {
            $setQuestions = DB::table('focus_quizzes')
                ->where('user_id', $userId)->where('quiz_set_id', $quizSetId)
                ->orderBy('created_at', 'asc')
                ->get(['id', 'quiz_set_id', 'question', 'option_a', 'option_b',
                       'option_c', 'option_d', 'correct_option', 'explanation', 'created_at'])
                ->map(fn ($r) => [
                    'id' => $r->id, 'quiz_set_id' => $r->quiz_set_id, 'question' => $r->question,
                    'options' => ['A' => $r->option_a, 'B' => $r->option_b,
                                  'C' => $r->option_c, 'D' => $r->option_d],
                    'correct_option' => $r->correct_option,
                    'explanation'    => $r->explanation ?? '',
                    'created_at'     => $r->created_at,
                ])->toArray();

            return response()->json(['status' => 'ok', 'quiz' => $quiz, 'questions' => $setQuestions]);
        }

        return response()->json(['status' => 'ok', 'quiz' => $quiz, 'quizzes' => $this->loadQuizzes($userId)]);
    }

    public function destroyQuiz(string $id): JsonResponse
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
                ->where('user_id', $userId)->where('quiz_set_id', $quizSetId)
                ->orderBy('created_at', 'asc')
                ->get(['id', 'quiz_set_id', 'question', 'option_a', 'option_b',
                       'option_c', 'option_d', 'correct_option', 'explanation', 'created_at'])
                ->map(fn ($r) => [
                    'id' => $r->id, 'quiz_set_id' => $r->quiz_set_id, 'question' => $r->question,
                    'options' => ['A' => $r->option_a, 'B' => $r->option_b,
                                  'C' => $r->option_c, 'D' => $r->option_d],
                    'correct_option' => $r->correct_option,
                    'explanation'    => $r->explanation ?? '',
                    'created_at'     => $r->created_at,
                ])->toArray();

            return response()->json(['status' => 'ok', 'questions' => $remaining]);
        }

        return response()->json(['status' => 'ok', 'quizzes' => $this->loadQuizzes($userId)]);
    }
}