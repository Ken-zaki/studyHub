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

        return view('home.friend-req', [
            'activeNav' => 'friend-requests',
            'incomingRequests' => $this->attachProfileMetadata($incomingRequests->all(), $provider, 'sender_id'),
            'outgoingRequests' => $this->attachProfileMetadata($outgoingRequests->all(), $provider, 'receiver_id'),
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
        if (!$provider->getProfileById($receiverId)) {
            return back()->withErrors(['friend_request' => 'That profile could not be found.']);
        }

        if (Friendship::areFriends($senderId, $receiverId)) {
            return back()->with('status', 'You are already friends.');
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

        return back()->with('status', 'Friend request accepted.');
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

        return back()->with('status', 'Friend request declined.');
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
            $firstName = trim((string) ($profile['first_name'] ?? ''));
            $lastName = trim((string) ($profile['last_name'] ?? ''));
            $displayName = trim($firstName . ' ' . $lastName);

            if ($displayName === '') {
                $displayName = trim((string) ($profile['username'] ?? '')) ?: 'User';
            }

            return [
                'request' => $request,
                'id' => (string) ($profile['id'] ?? $request->{$profileKey}),
                'name' => $displayName,
                'photo' => (string) ($profile['profile_photo_url'] ?? ''),
                'initials' => strtoupper(substr($firstName ?: $displayName, 0, 1) . substr($lastName, 0, 1)),
            ];
        }, $requests);
    }
}
