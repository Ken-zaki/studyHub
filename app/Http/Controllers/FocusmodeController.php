<?php
// ─────────────────────────────────────────────────────────────
//  FocusModeController.php
//  Place at: app/Http/Controllers/FocusModeController.php
//
//  REQUIRES these migrations (see bottom of this file):
//    - focus_materials
//    - focus_flashcard_decks
//    - focus_flashcards
//    - focus_quiz_sets
//    - focus_quizzes
//    - focus_sessions
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

    /**
     * Returns the current authenticated user ID from session,
     * or aborts with 401.
     */
    private function userId(): string
    {
        $id = (string) session('user_id', '');
        if ($id === '') {
            abort(401, 'Not authenticated.');
        }
        return $id;
    }

    /**
     * Find a material row from the DB by its UUID and the current user.
     * Returns the row as a plain object, or null if not found / not owned.
     */
    private function findMaterial(string $id): ?object
    {
        return DB::table('focus_materials')
            ->where('id', $id)
            ->where('user_id', $this->userId())
            ->first();
    }

    /**
     * Convert a DB material row into the shape the JS front-end expects.
     */
    private function materialToArray(object $m): array
    {
        return [
            'id'          => $m->id,
            'name'        => $m->name,
            'type'        => $m->type,
            'screen'      => $m->screen,
            'uploaded_at' => $m->created_at,
            // same-origin URL so the iframe viewer works without Google Docs proxy
            'url'         => url('/focus-mode/materials/' . $m->id . '/file'),
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Pages
    // ─────────────────────────────────────────────────────────

    /**
     * Display the Focus Mode page.
     * All data now comes from the DB, keyed by user_id.
     */
    public function index()
    {
        $userId = $this->userId();

        // ── Materials ──────────────────────────────────────────
        $materials = DB::table('focus_materials')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($m) => $this->materialToArray($m))
            ->values()
            ->all();

        // ── Flashcard decks + cards ────────────────────────────
        $decks = DB::table('focus_flashcard_decks')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($d) => (array) $d)
            ->values()
            ->all();

        $flashcards = DB::table('focus_flashcards')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($f) => (array) $f)
            ->values()
            ->all();

        // ── Quiz sets + questions ──────────────────────────────
        $quizSets = DB::table('focus_quiz_sets')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($s) => (array) $s)
            ->values()
            ->all();

        $quizzes = DB::table('focus_quizzes')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($q) => (array) $q)
            ->values()
            ->all();

        // ── Tracker aggregates ─────────────────────────────────
        $trackerRow = DB::table('focus_sessions')
            ->where('user_id', $userId)
            ->selectRaw('COUNT(*) as session_count, COALESCE(SUM(duration_seconds), 0) as total_seconds')
            ->first();

        return view('home.focus-mode', [
            'materials'           => $materials,
            'flashcards'          => $flashcards,
            'decks'               => $decks,
            'quizSets'            => $quizSets,
            'quizzes'             => $quizzes,
            'trackerSessionCount' => (int) ($trackerRow->session_count  ?? 0),
            'trackerTotalSeconds' => (int) ($trackerRow->total_seconds  ?? 0),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  Focus Sessions
    // ─────────────────────────────────────────────────────────

    /**
     * Save a completed focus session to the DB.
     * Called via AJAX when the user turns off Focus Mode or leaves.
     */
    public function storeSession(Request $request)
    {
        $validated = $request->validate([
            'duration' => 'required|integer|min:1|max:86400',
        ]);

        $userId = session('user_id');
        if (!$userId) {
            return response()->json(['message' => 'Missing session user id.'], 422);
        }

        DB::table('focus_sessions')->insert([
            'id'               => (string) Str::uuid(),
            'user_id'          => $userId,
            'duration_seconds' => (int) $validated['duration'],
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    // ─────────────────────────────────────────────────────────
    //  Materials — CRUD
    // ─────────────────────────────────────────────────────────

    /**
     * Upload a study material and persist the record to the DB.
     * Route: POST /focus-mode/materials
     */
    public function storeMaterial(Request $request)
    {
        $validated = $request->validate([
            'material' => 'required|file|mimes:pdf,doc,docx,ppt,pptx|max:20480',
            'screen'   => 'required|string|in:screenReview,screenFlashcard,screenQuiz',
        ]);

        $userId   = $this->userId();
        $file     = $request->file('material');
        $original = $file->getClientOriginalName();
        $safeName = Str::slug(pathinfo($original, PATHINFO_FILENAME));
        $ext      = strtolower($file->getClientOriginalExtension());
        $fileName = now()->format('YmdHis') . '_' . $safeName . '_' . Str::random(8) . '.' . $ext;

        $directory = public_path('uploads/focus-mode/' . $userId);
        File::ensureDirectoryExists($directory);
        $file->move($directory, $fileName);

        $relativePath = 'uploads/focus-mode/' . $userId . '/' . $fileName;
        $id           = (string) Str::uuid();

        DB::table('focus_materials')->insert([
            'id'         => $id,
            'user_id'    => $userId,
            'screen'     => $validated['screen'],
            'name'       => $original,
            'file_path'  => $relativePath,
            'type'       => $ext,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $material = $this->materialToArray(
            DB::table('focus_materials')->where('id', $id)->first()
        );

        return response()->json([
            'status'   => 'ok',
            'material' => $material,
        ]);
    }

    /**
     * Serve the raw file for a material (same-origin streaming).
     * Route: GET /focus-mode/materials/{id}/file
     */
    public function serveMaterial(string $id)
    {
        $material = $this->findMaterial($id);

        if (!$material) {
            abort(404, 'Material not found.');
        }

        $path = public_path($material->file_path ?? '');

        if (!$path || !file_exists($path)) {
            abort(404, 'File not found on disk.');
        }

        return response()->file($path);
    }

    /**
     * Delete a material from the DB and from disk.
     * Route: DELETE /focus-mode/materials/{id}
     */
    public function destroyMaterial(string $id)
    {
        $material = $this->findMaterial($id);

        if (!$material) {
            return response()->json(['message' => 'Material not found.'], 404);
        }

        // Delete file from disk
        if (!empty($material->file_path)) {
            $fullPath = public_path($material->file_path);
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        // Delete notes and the material row — cascade handles notes if FK set up
        DB::table('focus_material_notes')
            ->where('material_id', $id)
            ->delete();

        DB::table('focus_materials')
            ->where('id', $id)
            ->where('user_id', $this->userId())
            ->delete();

        return response()->json(['status' => 'ok']);
    }

    // ─────────────────────────────────────────────────────────
    //  Notes
    // ─────────────────────────────────────────────────────────

    /**
     * Return the saved notes for a material.
     * Route: GET /focus-mode/materials/{id}/notes
     *
     * BUG FIXED: was reading from DB but writing to session —
     * now both read and write go to focus_material_notes table.
     */
    public function showNote(string $id)
    {
        $userId   = $this->userId();
        $material = $this->findMaterial($id);

        if (!$material) {
            return response()->json(['message' => 'Material not found.'], 404);
        }

        $row = DB::table('focus_material_notes')
            ->where('user_id', $userId)
            ->where('material_id', $id)
            ->first();

        return response()->json([
            'status'  => 'ok',
            'content' => $row ? (string) $row->content : '',
        ]);
    }

    /**
     * Create or update the notes for a material.
     * Route: POST /focus-mode/materials/{id}/notes
     *
     * BUG FIXED: was writing to session('focus_material_notes') while
     * showNote() was reading from the DB — they never agreed on storage.
     * Now both use the DB.
     */
    public function saveNote(Request $request, string $id)
    {
        $userId   = $this->userId();
        $material = $this->findMaterial($id);

        if (!$material) {
            return response()->json(['message' => 'Material not found.'], 404);
        }

        $validated = $request->validate([
            'content' => 'present|nullable|string|max:65535',
        ]);

        DB::table('focus_material_notes')->upsert(
            [
                'user_id'     => $userId,
                'material_id' => $id,
                'content'     => $validated['content'] ?? '',
                'updated_at'  => now(),
                'created_at'  => now(),
            ],
            ['user_id', 'material_id'],   // unique key columns
            ['content', 'updated_at']     // columns to update on conflict
        );

        return response()->json(['status' => 'ok']);
    }

    // ─────────────────────────────────────────────────────────
    //  Flashcards & Decks
    // ─────────────────────────────────────────────────────────

    /**
     * Store a manually typed flashcard.
     * Route: POST /focus-mode/flashcards
     */
    public function storeFlashcard(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string|min:1|max:400',
            'answer'   => 'required|string|min:1|max:1000',
            'deck_id'  => 'nullable|string',
        ]);

        $userId = $this->userId();
        $id     = (string) Str::uuid();

        DB::table('focus_flashcards')->insert([
            'id'         => $id,
            'user_id'    => $userId,
            'deck_id'    => $validated['deck_id'] ?? null,
            'question'   => trim($validated['question']),
            'answer'     => trim($validated['answer']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $flashcard = (array) DB::table('focus_flashcards')->where('id', $id)->first();

        return response()->json([
            'status'    => 'ok',
            'flashcard' => $flashcard,
        ]);
    }

    /**
     * Delete a flashcard.
     * Route: DELETE /focus-mode/flashcards/{id}
     */
    public function destroyFlashcard(string $id)
    {
        DB::table('focus_flashcards')
            ->where('id', $id)
            ->where('user_id', $this->userId())
            ->delete();

        return response()->json(['status' => 'ok']);
    }

    /**
     * Create a flashcard deck.
     * Route: POST /focus-mode/decks
     */
    public function storeDeck(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:1|max:120',
        ]);

        $userId = $this->userId();
        $id     = (string) Str::uuid();

        DB::table('focus_flashcard_decks')->insert([
            'id'         => $id,
            'user_id'    => $userId,
            'name'       => trim($validated['name']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deck = (array) DB::table('focus_flashcard_decks')->where('id', $id)->first();

        return response()->json([
            'status' => 'ok',
            'deck'   => $deck,
        ]);
    }

    /**
     * Delete a deck and all its flashcards.
     * Route: DELETE /focus-mode/decks/{id}
     */
    public function destroyDeck(string $id)
    {
        $userId = $this->userId();

        DB::table('focus_flashcards')
            ->where('user_id', $userId)
            ->where('deck_id', $id)
            ->delete();

        DB::table('focus_flashcard_decks')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json(['status' => 'ok']);
    }

    // ─────────────────────────────────────────────────────────
    //  Quiz Sets & Quizzes
    // ─────────────────────────────────────────────────────────

    /**
     * Create a quiz set.
     * Route: POST /focus-mode/quiz-sets
     */
    public function storeQuizSet(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:1|max:120',
        ]);

        $userId = $this->userId();
        $id     = (string) Str::uuid();

        DB::table('focus_quiz_sets')->insert([
            'id'         => $id,
            'user_id'    => $userId,
            'name'       => trim($validated['name']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $set = (array) DB::table('focus_quiz_sets')->where('id', $id)->first();

        return response()->json([
            'status'   => 'ok',
            'quiz_set' => $set,
        ]);
    }

    /**
     * Delete a quiz set and all its questions.
     * Route: DELETE /focus-mode/quiz-sets/{id}
     */
    public function destroyQuizSet(string $id)
    {
        $userId = $this->userId();

        DB::table('focus_quizzes')
            ->where('user_id', $userId)
            ->where('quiz_set_id', $id)
            ->delete();

        DB::table('focus_quiz_sets')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->delete();

        return response()->json(['status' => 'ok']);
    }

    /**
     * Store a quiz question.
     * Route: POST /focus-mode/quizzes
     */
    public function storeQuiz(Request $request)
    {
        $validated = $request->validate([
            'quiz_set_id'    => 'nullable|string',
            'question'       => 'required|string|min:1|max:500',
            'option_a'       => 'required|string|min:1|max:400',
            'option_b'       => 'required|string|min:1|max:400',
            'option_c'       => 'required|string|min:1|max:400',
            'option_d'       => 'required|string|min:1|max:400',
            'correct_option' => 'required|in:A,B,C,D',
            'explanation'    => 'nullable|string|max:1000',
        ]);

        $userId = $this->userId();
        $id     = (string) Str::uuid();

        DB::table('focus_quizzes')->insert([
            'id'             => $id,
            'user_id'        => $userId,
            'quiz_set_id'    => $validated['quiz_set_id'] ?? null,
            'question'       => trim($validated['question']),
            'option_a'       => trim($validated['option_a']),
            'option_b'       => trim($validated['option_b']),
            'option_c'       => trim($validated['option_c']),
            'option_d'       => trim($validated['option_d']),
            'correct_option' => $validated['correct_option'],
            'explanation'    => trim($validated['explanation'] ?? ''),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $quiz = (array) DB::table('focus_quizzes')->where('id', $id)->first();

        return response()->json([
            'status' => 'ok',
            'quiz'   => $quiz,
        ]);
    }

    /**
     * Delete a quiz question.
     * Route: DELETE /focus-mode/quizzes/{id}
     */
    public function destroyQuiz(string $id)
    {
        DB::table('focus_quizzes')
            ->where('id', $id)
            ->where('user_id', $this->userId())
            ->delete();

        return response()->json(['status' => 'ok']);
    }

    // ─────────────────────────────────────────────────────────
    //  Music
    // ─────────────────────────────────────────────────────────

    /**
     * Stream background music.
     * Route: GET /focus-mode/music/stream
     */
    public function streamMusic()
    {
        $path = public_path('audio/relaxing-music.mp3');

        if (!file_exists($path)) {
            abort(404, 'Music file not found.');
        }

        return response()->file($path, [
            'Content-Type'  => 'audio/mpeg',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * Return available music tracks.
     * Route: GET /focus-mode/tracks
     */
    public function tracks()
    {
        $tracks = [
            ['id' => 1, 'label' => 'Focus Music', 'url' => url('/focus-mode/music/stream')],
        ];

        return response()->json(['tracks' => $tracks]);
    }

    /**
     * Set the active music track.
     * Route: POST /focus-mode/music/track
     */
    public function setActiveTrack(Request $request)
    {
        $validated = $request->validate([
            'track_id' => 'required|integer',
        ]);

        // Track preference is UI-only, session is fine here
        session(['focus_active_track' => $validated['track_id']]);

        return response()->json(['status' => 'ok']);
    }
}


/*
 ═══════════════════════════════════════════════════════════════════
  REQUIRED MIGRATIONS
  Create these files in database/migrations/ and run:
    php artisan migrate
 ═══════════════════════════════════════════════════════════════════

──────────────────────────────────────────────────────────────────
  xxxx_create_focus_materials_table.php
──────────────────────────────────────────────────────────────────
    Schema::create('focus_materials', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('user_id');          // matches session('user_id')
        $table->string('screen');
        $table->string('name');
        $table->string('file_path');
        $table->string('type', 10);
        $table->timestamps();
        $table->index('user_id');
    });

──────────────────────────────────────────────────────────────────
  xxxx_create_focus_material_notes_table.php
──────────────────────────────────────────────────────────────────
    Schema::create('focus_material_notes', function (Blueprint $table) {
        $table->id();
        $table->string('user_id');
        $table->uuid('material_id');
        $table->longText('content')->nullable();
        $table->timestamps();
        $table->unique(['user_id', 'material_id']);
        $table->index('material_id');
    });

──────────────────────────────────────────────────────────────────
  xxxx_create_focus_flashcard_decks_table.php
──────────────────────────────────────────────────────────────────
    Schema::create('focus_flashcard_decks', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('user_id');
        $table->string('name', 120);
        $table->timestamps();
        $table->index('user_id');
    });

──────────────────────────────────────────────────────────────────
  xxxx_create_focus_flashcards_table.php
──────────────────────────────────────────────────────────────────
    Schema::create('focus_flashcards', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('user_id');
        $table->uuid('deck_id')->nullable();
        $table->string('question', 400);
        $table->text('answer');
        $table->timestamps();
        $table->index('user_id');
        $table->index('deck_id');
    });

──────────────────────────────────────────────────────────────────
  xxxx_create_focus_quiz_sets_table.php
──────────────────────────────────────────────────────────────────
    Schema::create('focus_quiz_sets', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('user_id');
        $table->string('name', 120);
        $table->timestamps();
        $table->index('user_id');
    });

──────────────────────────────────────────────────────────────────
  xxxx_create_focus_quizzes_table.php
──────────────────────────────────────────────────────────────────
    Schema::create('focus_quizzes', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('user_id');
        $table->uuid('quiz_set_id')->nullable();
        $table->string('question', 500);
        $table->string('option_a', 400);
        $table->string('option_b', 400);
        $table->string('option_c', 400);
        $table->string('option_d', 400);
        $table->char('correct_option', 1);
        $table->text('explanation')->nullable();
        $table->timestamps();
        $table->index('user_id');
        $table->index('quiz_set_id');
    });

──────────────────────────────────────────────────────────────────
  xxxx_create_focus_sessions_table.php
──────────────────────────────────────────────────────────────────
    Schema::create('focus_sessions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('user_id');
        $table->unsignedInteger('duration_seconds');
        $table->timestamps();
        $table->index('user_id');
    });
*/