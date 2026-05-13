<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Friendship;
use App\Providers\SupabaseServiceProvider;

class MessageController extends Controller
{
    // ── Supabase REST helpers ──────────────────────────────────

    private function supabaseUrl(): string
    {
        return rtrim(env('SUPABASE_URL'), '/');
    }

    private function supabaseHeaders(): array
    {
        return [
            'apikey'        => env('SUPABASE_SERVICE_KEY'),
            'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ];
    }

    /** GET rows from a Supabase table */
    private function sbGet(string $table, array $query = []): array
    {
        $response = Http::withoutVerifying()
            ->withHeaders($this->supabaseHeaders())
            ->get($this->supabaseUrl() . '/rest/v1/' . $table, $query);

        return $response->successful() ? ($response->json() ?? []) : [];
    }

    /** POST (insert) a row into a Supabase table */
    private function sbPost(string $table, array $data): array
    {
        $response = Http::withoutVerifying()
            ->withHeaders($this->supabaseHeaders())
            ->post($this->supabaseUrl() . '/rest/v1/' . $table, $data);

        return $response->successful() ? ($response->json() ?? []) : [];
    }

    /** PATCH (update) rows matching a filter */
    private function sbPatch(string $table, array $filters, array $data): void
    {
        Http::withoutVerifying()
            ->withHeaders($this->supabaseHeaders())
            ->patch(
                $this->supabaseUrl() . '/rest/v1/' . $table . '?' . http_build_query($filters),
                $data
            );
    }

    /** POST a notification row, ignoring duplicates */
    private function pushNotification(array $data): void
    {
        Http::withoutVerifying()
            ->withHeaders(array_merge($this->supabaseHeaders(), [
                'Prefer' => 'resolution=ignore-duplicates',
            ]))
            ->post($this->supabaseUrl() . '/rest/v1/notifications', $data);
    }

    /** Upsert (insert or update) conversation settings */
    private function upsertConversationSetting(string $userId, string $friendId, array $data): void
    {
        $existing = $this->sbGet('conversation_settings', [
            'select' => 'id',
            'user_id' => 'eq.' . $userId,
            'friend_id' => 'eq.' . $friendId,
            'limit' => '1',
        ]);

        $data['updated_at'] = now()->toISOString();

        if (!empty($existing)) {
            $this->sbPatch('conversation_settings', [
                'user_id' => 'eq.' . $userId,
                'friend_id' => 'eq.' . $friendId,
            ], $data);
            return;
        }

        $this->sbPost('conversation_settings', array_merge([
            'id' => Str::uuid()->toString(),
            'user_id' => $userId,
            'friend_id' => $friendId,
            'is_archived' => false,
            'is_muted' => false,
            'created_at' => now()->toISOString(),
        ], $data));
    }

    // ── Session auth helpers ───────────────────────────────────

    private function userId(): ?string
    {
        return session('user_id') ?: null;
    }

    private function requireAuth()
    {
        if (!$this->userId() || session('is_banned')) {
            return redirect()->route('login');
        }
        return null;
    }

    // ── index() ────────────────────────────────────────────────

    public function index()
    {
        if ($r = $this->requireAuth()) return $r;
        $userId = $this->userId();
        $provider = new SupabaseServiceProvider();

        // Get friend IDs from LOCAL database using Friendship model
        $friendRows = Friendship::query()
            ->where('user_id', $userId)
            ->orWhere('friend_id', $userId)
            ->get(['user_id', 'friend_id']);

        $friendIds = [];
        foreach ($friendRows as $row) {
            $candidate = (string) ($row->user_id === $userId ? $row->friend_id : $row->user_id);
            if ($candidate !== '' && $candidate !== $userId) {
                $friendIds[] = $candidate;
            }
        }
        $friendIds = array_unique(array_filter($friendIds));

        $friends = [];
        $archivedFriends = [];

        foreach ($friendIds as $friendId) {
            // Get profile from Supabase
            $friendProfile = $provider->getProfileById($friendId);
            if (!$friendProfile) continue;

            $setting = $this->getConversationSetting($userId, $friendId);

            // Last message (sent)
            $sentLast = $this->sbGet('direct_messages', [
                'select'      => 'id,message,created_at,sender_id',
                'sender_id'   => 'eq.' . $userId,
                'receiver_id' => 'eq.' . $friendId,
                'order'       => 'created_at.desc',
                'limit'       => '1',
            ]);

            // Last message (received)
            $receivedLast = $this->sbGet('direct_messages', [
                'select'      => 'id,message,created_at,sender_id',
                'sender_id'   => 'eq.' . $friendId,
                'receiver_id' => 'eq.' . $userId,
                'order'       => 'created_at.desc',
                'limit'       => '1',
            ]);

            $lastMessage = null;
            $candidates  = array_filter(array_merge($sentLast, $receivedLast));
            if (!empty($candidates)) {
                usort($candidates, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
                $lastMessage = $candidates[0];
            }

            // Unread count
            $unreadResp = Http::withoutVerifying()
                ->withHeaders(array_merge(
                $this->supabaseHeaders(),
                ['Prefer' => 'count=exact']
            ))->get($this->supabaseUrl() . '/rest/v1/direct_messages', [
                'select'      => 'id',
                'sender_id'   => 'eq.' . $friendId,
                'receiver_id' => 'eq.' . $userId,
                'is_read'     => 'eq.false',
            ]);

            $unreadCount  = 0;
            $contentRange = $unreadResp->header('Content-Range');
            if ($contentRange && str_contains($contentRange, '/')) {
                $unreadCount = (int) explode('/', $contentRange)[1];
            }

            $friendObject = (object) [
                'id'                => $friendProfile['id'],
                'username'          => $friendProfile['username']          ?? '',
                'first_name'        => $friendProfile['first_name']        ?? '',
                'last_name'         => $friendProfile['last_name']         ?? '',
                'profile_photo_url' => $friendProfile['profile_photo_url'] ?? '',
                'last_message'      => $lastMessage ? (object) $lastMessage : null,
                'unread_count'      => $unreadCount,
                'is_muted'          => (bool) ($setting['is_muted'] ?? false),
                'is_archived'       => (bool) ($setting['is_archived'] ?? false),
            ];

            if ($friendObject->is_archived) {
                $archivedFriends[] = $friendObject;
            } else {
                $friends[] = $friendObject;
            }
        }

        // Sort by most recent message
        usort($friends, function ($a, $b) {
            $aTime = $a->last_message->created_at ?? '';
            $bTime = $b->last_message->created_at ?? '';
            return strcmp($bTime, $aTime);
        });

        return view('home.messages', [
            'friends' => collect($friends),
            'archivedFriends' => collect($archivedFriends),
        ]);
    }

    // ── conversation() ─────────────────────────────────────────

    public function conversation($friendId)
    {
        if ($r = $this->requireAuth()) return $r;
        $userId = $this->userId();

        // Mark incoming as read
        $this->sbPatch('direct_messages', [
            'sender_id'   => 'eq.' . $friendId,
            'receiver_id' => 'eq.' . $userId,
            'is_read'     => 'eq.false',
        ], ['is_read' => true]);

        // Fetch both directions
        $sent = $this->sbGet('direct_messages', [
            'select'      => 'id,sender_id,receiver_id,message,is_read,created_at',
            'sender_id'   => 'eq.' . $userId,
            'receiver_id' => 'eq.' . $friendId,
            'order'       => 'created_at.asc',
        ]);

        $received = $this->sbGet('direct_messages', [
            'select'      => 'id,sender_id,receiver_id,message,is_read,created_at',
            'sender_id'   => 'eq.' . $friendId,
            'receiver_id' => 'eq.' . $userId,
            'order'       => 'created_at.asc',
        ]);

        $messages = array_merge($sent, $received);
        usort($messages, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));

        // Attach profile info to each message (cache profiles)
        $profileCache = [];
        foreach ($messages as &$msg) {
            $sid = $msg['sender_id'];
            if (!isset($profileCache[$sid])) {
                $p = $this->sbGet('profiles', [
                    'select' => 'id,username,first_name,last_name,profile_photo_url',
                    'id'     => 'eq.' . $sid,
                    'limit'  => '1',
                ]);
                $profileCache[$sid] = $p[0] ?? [];
            }
            $p = $profileCache[$sid];
            $msg['username']          = $p['username']          ?? '';
            $msg['first_name']        = $p['first_name']        ?? '';
            $msg['last_name']         = $p['last_name']         ?? '';
            $msg['profile_photo_url'] = $p['profile_photo_url'] ?? '';
        }
        unset($msg);

        // ── SEEN: find the most recent message YOU sent that the friend has read ──
        // We look for sent messages where is_read = true, ordered desc, take the latest one.
        $lastReadRows = $this->sbGet('direct_messages', [
            'select'      => 'id,created_at',
            'sender_id'   => 'eq.' . $userId,
            'receiver_id' => 'eq.' . $friendId,
            'is_read'     => 'eq.true',
            'order'       => 'created_at.desc',
            'limit'       => '1',
        ]);
        $lastSeenMessageId = $lastReadRows[0]['id'] ?? null;

        // Friend profile for header
        $friendProfiles = $this->sbGet('profiles', [
            'select' => 'id,username,first_name,last_name,profile_photo_url',
            'id'     => 'eq.' . $friendId,
            'limit'  => '1',
        ]);

        return response()->json([
            'messages'            => $messages,
            'friend'              => $friendProfiles[0] ?? null,
            'auth_id'             => $userId,
            'last_seen_message_id' => $lastSeenMessageId, // ID of the last sent msg the friend has read
        ]);
    }

    // ── send() ─────────────────────────────────────────────────

    public function send(Request $request)
    {
        if ($r = $this->requireAuth()) return $r;

        $request->validate([
            'receiver_id' => 'required|string',
            'message'     => 'required|string|max:2000',
        ]);

        $userId     = $this->userId();
        $receiverId = $request->receiver_id;

        // Verify friendship using LOCAL database
        if (!Friendship::areFriends($userId, $receiverId)) {
            return response()->json(['error' => 'You can only message friends.'], 403);
        }

        // Insert message
        $inserted = $this->sbPost('direct_messages', [
            'id'          => Str::uuid()->toString(),
            'sender_id'   => $userId,
            'receiver_id' => $receiverId,
            'message'     => $request->message,
            'is_read'     => false,
            'created_at'  => now()->toISOString(),
        ]);

        $message = $inserted[0] ?? null;

        if ($message) {
            $profiles = $this->sbGet('profiles', [
                'select' => 'id,username,first_name,last_name,profile_photo_url',
                'id'     => 'eq.' . $userId,
                'limit'  => '1',
            ]);
            $p = $profiles[0] ?? [];
            $message['username']          = $p['username']          ?? '';
            $message['first_name']        = $p['first_name']        ?? '';
            $message['last_name']         = $p['last_name']         ?? '';
            $message['profile_photo_url'] = $p['profile_photo_url'] ?? '';

            // Build sender display name
            $firstName  = trim($p['first_name'] ?? '');
            $lastName   = trim($p['last_name']  ?? '');
            $senderName = trim($firstName . ' ' . $lastName);
            if ($senderName === '') {
                $senderName = $p['username'] ?? 'Someone';
            }

            // Build message preview (first 20 chars)
            $preview = mb_substr($request->message, 0, 20);
            if (mb_strlen($request->message) > 20) {
                $preview .= '…';
            }

            // Push notification to the receiver
            $this->pushNotification([
                'id'          => Str::uuid()->toString(),
                'user_id'     => $receiverId,
                'source_type' => 'message',
                'source_id'   => $message['id'],
                'trigger'     => 'new_message',
                'title'       => $senderName . ' sent you a message',
                'body'        => $preview,
                'icon'        => '💬',
                'urgency'     => 'info',
                'read'        => false,
                'created_at'  => now()->toISOString(),
            ]);
        }

        return response()->json(['message' => $message]);
    }

    // ── poll() ─────────────────────────────────────────────────
    // Returns:
    //   messages          — new incoming messages since after_id
    //   last_seen_message_id — ID of the last message YOU sent that the friend has now read
    //                          (used by the frontend to show/move the "Seen" indicator)

    public function poll(Request $request, $friendId)
    {
        if ($r = $this->requireAuth()) return $r;
        $userId  = $this->userId();
        $afterId = $request->query('after_id');

        // ── 1. Fetch new INCOMING messages ────────────────────
        $query = [
            'select'      => 'id,sender_id,receiver_id,message,is_read,created_at',
            'sender_id'   => 'eq.' . $friendId,
            'receiver_id' => 'eq.' . $userId,
            'order'       => 'created_at.asc',
        ];

        if ($afterId) {
            $afterRows = $this->sbGet('direct_messages', [
                'select' => 'created_at',
                'id'     => 'eq.' . $afterId,
                'limit'  => '1',
            ]);
            if (!empty($afterRows)) {
                $query['created_at'] = 'gt.' . $afterRows[0]['created_at'];
            }
        }

        $newMessages = $this->sbGet('direct_messages', $query);

        // Mark new incoming messages as read and attach profile info
        if (!empty($newMessages)) {
            $this->sbPatch('direct_messages', [
                'sender_id'   => 'eq.' . $friendId,
                'receiver_id' => 'eq.' . $userId,
                'is_read'     => 'eq.false',
            ], ['is_read' => true]);

            $profiles = $this->sbGet('profiles', [
                'select' => 'id,username,first_name,last_name,profile_photo_url',
                'id'     => 'eq.' . $friendId,
                'limit'  => '1',
            ]);
            $p = $profiles[0] ?? [];

            foreach ($newMessages as &$msg) {
                $msg['username']          = $p['username']          ?? '';
                $msg['first_name']        = $p['first_name']        ?? '';
                $msg['last_name']         = $p['last_name']         ?? '';
                $msg['profile_photo_url'] = $p['profile_photo_url'] ?? '';
            }
            unset($msg);
        }

        // ── 2. Check which of YOUR sent messages the friend has read ──
        // Return the ID of the most recent sent message where is_read = true.
        // The frontend uses this to know exactly which bubble gets the "Seen" tick.
        $lastReadRows = $this->sbGet('direct_messages', [
            'select'      => 'id',
            'sender_id'   => 'eq.' . $userId,
            'receiver_id' => 'eq.' . $friendId,
            'is_read'     => 'eq.true',
            'order'       => 'created_at.desc',
            'limit'       => '1',
        ]);
        $lastSeenMessageId = $lastReadRows[0]['id'] ?? null;

        return response()->json([
            'messages'             => $newMessages,
            'last_seen_message_id' => $lastSeenMessageId,
        ]);
    }

    // ── unreadCounts() ─────────────────────────────────────────

    public function unreadCounts()
    {
        if ($r = $this->requireAuth()) return $r;
        $userId = $this->userId();

        $rows = $this->sbGet('direct_messages', [
            'select'      => 'sender_id',
            'receiver_id' => 'eq.' . $userId,
            'is_read'     => 'eq.false',
        ]);

        $counts = [];
        foreach ($rows as $row) {
            $sid          = $row['sender_id'];
            $counts[$sid] = ($counts[$sid] ?? 0) + 1;
        }

        return response()->json(['counts' => $counts]);
    }

    // ── getConversationSetting() ───────────────────────────────

    private function getConversationSetting(string $userId, string $friendId): array
    {
        $rows = $this->sbGet('conversation_settings', [
            'select' => 'is_archived,is_muted',
            'user_id' => 'eq.' . $userId,
            'friend_id' => 'eq.' . $friendId,
            'limit' => '1',
        ]);

        return $rows[0] ?? [
            'is_archived' => false,
            'is_muted' => false,
        ];
    }

    // ── archive() ──────────────────────────────────────────────

    public function archive($friendId)
    {
        if ($r = $this->requireAuth()) return $r;

        $this->upsertConversationSetting($this->userId(), $friendId, [
            'is_archived' => true,
        ]);

        return response()->json(['success' => true]);
    }

    // ── unarchive() ────────────────────────────────────────────

    public function unarchive($friendId)
    {
        if ($r = $this->requireAuth()) return $r;

        $this->upsertConversationSetting($this->userId(), $friendId, [
            'is_archived' => false,
        ]);

        return response()->json(['success' => true]);
    }

    // ── mute() ─────────────────────────────────────────────────

    public function mute($friendId)
    {
        if ($r = $this->requireAuth()) return $r;

        $this->upsertConversationSetting($this->userId(), $friendId, [
            'is_muted' => true,
        ]);

        return response()->json(['success' => true]);
    }

    // ── unmute() ───────────────────────────────────────────────

    public function unmute($friendId)
    {
        if ($r = $this->requireAuth()) return $r;

        $this->upsertConversationSetting($this->userId(), $friendId, [
            'is_muted' => false,
        ]);

        return response()->json(['success' => true]);
    }
}
