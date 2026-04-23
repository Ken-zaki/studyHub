<?php

namespace App\Http\Controllers;

use App\Models\Friend;
use App\Models\FriendRequest;
use App\Models\User;
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

        $friends = collect();
        if ($currentUserId !== '') {
            $friends = Friend::query()
                ->where(function ($query) use ($currentUserId) {
                    $query->where('user_id', $currentUserId)
                        ->orWhere('friend_id', $currentUserId);
                })
                ->with(['user', 'friend'])
                ->get()
                ->map(function ($friend) use ($currentUserId) {
                    return (string) $friend->user_id === (string) $currentUserId 
                        ? $friend->friend 
                        : $friend->user;
                });
        }

        $otherUsers = collect();
        $pendingRequestIds = [];
        
        if ($currentUserId !== '') {
            $friendIds = $friends->pluck('id')->toArray();
            
            // Get all users except current user and friends
            $otherUsers = User::query()
                ->select('id', 'name')
                ->where('id', '!=', $currentUserId)
                ->whereNotIn('id', $friendIds)
                ->orderBy('name')
                ->get();
            
            // Get pending friend request IDs
            $pendingRequestIds = FriendRequest::query()
                ->where('sender_id', $currentUserId)
                ->where('status', 'pending')
                ->pluck('receiver_id')
                ->toArray();
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

        $incomingRequests = collect();
        $sentRequests = collect();

        if ($currentUserId !== '') {
            $incomingRequests = FriendRequest::query()
                ->where('receiver_id', $currentUserId)
                ->where('status', 'pending')
                ->with('sender')
                ->orderBy('created_at', 'desc')
                ->get();

            $sentRequests = FriendRequest::query()
                ->where('sender_id', $currentUserId)
                ->where('status', 'pending')
                ->with('receiver')
                ->orderBy('created_at', 'desc')
                ->get();
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
}
