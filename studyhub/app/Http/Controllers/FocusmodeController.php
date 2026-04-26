<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FocusModeController extends Controller
{
    /**
     * Display the Focus Mode page.
     */
    public function index()
    {
        return view('home.focus-mode', [
            'activeNav'  => 'focus-mode',          // ← for shared sidebar highlight
            'materials'  => session('focus_materials',  []),
            'flashcards' => session('focus_flashcards', []),
        ]);
    }

    /**
     * Save a completed focus session (AJAX).
     */
    public function storeSession(Request $request)
    {
        $validated = $request->validate([
            'duration' => 'required|integer|min:1|max:86400',
        ]);

        $userId = session('user_id');
        if (!$userId) {
            return response()->json(['message' => 'Not authenticated.'], 422);
        }

        // Insert directly via Supabase REST API (no Laravel model needed)
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'apikey'        => env('SUPABASE_SERVICE_KEY'),
            'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
            'Content-Type'  => 'application/json',
        ])->post(env('SUPABASE_URL') . '/rest/v1/focus_sessions', [
            'user_id'          => $userId,
            'duration_minutes' => (int) round($validated['duration'] / 60),
            'session_type'     => 'pomodoro',
        ]);

        return response()->json(['status' => $response->successful() ? 'ok' : 'error']);
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

        $userId    = session('user_id') ?: 'guest';
        $file      = $request->file('material');
        $origName  = $file->getClientOriginalName();
        $safeName  = Str::slug(pathinfo($origName, PATHINFO_FILENAME));
        $ext       = strtolower($file->getClientOriginalExtension());
        $fileName  = now()->format('YmdHis') . '_' . $safeName . '_' . Str::random(6) . '.' . $ext;
        $dir       = public_path('uploads/focus-mode/' . $userId);

        File::ensureDirectoryExists($dir);
        $file->move($dir, $fileName);

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

    /**
     * Store a manually typed flashcard.
     */
    public function storeFlashcard(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string|min:1|max:400',
            'answer'   => 'required|string|min:1|max:1000',
        ]);

        $flashcard = [
            'id'         => (string) Str::uuid(),
            'question'   => trim($validated['question']),
            'answer'     => trim($validated['answer']),
            'created_at' => now()->toDateTimeString(),
        ];

        $flashcards   = session('focus_flashcards', []);
        $flashcards[] = $flashcard;
        session(['focus_flashcards' => array_slice($flashcards, -200)]);

        return response()->json([
            'status'     => 'ok',
            'flashcard'  => $flashcard,
            'flashcards' => session('focus_flashcards', []),
        ]);
    }
}
