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
        \Log::info('FriendRequest session user_id: ' . session('user_id'));

        if ($redirect = $this->requireAuth()) {
            return $redirect;
        }

        $currentUserId = $this->currentUserId();
        \Log::info('FriendRequest index - currentUserId: ' . $currentUserId);
        
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

        \Log::info('FriendRequest index - incoming: ' . count($incomingRequests) . ', outgoing: ' . count($outgoingRequests));
        \Log::debug('Outgoing requests data:', $outgoingRequests->toArray());

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

            $entry = $this->profileEntry($profile);
            \Log::debug('User profile entry', [
                'profile_id_field' => $profileId,
                'entry_id' => $entry['id'],
                'profile_keys' => array_keys($profile),
                'email' => ($profile['email'] ?? 'N/A'),
            ]);

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
        
        // Check if receiverId is a valid UUID format
        $isValidUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $receiverId);
        
        \Log::info('FriendRequest send attempt', [
            'sender_id' => $senderId,
            'receiver_id_param' => $receiverId,
            'receiver_is_uuid' => $isValidUuid ? 'YES' : 'NO',
        ]);

        // If receiverId is not a UUID, try to find the user by email
        if (!$isValidUuid) {
            \Log::info('Non-UUID receiverId detected, attempting to look up user', ['receiver_id' => $receiverId]);
            
            // Try to find the user profile by email or other identifier
            $provider = new SupabaseServiceProvider();
            $profiles = $provider->getAllProfiles();
            $foundProfile = null;
            
            foreach ($profiles as $profile) {
                // Check if the profile's email or username matches the receiverId
                if (($profile['email'] ?? '') === $receiverId || 
                    ($profile['username'] ?? '') === $receiverId) {
                    $foundProfile = $profile;
                    break;
                }
            }
            
            if ($foundProfile && isset($foundProfile['id'])) {
                $actualUuid = $foundProfile['id'];
                // Verify it's actually a UUID
                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $actualUuid)) {
                    \Log::info('Found actual UUID for receiver', ['email' => $receiverId, 'uuid' => $actualUuid]);
                    $receiverId = $actualUuid;
                    $isValidUuid = true;
                } else {
                    \Log::warning('Found profile but id is not a valid UUID', ['receiver_id' => $receiverId, 'profile_id' => $actualUuid]);
                    return back()->withErrors(['friend_request' => 'Unable to find valid user profile.']);
                }
            } else {
                \Log::warning('Could not find user profile for non-UUID receiverId', ['receiver_id' => $receiverId]);
                return back()->withErrors(['friend_request' => 'User not found.']);
            }
        }

        if ($receiverId === '' || $receiverId === $senderId) {
            return back()->withErrors(['friend_request' => 'Invalid friend request target.']);
        }

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

        $newRequest = FriendRequest::create([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'status' => 'pending',
        ]);
        
        \Log::info('FriendRequest created successfully', [
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'id' => $newRequest->id,
        ]);
        
        // Verify it was actually saved
        $verify = FriendRequest::find($newRequest->id);
        if ($verify) {
            \Log::info('Verification: Request found in database after creation');
        } else {
            \Log::error('Verification FAILED: Request NOT found in database after creation!');
        }

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
            );

            Friendship::firstOrCreate(
                [
                    'user_id' => $friendRequest->receiver_id,
                    'friend_id' => $friendRequest->sender_id,
                ],
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
        if (empty($requests)) {
            return [];
        }

        $profilesById = [];
        $profileIds = array_values(array_filter(array_map(
            fn ($request) => (string) ($request->{$profileKey} ?? ''),
            $requests
        )));

        \Log::debug('attachProfileMetadata - profileKey: ' . $profileKey . ', need to find ' . count($profileIds) . ' profiles');

        try {
            $allProfiles = $provider->getAllProfiles();
            \Log::debug('Got ' . count($allProfiles) . ' profiles from Supabase');
            
            foreach ($allProfiles as $profile) {
                $profileId = (string) ($profile['id'] ?? '');
                if ($profileId !== '' && in_array($profileId, $profileIds, true)) {
                    $profilesById[$profileId] = $profile;
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error fetching profiles from Supabase: ' . $e->getMessage());
        }

        \Log::debug('Found ' . count($profilesById) . ' matching profiles from Supabase');

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
        // IMPORTANT: The 'id' field in Supabase profiles should be the UUID
        // If it's not, we need to identify and use the correct UUID field
        $profileId = (string) ($profile['id'] ?? '');
        
        // Log the ENTIRE profile structure to understand the data
        static $loggedProfiles = 0;
        if ($loggedProfiles < 3) {  // Log only first 3 to avoid spam
            \Log::debug('Profile data structure', [
                'profile_id' => $profileId,
                'all_fields' => array_keys($profile),
                'sample_data' => [
                    'id' => $profile['id'] ?? null,
                    'email' => $profile['email'] ?? null,
                    'username' => $profile['username'] ?? null,
                    'first_name' => $profile['first_name'] ?? null,
                    'last_name' => $profile['last_name'] ?? null,
                ],
            ]);
            $loggedProfiles++;
        }
        
        // Validate that profileId is a UUID format
        $isValidUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $profileId);
        
        if (!$isValidUuid && !empty($profileId)) {
            \Log::warning('Profile id is not a valid UUID', [
                'profile_id' => $profileId,
                'email' => $profile['email'] ?? null,
            ]);
            
            // If profile['id'] is not a valid UUID, it might be that Supabase is returning
            // the wrong field. Try to find other UUID fields
            $possibleUuidFields = ['user_id', 'uid', 'uuid', 'auth_id'];
            $foundUuid = null;
            
            foreach ($possibleUuidFields as $field) {
                if (isset($profile[$field])) {
                    $candidateUuid = (string) $profile[$field];
                    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $candidateUuid)) {
                        $foundUuid = $candidateUuid;
                        \Log::info('Found valid UUID in alternative field', [
                            'field' => $field,
                            'uuid' => $foundUuid,
                        ]);
                        break;
                    }
                }
            }
            
            if ($foundUuid) {
                $profileId = $foundUuid;
            } else {
                \Log::error('Could not find valid UUID in profile', [
                    'profile_fields' => array_keys($profile),
                    'id_field_value' => $profileId,
                ]);
            }
        }
        
        // Log what we're getting to help debug ID issues
        if (empty($profileId)) {
            \Log::warning('Profile has no id field', ['profile_keys' => array_keys($profile)]);
        }
        
        $firstName = trim((string) ($profile['first_name'] ?? ''));
        $lastName = trim((string) ($profile['last_name'] ?? ''));
        $username = trim((string) ($profile['username'] ?? ''));
        $email = trim((string) ($profile['email'] ?? ''));
        
        // Build display name with fallbacks
        $displayName = '';
        if ($firstName || $lastName) {
            $displayName = trim($firstName . ' ' . $lastName);
        }
        if (!$displayName && $username) {
            $displayName = $username;
        }
        if (!$displayName && $email) {
            $displayName = explode('@', $email)[0];
        }
        if (!$displayName) {
            $displayName = 'User';
        }

        // Build initials
        $initialsStr = substr($firstName ?: $displayName, 0, 1);
        if ($lastName) {
            $initialsStr .= substr($lastName, 0, 1);
        }
        if (strlen($initialsStr) < 2 && $username) {
            $initialsStr .= substr($username, 1, 1);
        }

        $status = strtolower((string) ($profile['status'] ?? ''));
        $isActive = (bool) ($profile['is_online'] ?? false)
            || (bool) ($profile['is_active'] ?? false)
            || in_array($status, ['online', 'active'], true);

        return array_merge([
            'id' => $profileId,
            'name' => $displayName,
            'username' => $username ?: 'user',
            'photo' => (string) ($profile['profile_photo_url'] ?? ''),
            'initials' => strtoupper($initialsStr ?: 'U'),
            'is_active' => $isActive,
        ], $extra);
    }

}
