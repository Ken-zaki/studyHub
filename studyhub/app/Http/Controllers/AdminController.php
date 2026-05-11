<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AdminController extends Controller
{
    // ── Supabase helpers ─────────────────────────────────────────
    private string $sbUrl;
    private string $sbSvc;
    private string $sbAnon;

    public function __construct()
    {
        $this->sbUrl  = env('SUPABASE_URL',         '');
        $this->sbSvc  = env('SUPABASE_SERVICE_KEY',  '');
        $this->sbAnon = env('SUPABASE_ANON_KEY',     '');
    }

    /**
     * Headers for privileged Supabase requests (service key bypasses RLS).
     */
    private function svcHeaders(): array
    {
        return [
            'apikey'        => $this->sbSvc,
            'Authorization' => "Bearer {$this->sbSvc}",
            'Content-Type'  => 'application/json',
        ];
    }

    /**
     * Shared middleware-style guard: redirect non-admins away.
     * Call at the top of every method.
     */
    private function requireAdmin()
    {
        $role = session('user_role', 'student');
        if (! in_array($role, ['admin', 'moderator'])) {
            abort(403, 'Access denied — admin only.');
        }
    }

    // ════════════════════════════════════════════════════════════
    // DASHBOARD
    // ════════════════════════════════════════════════════════════
    public function dashboard()
    {
        $this->requireAdmin();
        return view('admin.dashboard');
    }

    // ════════════════════════════════════════════════════════════
    // USER MANAGEMENT
    // ════════════════════════════════════════════════════════════
    public function users()
    {
        $this->requireAdmin();
        return view('admin.users');
    }

    // ════════════════════════════════════════════════════════════
    // REPORTS
    // ════════════════════════════════════════════════════════════
    public function reports()
    {
        $this->requireAdmin();
        return view('admin.reports');
    }

    // ════════════════════════════════════════════════════════════
    // RESOURCE APPROVAL
    // ════════════════════════════════════════════════════════════
    public function resources()
    {
        $this->requireAdmin();
        return view('admin.resources');
    }

    // ════════════════════════════════════════════════════════════
    // ADMIN LOGS
    // ════════════════════════════════════════════════════════════
    public function logs()
    {
        $this->requireAdmin();
        return view('admin.logs');
    }

    // ════════════════════════════════════════════════════════════
    // POSTS FEED  (new tab)
    // ════════════════════════════════════════════════════════════
    public function posts()
    {
        $this->requireAdmin();
        return view('admin.posts');
    }

    // ════════════════════════════════════════════════════════════
    // SETTINGS
    // ════════════════════════════════════════════════════════════
    public function settings()
    {
        $this->requireAdmin();
        return view('admin.settings');
    }

    // ════════════════════════════════════════════════════════════
    // FRIEND REQUEST HELPERS (used by profile pages)
    // ════════════════════════════════════════════════════════════

    /**
     * Send a friend request.
     * Route: POST /friend-requests/send/{receiverId}
     */
    public function sendFriendRequest(Request $request, string $receiverId)
    {
        $senderId = session('user_id');
        if (! $senderId) return redirect()->back()->withErrors(['Not logged in.']);

        $response = Http::withHeaders($this->svcHeaders())
            ->post("{$this->sbUrl}/rest/v1/friend_requests", [
                'sender_id'   => $senderId,
                'receiver_id' => $receiverId,
                'status'      => 'pending',
            ]);

        if ($response->successful()) {
            return redirect()->back()->with('status', 'Friend request sent!');
        }

        // 409 = already sent — treat as success
        if ($response->status() === 409) {
            return redirect()->back()->with('status', 'Friend request already sent.');
        }

        return redirect()->back()->withErrors([$response->json('message') ?? 'Failed to send request.']);
    }

    /**
     * Accept a friend request.
     * Route: POST /friend-requests/{friendRequest}/accept
     */
    public function acceptFriendRequest(Request $request, string $requestId)
    {
        $userId = session('user_id');
        if (! $userId) return redirect()->back()->withErrors(['Not logged in.']);

        // Fetch the request to confirm receiver = current user
        $fetchRes = Http::withHeaders($this->svcHeaders())
            ->get("{$this->sbUrl}/rest/v1/friend_requests?id=eq.{$requestId}&select=id,sender_id,receiver_id,status");

        $fr = $fetchRes->json()[0] ?? null;
        if (! $fr || $fr['receiver_id'] !== $userId) {
            return redirect()->back()->withErrors(['Not authorised.']);
        }

        // Update status to accepted — the DB trigger populates user_friends
        Http::withHeaders($this->svcHeaders())
            ->patch("{$this->sbUrl}/rest/v1/friend_requests?id=eq.{$requestId}", [
                'status' => 'accepted',
            ]);

        return redirect()->back()->with('status', 'Friend request accepted!');
    }

    /**
     * Decline a friend request.
     * Route: POST /friend-requests/{friendRequest}/decline
     */
    public function declineFriendRequest(Request $request, string $requestId)
    {
        $userId = session('user_id');
        if (! $userId) return redirect()->back()->withErrors(['Not logged in.']);

        Http::withHeaders($this->svcHeaders())
            ->patch("{$this->sbUrl}/rest/v1/friend_requests?id=eq.{$requestId}", [
                'status' => 'declined',
            ]);

        return redirect()->back()->with('status', 'Friend request declined.');
    }

    /**
     * Remove a friend (delete from user_friends both directions).
     * Route: POST /friends/remove/{friendId}
     */
    public function removeFriend(Request $request, string $friendId)
    {
        $userId = session('user_id');
        if (! $userId) return redirect()->back()->withErrors(['Not logged in.']);

        // Delete both directions
        Http::withHeaders($this->svcHeaders())
            ->delete("{$this->sbUrl}/rest/v1/user_friends?user_id=eq.{$userId}&friend_id=eq.{$friendId}");

        Http::withHeaders($this->svcHeaders())
            ->delete("{$this->sbUrl}/rest/v1/user_friends?user_id=eq.{$friendId}&friend_id=eq.{$userId}");

        // Also delete any friend_requests between them
        Http::withHeaders($this->svcHeaders())
            ->delete("{$this->sbUrl}/rest/v1/friend_requests?or=(and(sender_id.eq.{$userId},receiver_id.eq.{$friendId}),and(sender_id.eq.{$friendId},receiver_id.eq.{$userId}))");

        return redirect()->back()->with('status', 'Friend removed.');
    }

    // ════════════════════════════════════════════════════════════
    // PROFILE VIEW  (other users)
    // ════════════════════════════════════════════════════════════

    /**
     * View another user's profile by username.
     * Route: GET /profile/{username}
     */
    public function profileView(Request $request, string $username)
    {
        $currentUserId = session('user_id', '');

        // Look up the viewed user by username
        $res = Http::withHeaders([
                'apikey'        => $this->sbAnon,
                'Authorization' => "Bearer {$this->sbAnon}",
            ])
            ->get("{$this->sbUrl}/rest/v1/profiles?username=eq.{$username}&select=id,first_name,last_name,username,profile_photo_url,created_at,bio");

        $profile = $res->json()[0] ?? null;
        $userId  = $profile['id'] ?? null;

        // If it's the user's own profile, redirect to /profile
        if ($userId && $userId === $currentUserId) {
            return redirect()->route('profile');
        }

        // Determine relationship state
        $relationshipState = 'none';
        $pendingRequestId  = null;

        if ($userId && $currentUserId) {
            // Check for existing friendship
            $friendRes = Http::withHeaders($this->svcHeaders())
                ->get("{$this->sbUrl}/rest/v1/user_friends?or=(and(user_id.eq.{$currentUserId},friend_id.eq.{$userId}),and(user_id.eq.{$userId},friend_id.eq.{$currentUserId}))&select=id&limit=1");

            if (! empty($friendRes->json())) {
                $relationshipState = 'friends';
            } else {
                // Check friend requests
                $reqRes = Http::withHeaders($this->svcHeaders())
                    ->get("{$this->sbUrl}/rest/v1/friend_requests?or=(and(sender_id.eq.{$currentUserId},receiver_id.eq.{$userId}),and(sender_id.eq.{$userId},receiver_id.eq.{$currentUserId}))&status=eq.pending&select=id,sender_id,receiver_id&limit=1");

                $req = $reqRes->json()[0] ?? null;
                if ($req) {
                    $pendingRequestId = $req['id'];
                    $relationshipState = $req['sender_id'] === $currentUserId
                        ? 'pending_outgoing'
                        : 'pending_incoming';
                }
            }
        }

        return view('home.profile-view', compact(
            'userId',
            'username',
            'relationshipState',
            'pendingRequestId',
            'profile',
        ));
    }
}
