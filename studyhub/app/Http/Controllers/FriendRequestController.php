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
        $provider = new SupabaseServiceProvider();

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

        $friendIds = $this->loadFriendIds($currentUserId);
        $pendingOutgoingIds = $this->pluckRequestIds($outgoingRequests->all(), 'receiver_id');
        $pendingIncomingIds = $this->pluckRequestIds($incomingRequests->all(), 'sender_id');

        $discoverProfiles = [];
        $friends = [];

        foreach ($provider->getAllProfiles() as $profile) {
            $profileId = (string) ($profile['id'] ?? '');
            if ($profileId === '' || $profileId === $currentUserId) {
                continue;
            }

            if (isset($friendIds[$profileId])) {
                $friends[] = $this->profileEntry($profile, [
                    'relationship' => 'friends',
                ]);
                continue;
            }

            $discoverProfiles[] = $this->profileEntry($profile, [
                'relationship' => isset($pendingIncomingIds[$profileId])
                    ? 'pending_incoming'
                    : (isset($pendingOutgoingIds[$profileId]) ? 'pending_outgoing' : 'available'),
            ]);
        }

        return view('home.friend-req', [
            'activeNav' => 'friend-requests',
            'incomingRequests' => $this->attachProfileMetadata($incomingRequests->all(), $provider, 'sender_id'),
            'outgoingRequests' => $this->attachProfileMetadata($outgoingRequests->all(), $provider, 'receiver_id'),
            'discoverProfiles' => $discoverProfiles,
            'friends' => $friends,
        ]);
    }

    public function send(Request $request, string $receiverId): RedirectResponse
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $senderId = $this->currentUserId();
        $receiverId = trim($receiverId);

        if ($receiverId === '' || $receiverId === $senderId) {
            return back()->withErrors(['friend_request' => 'Invalid friend request target.']);
        }

        $provider = new SupabaseServiceProvider();

        if (Friendship::areFriends($senderId, $receiverId)) {
            return back()->with('status', 'You are already friends.');
        }

        $existingRequest = FriendRequest::query()
            ->where('sender_id', $senderId)
            ->where('receiver_id', $receiverId)
            ->first();

        if ($existingRequest) {
            if ($existingRequest->status === 'pending') {
                return back()->with('status', 'Friend request already sent.');
            }

            $existingRequest->update([
                'status' => 'pending',
                'responded_at' => null,
            ]);

            return back()->with('status', 'Friend request sent.');
        }

        $pendingRequest = FriendRequest::query()
            ->between($senderId, $receiverId)
            ->where('status', 'pending')
            ->first();

        if ($pendingRequest) {
            if ($pendingRequest->sender_id === $senderId) {
                return back()->with('status', 'Friend request already sent.');
            }

            return back()->with('status', 'They already sent you a friend request.');
        }

        FriendRequest::create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'status' => 'pending',
        ]);

        return back()->with('status', 'Friend request sent.');
    }

    public function accept(FriendRequest $friendRequest): RedirectResponse
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $currentUserId = $this->currentUserId();

        if ($friendRequest->receiver_id !== $currentUserId) {
            abort(403, 'You cannot accept this request.');
        }

        if ($friendRequest->status !== 'pending') {
            return back()->with('status', 'This request has already been processed.');
        }

        DB::transaction(function () use ($friendRequest) {
            $friendRequest->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            Friendship::firstOrCreate(
                [
                    'user_id' => $friendRequest->sender_id,
                    'friend_id' => $friendRequest->receiver_id,
                ],
                [
                    'accepted_at' => now(),
                ]
            );

            Friendship::firstOrCreate(
                [
                    'user_id' => $friendRequest->receiver_id,
                    'friend_id' => $friendRequest->sender_id,
                ],
                [
                    'accepted_at' => now(),
                ]
            );
        });

        return redirect()->route('friend-requests', ['tab' => 'friends'])->with('status', 'Friend request accepted.');
    }

    public function decline(FriendRequest $friendRequest): RedirectResponse
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $currentUserId = $this->currentUserId();

        if ($friendRequest->receiver_id !== $currentUserId) {
            abort(403, 'You cannot decline this request.');
        }

        if ($friendRequest->status !== 'pending') {
            return back()->with('status', 'This request has already been processed.');
        }

        $friendRequest->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);

        return redirect()->route('friend-requests', ['tab' => 'requests'])->with('status', 'Friend request declined.');
    }

    public function cancel(FriendRequest $friendRequest): RedirectResponse
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $currentUserId = $this->currentUserId();

        if ($friendRequest->sender_id !== $currentUserId) {
            abort(403, 'You can only cancel your own friend requests.');
        }

        if ($friendRequest->status !== 'pending') {
            return back()->with('status', 'This request has already been processed.');
        }

        $friendRequest->delete();

        return redirect()->route('friend-requests', ['tab' => 'requests'])->with('status', 'Friend request cancelled.');
    }

    public function remove(string $friendId): RedirectResponse
    {
        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $currentUserId = $this->currentUserId();
        $friendId = trim($friendId);

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

        return redirect()->route('friend-requests', ['tab' => 'friends'])->with('status', 'Friend removed.');
    }

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

    private function attachProfileMetadata(array $requests, SupabaseServiceProvider $provider, string $profileKey): array
    {
        $profilesById = [];
        $profileIds = array_values(array_filter(array_map(
            fn ($request) => (string) ($request->{$profileKey} ?? ''),
            $requests
        )));

        foreach ($provider->getAllProfiles() as $profile) {
            $profileId = (string) ($profile['id'] ?? '');
            if ($profileId !== '' && in_array($profileId, $profileIds, true)) {
                $profilesById[$profileId] = $profile;
            }
        }

        return array_map(function (FriendRequest $request) use ($profilesById, $profileKey): array {
            $profile = $profilesById[(string) $request->{$profileKey}] ?? [];
            return array_merge([
                'request' => $request,
            ], $this->profileEntry($profile, [
                'id' => (string) ($profile['id'] ?? $request->{$profileKey}),
            ]));
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
        $firstName = trim((string) ($profile['first_name'] ?? ''));
        $lastName = trim((string) ($profile['last_name'] ?? ''));
        $displayName = trim($firstName . ' ' . $lastName);

        if ($displayName === '') {
            $displayName = trim((string) ($profile['username'] ?? '')) ?: 'User';
        }

        $status = strtolower((string) ($profile['status'] ?? ''));
        $isActive = (bool) ($profile['is_online'] ?? false)
            || (bool) ($profile['is_active'] ?? false)
            || in_array($status, ['online', 'active'], true);

        return array_merge([
            'id' => (string) ($profile['id'] ?? ''),
            'name' => $displayName,
            'username' => (string) ($profile['username'] ?? ''),
            'photo' => (string) ($profile['profile_photo_url'] ?? ''),
            'initials' => strtoupper(substr($firstName ?: $displayName, 0, 1) . substr($lastName, 0, 1)),
            'is_active' => $isActive,
        ], $extra);
    }

}
