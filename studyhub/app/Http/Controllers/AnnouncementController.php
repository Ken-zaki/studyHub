<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    private string $sbUrl;
    private string $sbSvc;

    public function __construct()
    {
        $this->sbUrl = config('services.supabase.url',         '');
        $this->sbSvc = config('services.supabase.service_key', '');
    }

    /**
     * Raw cURL — mirrors SupabaseServiceProvider::request() exactly.
     * Uses CURLOPT_SSL_VERIFYPEER = false to avoid cURL error 60 on Windows.
     * Returns decoded array on success, ['error' => true, 'message' => ...] on failure.
     */
    private function supabase(string $method, string $path, ?array $body = null): array
    {
        $url = $this->sbUrl . $path;

        $headers = [
            'apikey: '               . $this->sbSvc,
            'Authorization: Bearer ' . $this->sbSvc,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_SSL_VERIFYPEER => false,   // fix for Windows localhost cURL error 60
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

    // ── POST /admin/announcements ────────────────────────────────
    public function store(Request $request)
    {
        $this->requireAdmin();

        $validated = $request->validate([
            'title'    => 'required|string|max:255',
            'body'     => 'required|string',
            'priority' => 'sometimes|in:normal,important,urgent',
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

        // Supabase returns an array of rows with Prefer: return=representation
        $announcement   = $res[0] ?? null;
        $announcementId = $announcement['id'] ?? null;

        if (! $announcementId) {
            return response()->json(['error' => 'Announcement created but no ID returned.'], 500);
        }

        // 2. Fan-out notifications to all non-banned users via DB function
        $rpcRes = $this->supabase('POST', '/rest/v1/rpc/broadcast_announcement', [
            'p_announcement_id' => $announcementId,
            'p_title'           => $validated['title'],
            'p_body'            => $validated['body'],
            'p_priority'        => $priority,
        ]);

        // 3. Log admin action (non-fatal — don't fail the request if this errors)
        $this->supabase('POST', '/rest/v1/admin_logs', [
            'admin_id'    => $adminId ?: null,
            'action'      => 'create_announcement',
            'target_type' => 'announcement',
            'target_id'   => $announcementId,
            'notes'       => "Created announcement: {$validated['title']}",
        ]);

        if (isset($rpcRes['error'])) {
            return response()->json([
                'success'      => true,
                'rpc_warning'  => 'Announcement saved but notifications failed: ' . ($rpcRes['message'] ?? 'unknown error'),
                'announcement' => $announcement,
            ]);
        }

        return response()->json([
            'success'      => true,
            'announcement' => $announcement,
        ]);
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
}
