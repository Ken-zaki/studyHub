<?php
// ─────────────────────────────────────────────────────────────
//  FocusModeController.php
//  Place at: app/Http/Controllers/FocusModeController.php
// ─────────────────────────────────────────────────────────────
 
namespace App\Http\Controllers;
 
use Illuminate\Http\Request;
use App\Models\FocusSession;
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
            'materials' => session('focus_materials', []),
            'flashcards' => session('focus_flashcards', []),
        ]);
    }
 
    /**
     * Save a completed focus session (called via AJAX when
     * the user turns off Focus Mode or leaves the page).
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
 
        FocusSession::create([
            'user_id'          => $userId,
            'duration_seconds' => $validated['duration'],
        ]);
 
        return response()->json(['status' => 'ok']);
    }

    /**
     * Upload a study material file for the active focus session.
     */
    public function storeMaterial(Request $request)
    {
        $validated = $request->validate([
            'material' => 'required|file|mimes:pdf,doc,docx,ppt,pptx|max:20480',
            'screen' => 'required|string|in:screenReview,screenFlashcard,screenQuiz',
        ]);

        $userId = session('user_id') ?: 'guest';
        $file = $request->file('material');
        $originalName = $file->getClientOriginalName();
        $safeName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $extension = strtolower($file->getClientOriginalExtension());
        $fileName = now()->format('YmdHis') . '_' . $safeName . '_' . Str::random(8) . '.' . $extension;

        $directory = public_path('uploads/focus-mode/' . $userId);
        File::ensureDirectoryExists($directory);
        $file->move($directory, $fileName);

        $material = [
            'id' => (string) Str::uuid(),
            'screen' => $validated['screen'],
            'name' => $originalName,
            'url' => asset('uploads/focus-mode/' . $userId . '/' . $fileName),
            'type' => $extension,
            'uploaded_at' => now()->toDateTimeString(),
        ];

        $materials = session('focus_materials', []);
        $materials[] = $material;
        session(['focus_materials' => array_slice($materials, -20)]);

        return response()->json([
            'status' => 'ok',
            'material' => $material,
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
            'answer' => 'required|string|min:1|max:1000',
        ]);

        $flashcard = [
            'id' => (string) Str::uuid(),
            'question' => trim($validated['question']),
            'answer' => trim($validated['answer']),
            'created_at' => now()->toDateTimeString(),
        ];

        $flashcards = session('focus_flashcards', []);
        $flashcards[] = $flashcard;
        session(['focus_flashcards' => array_slice($flashcards, -200)]);

        return response()->json([
            'status' => 'ok',
            'flashcard' => $flashcard,
            'flashcards' => session('focus_flashcards', []),
        ]);
    }
}
 