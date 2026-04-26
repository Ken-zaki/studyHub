<?php

namespace App\Http\Controllers;

use App\Models\Friend;
use App\Models\FriendRequest;
use App\Models\User;
use App\Providers\SupabaseServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FriendController extends Controller
{
    /**
     * Display all friends
     */
    public function index(Request $request): View
    {
        $currentUserId = (string) $request->session()->get('user_id', '');
        $profiles = $this->loadProfiles();
        $profilesById = collect($profiles)->keyBy(fn (array $profile) => (string) ($profile['id'] ?? ''));

        $friends = collect();
        if ($currentUserId !== '') {
            $friendships = Friend::query()
                ->where(function ($query) use ($currentUserId) {
                    $query->where('user_id', $currentUserId)
                        ->orWhere('friend_id', $currentUserId);
                })
                ->get()
                ->map(function ($friendship) use ($currentUserId, $profilesById) {
                    $friendId = (string) ((string) $friendship->user_id === (string) $currentUserId
                        ? $friendship->friend_id
                        : $friendship->user_id);

                    return $this->profileToObject($profilesById->get($friendId), $friendId);
                });

            $friends = $friendships;
        }

        $otherUsers = collect();
        $pendingRequestIds = [];
        
        if ($currentUserId !== '') {
            $friendIds = $friends->pluck('id')->filter()->map(fn ($id) => (string) $id)->values()->all();
            
            $pendingRequestIds = FriendRequest::query()
                ->where(function ($query) use ($currentUserId) {
                    $query->where('sender_id', $currentUserId)
                        ->orWhere('receiver_id', $currentUserId);
                })
                ->where('status', 'pending')
                ->get()
                ->flatMap(function ($request) use ($currentUserId) {
                    return [
                        (string) $request->sender_id === (string) $currentUserId
                            ? (string) $request->receiver_id
                            : (string) $request->sender_id,
                    ];
                })
                ->unique()
                ->values()
                ->all();

            $excludedIds = array_values(array_unique(array_merge([$currentUserId], $friendIds, $pendingRequestIds)));

            // Get all users from Supabase profiles except current user, friends, and pending requests
            $otherUsers = collect($profiles)
                ->reject(fn (array $profile) => in_array((string) ($profile['id'] ?? ''), $excludedIds, true))
                ->map(fn (array $profile) => $this->profileToObject($profile, (string) ($profile['id'] ?? '')))
                ->sortBy(fn ($user) => strtolower((string) ($user->name ?? '')))
                ->values();
        } else {
            $otherUsers = collect();
        }

        return view('home.friend', [
            'currentUserId' => $currentUserId,
            'friends' => $friends,
            'otherUsers' => $otherUsers,
            'pendingRequestIds' => $pendingRequestIds,
        ]);
    }

    /**
     * Display friend requests
     */
    public function requests(Request $request): View
    {
        $currentUserId = (string) $request->session()->get('user_id', '');
        $profiles = $this->loadProfiles();
        $profilesById = collect($profiles)->keyBy(fn (array $profile) => (string) ($profile['id'] ?? ''));

        $incomingRequests = collect();
        $sentRequests = collect();

        if ($currentUserId !== '') {
            $incomingRequests = FriendRequest::query()
                ->where('receiver_id', $currentUserId)
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();

            $incomingRequests = $incomingRequests->map(function ($friendRequest) use ($profilesById) {
                $friendRequest->setRelation(
                    'sender',
                    $this->profileToObject($profilesById->get((string) $friendRequest->sender_id), (string) $friendRequest->sender_id)
                );
                $friendRequest->setRelation(
                    'receiver',
                    $this->profileToObject($profilesById->get((string) $friendRequest->receiver_id), (string) $friendRequest->receiver_id)
                );

                return $friendRequest;
            });

            $sentRequests = FriendRequest::query()
                ->where('sender_id', $currentUserId)
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();

            $sentRequests = $sentRequests->map(function ($friendRequest) use ($profilesById) {
                $friendRequest->setRelation(
                    'sender',
                    $this->profileToObject($profilesById->get((string) $friendRequest->sender_id), (string) $friendRequest->sender_id)
                );
                $friendRequest->setRelation(
                    'receiver',
                    $this->profileToObject($profilesById->get((string) $friendRequest->receiver_id), (string) $friendRequest->receiver_id)
                );

                return $friendRequest;
            });
        }

        return view('home.friend-req', [
            'currentUserId' => $currentUserId,
            'incomingRequests' => $incomingRequests,
            'sentRequests' => $sentRequests,
        ]);
    }

    /**
     * Send a friend request
     */
    public function sendRequest(Request $request): RedirectResponse
    {
        $currentUserId = (string) $request->session()->get('user_id', '');

        if ($currentUserId === '') {
            return back()->withErrors(['session' => 'Please log in first.']);
        }

        $validated = $request->validate([
            'receiver_id' => ['required', 'string', 'max:191'],
        ]);

        if ($validated['receiver_id'] === $currentUserId) {
            return back()->withErrors(['receiver_id' => 'You cannot send a friend request to yourself.']);
        }

        // Check if friend request already exists
        $existingRequest = FriendRequest::query()
            ->where(function ($query) use ($currentUserId, $validated) {
                $query->where('sender_id', $currentUserId)
                    ->where('receiver_id', $validated['receiver_id']);
            })
            ->orWhere(function ($query) use ($currentUserId, $validated) {
                $query->where('sender_id', $validated['receiver_id'])
                    ->where('receiver_id', $currentUserId);
            })
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return back()->withErrors(['receiver_id' => 'Friend request already exists.']);
        }

        // Check if already friends
        $alreadyFriends = Friend::query()
            ->where(function ($query) use ($currentUserId, $validated) {
                $query->where('user_id', $currentUserId)
                    ->where('friend_id', $validated['receiver_id']);
            })
            ->orWhere(function ($query) use ($currentUserId, $validated) {
                $query->where('user_id', $validated['receiver_id'])
                    ->where('friend_id', $currentUserId);
            })
            ->first();

        if ($alreadyFriends) {
            return back()->withErrors(['receiver_id' => 'You are already friends with this user.']);
        }

        FriendRequest::create([
            'sender_id' => $currentUserId,
            'receiver_id' => $validated['receiver_id'],
            'status' => 'pending',
        ]);

        return redirect()->route('friend-requests')->with('success', 'Friend request sent successfully.');
    }

    /**
     * Accept a friend request
     */
    public function acceptRequest(Request $request, $requestId): RedirectResponse
    {
        $currentUserId = (string) $request->session()->get('user_id', '');

        if ($currentUserId === '') {
            return back()->withErrors(['session' => 'Please log in first.']);
        }

        $friendRequest = FriendRequest::query()
            ->where('id', $requestId)
            ->where('receiver_id', $currentUserId)
            ->first();

        if (!$friendRequest) {
            return back()->withErrors(['request' => 'Friend request not found.']);
        }

        // Create friendship (both directions to make it easier to query)
        Friend::create([
            'user_id' => $friendRequest->sender_id,
            'friend_id' => $friendRequest->receiver_id,
        ]);

        // Update request status
        $friendRequest->update(['status' => 'accepted']);

        return redirect()->route('friend-requests')->with('success', 'Friend request accepted.');
    }

    /**
     * Reject a friend request
     */
    public function rejectRequest(Request $request, $requestId): RedirectResponse
    {
        $currentUserId = (string) $request->session()->get('user_id', '');

        if ($currentUserId === '') {
            return back()->withErrors(['session' => 'Please log in first.']);
        }

        $friendRequest = FriendRequest::query()
            ->where('id', $requestId)
            ->where('receiver_id', $currentUserId)
            ->first();

        if (!$friendRequest) {
            return back()->withErrors(['request' => 'Friend request not found.']);
        }

        $friendRequest->update(['status' => 'rejected']);

        return redirect()->route('friend-requests')->with('success', 'Friend request rejected.');
    }

    /**
     * Remove a friend
     */
    public function removeFriend(Request $request, $friendId): RedirectResponse
    {
        $currentUserId = (string) $request->session()->get('user_id', '');

        if ($currentUserId === '') {
            return back()->withErrors(['session' => 'Please log in first.']);
        }

        $friendship = Friend::query()
            ->where(function ($query) use ($currentUserId, $friendId) {
                $query->where('user_id', $currentUserId)
                    ->where('friend_id', $friendId);
            })
            ->orWhere(function ($query) use ($currentUserId, $friendId) {
                $query->where('user_id', $friendId)
                    ->where('friend_id', $currentUserId);
            })
            ->first();

        if (!$friendship) {
            return back()->withErrors(['friend' => 'Friendship not found.']);
        }

        $friendship->delete();

        return redirect()->route('friends')->with('success', 'Friend removed successfully.');
    }

    /**
     * Load all profiles from Supabase, with a fallback to the Laravel users table.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadProfiles(): array
    {
        $profiles = (new SupabaseServiceProvider())->getAllProfiles();

        if (is_array($profiles) && !empty($profiles)) {
            return $profiles;
        }

        return User::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(function (User $user): array {
                return [
                    'id' => (string) $user->id,
                    'name' => (string) $user->name,
                    'first_name' => '',
                    'last_name' => '',
                    'username' => '',
                    'profile_photo_url' => '',
                    'email' => '',
                ];
            })
            ->all();
    }

    /**
     * Convert a profile array into a simple object for Blade views.
     *
     * @param array<string, mixed>|null $profile
     */
    private function profileToObject(?array $profile, string $fallbackId = ''): object
    {
        $profile = $profile ?? [];

        $firstName = trim((string) ($profile['first_name'] ?? ''));
        $lastName = trim((string) ($profile['last_name'] ?? ''));
        $name = trim($firstName . ' ' . $lastName);

        if ($name === '') {
            $name = trim((string) ($profile['name'] ?? $profile['username'] ?? ''));
        }

        if ($name === '') {
            $name = 'User';
        }

        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = strtoupper(substr($parts[0] ?? 'U', 0, 1) . substr($parts[1] ?? '', 0, 1));

        return (object) [
            'id' => (string) ($profile['id'] ?? $fallbackId),
            'name' => $name,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => (string) ($profile['username'] ?? ''),
            'profile_photo_url' => (string) ($profile['profile_photo_url'] ?? ''),
            'email' => (string) ($profile['email'] ?? ''),
            'initials' => $initials,
        ];
    }
}
