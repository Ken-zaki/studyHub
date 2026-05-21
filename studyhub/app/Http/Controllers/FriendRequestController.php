<?php

namespace App\Http\Controllers;

use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Providers\SupabaseServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FriendRequestController extends Controller
{
    // ──────────────────────────────────────────────────────────────
    // SUPABASE LOW-LEVEL HELPERS
    // (retained from v1 for direct REST calls that bypass Eloquent,
    //  e.g. the explicit user_friends upsert in accept())
    // ──────────────────────────────────────────────────────────────

    private function sbUrl(): string
    {
        return rtrim(env('SUPABASE_URL'), '/') . '/rest/v1/';
    }

    private function sbHeaders(): array
    {
        return [
            'apikey'        => env('SUPABASE_SERVICE_KEY'),
            'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ];
    }

    private function sbPost(string $table, array $data, array $extraHeaders = []): bool
    {
        $response = Http::withoutVerifying()
            ->withHeaders(array_merge($this->sbHeaders(), $extraHeaders))
            ->post($this->sbUrl() . $table, $data);
        return !$response->failed();
    }

    private function sbDelete(string $table, array $match): bool
    {
        $response = Http::withoutVerifying()
            ->withHeaders($this->sbHeaders())
            ->delete($this->sbUrl() . $table . '?' . http_build_query($match));
        return !$response->failed();
    }

    // ──────────────────────────────────────────────────────────────
    // ROUTES
    // ──────────────────────────────────────────────────────────────

    public function index()
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $currentUserId = $this->currentUserId();
        $provider      = new SupabaseServiceProvider();

        // Load ALL profiles once — service key bypasses RLS
        $allProfiles  = $provider->getAllProfiles();
        $profilesById = $this->indexProfilesById($allProfiles);

        $incomingRequests = FriendRequest::query()
            ->where('receiver_id', $currentUserId)
            ->where('status', 'pending')
            ->latest()
            ->get();

        $outgoingRequests = FriendRequest::query()
            ->where('sender_id', $currentUserId)
            ->where('status', 'pending')
            ->latest()
            ->get();

        $friendIds          = $this->loadFriendIds($currentUserId);
        $pendingOutgoingIds = $this->pluckRequestIds($outgoingRequests->all(), 'receiver_id');
        $pendingIncomingIds = $this->pluckRequestIds($incomingRequests->all(), 'sender_id');

        $discoverProfiles = [];
        $friends          = [];

        foreach ($allProfiles as $profile) {
            $profileId = $this->resolveProfileId($profile);

            if ($profileId === '' || $profileId === $currentUserId) {
                continue;
            }

            if (isset($friendIds[$profileId])) {
                $friends[] = $this->profileEntry($profile, ['relationship' => 'friends']);
                continue;
            }

            $discoverProfiles[] = $this->profileEntry($profile, [
                'relationship' => isset($pendingIncomingIds[$profileId])
                    ? 'pending_incoming'
                    : (isset($pendingOutgoingIds[$profileId]) ? 'pending_outgoing' : 'available'),
            ]);
        }

        return view('home.friend-req', [
            'activeNav'        => 'friend-requests',
            'incomingRequests' => $this->attachProfileMetadata($incomingRequests->all(), $profilesById, 'sender_id'),
            'outgoingRequests' => $this->attachProfileMetadata($outgoingRequests->all(), $profilesById, 'receiver_id'),
            'discoverProfiles' => $discoverProfiles,
            'friends'          => $friends,
        ]);
    }

    // ── Send friend request ───────────────────────────────────────

    public function send(Request $request, string $receiverId): RedirectResponse
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $senderId   = $this->currentUserId();
        $receiverId = trim($receiverId);
        $provider   = new SupabaseServiceProvider();

        // If not a UUID, try resolving by email or username
        if (!$this->isValidUuid($receiverId)) {
            $foundProfile = $provider->getProfileByEmail($receiverId)
                         ?? $provider->getProfileByUsername($receiverId);

            if (!$foundProfile) {
                return back()->withErrors(['friend_request' => 'User not found.']);
            }

            $foundId = $this->resolveProfileId($foundProfile);
            if ($foundId === '') {
                return back()->withErrors(['friend_request' => 'User not found.']);
            }

            $receiverId = $foundId;
        }

        if ($receiverId === '' || $receiverId === $senderId) {
            return back()->withErrors(['friend_request' => 'Invalid friend request target.']);
        }

        // Verify receiver exists
        if (!$provider->getProfileById($receiverId)) {
            return back()->withErrors(['friend_request' => 'User not found.']);
        }

        if (Friendship::areFriends($senderId, $receiverId)) {
            return back()->with('status', 'You are already friends.');
        }

        // Check exact direction: sender → receiver (any status)
        $exactRequest = FriendRequest::query()
            ->where('sender_id', $senderId)
            ->where('receiver_id', $receiverId)
            ->first();

        if ($exactRequest) {
            if ($exactRequest->status === 'pending') {
                return back()->with('status', 'Friend request already sent.');
            }
            // Was declined — allow resending
            $exactRequest->update(['status' => 'pending', 'responded_at' => null]);
            $this->pushFriendRequestNotification($senderId, $receiverId, $provider);
            return back()->with('status', 'Friend request sent.');
        }

        // Block if a reverse pending request already exists
        $reverseRequest = FriendRequest::query()
            ->where('sender_id', $receiverId)
            ->where('receiver_id', $senderId)
            ->where('status', 'pending')
            ->first();

        if ($reverseRequest) {
            return back()->with('status', 'They already sent you a friend request. Check your Requests tab.');
        }

        FriendRequest::create([
            'sender_id'   => $senderId,
            'receiver_id' => $receiverId,
            'status'      => 'pending',
        ]);

        $this->pushFriendRequestNotification($senderId, $receiverId, $provider);

        return back()->with('status', 'Friend request sent.');
    }

    // ── Accept friend request ─────────────────────────────────────
    // FIX (from v1): explicitly insert BOTH directions into user_friends
    // via direct Supabase REST calls, because the DB trigger does not
    // fire when updates are made through the service-key REST API.

    public function accept(FriendRequest $friendRequest): RedirectResponse
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        if ($friendRequest->receiver_id !== $this->currentUserId()) {
            abort(403, 'You cannot accept this request.');
        }

        if ($friendRequest->status !== 'pending') {
            return back()->with('status', 'This request has already been processed.');
        }

        $senderId   = $friendRequest->sender_id;
        $receiverId = $friendRequest->receiver_id;

        DB::transaction(function () use ($friendRequest, $senderId, $receiverId) {
            $friendRequest->update([
                'status'       => 'accepted',
                'responded_at' => now(),
            ]);

            // Eloquent upsert via Friendship model
            Friendship::firstOrCreate(['user_id' => $senderId,   'friend_id' => $receiverId]);
            Friendship::firstOrCreate(['user_id' => $receiverId, 'friend_id' => $senderId]);
        });

        // Belt-and-suspenders: also push both rows directly to user_friends
        // via Supabase REST so the friendship exists even if the DB trigger
        // or Eloquent observer is not wired up. ON CONFLICT → ignore duplicates.
        $upsertHeaders = ['Prefer' => 'resolution=ignore-duplicates,return=representation'];

        $this->sbPost('user_friends', ['user_id' => $senderId,   'friend_id' => $receiverId], $upsertHeaders);
        $this->sbPost('user_friends', ['user_id' => $receiverId, 'friend_id' => $senderId],   $upsertHeaders);

        Log::info('[FriendRequest] Accepted and user_friends populated', [
            'request_id' => $friendRequest->id,
            'sender'     => $senderId,
            'receiver'   => $receiverId,
        ]);

        // Notify the original sender
        $provider     = new SupabaseServiceProvider();
        $accepterProf = $provider->getProfileById($receiverId);

        if ($accepterProf) {
            $firstName    = trim($accepterProf['first_name'] ?? '');
            $lastName     = trim($accepterProf['last_name']  ?? '');
            $accepterName = trim($firstName . ' ' . $lastName);
            if ($accepterName === '') {
                $accepterName = $accepterProf['username'] ?? 'Someone';
            }

            $this->pushNotification([
                'id'          => Str::uuid()->toString(),
                'user_id'     => $senderId,
                'source_type' => 'friend_request',
                'source_id'   => $receiverId,
                'trigger'     => 'friend_request_accepted',
                'title'       => 'You and ' . $accepterName . ' are now friends! 🎉',
                'body'        => 'Start a conversation',
                'icon'        => '🎉',
                'urgency'     => 'info',
                'read'        => false,
                'created_at'  => now()->toISOString(),
            ]);
        }

        return redirect()->route('friend-requests', ['tab' => 'friends'])
            ->with('status', 'Friend request accepted.');
    }

    // ── Decline friend request ────────────────────────────────────

    public function decline(FriendRequest $friendRequest): RedirectResponse
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        if ($friendRequest->receiver_id !== $this->currentUserId()) {
            abort(403, 'You cannot decline this request.');
        }

        if ($friendRequest->status !== 'pending') {
            return back()->with('status', 'This request has already been processed.');
        }

        $friendRequest->update(['status' => 'declined', 'responded_at' => now()]);

        // Clean up any stale user_friends rows just in case
        $this->sbDelete('user_friends', [
            'user_id'   => 'eq.' . $friendRequest->sender_id,
            'friend_id' => 'eq.' . $friendRequest->receiver_id,
        ]);
        $this->sbDelete('user_friends', [
            'user_id'   => 'eq.' . $friendRequest->receiver_id,
            'friend_id' => 'eq.' . $friendRequest->sender_id,
        ]);

        return redirect()->route('friend-requests', ['tab' => 'requests'])
            ->with('status', 'Friend request declined.');
    }

    // ── Cancel outgoing friend request ────────────────────────────

    public function cancel(FriendRequest $friendRequest): RedirectResponse
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        if ($friendRequest->sender_id !== $this->currentUserId()) {
            abort(403, 'You can only cancel your own friend requests.');
        }

        if ($friendRequest->status !== 'pending') {
            return back()->with('status', 'This request has already been processed.');
        }

        $friendRequest->delete();

        return redirect()->route('friend-requests', ['tab' => 'requests'])
            ->with('status', 'Friend request cancelled.');
    }

    // ── Remove friend ─────────────────────────────────────────────

    public function remove(string $friendId): RedirectResponse
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $currentUserId = $this->currentUserId();
        $friendId      = trim($friendId);

        if ($friendId === '' || $friendId === $currentUserId) {
            return back()->withErrors(['friend_request' => 'Invalid friend ID.']);
        }

        if (!Friendship::areFriends($currentUserId, $friendId)) {
            return back()->withErrors(['friend_request' => 'You are not friends with this user.']);
        }

        DB::transaction(function () use ($currentUserId, $friendId) {
            Friendship::query()
                ->where('user_id', $currentUserId)
                ->where('friend_id', $friendId)
                ->delete();

            Friendship::query()
                ->where('user_id', $friendId)
                ->where('friend_id', $currentUserId)
                ->delete();
        });

        // Also remove from user_friends table directly (mirrors accept() logic)
        $this->sbDelete('user_friends', [
            'user_id'   => 'eq.' . $currentUserId,
            'friend_id' => 'eq.' . $friendId,
        ]);
        $this->sbDelete('user_friends', [
            'user_id'   => 'eq.' . $friendId,
            'friend_id' => 'eq.' . $currentUserId,
        ]);

        return redirect()->route('friend-requests', ['tab' => 'friends'])
            ->with('status', 'Friend removed.');
    }

    // ──────────────────────────────────────────────────────────────
    // NOTIFICATION HELPERS
    // ──────────────────────────────────────────────────────────────

    private function supabaseUrl(): string
    {
        return rtrim(env('SUPABASE_URL'), '/');
    }

    private function supabaseHeaders(): array
    {
        return [
            'apikey'        => env('SUPABASE_SERVICE_KEY'),
            'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ];
    }

    private function pushNotification(array $data): void
    {
        Http::withoutVerifying()
            ->withHeaders(array_merge($this->supabaseHeaders(), [
                'Prefer' => 'resolution=ignore-duplicates',
            ]))
            ->post($this->supabaseUrl() . '/rest/v1/notifications', $data);
    }

    private function pushFriendRequestNotification(
        string $senderId,
        string $receiverId,
        SupabaseServiceProvider $provider
    ): void {
        $senderProfile = $provider->getProfileById($senderId);
        if (!$senderProfile) return;

        $firstName  = trim($senderProfile['first_name'] ?? '');
        $lastName   = trim($senderProfile['last_name']  ?? '');
        $senderName = trim($firstName . ' ' . $lastName);
        if ($senderName === '') {
            $senderName = $senderProfile['username'] ?? 'Someone';
        }

        $this->pushNotification([
            'id'          => Str::uuid()->toString(),
            'user_id'     => $receiverId,
            'source_type' => 'friend_request',
            'source_id'   => $senderId,
            'trigger'     => 'friend_request_received',
            'title'       => $senderName . ' sent you a friend request',
            'body'        => 'Tap to view their profile',
            'icon'        => '🤝',
            'urgency'     => 'info',
            'read'        => false,
            'created_at'  => now()->toISOString(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────

    private function currentUserId(): string
    {
        return trim((string) session('user_id', ''));
    }

    private function requireAuth(): ?RedirectResponse
    {
        if ($this->currentUserId() === '') {
            return redirect()->route('login');
        }
        if (session('is_banned')) {
            session()->flush();
            return redirect()->route('login')->with('error', 'Your account has been suspended.');
        }
        return null;
    }

    private function isValidUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value
        );
    }

    private function resolveProfileId(array $profile): string
    {
        $candidate = (string) ($profile['id'] ?? '');
        if ($this->isValidUuid($candidate)) {
            return $candidate;
        }
        foreach (['user_id', 'uid', 'uuid', 'auth_id'] as $field) {
            $alt = (string) ($profile[$field] ?? '');
            if ($this->isValidUuid($alt)) {
                return $alt;
            }
        }
        return $candidate;
    }

    private function indexProfilesById(array $profiles): array
    {
        $indexed = [];
        foreach ($profiles as $profile) {
            $id = $this->resolveProfileId($profile);
            if ($id !== '') {
                $indexed[$id] = $profile;
            }
        }
        return $indexed;
    }

    private function attachProfileMetadata(array $requests, array $profilesById, string $profileKey): array
    {
        if (empty($requests)) {
            return [];
        }

        return array_map(function (FriendRequest $request) use ($profilesById, $profileKey): array {
            $userId  = (string) ($request->{$profileKey} ?? '');
            $profile = $profilesById[$userId] ?? [];

            return array_merge(
                ['request' => $request],
                $this->profileEntry($profile, [
                    'id' => $userId !== '' ? $userId : (string) ($profile['id'] ?? ''),
                ])
            );
        }, $requests);
    }

    private function loadFriendIds(string $currentUserId): array
    {
        $friendRows = Friendship::query()
            ->where('user_id', $currentUserId)
            ->orWhere('friend_id', $currentUserId)
            ->get(['user_id', 'friend_id']);

        $friendIds = [];
        foreach ($friendRows as $row) {
            $candidate = (string) ($row->user_id === $currentUserId ? $row->friend_id : $row->user_id);
            if ($candidate !== '' && $candidate !== $currentUserId) {
                $friendIds[$candidate] = true;
            }
        }
        return $friendIds;
    }

    private function pluckRequestIds(array $requests, string $profileKey): array
    {
        $ids = [];
        foreach ($requests as $request) {
            $id = (string) ($request->{$profileKey} ?? '');
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        return $ids;
    }

    private function profileEntry(array $profile, array $extra = []): array
    {
        $profileId = $this->resolveProfileId($profile);

        $firstName = trim((string) ($profile['first_name'] ?? ''));
        $lastName  = trim((string) ($profile['last_name']  ?? ''));
        $username  = trim((string) ($profile['username']   ?? ''));
        $email     = trim((string) ($profile['email']      ?? ''));

        if ($firstName !== '' || $lastName !== '') {
            $displayName = trim($firstName . ' ' . $lastName);
        } elseif ($username !== '') {
            $displayName = $username;
        } elseif ($email !== '') {
            $displayName = explode('@', $email)[0];
        } else {
            $displayName = 'Unknown User';
        }

        $initialsStr = mb_substr($firstName ?: $displayName, 0, 1);
        if ($lastName !== '') {
            $initialsStr .= mb_substr($lastName, 0, 1);
        } elseif (mb_strlen($initialsStr) < 2 && $username !== '') {
            $initialsStr .= mb_substr($username, 1, 1);
        }

        $status   = strtolower((string) ($profile['status'] ?? ''));
        $isActive = (bool) ($profile['is_online'] ?? false)
            || (bool) ($profile['is_active'] ?? false)
            || in_array($status, ['online', 'active'], true);

        return array_merge([
            'id'        => $profileId,
            'name'      => $displayName,
            'username'  => $username ?: 'user',
            'photo'     => (string) ($profile['profile_photo_url'] ?? ''),
            'initials'  => strtoupper($initialsStr ?: 'U'),
            'is_active' => $isActive,
        ], $extra);
    }
}
