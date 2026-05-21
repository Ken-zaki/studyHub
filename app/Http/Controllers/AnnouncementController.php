<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    private string $sbUrl;
    private string $sbSvc;
    private string $sbAnon;

    public function __construct()
    {
        $this->sbUrl  = config('services.supabase.url',         '');
        $this->sbSvc  = config('services.supabase.service_key', '');
        $this->sbAnon = config('services.supabase.anon_key',    '');
    }

    /**
     * Raw cURL — mirrors SupabaseServiceProvider::request() exactly.
     * Uses CURLOPT_SSL_VERIFYPEER = false to avoid cURL error 60 on Windows.
     * Returns decoded array on success, ['error' => true, 'message' => ...] on failure.
     */
    private function supabase(string $method, string $path, ?array $body = null, bool $useAnon = false): array
    {
        $url = $this->sbUrl . $path;
        $key = $useAnon ? $this->sbAnon : $this->sbSvc;

        $headers = [
            'apikey: '               . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 15,
        ]);

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            \Log::error('AnnouncementController cURL error', ['path' => $path, 'error' => $curlError]);
            return ['error' => true, 'message' => 'Network error: ' . $curlError];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            \Log::warning('AnnouncementController HTTP ' . $httpCode, ['path' => $path, 'response' => $response]);
            return [
                'error'   => true,
                'status'  => $httpCode,
                'message' => is_array($decoded)
                    ? ($decoded['message'] ?? $decoded['error_description'] ?? 'Request failed')
                    : 'Request failed',
            ];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Upload a single file to Supabase Storage bucket "announcement-files".
     * Returns the public URL on success, or null on failure.
     */
    private function uploadFileToStorage(\Illuminate\Http\UploadedFile $file): ?string
    {
        $bucket    = 'announcement-files';
        $safeName  = time() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '_', $file->getClientOriginalName());
        $uploadUrl = $this->sbUrl . '/storage/v1/object/' . $bucket . '/' . $safeName;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $uploadUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'apikey: '               . $this->sbSvc,
                'Authorization: Bearer ' . $this->sbSvc,
                'Content-Type: '         . $file->getMimeType(),
                'x-upsert: true',
            ],
            CURLOPT_POSTFIELDS     => file_get_contents($file->getRealPath()),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode >= 400) {
            \Log::error('AnnouncementController: Storage upload failed', [
                'file'     => $safeName,
                'httpCode' => $httpCode,
                'curlErr'  => $curlErr,
                'response' => $response,
            ]);
            return null;
        }

        // Build the public URL
        return $this->sbUrl . '/storage/v1/object/public/' . $bucket . '/' . $safeName;
    }

    private function requireAdmin(): void
    {
        $role = session('user_role', 'student');
        if (! in_array($role, ['admin', 'moderator'])) {
            abort(403, 'Admin access required.');
        }
    }

    // ── GET /admin/announcements ─────────────────────────────────
    public function index()
    {
        $this->requireAdmin();
        return view('admin.announcements');
    }

    // ── GET /announcements (student-facing page) ─────────────────
    public function studentIndex()
    {
        $userId = session('user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        // Fetch active announcements with their attached files
        $announcements = $this->supabase(
            'GET',
            '/rest/v1/announcements?is_active=eq.true&order=created_at.desc&limit=50',
            null,
            true   // use anon key — students can read
        );

        if (isset($announcements['error'])) {
            $announcements = [];
        }

        // For each announcement, fetch its files
        foreach ($announcements as &$ann) {
            $files = $this->supabase(
                'GET',
                '/rest/v1/announcement_files?announcement_id=eq.' . urlencode($ann['id'])
                    . '&order=created_at.asc',
                null,
                true
            );
            $ann['files'] = isset($files['error']) ? [] : $files;
        }
        unset($ann);

        return view('home.announcements', [
            'activeNav'      => 'announcements',
            'announcements'  => $announcements,
            'supabaseUrl'    => $this->sbUrl,
            'supabaseAnonKey'=> $this->sbAnon,
            'userId'         => $userId,
        ]);
    }

    // ── POST /admin/announcements ────────────────────────────────
    // Now accepts multipart/form-data so files can be attached.
    public function store(Request $request)
    {
        $this->requireAdmin();

        $validated = $request->validate([
            'title'    => 'required|string|max:255',
            'body'     => 'required|string',
            'priority' => 'sometimes|in:normal,important,urgent',
            // Each uploaded file: max 20 MB, common doc/image/archive types
            'files.*'  => 'sometimes|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,png,jpg,jpeg,gif,zip,rar',
        ]);

        $adminId  = session('user_id');
        $priority = $validated['priority'] ?? 'normal';

        // 1. Insert announcement
        $res = $this->supabase('POST', '/rest/v1/announcements', [
            'admin_id'  => $adminId ?: null,
            'title'     => $validated['title'],
            'body'      => $validated['body'],
            'priority'  => $priority,
            'is_active' => true,
        ]);

        if (isset($res['error'])) {
            return response()->json([
                'error' => $res['message'] ?? 'Failed to create announcement.',
            ], 422);
        }

        $announcement   = $res[0] ?? null;
        $announcementId = $announcement['id'] ?? null;

        if (! $announcementId) {
            return response()->json(['error' => 'Announcement created but no ID returned.'], 500);
        }

        // 2. Upload attached files (if any) and record them
        $uploadedFiles  = [];
        $failedFiles    = [];

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                if (! $file->isValid()) {
                    $failedFiles[] = $file->getClientOriginalName();
                    continue;
                }

                $publicUrl = $this->uploadFileToStorage($file);

                if (! $publicUrl) {
                    $failedFiles[] = $file->getClientOriginalName();
                    continue;
                }

                // Record the file in announcement_files
                $fileRes = $this->supabase('POST', '/rest/v1/announcement_files', [
                    'announcement_id' => $announcementId,
                    'file_name'       => $file->getClientOriginalName(),
                    'file_url'        => $publicUrl,
                    'file_size'       => $file->getSize(),
                    'mime_type'       => $file->getMimeType(),
                ]);

                if (! isset($fileRes['error'])) {
                    $uploadedFiles[] = [
                        'name' => $file->getClientOriginalName(),
                        'url'  => $publicUrl,
                    ];
                } else {
                    $failedFiles[] = $file->getClientOriginalName();
                }
            }
        }

        // 3. Fan-out notifications to all non-banned users via DB function
        $rpcRes = $this->supabase('POST', '/rest/v1/rpc/broadcast_announcement', [
            'p_announcement_id' => $announcementId,
            'p_title'           => $validated['title'],
            'p_body'            => $validated['body'],
            'p_priority'        => $priority,
        ]);

        // 4. Log admin action (non-fatal)
        $this->supabase('POST', '/rest/v1/admin_logs', [
            'admin_id'    => $adminId ?: null,
            'action'      => 'create_announcement',
            'target_type' => 'announcement',
            'target_id'   => $announcementId,
            'notes'       => "Created announcement: {$validated['title']} (files: " . count($uploadedFiles) . ')',
        ]);

        $response = [
            'success'        => true,
            'announcement'   => $announcement,
            'uploaded_files' => $uploadedFiles,
        ];

        if (! empty($failedFiles)) {
            $response['file_warning'] = 'Some files failed to upload: ' . implode(', ', $failedFiles);
        }

        if (isset($rpcRes['error'])) {
            $response['rpc_warning'] = 'Announcement saved but notifications failed: ' . ($rpcRes['message'] ?? 'unknown error');
        }

        return response()->json($response);
    }

    // ── PATCH /admin/announcements/{id} ─────────────────────────
    public function update(Request $request, string $id)
    {
        $this->requireAdmin();

        $data = $request->only(['title', 'body', 'priority', 'is_active']);

        $res = $this->supabase('PATCH', '/rest/v1/announcements?id=eq.' . urlencode($id), $data);

        if (isset($res['error'])) {
            return response()->json(['error' => 'Update failed: ' . ($res['message'] ?? '')], 422);
        }

        return response()->json(['success' => true]);
    }

    // ── DELETE /admin/announcements/{id} ────────────────────────
    public function destroy(string $id)
    {
        $this->requireAdmin();

        // Files are deleted automatically via ON DELETE CASCADE on announcement_files.
        // Storage objects however must be cleaned up separately if needed.
        $this->supabase('DELETE', '/rest/v1/announcements?id=eq.' . urlencode($id));

        $this->supabase('POST', '/rest/v1/admin_logs', [
            'admin_id'    => session('user_id') ?: null,
            'action'      => 'delete_announcement',
            'target_type' => 'announcement',
            'target_id'   => $id,
            'notes'       => 'Announcement deleted.',
        ]);

        return response()->json(['success' => true]);
    }

    // ── DELETE /admin/announcements/{annId}/files/{fileId} ───────
    public function destroyFile(string $annId, string $fileId)
    {
        $this->requireAdmin();

        $this->supabase('DELETE', '/rest/v1/announcement_files?id=eq.' . urlencode($fileId)
            . '&announcement_id=eq.' . urlencode($annId));

        return response()->json(['success' => true]);
    }
}
