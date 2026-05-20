<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResourceController extends Controller
{
    // ── Auth helper ──────────────────────────────────────────

    private function currentUserId(): string
    {
        return (string) session('user_id', '');
    }

    // ── Supabase helpers ─────────────────────────────────────

    private function sbUrl(): string
    {
        return rtrim(config('services.supabase.url'), '/');
    }

    private function sbServiceKey(): string
    {
        return config('services.supabase.service_key');
    }

    /**
     * Service-key query — bypasses RLS entirely.
     */
    private function sbQuery(string $table, array $params = []): array
    {
        $response = Http::withoutVerifying()
            ->withHeaders([
                'apikey'        => $this->sbServiceKey(),
                'Authorization' => 'Bearer ' . $this->sbServiceKey(),
                'Content-Type'  => 'application/json',
            ])->get($this->sbUrl() . '/rest/v1/' . $table, $params);

        if ($response->failed()) {
            Log::error('[Supabase] sbQuery failed', [
                'table'  => $table,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return [];
        }

        return $response->json() ?? [];
    }

    // ── Page ─────────────────────────────────────────────────

    /**
     * Show the resources page.
     */
    public function index()
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return redirect()->route('login');
        }

        return view('home.resources', [
            'activeNav' => 'resources',
        ]);
    }

    // ── List / search ─────────────────────────────────────────

    /**
     * Return paginated resources as JSON.
     * GET /api/resources?subject=&type=&visibility=public&search=&page=1&limit=20&is_curated=1
     */
    public function list(Request $request)
    {
        $userId     = $this->currentUserId();
        $subject    = $request->query('subject');
        $type       = $request->query('type');
        $visibility = $request->query('visibility', 'public');
        $search     = $request->query('search');
        $page       = max(1, (int) $request->query('page', 1));
        $limit      = min(40, max(1, (int) $request->query('limit', 20)));
        $offset     = ($page - 1) * $limit;

        $query = DB::table('resources')
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc');

        if ($visibility === 'public') {
            $query->where('visibility', 'public');
        } elseif ($visibility === 'private' && $userId !== '') {
            $following = DB::table('follows')
                ->where('follower_id', $userId)
                ->pluck('following_id');

            $query->where('visibility', 'private')
                  ->whereIn('uploaded_by', $following);
        }

        if ($subject) {
            $query->where('subject', $subject);
        }

        if ($type) {
            $query->where('file_type', $type);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('description', 'ilike', "%{$search}%")
                  ->orWhere('subject', 'ilike', "%{$search}%");
            });
        }

        // ── Curated filter (used by the Admin Curated section) ──
        if ($request->boolean('is_curated')) {
            $query->where('is_curated', true);
        }
        // ────────────────────────────────────────────────────────

        $total     = (clone $query)->count();
        $resources = $query->offset($offset)->limit($limit)->get();

        $ids = $resources->pluck('id')->toArray();

        $ratings = DB::table('resource_ratings')
            ->selectRaw('resource_id, AVG(rating)::numeric(3,2) as avg_rating, COUNT(*) as rating_count')
            ->whereIn('resource_id', $ids)
            ->groupBy('resource_id')
            ->get()
            ->keyBy('resource_id');

        // Fetch bookmarks for current user
        $bookmarkedIds = [];
        if ($userId !== '' && !empty($ids)) {
            $bookmarkedIds = DB::table('resource_bookmarks')
                ->where('user_id', $userId)
                ->whereIn('resource_id', $ids)
                ->pluck('resource_id')
                ->toArray();
        }

        $uploaderIds = $resources->pluck('uploaded_by')->unique()->filter()->values()->toArray();
        $profiles    = [];
        if (!empty($uploaderIds)) {
            $rows = $this->sbQuery('profiles', [
                'select' => 'id,first_name,last_name,username,profile_photo_url',
                'id'     => 'in.(' . implode(',', $uploaderIds) . ')',
            ]);
            foreach ($rows as $p) {
                $profiles[(string) $p['id']] = $p;
            }
        }

        $data = $resources->map(function ($r) use ($ratings, $profiles, $userId, $bookmarkedIds) {
            $ratingRow = $ratings[(string) $r->id] ?? null;
            $profile   = $profiles[(string) ($r->uploaded_by ?? '')] ?? null;

            $uploaderName = '';
            if ($profile) {
                $uploaderName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
                if ($uploaderName === '') {
                    $uploaderName = $profile['username'] ?? 'Unknown';
                }
            }

            return [
                'id'               => $r->id,
                'title'            => $r->title,
                'description'      => $r->description ?? null,
                'subject'          => $r->subject ?? null,
                'file_type'        => $r->file_type ?? null,
                'file_url'         => $r->file_url ?? null,
                'file_size'        => $r->file_size ?? null,
                'visibility'       => $r->visibility ?? 'public',
                'uploaded_by'      => $r->uploaded_by ?? null,
                'uploader_name'    => $uploaderName,
                'uploader_photo'   => $profile['profile_photo_url'] ?? null,
                'uploader_username'=> $profile['username'] ?? null,
                'avg_rating'       => $ratingRow ? (float) $ratingRow->avg_rating : null,
                'rating_count'     => $ratingRow ? (int) $ratingRow->rating_count : 0,
                'is_own'           => $userId !== '' && (string) ($r->uploaded_by ?? '') === $userId,
                'is_bookmarked'    => in_array((string) $r->id, array_map('strval', $bookmarkedIds)),
                'is_curated'       => (bool) ($r->is_curated ?? false), // ← ADDED
                'created_at'       => $r->created_at,
            ];
        });

        return response()->json([
            'data'  => $data,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * Top-rated resources for the newsfeed sidebar widget.
     * GET /api/resources/trending?limit=5
     */
    public function trending(Request $request)
    {
        $limit = min(10, max(1, (int) $request->query('limit', 5)));

        $rows = DB::table('resources as r')
            ->join(
                DB::raw('(
                    SELECT resource_id,
                           AVG(rating)::numeric(3,2) AS avg_rating,
                           COUNT(*)                  AS rating_count
                    FROM resource_ratings
                    GROUP BY resource_id
                    HAVING COUNT(*) >= 1
                ) AS agg'),
                'r.id', '=', 'agg.resource_id'
            )
            ->select(
                'r.id', 'r.title', 'r.subject', 'r.file_type',
                'agg.avg_rating', 'agg.rating_count'
            )
            ->where('r.is_approved', true)
            ->where('r.visibility', 'public')
            ->orderByDesc('agg.avg_rating')
            ->orderByDesc('agg.rating_count')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * Community-recommended (top-rated) resources with full details.
     * GET /api/resources/top-rated?limit=10&min_ratings=2
     */
    public function topRated(Request $request)
    {
        $userId     = $this->currentUserId();
        $limit      = min(20, max(1, (int) $request->query('limit', 10)));
        $minRatings = max(1, (int) $request->query('min_ratings', 1));

        $rows = DB::table('resources as r')
            ->join(
                DB::raw("(
                    SELECT resource_id,
                           AVG(rating)::numeric(3,2) AS avg_rating,
                           COUNT(*)                  AS rating_count
                    FROM resource_ratings
                    GROUP BY resource_id
                    HAVING COUNT(*) >= {$minRatings}
                ) AS agg"),
                'r.id', '=', 'agg.resource_id'
            )
            ->select(
                'r.id', 'r.title', 'r.description', 'r.subject',
                'r.file_type', 'r.file_url', 'r.file_size',
                'r.visibility', 'r.uploaded_by', 'r.created_at',
                'agg.avg_rating', 'agg.rating_count'
            )
            ->where('r.is_approved', true)
            ->where('r.visibility', 'public')
            ->orderByDesc('agg.avg_rating')
            ->orderByDesc('agg.rating_count')
            ->orderByDesc('r.created_at')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $ids         = $rows->pluck('id')->toArray();
        $uploaderIds = $rows->pluck('uploaded_by')->unique()->filter()->values()->toArray();

        // Fetch uploader profiles
        $profiles = [];
        if (!empty($uploaderIds)) {
            $fetched = $this->sbQuery('profiles', [
                'select' => 'id,first_name,last_name,username,profile_photo_url',
                'id'     => 'in.(' . implode(',', $uploaderIds) . ')',
            ]);
            foreach ($fetched as $p) {
                $profiles[(string) $p['id']] = $p;
            }
        }

        // Fetch current user's bookmarks
        $bookmarkedIds = [];
        if ($userId !== '') {
            $bookmarkedIds = DB::table('resource_bookmarks')
                ->where('user_id', $userId)
                ->whereIn('resource_id', $ids)
                ->pluck('resource_id')
                ->map(fn($id) => (string) $id)
                ->toArray();
        }

        $data = $rows->map(function ($r) use ($profiles, $userId, $bookmarkedIds) {
            $profile      = $profiles[(string) ($r->uploaded_by ?? '')] ?? null;
            $uploaderName = '';
            if ($profile) {
                $uploaderName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
                if ($uploaderName === '') {
                    $uploaderName = $profile['username'] ?? 'Unknown';
                }
            }

            return [
                'id'               => $r->id,
                'title'            => $r->title,
                'description'      => $r->description ?? null,
                'subject'          => $r->subject ?? null,
                'file_type'        => $r->file_type ?? null,
                'file_url'         => $r->file_url ?? null,
                'file_size'        => $r->file_size ?? null,
                'visibility'       => $r->visibility ?? 'public',
                'uploaded_by'      => $r->uploaded_by ?? null,
                'uploader_name'    => $uploaderName,
                'uploader_photo'   => $profile['profile_photo_url'] ?? null,
                'uploader_username'=> $profile['username'] ?? null,
                'avg_rating'       => (float) $r->avg_rating,
                'rating_count'     => (int) $r->rating_count,
                'is_own'           => $userId !== '' && (string) ($r->uploaded_by ?? '') === $userId,
                'is_bookmarked'    => in_array((string) $r->id, $bookmarkedIds),
                'created_at'       => $r->created_at,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Most active study groups for the newsfeed sidebar widget.
     * GET /api/study-groups/active?limit=4
     */
    public function activeGroups(Request $request)
    {
        $limit = min(10, max(1, (int) $request->query('limit', 4)));

        $rows = DB::table('study_groups as sg')
            ->leftJoin(
                DB::raw('(
                    SELECT group_id, COUNT(*) AS member_count
                    FROM study_group_members
                    GROUP BY group_id
                ) AS mc'),
                'sg.id', '=', 'mc.group_id'
            )
            ->leftJoin(
                DB::raw('(
                    SELECT group_id, MAX(created_at) AS last_message_at
                    FROM group_messages
                    GROUP BY group_id
                ) AS lm'),
                'sg.id', '=', 'lm.group_id'
            )
            ->select(
                'sg.id', 'sg.name', 'sg.subject',
                DB::raw('COALESCE(mc.member_count, 0) AS member_count'),
                'lm.last_message_at'
            )
            ->where('sg.is_public', true)
            ->orderByDesc('mc.member_count')
            ->orderByDesc('lm.last_message_at')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $rows]);
    }

    // ── Single resource ───────────────────────────────────────

    /**
     * Return a single resource with files, ratings summary, and uploader profile.
     * GET /api/resources/{id}
     */
    public function show(string $id)
    {
        $userId   = $this->currentUserId();
        $resource = DB::table('resources')->where('id', $id)->first();

        if (!$resource) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        if (!$resource->is_approved) {
            if ($userId === '' || (string) $resource->uploaded_by !== $userId) {
                return response()->json(['error' => 'Resource not available'], 403);
            }
        }

        $files = DB::table('resource_files')
            ->where('resource_id', $id)
            ->orderBy('created_at')
            ->get(['id', 'file_name', 'file_url', 'file_size']);

        $ratingRow = DB::table('resource_ratings')
            ->selectRaw('AVG(rating)::numeric(3,2) as avg_rating, COUNT(*) as rating_count')
            ->where('resource_id', $id)
            ->first();

        $myRating = null;
        if ($userId !== '') {
            $myRating = DB::table('resource_ratings')
                ->where('resource_id', $id)
                ->where('user_id', $userId)
                ->value('rating');
        }

        // Check if bookmarked
        $isBookmarked = false;
        if ($userId !== '') {
            $isBookmarked = DB::table('resource_bookmarks')
                ->where('resource_id', $id)
                ->where('user_id', $userId)
                ->exists();
        }

        $profile = [];
        if ($resource->uploaded_by) {
            $rows = $this->sbQuery('profiles', [
                'select' => 'id,first_name,last_name,username,profile_photo_url',
                'id'     => 'eq.' . $resource->uploaded_by,
            ]);
            $profile = $rows[0] ?? [];
        }

        $uploaderName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
        if ($uploaderName === '') {
            $uploaderName = $profile['username'] ?? 'Unknown';
        }

        return response()->json([
            'resource' => [
                'id'               => $resource->id,
                'title'            => $resource->title,
                'description'      => $resource->description ?? null,
                'content'          => $resource->content ?? null,
                'subject'          => $resource->subject ?? null,
                'file_type'        => $resource->file_type ?? null,
                'file_url'         => $resource->file_url ?? null,
                'file_size'        => $resource->file_size ?? null,
                'link_url'         => $resource->link_url ?? null,
                'visibility'       => $resource->visibility ?? 'public',
                'is_approved'      => (bool) $resource->is_approved,
                'is_curated'       => (bool) ($resource->is_curated ?? false),
                'uploaded_by'      => $resource->uploaded_by ?? null,
                'uploader_name'    => $uploaderName,
                'uploader_username'=> $profile['username'] ?? null,
                'uploader_photo'   => $profile['profile_photo_url'] ?? null,
                'is_own'           => $userId !== '' && (string) ($resource->uploaded_by ?? '') === $userId,
                'is_bookmarked'    => $isBookmarked,
                'avg_rating'       => $ratingRow ? (float) $ratingRow->avg_rating : null,
                'rating_count'     => $ratingRow ? (int) $ratingRow->rating_count : 0,
                'my_rating'        => $myRating ? (int) $myRating : null,
                'created_at'       => $resource->created_at,
                'updated_at'       => $resource->updated_at ?? null,
            ],
            'files' => $files,
        ]);
    }

    // ── Upload ────────────────────────────────────────────────

    /**
     * Upload a new resource (multipart/form-data).
     * POST /api/resources
     */
    public function store(Request $request)
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $request->validate([
                'title'       => 'required|string|max:255',
                'subject'     => 'required|string|max:100',
                'file_type'   => 'required|string|max:50',
                'description' => 'nullable|string|max:2000',
                'content'     => 'nullable|string',
                'link_url'    => 'nullable|url|max:2048',
                'visibility'  => 'nullable|in:public,private',
                'files'       => 'nullable|array|max:10',
                'files.*'     => 'file|max:51200',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Validation failed', 'errors' => $e->errors()], 422);
        }

        $resourceId = (string) Str::uuid();

        $primaryFileUrl   = null;
        $primaryFileSize  = null;
        $originalFilename = null;

        $uploadedFiles = $request->file('files', []);
        if (!is_array($uploadedFiles)) {
            $uploadedFiles = [$uploadedFiles];
        }

        if (!empty($uploadedFiles)) {
            $firstFile        = $uploadedFiles[0];
            $path             = $firstFile->store("resources/{$userId}", 'public');
            $primaryFileUrl   = asset('storage/' . $path);
            $primaryFileSize  = $firstFile->getSize();
            $originalFilename = $firstFile->getClientOriginalName();
        }

        DB::table('resources')->insert([
            'id'                => $resourceId,
            'uploaded_by'       => $userId,
            'title'             => $request->input('title'),
            'description'       => $request->input('description'),
            'content'           => $request->input('content'),
            'subject'           => $request->input('subject'),
            'file_type'         => $request->input('file_type'),
            'file_url'          => $primaryFileUrl ?? $request->input('link_url'),
            'link_url'          => $request->input('link_url'),
            'file_size'         => $primaryFileSize,
            'original_filename' => $originalFilename,
            'visibility'        => $request->input('visibility', 'public'),
            'is_approved'       => true,
            'is_curated'        => false,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        foreach ($uploadedFiles as $file) {
            $path    = $file->store("resources/{$userId}/files", 'public');
            $fileUrl = asset('storage/' . $path);

            DB::table('resource_files')->insert([
                'id'           => (string) Str::uuid(),
                'resource_id'  => $resourceId,
                'uploaded_by'  => $userId,
                'file_name'    => $file->getClientOriginalName(),
                'file_url'     => $fileUrl,
                'file_size'    => $file->getSize(),
                'storage_path' => $path,
                'created_at'   => now(),
            ]);
        }

        return response()->json([
            'success'     => true,
            'resource_id' => $resourceId,
        ], 201);
    }

    // ── Edit ─────────────────────────────────────────────────

    /**
     * Update a resource's metadata.
     * PUT /api/resources/{id}
     */
    public function update(Request $request, string $id)
    {
        $userId   = $this->currentUserId();
        $resource = DB::table('resources')->where('id', $id)->first();

        if (!$resource) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        if ((string) $resource->uploaded_by !== $userId) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
            'content'     => 'sometimes|nullable|string',
            'subject'     => 'sometimes|string|max:100',
            'file_type'   => 'sometimes|string|max:50',
            'visibility'  => 'sometimes|in:public,private',
            'link_url'    => 'sometimes|nullable|url|max:2048',
        ]);

        $fields = array_filter([
            'title'       => $request->input('title'),
            'description' => $request->input('description'),
            'content'     => $request->input('content'),
            'subject'     => $request->input('subject'),
            'file_type'   => $request->input('file_type'),
            'visibility'  => $request->input('visibility'),
            'link_url'    => $request->input('link_url'),
            'updated_at'  => now(),
        ], fn($v) => $v !== null);

        DB::table('resources')->where('id', $id)->update($fields);

        $newFiles = $request->file('files', []);
        if (!empty($newFiles)) {
            foreach ($newFiles as $file) {
                $path    = $file->store("resources/{$userId}/files", 'public');
                $fileUrl = asset('storage/' . $path);

                DB::table('resource_files')->insert([
                    'id'           => (string) Str::uuid(),
                    'resource_id'  => $id,
                    'uploaded_by'  => $userId,
                    'file_name'    => $file->getClientOriginalName(),
                    'file_url'     => $fileUrl,
                    'file_size'    => $file->getSize(),
                    'storage_path' => $path,
                    'created_at'   => now(),
                ]);
            }
        }

        $removeFileIds = $request->input('remove_file_ids', []);
        if (!empty($removeFileIds)) {
            $toDelete = DB::table('resource_files')
                ->where('resource_id', $id)
                ->whereIn('id', $removeFileIds)
                ->get(['id', 'storage_path']);

            foreach ($toDelete as $f) {
                if ($f->storage_path) {
                    Storage::disk('public')->delete($f->storage_path);
                }
            }
            DB::table('resource_files')
                ->where('resource_id', $id)
                ->whereIn('id', $removeFileIds)
                ->delete();
        }

        return response()->json(['success' => true]);
    }

    // ── Delete ────────────────────────────────────────────────

    /**
     * Delete a resource and its files.
     * DELETE /api/resources/{id}
     */
    public function destroy(string $id)
    {
        $userId   = $this->currentUserId();
        $resource = DB::table('resources')->where('id', $id)->first();

        if (!$resource) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        if ((string) $resource->uploaded_by !== $userId) {
            $role = session('user_role', '');
            if (!in_array($role, ['admin', 'moderator'])) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        $files = DB::table('resource_files')
            ->where('resource_id', $id)
            ->get(['storage_path']);

        foreach ($files as $f) {
            if ($f->storage_path) {
                Storage::disk('public')->delete($f->storage_path);
            }
        }

        // Also remove all bookmarks for this resource
        DB::table('resource_bookmarks')->where('resource_id', $id)->delete();

        DB::table('resources')->where('id', $id)->delete();

        return response()->json(['success' => true]);
    }

    // ── Bookmarks ─────────────────────────────────────────────

    /**
     * Toggle bookmark on a resource.
     * POST /api/resources/{id}/bookmark
     * Returns: { bookmarked: bool }
     */
    public function toggleBookmark(string $id)
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $exists = DB::table('resource_bookmarks')
            ->where('resource_id', $id)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            DB::table('resource_bookmarks')
                ->where('resource_id', $id)
                ->where('user_id', $userId)
                ->delete();

            return response()->json(['success' => true, 'bookmarked' => false]);
        }

        // Verify resource exists
        $resource = DB::table('resources')->where('id', $id)->first();
        if (!$resource) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        DB::table('resource_bookmarks')->insert([
            'id'          => (string) Str::uuid(),
            'resource_id' => $id,
            'user_id'     => $userId,
            'created_at'  => now(),
        ]);

        return response()->json(['success' => true, 'bookmarked' => true]);
    }

    /**
     * Get the current user's bookmarked resources.
     * GET /api/resources/bookmarks
     */
    public function bookmarks(Request $request)
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $page   = max(1, (int) $request->query('page', 1));
        $limit  = min(40, max(1, (int) $request->query('limit', 20)));
        $offset = ($page - 1) * $limit;

        $total = DB::table('resource_bookmarks')
            ->where('user_id', $userId)
            ->count();

        $rows = DB::table('resource_bookmarks as rb')
            ->join('resources as r', 'rb.resource_id', '=', 'r.id')
            ->where('rb.user_id', $userId)
            ->where('r.is_approved', true)
            ->select(
                'r.id', 'r.title', 'r.description', 'r.subject',
                'r.file_type', 'r.file_url', 'r.file_size',
                'r.visibility', 'r.uploaded_by', 'r.created_at',
                'rb.created_at as bookmarked_at'
            )
            ->orderByDesc('rb.created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return response()->json(['data' => [], 'total' => 0, 'page' => $page, 'limit' => $limit]);
        }

        $ids = $rows->pluck('id')->toArray();

        $ratings = DB::table('resource_ratings')
            ->selectRaw('resource_id, AVG(rating)::numeric(3,2) as avg_rating, COUNT(*) as rating_count')
            ->whereIn('resource_id', $ids)
            ->groupBy('resource_id')
            ->get()
            ->keyBy('resource_id');

        $uploaderIds = $rows->pluck('uploaded_by')->unique()->filter()->values()->toArray();
        $profiles    = [];
        if (!empty($uploaderIds)) {
            $fetched = $this->sbQuery('profiles', [
                'select' => 'id,first_name,last_name,username,profile_photo_url',
                'id'     => 'in.(' . implode(',', $uploaderIds) . ')',
            ]);
            foreach ($fetched as $p) {
                $profiles[(string) $p['id']] = $p;
            }
        }

        $data = $rows->map(function ($r) use ($ratings, $profiles, $userId) {
            $ratingRow    = $ratings[(string) $r->id] ?? null;
            $profile      = $profiles[(string) ($r->uploaded_by ?? '')] ?? null;
            $uploaderName = '';
            if ($profile) {
                $uploaderName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
                if ($uploaderName === '') {
                    $uploaderName = $profile['username'] ?? 'Unknown';
                }
            }

            return [
                'id'               => $r->id,
                'title'            => $r->title,
                'description'      => $r->description ?? null,
                'subject'          => $r->subject ?? null,
                'file_type'        => $r->file_type ?? null,
                'file_url'         => $r->file_url ?? null,
                'file_size'        => $r->file_size ?? null,
                'visibility'       => $r->visibility ?? 'public',
                'uploaded_by'      => $r->uploaded_by ?? null,
                'uploader_name'    => $uploaderName,
                'uploader_photo'   => $profile['profile_photo_url'] ?? null,
                'uploader_username'=> $profile['username'] ?? null,
                'avg_rating'       => $ratingRow ? (float) $ratingRow->avg_rating : null,
                'rating_count'     => $ratingRow ? (int) $ratingRow->rating_count : 0,
                'is_own'           => $userId !== '' && (string) ($r->uploaded_by ?? '') === $userId,
                'is_bookmarked'    => true,
                'bookmarked_at'    => $r->bookmarked_at,
                'created_at'       => $r->created_at,
            ];
        });

        return response()->json([
            'data'  => $data,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    // ── Ratings ───────────────────────────────────────────────

    /**
     * Submit a rating (1–5). One rating per user per resource — INSERT only.
     * POST /api/resources/{id}/rate
     */
    public function rate(Request $request, string $id)
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $request->validate(['rating' => 'required|integer|min:1|max:5']);

        $exists = DB::table('resource_ratings')
            ->where('resource_id', $id)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'You have already rated this resource'], 409);
        }

        DB::table('resource_ratings')->insert([
            'id'          => (string) Str::uuid(),
            'resource_id' => $id,
            'user_id'     => $userId,
            'rating'      => $request->input('rating'),
            'created_at'  => now(),
        ]);

        $agg = DB::table('resource_ratings')
            ->selectRaw('AVG(rating)::numeric(3,2) as avg_rating, COUNT(*) as rating_count')
            ->where('resource_id', $id)
            ->first();

        return response()->json([
            'success'      => true,
            'avg_rating'   => $agg ? (float) $agg->avg_rating : null,
            'rating_count' => $agg ? (int)   $agg->rating_count : 0,
        ]);
    }

    // ── Comments ─────────────────────────────────────────────

    /**
     * Get comments for a resource.
     * GET /api/resources/{id}/comments
     */
    public function comments(string $id)
    {
        $rows = DB::table('resource_comments')
            ->where('resource_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($rows->isEmpty()) {
            return response()->json(['comments' => []]);
        }

        $userIds  = $rows->pluck('user_id')->unique()->filter()->values()->toArray();
        $profiles = [];
        if (!empty($userIds)) {
            $fetched = $this->sbQuery('profiles', [
                'select' => 'id,first_name,last_name,username,profile_photo_url',
                'id'     => 'in.(' . implode(',', $userIds) . ')',
            ]);
            foreach ($fetched as $p) {
                $profiles[(string) $p['id']] = $p;
            }
        }

        $userId   = $this->currentUserId();
        $comments = $rows->map(function ($c) use ($profiles, $userId) {
            $profile = $profiles[(string) ($c->user_id ?? '')] ?? null;
            $name    = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
            if ($name === '') {
                $name = $profile['username'] ?? 'User';
            }
            return [
                'id'              => $c->id,
                'user_id'         => $c->user_id,
                'content'         => $c->content,
                'upvotes'         => $c->upvotes ?? 0,
                'created_at'      => $c->created_at,
                'updated_at'      => $c->updated_at ?? null,
                'is_own'          => $userId !== '' && (string) ($c->user_id ?? '') === $userId,
                'author_name'     => $name,
                'author_username' => $profile['username'] ?? null,
                'author_photo'    => $profile['profile_photo_url'] ?? null,
            ];
        });

        return response()->json(['comments' => $comments]);
    }

    /**
     * Post a comment on a resource.
     * POST /api/resources/{id}/comments
     */
    public function addComment(Request $request, string $id)
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $request->validate(['content' => 'required|string|max:2000']);

        $commentId = (string) Str::uuid();

        DB::table('resource_comments')->insert([
            'id'          => $commentId,
            'resource_id' => $id,
            'user_id'     => $userId,
            'content'     => $request->input('content'),
            'upvotes'     => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return response()->json(['success' => true, 'comment_id' => $commentId], 201);
    }

    /**
     * Edit own comment.
     * PUT /api/resources/comments/{commentId}
     */
    public function updateComment(Request $request, string $commentId)
    {
        $userId  = $this->currentUserId();
        $comment = DB::table('resource_comments')->where('id', $commentId)->first();

        if (!$comment) {
            return response()->json(['error' => 'Comment not found'], 404);
        }

        if ((string) $comment->user_id !== $userId) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $request->validate(['content' => 'required|string|max:2000']);

        DB::table('resource_comments')->where('id', $commentId)->update([
            'content'    => $request->input('content'),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Delete own comment.
     * DELETE /api/resources/comments/{commentId}
     */
    public function deleteComment(string $commentId)
    {
        $userId  = $this->currentUserId();
        $comment = DB::table('resource_comments')->where('id', $commentId)->first();

        if (!$comment) {
            return response()->json(['error' => 'Comment not found'], 404);
        }

        if ((string) $comment->user_id !== $userId) {
            $role = session('user_role', '');
            if (!in_array($role, ['admin', 'moderator'])) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        DB::table('resource_comments')->where('id', $commentId)->delete();

        return response()->json(['success' => true]);
    }

    // ── Upvote comment ────────────────────────────────────────

    /**
     * Upvote a comment.
     * POST /api/resources/comments/{commentId}/upvote
     */
    public function upvoteComment(string $commentId)
    {
        $userId = $this->currentUserId();
        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        DB::table('resource_comments')
            ->where('id', $commentId)
            ->increment('upvotes');

        $upvotes = DB::table('resource_comments')
            ->where('id', $commentId)
            ->value('upvotes');

        return response()->json(['success' => true, 'upvotes' => $upvotes]);
    }

    // ── My uploads ────────────────────────────────────────────

    /**
     * Return the current user's uploads for the sidebar widget.
     * GET /api/resources/my-uploads
     */
    public function myUploads()
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $rows = DB::table('resources')
            ->where('uploaded_by', $userId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'title', 'file_type', 'subject', 'visibility', 'is_approved', 'created_at']);

        return response()->json(['data' => $rows]);
    }

    // ── Report ────────────────────────────────────────────────

    /**
     * Report a resource.
     * POST /api/resources/{id}/report
     */
    public function report(Request $request, string $id)
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $request->validate([
            'reason'  => 'required|string|max:100',
            'details' => 'nullable|string|max:1000',
        ]);

        $reason = $request->input('details')
            ? $request->input('reason') . ': ' . $request->input('details')
            : $request->input('reason');

        DB::table('reports')->insert([
            'id'                    => (string) Str::uuid(),
            'reported_by'           => $userId,
            'reported_content_type' => 'resource',
            'reported_content_id'   => $id,
            'reason'                => $reason,
            'status'                => 'pending',
            'created_at'            => now(),
        ]);

        return response()->json(['success' => true]);
    }

    // ── Admin: approve ────────────────────────────────────────

    /**
     * Approve a resource (admin/moderator only).
     * POST /api/resources/{id}/approve
     */
    public function approve(string $id)
    {
        $role = session('user_role', '');

        if (!in_array($role, ['admin', 'moderator'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $updated = DB::table('resources')
            ->where('id', $id)
            ->update(['is_approved' => true, 'updated_at' => now()]);

        if (!$updated) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        return response()->json(['success' => true]);
    }

    // ── Admin: toggle curated ─────────────────────────────────

    /**
     * Toggle the is_curated flag on a resource (admin/moderator only).
     * POST /api/resources/{id}/curate
     * Returns: { curated: bool }
     */
    public function toggleCurated(string $id)
    {
        $role = session('user_role', '');

        if (!in_array($role, ['admin', 'moderator'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $resource = DB::table('resources')->where('id', $id)->first();

        if (!$resource) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        $newValue = ! (bool) ($resource->is_curated ?? false);

        DB::table('resources')
            ->where('id', $id)
            ->update(['is_curated' => $newValue, 'updated_at' => now()]);

        return response()->json(['success' => true, 'curated' => $newValue]);
    }
}
