<?php

namespace App\Http\Controllers;

use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
use App\Models\Friendship;
use App\Providers\SupabaseServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudyGroupController extends Controller
{
    /**
     * Get the current user's ID from session (NOT Auth::id()).
     */
    private function currentUserId(): string
    {
        return (string) session('user_id', '');
    }

    /**
     * Show the study groups page.
     */
    public function index()
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return redirect()->route('login');
        }

        $groups = StudyGroup::whereHas('members', fn($q) => $q->where('user_id', $userId))
            ->withCount('members')
            ->orderByDesc('created_at')
            ->get();

        $friendsAsUser   = Friendship::where('user_id', $userId)->pluck('friend_id')->toArray();
        $friendsAsFriend = Friendship::where('friend_id', $userId)->pluck('user_id')->toArray();
        $friendIds       = array_unique(array_merge($friendsAsUser, $friendsAsFriend));

        $provider = new SupabaseServiceProvider();
        $profiles = $provider->getProfilesByIds($friendIds);

        $profilesById = [];

        foreach ($profiles as $profile) {
            $id = (string) ($profile['id'] ?? '');

            if ($id !== '') {
                $profilesById[$id] = $profile;
            }
        }

        $friends = [];
        foreach ($friendIds as $friendId) {
            $friendId = (string) $friendId;

            if (!isset($profilesById[$friendId])) {
                $friends[] = [
                    'id'       => $friendId,
                    'name'     => 'Friend',
                    'username' => '',
                    'photo'    => '',
                    'initials' => 'FR',
                ];
                continue;
            }

            $profile   = $profilesById[$friendId];
            $firstName = trim((string) ($profile['first_name'] ?? ''));
            $lastName  = trim((string) ($profile['last_name']  ?? ''));
            $name      = trim($firstName . ' ' . $lastName);

            if ($name === '') {
                $name = trim((string) ($profile['username'] ?? '')) ?: 'Friend';
            }

            $friends[] = [
                'id'       => $friendId,
                'name'     => $name,
                'username' => (string) ($profile['username'] ?? ''),
                'photo'    => (string) ($profile['profile_photo_url'] ?? ''),
                'initials' => strtoupper(
                    substr($firstName ?: $name, 0, 1) . substr($lastName, 0, 1)
                ),
            ];
        }

        return view('home.study-groups', [
            'groups' => $groups,
            'friends' => $friends,
            'activeNav' => 'study-groups',
        ]);
    }

    /**
     * Get user's study groups as JSON (for calendar sharing)
     */
    public function getGroupsJson()
    {
        $userId = $this->currentUserId();
        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $groups = StudyGroup::whereHas('members', fn($q) => $q->where('user_id', $userId))
            ->withCount('members')
            ->orderByDesc('created_at')
            ->get();

        $formatted = [];
        foreach ($groups as $group) {
            $formatted[] = [
                'id'           => (string) $group->id,
                'name'         => (string) $group->name,
                'description'  => (string) ($group->description ?? ''),
                'is_private'   => (string) $group->is_private,
                'photo'        => (string) ($group->photo_url ?? ''),
                'member_count' => $group->members_count ?? 0,
            ];
        }

        return response()->json(['groups' => $formatted]);
    }

    /**
     * Get shared calendars from group members that have shared with the current user.
     *
     * FIX: Removed the stale "pending share requests" block — after the CalendarShareController
     * fix, shareWithGroup() writes directly to calendar_shares (active), so there are no
     * pending requests to display here. Showing them caused phantom/duplicate entries.
     */
    public function getGroupSharedCalendars(string $groupId)
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
                return response()->json(['error' => 'Not a member of this group'], 403);
            }

            // Get all group members except current user
            $groupMembers = \DB::table('study_group_members')
                ->where('group_id', $groupId)
                ->where('user_id', '!=', $userId)
                ->pluck('user_id')
                ->map(fn($id) => (string) $id)
                ->toArray();

            // Load Supabase profiles
            $provider = new SupabaseServiceProvider();
            $allProfiles = $provider->getAllProfiles();
            $profilesById = [];
            foreach ($allProfiles as $profile) {
                $id = (string) ($profile['id'] ?? '');
                if ($id !== '') {
                    $profilesById[$id] = $profile;
                }
            }

            $sharedCalendars = [];

            // Also include the current user's own calendar if they have shared it with this group
            $currentUserHasShared = \DB::table('calendar_shares')
                ->where('owner_id', $userId)
                ->whereIn('recipient_id', $groupMembers)
                ->where('status', 'active')
                ->exists();

            if ($currentUserHasShared) {
                $profile   = $profilesById[$userId] ?? [];
                $firstName = trim((string) ($profile['first_name'] ?? ''));
                $lastName  = trim((string) ($profile['last_name'] ?? ''));
                $name      = trim($firstName . ' ' . $lastName) ?: (string) ($profile['username'] ?? 'You');

                $events = \DB::table('calendar_events')
                    ->where('user_id', $userId)
                    ->orderBy('event_date')
                    ->get(['id', 'title', 'event_date', 'event_time', 'description', 'category'])
                    ->toArray();

                $sharedCalendars[] = [
                    'owner_id'       => $userId,
                    'owner_name'     => $name . ' (You)',
                    'owner_username' => (string) ($profile['username'] ?? ''),
                    'owner_photo'    => (string) ($profile['profile_photo_url'] ?? ''),
                    'events'         => $events,
                    'status'         => 'active',
                ];
            }

            // Check for active calendars shared FROM other group members TO current user
            foreach ($groupMembers as $memberId) {
                $share = \DB::table('calendar_shares')
                    ->where('owner_id', $memberId)
                    ->where('recipient_id', $userId)
                    ->where('status', 'active')
                    ->first();

                if (!$share) {
                    continue;
                }

                $profile   = $profilesById[$memberId] ?? [];
                $firstName = trim((string) ($profile['first_name'] ?? ''));
                $lastName  = trim((string) ($profile['last_name'] ?? ''));
                $name      = trim($firstName . ' ' . $lastName) ?: (string) ($profile['username'] ?? 'Member');

                $events = \DB::table('calendar_events')
                    ->where('user_id', $memberId)
                    ->orderBy('event_date')
                    ->get(['id', 'title', 'event_date', 'event_time', 'description', 'category'])
                    ->toArray();

                $sharedCalendars[] = [
                    'owner_id'       => $memberId,
                    'owner_name'     => $name,
                    'owner_username' => (string) ($profile['username'] ?? ''),
                    'owner_photo'    => (string) ($profile['profile_photo_url'] ?? ''),
                    'events'         => $events,
                    'status'         => 'active',
                ];
            }

            return response()->json(['calendars' => $sharedCalendars]);

        } catch (\Exception $e) {
            \Log::error('getGroupSharedCalendars error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to load shared calendars: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Create a new study group.
     */
    public function store(Request $request)
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated. Please log in again.'], 401);
        }

        try {
            $request->validate([
                'name'       => 'required|string|max:255',
                'subject'    => 'nullable|string|max:255',
                'members'    => 'nullable|array',
                'members.*'  => ['nullable', 'string', 'regex:/^[0-9a-f\-]{36}$/i'],
                'is_private' => 'nullable|in:0,1',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Validation failed.', 'errors' => $e->errors()], 422);
        }

        try {
            $isPrivate = (int) $request->input('is_private', 0);

            $group = StudyGroup::create([
                'id'          => (string) Str::uuid(),
                'name'        => $request->input('name'),
                'subject'     => $request->input('subject'),
                'description' => null,
                'is_public'   => $isPrivate === 0,
                'created_by'  => $userId,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to create group.', 'message' => $e->getMessage()], 500);
        }

        try {
            StudyGroupMember::create([
                'id'       => (string) Str::uuid(),
                'group_id' => $group->id,
                'user_id'  => $userId,
                'role'     => 'admin',
            ]);

            foreach (($request->input('members') ?? []) as $memberId) {
                $memberId = (string) $memberId;
                if ($memberId !== '' && $memberId !== $userId) {
                    StudyGroupMember::firstOrCreate(
                        ['group_id' => $group->id, 'user_id' => $memberId],
                        ['id' => (string) Str::uuid(), 'role' => 'member']
                    );
                }
            }
        } catch (\Throwable $e) {
            return response()->json([
                'group' => [
                    'id'      => $group->id,
                    'name'    => $group->name,
                    'subject' => $group->subject,
                ],
                'warning' => 'Group created but some members could not be added: ' . $e->getMessage(),
            ]);
        }

        $notifRows = [];
        foreach (($request->input('members') ?? []) as $memberId) {
            $memberId = (string) $memberId;
            if ($memberId === '' || $memberId === $userId) continue;

            $adderProfile = (new SupabaseServiceProvider())->getProfileById($userId);
            $adderName = trim(($adderProfile['first_name'] ?? '') . ' ' . ($adderProfile['last_name'] ?? ''));
            if (!$adderName) $adderName = $adderProfile['username'] ?? 'Someone';

            $notifRows[] = [
                'user_id'     => $memberId,
                'source_type' => 'study_group',
                'source_id'   => $group->id,
                'trigger'     => 'added_to_group',
                'title'       => "👥 {$adderName} added you to a study group",
                'body'        => $group->name . ($group->subject ? " · {$group->subject}" : ''),
                'icon'        => '👥',
                'urgency'     => 'info',
            ];
        }
        $this->insertNotifications($notifRows);

        return response()->json([
            'group' => [
                'id'         => $group->id,
                'name'       => $group->name,
                'subject'    => $group->subject,
                'is_private' => !$group->is_public,
            ],
        ]);
    }

    /**
     * Update a study group (rename, etc.).
     */
    public function update(Request $request, string $groupId)
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $group = StudyGroup::find($groupId);

        if (!$group) {
            return response()->json(['error' => 'Group not found'], 404);
        }

        if ($group->created_by !== $userId) {
            return response()->json(['error' => 'Only the group creator can rename the group'], 403);
        }

        $request->validate([
            'name'    => 'sometimes|string|max:255',
            'subject' => 'sometimes|nullable|string|max:255',
        ]);

        if ($request->has('name')) {
            $group->name = $request->input('name');
        }
        if ($request->has('subject')) {
            $group->subject = $request->input('subject');
        }

        $group->save();

        return response()->json([
            'success' => true,
            'group'   => [
                'id'      => $group->id,
                'name'    => $group->name,
                'subject' => $group->subject,
            ],
        ]);
    }

    /**
     * Upload / change the group photo.
     */
    public function updatePhoto(Request $request, string $groupId)
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $group = StudyGroup::find($groupId);

        if (!$group) {
            return response()->json(['error' => 'Group not found'], 404);
        }

        if ($group->created_by !== $userId) {
            return response()->json(['error' => 'Only the group creator can change the photo'], 403);
        }

        $request->validate([
            'photo' => 'required|image|max:4096',
        ]);

        if ($group->photo_path) {
            Storage::disk('public')->delete($group->photo_path);
        }

        $path     = $request->file('photo')->store('group_photos', 'public');
        $photoUrl = asset('storage/' . $path);

        $group->photo_path = $path;
        $group->photo      = $photoUrl;
        $group->save();

        return response()->json([
            'success'   => true,
            'photo_url' => $photoUrl,
        ]);
    }

    /**
     * Return members of a group as JSON, with Supabase profile data.
     */
    public function members(string $groupId)
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $isMember = StudyGroupMember::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->exists();

        if (!$isMember) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $group = StudyGroup::find($groupId);
        if (!$group) {
            return response()->json(['error' => 'Group not found'], 404);
        }

        $memberRows = StudyGroupMember::where('group_id', $groupId)->get();

        if ($memberRows->isEmpty()) {
            return response()->json(['members' => []]);
        }

        $userIds = $memberRows->pluck('user_id')->map(fn($id) => (string) $id)->unique()->values()->toArray();

        $provider     = new SupabaseServiceProvider();
        $allProfiles  = $provider->getAllProfiles();
        $profilesById = [];
        foreach ($allProfiles as $profile) {
            $pid = (string) ($profile['id'] ?? '');
            if ($pid !== '') {
                $profilesById[$pid] = $profile;
            }
        }

        $members = [];
        foreach ($memberRows as $row) {
            $memberId = (string) $row->user_id;
            $profile  = $profilesById[$memberId] ?? null;

            $firstName = trim((string) ($profile['first_name'] ?? ''));
            $lastName  = trim((string) ($profile['last_name']  ?? ''));
            $name      = trim($firstName . ' ' . $lastName);

            if ($name === '') {
                $name = trim((string) ($profile['username'] ?? '')) ?: 'Member';
            }

            $members[] = [
                'id'         => $memberId,
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'name'       => $name,
                'username'   => (string) ($profile['username'] ?? ''),
                'photo'      => (string) ($profile['profile_photo_url']
                                ?? $profile['avatar_url']
                                ?? $profile['photo_url']
                                ?? ''),
                'role'       => $row->role,
                'is_owner'   => $memberId === (string) $group->created_by,
            ];
        }

        usort($members, function ($a, $b) {
            if ($a['is_owner'] !== $b['is_owner']) {
                return $a['is_owner'] ? -1 : 1;
            }
            if ($a['role'] !== $b['role']) {
                return $a['role'] === 'admin' ? -1 : 1;
            }
            return strcmp($a['name'], $b['name']);
        });

        return response()->json(['members' => $members]);
    }

    /**
     * Delete a study group.
     */
    public function destroy(string $groupId)
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $group = StudyGroup::find($groupId);

        if (!$group) {
            return response()->json(['error' => 'Group not found'], 404);
        }

        if ($group->created_by !== $userId) {
            return response()->json(['error' => 'Only the group creator can delete the group'], 403);
        }

        try {
            StudyGroupMember::where('group_id', $groupId)->delete();
            $group->delete();

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to delete group: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get friends as JSON (for AJAX modal population).
     */
    public function getFriends()
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $friendsAsUser   = Friendship::where('user_id', $userId)->pluck('friend_id')->toArray();
        $friendsAsFriend = Friendship::where('friend_id', $userId)->pluck('user_id')->toArray();
        $friendIds       = array_unique(array_merge($friendsAsUser, $friendsAsFriend));

        $provider = new SupabaseServiceProvider();
        $profiles = $provider->getProfilesByIds($friendIds);

        $profilesById = [];

        foreach ($profiles as $profile) {
            $id = (string) ($profile['id'] ?? '');

            if ($id !== '') {
                $profilesById[$id] = $profile;
            }
        }

        $friends = [];
        foreach ($friendIds as $friendId) {
            $friendId = (string) $friendId;

            if (!isset($profilesById[$friendId])) {
                $friends[] = [
                    'id'       => $friendId,
                    'name'     => 'Friend',
                    'username' => '',
                    'photo'    => '',
                    'initials' => 'FR',
                ];
                continue;
            }

            $profile   = $profilesById[$friendId];
            $firstName = trim((string) ($profile['first_name'] ?? ''));
            $lastName  = trim((string) ($profile['last_name']  ?? ''));
            $name      = trim($firstName . ' ' . $lastName);

            if ($name === '') {
                $name = trim((string) ($profile['username'] ?? '')) ?: 'Friend';
            }

            $friends[] = [
                'id'       => $friendId,
                'name'     => $name,
                'username' => (string) ($profile['username'] ?? ''),
                'photo'    => (string) ($profile['profile_photo_url'] ?? ''),
                'initials' => strtoupper(
                    substr($firstName ?: $name, 0, 1) . substr($lastName, 0, 1)
                ),
            ];
        }

        return response()->json(['friends' => $friends]);
    }

    /**
     * Return messages for a group (JSON).
     */
    public function messages(string $groupId)
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $isMember = StudyGroupMember::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->exists();

        if (!$isMember) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $rows = DB::table('group_messages')
            ->where('group_id', $groupId)
            ->orderBy('created_at')
            ->get();

        $provider     = new SupabaseServiceProvider();
        $profileCache = [];

        $messages = $rows->map(function ($row) use ($provider, &$profileCache) {
            $senderId = (string) ($row->user_id ?? '');

            if ($senderId !== '' && !isset($profileCache[$senderId])) {
                $profileCache[$senderId] = $provider->getProfileById($senderId);
            }

            $profile = $profileCache[$senderId] ?? null;

            return [
                'id'           => $row->id,
                'user_id'      => $row->user_id,
                'message'      => $row->message,
                'created_at'   => $row->created_at,
                'sender_first' => $profile['first_name'] ?? null,
                'sender_last'  => $profile['last_name']  ?? null,
                'sender_photo' => $profile['profile_photo_url']
                              ?? $profile['avatar_url']
                              ?? $profile['photo_url']
                              ?? $profile['avatar']
                              ?? null,
                'attachments'  => DB::table('group_message_attachments')
                    ->where('message_id', $row->id)
                    ->get()
                    ->map(fn($a) => [
                        'name' => $a->file_name,
                        'url'  => $a->file_url,
                        'size' => $a->file_size,
                        'type' => $a->attachment_type,
                    ])
                    ->toArray(),
            ];
        });

        return response()->json(['messages' => $messages]);
    }

    /**
     * Post a message (text + optional file attachments).
     */
    public function sendMessage(Request $request, string $groupId)
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $isMember = StudyGroupMember::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->exists();

        if (!$isMember) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $request->validate([
            'message'       => 'nullable|string|max:5000',
            'attachments'   => 'nullable|array|max:10',
            'attachments.*' => 'file|max:51200',
        ]);

        $msgId = (string) Str::uuid();

        $members = StudyGroupMember::where('group_id', $groupId)
            ->where('user_id', '!=', $userId)
            ->pluck('user_id');

        $group         = StudyGroup::find($groupId);
        $senderProfile = (new SupabaseServiceProvider())->getProfileById($userId);
        $senderName    = trim(($senderProfile['first_name'] ?? '') . ' ' . ($senderProfile['last_name'] ?? ''));
        if (!$senderName) $senderName = $senderProfile['username'] ?? 'Someone';

        $preview = $request->input('message')
            ? Str::limit($request->input('message'), 60)
            : '📎 Sent an attachment';

        $notifRows = [];
        foreach ($members as $memberId) {
            $notifRows[] = [
                'user_id'     => (string) $memberId,
                'source_type' => 'study_group',
                'source_id'   => $groupId,
                'trigger'     => 'new_group_message' . $msgId,
                'title'       => "💬 New message in {$group->name}",
                'body'        => "{$senderName}: {$preview}",
                'icon'        => '💬',
                'urgency'     => 'info',
            ];
        }
        $this->insertNotifications($notifRows);

        DB::table('group_messages')->insert([
            'id'         => $msgId,
            'group_id'   => $groupId,
            'user_id'    => $userId,
            'message'    => $request->input('message') ?? '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($request->file('attachments', []) as $file) {
            $isImage = str_starts_with($file->getMimeType(), 'image/');
            $folder  = $isImage ? 'group_images' : 'group_files';
            $path    = $file->store($folder, 'public');
            $url     = asset('storage/' . $path);

            DB::table('group_message_attachments')->insert([
                'id'              => (string) Str::uuid(),
                'message_id'      => $msgId,
                'file_name'       => $file->getClientOriginalName(),
                'file_url'        => $url,
                'file_size'       => $file->getSize(),
                'attachment_type' => $isImage ? 'image' : 'file',
                'storage_path'    => $path,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        return response()->json(['ok' => true, 'message_id' => $msgId]);
    }

    /**
     * Insert Supabase notifications.
     */
    private function insertNotifications(array $rows): void
    {
        $url    = config('services.supabase.url');
        $svcKey = config('services.supabase.service_key');

        if (!$url || !$svcKey || empty($rows)) return;

        foreach ($rows as &$row) {
            $row['id']         = (string) Str::uuid();
            $row['read']       = false;
            $row['created_at'] = now()->toIso8601String();
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->withHeaders([
                    'apikey'        => $svcKey,
                    'Authorization' => "Bearer {$svcKey}",
                    'Content-Type'  => 'application/json',
                    'Prefer'        => 'return=minimal',
                ])->post("{$url}/rest/v1/notifications", $rows);

            \Log::info('Notification insert', [
                'rows'      => count($rows),
                'http_code' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Failed to insert notifications: ' . $e->getMessage());
        }
    }
}
