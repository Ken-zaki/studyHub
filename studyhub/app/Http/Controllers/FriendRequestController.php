<?php

namespace App\Http\Controllers;

use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Providers\SupabaseServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FriendRequestController extends Controller
{
    public function index()
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $currentUserId = $this->currentUserId();
        $provider      = new SupabaseServiceProvider();

        // Load ALL profiles once — service key used inside provider, bypasses RLS
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

    public function send(Request $request, string $receiverId): RedirectResponse
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $senderId   = $this->currentUserId();
        $receiverId = trim($receiverId);
        $provider   = new SupabaseServiceProvider();

        // Profiles always have UUID ids (linked to auth.users).
        // If somehow a non-UUID arrives, try resolving by email/username.
        if (!$this->isValidUuid($receiverId)) {
            $foundProfile = null;

            // Try email first
            $foundProfile = $provider->getProfileByEmail($receiverId);
            
            // Fall back to username
            if (!$foundProfile) {
                $foundProfile = $provider->getProfileByUsername($receiverId);
            }

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

        // Verify receiver profile exists
        $receiverProfile = $provider->getProfileById($receiverId);
        if (!$receiverProfile) {
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
            return back()->with('status', 'Friend request sent.');
        }

        // Check reverse direction — only block if PENDING
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

        return back()->with('status', 'Friend request sent.');
    }

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

        DB::transaction(function () use ($friendRequest) {
            $friendRequest->update([
                'status'       => 'accepted',
                'responded_at' => now(),
            ]);

            // NOTE: friends table has no accepted_at column — only user_id + friend_id
            Friendship::firstOrCreate([
                'user_id'   => $friendRequest->sender_id,
                'friend_id' => $friendRequest->receiver_id,
            ]);

            Friendship::firstOrCreate([
                'user_id'   => $friendRequest->receiver_id,
                'friend_id' => $friendRequest->sender_id,
            ]);
        });

        return redirect()->route('friend-requests', ['tab' => 'friends'])
            ->with('status', 'Friend request accepted.');
    }

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

        return redirect()->route('friend-requests', ['tab' => 'requests'])
            ->with('status', 'Friend request declined.');
    }

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

        return redirect()->route('friend-requests', ['tab' => 'friends'])
            ->with('status', 'Friend removed.');
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