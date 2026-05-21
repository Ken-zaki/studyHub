<?php

namespace App\Http\Controllers;

use App\Models\CalendarShare;
use App\Models\CalendarShareRequest;
use App\Providers\SupabaseServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CalendarShareController extends Controller
{
    private function currentUserId(): string
    {
        return (string) session('user_id', '');
    }

    /**
     * Get user's friends (for sharing calendar with)
     */
    public function getFriendsForSharing()
    {
        $userId = $this->currentUserId();
        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $provider = new SupabaseServiceProvider();
            $allProfiles = $provider->getAllProfiles();
            $profilesById = [];
            foreach ($allProfiles as $profile) {
                $id = (string) ($profile['id'] ?? '');
                if ($id !== '') {
                    $profilesById[$id] = $profile;
                }
            }

            $friendIds = [];
            try {
                $friendsAsUser = \DB::table('friends')
                    ->where('user_id', $userId)
                    ->pluck('friend_id')
                    ->toArray();
                $friendsAsFriend = \DB::table('friends')
                    ->where('friend_id', $userId)
                    ->pluck('user_id')
                    ->toArray();
                $friendIds = array_unique(array_merge($friendsAsUser, $friendsAsFriend));
            } catch (\Exception $e) {
                \Log::warning('Friends table query failed: ' . $e->getMessage());
                $friendIds = [];
            }

            $activeShares = CalendarShare::where('owner_id', $userId)
                ->where('status', 'active')
                ->pluck('recipient_id')
                ->toArray();

            $pendingRequests = CalendarShareRequest::where('requester_id', $userId)
                ->where('status', 'pending')
                ->pluck('recipient_id')
                ->toArray();

            $friends = [];
            foreach ($friendIds as $friendId) {
                $friendId = (string) $friendId;
                $profile = $profilesById[$friendId] ?? null;

                if (!$profile) {
                    continue;
                }

                $firstName = trim((string) ($profile['first_name'] ?? ''));
                $lastName = trim((string) ($profile['last_name'] ?? ''));
                $name = trim($firstName . ' ' . $lastName);
                if (!$name) $name = (string) ($profile['username'] ?? 'Friend');

                $friends[] = [
                    'id'             => $friendId,
                    'name'           => $name,
                    'username'       => (string) ($profile['username'] ?? ''),
                    'photo'          => (string) ($profile['profile_photo_url'] ?? ''),
                    'initials'       => strtoupper(substr($firstName ?: $name, 0, 1) . substr($lastName, 0, 1)),
                    'sharing_status' => in_array($friendId, $activeShares) ? 'active'
                        : (in_array($friendId, $pendingRequests) ? 'pending' : 'none'),
                ];
            }

            return response()->json(['friends' => $friends]);
        } catch (\Exception $e) {
            \Log::error('getFriendsForSharing error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load friends: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Request to share calendar with a friend
     */
    public function requestShare(Request $request, string $friendId)
    {
        try {
            $userId = $this->currentUserId();
            if ($userId === '') {
                return response()->json(['error' => 'Not authenticated'], 401);
            }

            $friendId = (string) $friendId;
            if ($userId === $friendId) {
                return response()->json(['error' => 'Cannot share with yourself'], 400);
            }

            $existing = CalendarShare::where('owner_id', $userId)
                ->where('recipient_id', $friendId)
                ->where('status', 'active')
                ->exists();
            if ($existing) {
                return response()->json(['error' => 'Already sharing with this friend'], 400);
            }

            $pendingRequest = CalendarShareRequest::where('requester_id', $userId)
                ->where('recipient_id', $friendId)
                ->where('status', 'pending')
                ->first();

            if ($pendingRequest) {
                return response()->json(['error' => 'Request already pending'], 400);
            }

            CalendarShareRequest::create([
                'id'           => (string) Str::uuid(),
                'requester_id' => $userId,
                'recipient_id' => $friendId,
                'message'      => $request->input('message'),
                'status'       => 'pending',
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('requestShare error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to send share request: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Accept calendar share request
     */
    public function acceptShare(Request $request, string $requestId)
    {
        try {
            $userId = $this->currentUserId();
            if ($userId === '') {
                return response()->json(['error' => 'Not authenticated'], 401);
            }

            $shareRequest = CalendarShareRequest::find($requestId);
            if (!$shareRequest) {
                return response()->json(['error' => 'Request not found'], 404);
            }

            if ($shareRequest->recipient_id !== $userId) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            CalendarShare::create([
                'id'           => (string) Str::uuid(),
                'owner_id'     => $shareRequest->requester_id,
                'recipient_id' => $userId,
                'status'       => 'active',
            ]);

            $shareRequest->update(['status' => 'accepted']);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('acceptShare error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to accept share request: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reject calendar share request
     */
    public function rejectShare(Request $request, string $requestId)
    {
        try {
            $userId = $this->currentUserId();
            if ($userId === '') {
                return response()->json(['error' => 'Not authenticated'], 401);
            }

            $shareRequest = CalendarShareRequest::find($requestId);
            if (!$shareRequest) {
                return response()->json(['error' => 'Request not found'], 404);
            }

            if ($shareRequest->recipient_id !== $userId) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            $shareRequest->update(['status' => 'rejected']);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('rejectShare error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to reject share request: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get incoming share requests
     */
    public function getShareRequests()
    {
        try {
            $userId = $this->currentUserId();
            if ($userId === '') {
                return response()->json(['error' => 'Not authenticated'], 401);
            }

            $requests = CalendarShareRequest::where('recipient_id', $userId)
                ->where('status', 'pending')
                ->with('requester')
                ->orderByDesc('created_at')
                ->get();

            $provider = new SupabaseServiceProvider();
            $allProfiles = $provider->getAllProfiles();
            $profilesById = [];
            foreach ($allProfiles as $profile) {
                $id = (string) ($profile['id'] ?? '');
                if ($id !== '') {
                    $profilesById[$id] = $profile;
                }
            }

            $formattedRequests = [];
            foreach ($requests as $req) {
                $requesterId = (string) $req->requester_id;
                $profile = $profilesById[$requesterId] ?? null;

                $firstName = trim((string) ($profile['first_name'] ?? ''));
                $lastName = trim((string) ($profile['last_name'] ?? ''));
                $name = trim($firstName . ' ' . $lastName);
                if (!$name) $name = (string) ($profile['username'] ?? 'Friend');

                $formattedRequests[] = [
                    'id'                 => $req->id,
                    'requester_id'       => $requesterId,
                    'requester_name'     => $name,
                    'requester_username' => (string) ($profile['username'] ?? ''),
                    'requester_photo'    => (string) ($profile['profile_photo_url'] ?? ''),
                    'message'            => $req->message,
                    'created_at'         => $req->created_at,
                ];
            }

            return response()->json(['requests' => $formattedRequests]);
        } catch (\Exception $e) {
            \Log::error('getShareRequests error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load share requests: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get shared calendars (calendars I'm receiving from friends)
     */
    public function getSharedCalendars()
    {
        try {
            $userId = $this->currentUserId();
            if ($userId === '') {
                return response()->json(['error' => 'Not authenticated'], 401);
            }

            $shares = CalendarShare::where('recipient_id', $userId)
                ->where('status', 'active')
                ->with('owner')
                ->get();

            $provider = new SupabaseServiceProvider();
            $allProfiles = $provider->getAllProfiles();
            $profilesById = [];
            foreach ($allProfiles as $profile) {
                $id = (string) ($profile['id'] ?? '');
                if ($id !== '') {
                    $profilesById[$id] = $profile;
                }
            }

            $calendars = [];
            foreach ($shares as $share) {
                $ownerId = (string) $share->owner_id;
                $profile = $profilesById[$ownerId] ?? null;

                $firstName = trim((string) ($profile['first_name'] ?? ''));
                $lastName = trim((string) ($profile['last_name'] ?? ''));
                $name = trim($firstName . ' ' . $lastName);
                if (!$name) $name = (string) ($profile['username'] ?? 'Friend');

                $events = \DB::table('calendar_events')
                    ->where('user_id', $ownerId)
                    ->get(['id', 'title', 'event_date', 'event_time', 'description', 'category'])
                    ->toArray();

                $calendars[] = [
                    'id'              => $share->id,
                    'owner_id'        => $ownerId,
                    'owner_name'      => $name,
                    'owner_username'  => (string) ($profile['username'] ?? ''),
                    'owner_photo'     => (string) ($profile['profile_photo_url'] ?? ''),
                    'can_see_details' => $share->can_see_details,
                    'events'          => $events,
                ];
            }

            return response()->json(['calendars' => $calendars]);
        } catch (\Exception $e) {
            \Log::error('getSharedCalendars error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load shared calendars: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Revoke calendar sharing (stop sharing MY calendar with someone)
     */
    public function revokeShare(Request $request, string $recipientId)
    {
        try {
            $userId = $this->currentUserId();
            if ($userId === '') {
                return response()->json(['error' => 'Not authenticated'], 401);
            }

            $recipientId = (string) $recipientId;
            $share = CalendarShare::where('owner_id', $userId)
                ->where('recipient_id', $recipientId)
                ->first();

            if (!$share) {
                return response()->json(['error' => 'Share not found'], 404);
            }

            $share->update(['status' => 'revoked']);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('revokeShare error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to revoke share: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Stop receiving a friend's shared calendar
     */
    public function revokeReceivedShare(Request $request, string $ownerId)
    {
        try {
            $userId = $this->currentUserId();
            if ($userId === '') {
                return response()->json(['error' => 'Not authenticated'], 401);
            }

            $ownerId = (string) $ownerId;
            $share = CalendarShare::where('owner_id', $ownerId)
                ->where('recipient_id', $userId)
                ->first();

            if (!$share) {
                return response()->json(['error' => 'Share not found'], 404);
            }

            $share->update(['status' => 'revoked']);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('revokeReceivedShare error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to revoke received share: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Share calendar with all members of a study group.
     *
     * FIX: Previously this only created calendar_share_requests (pending),
     * but getGroupSharedCalendars() only reads from calendar_shares (active).
     * Now we write directly to calendar_shares as 'active' — group membership
     * is already an implicit trust relationship, no approval step needed.
     */
    public function shareWithGroup(Request $request, string $groupId)
    {
        try {
            $userId = $this->currentUserId();
            if ($userId === '') {
                return response()->json(['error' => 'Not authenticated'], 401);
            }

            $groupId = (string) $groupId;

            // Verify user is a member of the group
            $isMember = \DB::table('study_group_members')
                ->where('group_id', $groupId)
                ->where('user_id', $userId)
                ->exists();

            if (!$isMember) {
                return response()->json(['error' => 'You are not a member of this group'], 403);
            }

            // Get all group members except current user
            $groupMembers = \DB::table('study_group_members')
                ->where('group_id', $groupId)
                ->where('user_id', '!=', $userId)
                ->pluck('user_id')
                ->toArray();

            if (empty($groupMembers)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No other members in this group',
                ]);
            }

            $message = $request->input('message', '');
            $count = 0;

            foreach ($groupMembers as $memberId) {
                $memberId = (string) $memberId;

                // Skip if already actively shared with this member
                $existingShare = CalendarShare::where('owner_id', $userId)
                    ->where('recipient_id', $memberId)
                    ->where('status', 'active')
                    ->exists();

                if ($existingShare) {
                    continue;
                }

                // Write directly to calendar_shares as active.
                // Use updateOrCreate to safely handle any old revoked/pending rows.
                CalendarShare::updateOrCreate(
                    [
                        'owner_id'     => $userId,
                        'recipient_id' => $memberId,
                    ],
                    [
                        'id'              => (string) Str::uuid(),
                        'status'          => 'active',
                        'can_see_details' => true,
                    ]
                );

                $count++;
            }

            // Post a notification message to the group chat
            try {
                \DB::table('group_messages')->insert([
                    'id'         => (string) Str::uuid(),
                    'group_id'   => $groupId,
                    'user_id'    => $userId,
                    'message'    => json_encode([
                        'type'    => 'calendar_share',
                        'user_id' => $userId,
                        'message' => $message ?: 'Shared their calendar with the group',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                \Log::warning('Failed to post calendar share message to group: ' . $e->getMessage());
                // Non-fatal — shares were already created successfully
            }

            return response()->json([
                'success' => true,
                'message' => "Calendar shared with {$count} group member" . ($count !== 1 ? 's' : ''),
            ]);

        } catch (\Exception $e) {
            \Log::error('shareWithGroup error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to share with group: ' . $e->getMessage()], 500);
        }
    }
}
