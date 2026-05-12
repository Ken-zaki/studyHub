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
     * This app uses custom session auth, not Laravel's built-in Auth.
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

        $provider = new SupabaseServiceProvider();

        // Load ALL profiles once
        $allProfiles  = $provider->getAllProfiles();
        $profilesById = [];
        foreach ($allProfiles as $profile) {
            $id = (string) ($profile['id'] ?? '');
            if ($id !== '') {
                $profilesById[$id] = $profile;
            }
        }

        // Get friends from the local 'friends' table using Eloquent
        // Friends where this user is the initiator
        $friendsAsUser = Friendship::where('user_id', $userId)->pluck('friend_id')->toArray();

        // Friends where this user is the recipient
        $friendsAsFriend = Friendship::where('friend_id', $userId)->pluck('user_id')->toArray();

        // Combine and get unique friend IDs
        $friendIds = array_unique(array_merge($friendsAsUser, $friendsAsFriend));

        // Build friends array from cached profiles
        $friends = [];
        foreach ($friendIds as $friendId) {
            $friendId = (string) $friendId;

            // Always add friend to list, even if profile is not found
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

            $profile  = $profilesById[$friendId];
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

        // Debug: Log what we're getting
        \Log::info('StudyGroup index - Friend IDs found from friends table: ' . count($friendIds), [
            'friend_ids' => $friendIds,
            'friends_count' => count($friends),
        ]);

        return view('home.study-groups', compact('groups', 'friends'));
    }

    /**
     * Create a new study group.
     */
    public function store(Request $request)
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json([
                'error' => 'Not authenticated. Please log in again.'
            ], 401);
        }

        try {
            $request->validate([
                'name'      => 'required|string|max:255',
                'subject'   => 'nullable|string|max:255',
                'members'   => 'nullable|array',
                // Supabase UUIDs — validate format only, NOT exists:users,id
                'members.*' => ['nullable', 'string', 'regex:/^[0-9a-f\-]{36}$/i'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error'  => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $group = StudyGroup::create([
                'id'          => (string) Str::uuid(),
                'name'        => $request->input('name'),
                'subject'     => $request->input('subject'),
                'description' => null,
                'is_public'   => true,
                'created_by'  => $userId,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error'   => 'Failed to create group.',
                'message' => $e->getMessage(),
            ], 500);
        }

        try {
            // Add creator as admin member
            StudyGroupMember::create([
                'id'       => (string) Str::uuid(),
                'group_id' => $group->id,
                'user_id'  => $userId,
                'role'     => 'admin',
            ]);

            // Add selected friends as members
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

            // Get adder's name from Supabase
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
                'id'      => $group->id,
                'name'    => $group->name,
                'subject' => $group->subject,
            ],
        ]);
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

        // Find the group
        $group = StudyGroup::find($groupId);

        if (!$group) {
            return response()->json(['error' => 'Group not found'], 404);
        }

        // Check if user is the creator (admin)
        if ($group->created_by !== $userId) {
            return response()->json(['error' => 'Only the group creator can delete the group'], 403);
        }

        try {
            // Delete all members
            StudyGroupMember::where('group_id', $groupId)->delete();

            // Delete the group
            $group->delete();

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to delete group: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get friends as JSON (for AJAX modal population).
     * Uses the local 'friends' table (Friendship model)
     */
    public function getFriends()
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $provider = new SupabaseServiceProvider();

        // Load ALL profiles once
        $allProfiles  = $provider->getAllProfiles();
        $profilesById = [];
        foreach ($allProfiles as $profile) {
            $id = (string) ($profile['id'] ?? '');
            if ($id !== '') {
                $profilesById[$id] = $profile;
            }
        }

        // Get friends from the local 'friends' table using Eloquent
        // Friends where this user is the initiator
        $friendsAsUser = Friendship::where('user_id', $userId)->pluck('friend_id')->toArray();

        // Friends where this user is the recipient
        $friendsAsFriend = Friendship::where('friend_id', $userId)->pluck('user_id')->toArray();

        // Combine and get unique friend IDs
        $friendIds = array_unique(array_merge($friendsAsUser, $friendsAsFriend));

        // Build friends array from cached profiles
        $friends = [];
        foreach ($friendIds as $friendId) {
            $friendId = (string) $friendId;

            // Always add friend to list, even if profile is not found
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

            $profile  = $profilesById[$friendId];
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

        \Log::info('GetFriends - Friend IDs found from friends table: ' . count($friendIds), [
            'friend_ids' => $friendIds,
            'friends_count' => count($friends),
        ]);

        return response()->json(['friends' => $friends]);
    }

    /**
     * Return messages for a group (JSON).
     * Fetches from local SQLite group_messages table.
     */
    public function messages(string $groupId)
    {
        $userId = $this->currentUserId();

        if ($userId === '') {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Verify membership
        $isMember = StudyGroupMember::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->exists();

        if (!$isMember) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        // Fetch messages from local SQLite, resolve sender names from Supabase
        $rows = DB::table('group_messages')
            ->where('group_id', $groupId)
            ->orderBy('created_at')
            ->get();

        $provider = new SupabaseServiceProvider();

        // Cache profiles so we don't hit Supabase once per message
        $profileCache = [];

        $messages = $rows->map(function ($row) use ($userId, $provider, &$profileCache) {
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
                // Try common field name variations
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

        // Notify all group members except sender
        $members = StudyGroupMember::where('group_id', $groupId)
            ->where('user_id', '!=', $userId)
            ->pluck('user_id');

        $group = StudyGroup::find($groupId);
        $senderProfile = (new SupabaseServiceProvider())->getProfileById($userId);
        $senderName = trim(($senderProfile['first_name'] ?? '') . ' ' . ($senderProfile['last_name'] ?? ''));
        if (!$senderName) $senderName = $senderProfile['username'] ?? 'Someone';

        $preview = $request->input('message') ?
            Str::limit($request->input('message'), 60) :
            '📎 Sent an attachment';

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
            $path = $file->store($folder, 'public');
            $url  = asset('storage/' . $path);

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
     * Insert a Supabase notification for one or more users.
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
                'response'  => $response->body(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Failed to insert notifications: ' . $e->getMessage());
        }
    }
}
