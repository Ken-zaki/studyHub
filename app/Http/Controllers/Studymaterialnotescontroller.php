<?php

namespace App\Http\Controllers;

use App\Models\StudyMaterialNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudyMaterialNotesController extends Controller
{
    /**
     * GET /focus-mode/materials/{materialId}/notes
     * Return the note for one material.
     */
    public function show(int $materialId): JsonResponse
    {
        $note = StudyMaterialNote::where('user_id', Auth::id())
            ->where('study_material_id', $materialId)
            ->first();

        return response()->json([
            'content' => $note?->content ?? '',
        ]);
    }

    /**
     * POST /focus-mode/materials/{materialId}/notes
     * Upsert the note for one material.
     */
    public function save(Request $request, int $materialId): JsonResponse
    {
        $request->validate([
            'content' => 'nullable|string|max:65535',
        ]);

        $note = StudyMaterialNote::updateOrCreate(
            [
                'user_id'            => Auth::id(),
                'study_material_id'  => $materialId,
            ],
            [
                'content' => $request->input('content', ''),
            ]
        );

        return response()->json([
            'ok'      => true,
            'content' => $note->content,
        ]);
    }
}