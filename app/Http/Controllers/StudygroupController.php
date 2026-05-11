<?php

namespace App\Http\Controllers;

use App\Models\StudyGroup;
use App\Models\StudyGroupMember;
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

        // Query friend IDs from Supabase (useServiceKey: true to bypass RLS)
        $friendsAsUser   = $provider->queryTable('friends', [
            'select'  => 'friend_id',
            'user_id' => 'eq.' . $userId,
        ], true);

        $friendsAsFriend = $provider->queryTable('friends', [
            'select'    => 'user_id',
            'friend_id' => 'eq.' . $userId,
        ], true);

        // Collect all unique friend IDs
        $friendIds = [];
        if (is_array($friendsAsUser)) {
            foreach ($friendsAsUser as $row) {
                $id = (string) ($row['friend_id'] ?? '');
                if ($id !== '') $friendIds[$id] = true;
            }
        }
        if (is_array($friendsAsFriend)) {
            foreach ($friendsAsFriend as $row) {
                $id = (string) ($row['user_id'] ?? '');
                if ($id !== '') $friendIds[$id] = true;
            }
        }

        // Also check user_friends table
        $userFriendsAsUser   = $provider->queryTable('user_friends', [
            'select'  => 'friend_id',
            'user_id' => 'eq.' . $userId,
        ], true);

        $userFriendsAsFriend = $provider->queryTable('user_friends', [
            'select'    => 'user_id',
            'friend_id' => 'eq.' . $userId,
        ], true);

        if (is_array($userFriendsAsUser)) {
            foreach ($userFriendsAsUser as $row) {
                $id = (string) ($row['friend_id'] ?? '');
                if ($id !== '') $friendIds[$id] = true;
            }
        }
        if (is_array($userFriendsAsFriend)) {
            foreach ($userFriendsAsFriend as $row) {
                $id = (string) ($row['user_id'] ?? '');
                if ($id !== '') $friendIds[$id] = true;
            }
        }

        // Build friends array from cached profiles
        $friends = [];
        foreach (array_keys($friendIds) as $friendId) {
            if (!isset($profilesById[$friendId])) {
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
        \Log::info('StudyGroup index - Friend IDs found: ' . count($friendIds), [
            'friend_ids' => array_keys($friendIds),
            'friends_count' => count($friends),
            'all_profiles_count' => count($profilesById),
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

        return response()->json([
            'group' => [
                'id'      => $group->id,
                'name'    => $group->name,
                'subject' => $group->subject,
            ],
        ]);
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
                'sender_photo' => $profile['profile_photo_url'] ?? null,
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
            $path    = $file->store("public/{$folder}");
            $url     = Storage::url($path);

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
}