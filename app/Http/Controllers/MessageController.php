<?php

namespace App\Http\Controllers;

use App\Models\DirectMessage;
use App\Models\User;
use App\Providers\SupabaseServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $currentUserId = (string) $request->session()->get('user_id', '');
        $selectedUserId = (string) $request->query('user', '');
        $selectedUserName = trim((string) $request->query('name', ''));
        $currentUserName = $this->sessionDisplayName($request);

        $profileMap = collect($this->loadProfiles())
            ->map(fn (array $profile) => $this->profileToObject($profile))
            ->keyBy(fn ($user) => (string) $user->id);

        $unreadCounts = collect();
        $conversationNames = collect();

        if ($currentUserId !== '') {
            $sentTo = DirectMessage::query()
                ->where('sender_id', $currentUserId)
                ->pluck('receiver_id');

            $receivedFrom = DirectMessage::query()
                ->where('receiver_id', $currentUserId)
                ->pluck('sender_id');

            $unreadCounts = DirectMessage::query()
                ->selectRaw('sender_id, COUNT(*) as total')
                ->where('receiver_id', $currentUserId)
                ->whereNull('seen_at')
                ->groupBy('sender_id')
                ->pluck('total', 'sender_id')
                ->map(fn ($total) => (int) $total);

            $conversationMessages = DirectMessage::query()
                ->where(function ($query) use ($currentUserId) {
                    $query->where('sender_id', $currentUserId)
                        ->orWhere('receiver_id', $currentUserId);
                })
                ->orderByDesc('created_at')
                ->get(['sender_id', 'sender_name', 'receiver_id', 'receiver_name']);

            foreach ($conversationMessages as $message) {
                if ((string) $message->sender_id === $currentUserId) {
                    $otherId = (string) $message->receiver_id;
                    $otherName = trim((string) $message->receiver_name);
                } else {
                    $otherId = (string) $message->sender_id;
                    $otherName = trim((string) $message->sender_name);
                }

                if ($otherId === '' || $otherId === $currentUserId) {
                    continue;
                }

                if (!$conversationNames->has($otherId) && $otherName !== '') {
                    $conversationNames->put($otherId, $otherName);
                }
            }
        }

        $users = $profileMap
            ->filter(fn ($user) => (string) $user->id !== '' && (string) $user->id !== $currentUserId)
            ->map(function ($user) use ($unreadCounts, $conversationNames, $profileMap) {
                $id = (string) $user->id;
                $user = $profileMap->get($id);
                $resolvedName = $this->resolveDisplayName(
                    conversationId: $id,
                    profileMap: $profileMap,
                    conversationNames: $conversationNames
                );

                if ($user) {
                    $user->name = $resolvedName;
                    $parts = preg_split('/\s+/', $resolvedName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    $user->initials = strtoupper(substr($parts[0] ?? 'U', 0, 1) . substr($parts[1] ?? '', 0, 1));
                } else {
                    $user = $this->fallbackUserObject($resolvedName);
                }

                $user->unread_count = (int) ($unreadCounts->get($id, 0));

                return $user;
            })
            ->sortBy(fn ($user) => strtolower((string) ($user->name ?? '')))
            ->values();

        if ($selectedUserId === '' && $users->isNotEmpty()) {
            $selectedUserId = (string) $users->first()->id;
        }

        if (
            $selectedUserId !== ''
            && $selectedUserId !== $currentUserId
            && !$users->contains(fn ($user) => (string) $user->id === $selectedUserId)
        ) {
            $selectedUser = $profileMap->get($selectedUserId) ?? $this->fallbackUserObject($this->resolveDisplayName(
                conversationId: $selectedUserId,
                profileMap: $profileMap,
                conversationNames: $conversationNames,
                fallbackName: $selectedUserName
            ));

            if ($selectedUserName !== '') {
                $selectedUser->name = $selectedUserName;
                $parts = preg_split('/\s+/', $selectedUserName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $selectedUser->initials = strtoupper(substr($parts[0] ?? 'U', 0, 1) . substr($parts[1] ?? '', 0, 1));
            }

            $selectedUser->unread_count = (int) ($unreadCounts->get($selectedUserId, 0));
            $users->prepend($selectedUser);
        }

        if ($selectedUserId !== '' && $selectedUserName !== '') {
            $users = $users->map(function ($user) use ($selectedUserId, $selectedUserName) {
                if ((string) $user->id !== $selectedUserId) {
                    return $user;
                }

                $user->name = $selectedUserName;
                $parts = preg_split('/\s+/', $selectedUserName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $user->initials = strtoupper(substr($parts[0] ?? 'U', 0, 1) . substr($parts[1] ?? '', 0, 1));

                return $user;
            });
        }

        $selectedUserDisplayName = $selectedUserId !== ''
            ? $this->resolveDisplayName(
                conversationId: $selectedUserId,
                profileMap: $profileMap,
                conversationNames: $conversationNames,
                fallbackName: $selectedUserName
            )
            : 'Unknown User';

        $messages = collect();

        if ($currentUserId !== '' && $selectedUserId !== '') {
            DirectMessage::query()
                ->where('sender_id', $selectedUserId)
                ->where('receiver_id', $currentUserId)
                ->whereNull('seen_at')
                ->update(['seen_at' => now()]);

            $messages = DirectMessage::query()
                ->where(function ($query) use ($currentUserId, $selectedUserId) {
                    $query->where('sender_id', $currentUserId)
                        ->where('receiver_id', $selectedUserId);
                })
                ->orWhere(function ($query) use ($currentUserId, $selectedUserId) {
                    $query->where('sender_id', $selectedUserId)
                        ->where('receiver_id', $currentUserId);
                })
                ->orderBy('created_at')
                ->get();

            $messages = $messages->map(function ($message) use ($profileMap, $currentUserName, $selectedUserId, $selectedUserName, $currentUserId) {
                $senderName = $this->sanitizeDisplayName((string) ($message->sender_name ?? ''));
                $receiverName = $this->sanitizeDisplayName((string) ($message->receiver_name ?? ''));

                $message->sender_display_name = $senderName !== ''
                    ? $senderName
                    : ($message->sender_id === $currentUserId
                        ? ($currentUserName !== '' ? $currentUserName : 'You')
                        : $this->resolveDisplayName((string) $message->sender_id, $profileMap, collect(), $selectedUserName));

                $message->receiver_display_name = $receiverName !== ''
                    ? $receiverName
                    : ($message->receiver_id === $currentUserId
                        ? ($currentUserName !== '' ? $currentUserName : 'You')
                        : $this->resolveDisplayName((string) $message->receiver_id, $profileMap, collect(), $selectedUserName));

                return $message;
            });
        }

        return view('home.messages', [
            'currentUserId' => $currentUserId,
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'selectedUserDisplayName' => $selectedUserDisplayName,
            'messages' => $messages,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $currentUserId = (string) $request->session()->get('user_id', '');

        if ($currentUserId === '') {
            return back()->withErrors(['session' => 'Please log in first.']);
        }

        $validated = $request->validate([
            'receiver_id' => ['required', 'string', 'max:191'],
            'receiver_name' => ['nullable', 'string', 'max:191'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if ($validated['receiver_id'] === $currentUserId) {
            return back()->withErrors(['receiver_id' => 'You cannot message yourself.']);
        }

        $senderName = trim((string) $request->session()->get('user_first_name', '') . ' ' . (string) $request->session()->get('user_last_name', ''));
        if ($senderName === '') {
            $senderName = trim((string) $request->session()->get('user_username', ''));
        }

        $receiverName = $this->resolveDisplayName(
            conversationId: (string) $validated['receiver_id'],
            profileMap: collect($this->loadProfiles())->map(fn (array $profile) => $this->profileToObject($profile))->keyBy(fn ($user) => (string) $user->id),
            conversationNames: collect(),
            fallbackName: trim((string) ($validated['receiver_name'] ?? ''))
        );

        DirectMessage::create([
            'sender_id' => $currentUserId,
            'sender_name' => $senderName !== '' ? $senderName : 'You',
            'receiver_id' => $validated['receiver_id'],
            'receiver_name' => $receiverName !== '' ? $receiverName : null,
            'message' => $validated['message'],
            'seen_at' => null,
        ]);

        $params = ['user' => $validated['receiver_id']];
        if (!empty($receiverName)) {
            $params['name'] = $receiverName;
        }

        return redirect()->route('messages', $params);
    }

    /**
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
     * @param array<string, mixed> $profile
     */
    private function profileToObject(array $profile): object
    {
        $firstName = trim((string) ($profile['first_name'] ?? ''));
        $lastName = trim((string) ($profile['last_name'] ?? ''));
        $name = trim($firstName . ' ' . $lastName);

        if ($name === '') {
            $name = trim((string) ($profile['name'] ?? $profile['username'] ?? 'User'));
        }

        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = strtoupper(substr($parts[0] ?? 'U', 0, 1) . substr($parts[1] ?? '', 0, 1));

        return (object) [
            'id' => (string) ($profile['id'] ?? ''),
            'name' => $name,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => (string) ($profile['username'] ?? ''),
            'profile_photo_url' => (string) ($profile['profile_photo_url'] ?? ''),
            'email' => (string) ($profile['email'] ?? ''),
            'initials' => $initials,
            'unread_count' => 0,
        ];
    }

    private function fallbackUserObject(string $name = 'Unknown User'): object
    {
        $cleanName = trim($name) !== '' ? trim($name) : 'Unknown User';
        $parts = preg_split('/\s+/', $cleanName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = strtoupper(substr($parts[0] ?? 'U', 0, 1) . substr($parts[1] ?? '', 0, 1));

        return (object) [
            'id' => '',
            'name' => $cleanName,
            'first_name' => '',
            'last_name' => '',
            'username' => '',
            'profile_photo_url' => '',
            'email' => '',
            'initials' => $initials,
            'unread_count' => 0,
        ];
    }

    private function resolveDisplayName(string $conversationId, $profileMap, $conversationNames = null, string $fallbackName = 'Unknown User'): string
    {
        $profile = $profileMap->get($conversationId);
        if ($profile) {
            $profileName = trim((string) ($profile->name ?? ''));
            if ($profileName !== '' && !$this->isFallbackDisplayName($profileName)) {
                return $profileName;
            }

            $firstLast = trim((string) ($profile->first_name ?? '') . ' ' . (string) ($profile->last_name ?? ''));
            if ($firstLast !== '') {
                return $firstLast;
            }

            $username = trim((string) ($profile->username ?? ''));
            if ($username !== '') {
                return $username;
            }
        }

        $conversationName = '';
        if ($conversationNames instanceof \Illuminate\Support\Collection) {
            $conversationName = trim((string) $conversationNames->get($conversationId, ''));
        }

        if ($conversationName !== '' && !$this->isFallbackDisplayName($conversationName)) {
            return $conversationName;
        }

        return trim($fallbackName) !== '' ? trim($fallbackName) : 'Unknown User';
    }

    private function isFallbackDisplayName(string $name): bool
    {
        return (bool) preg_match('/^(user\s+)?[a-f0-9\-]{6,}$/i', trim($name))
            || (bool) preg_match('/^User\s+[a-f0-9\-]{6,}$/i', trim($name))
            || trim($name) === 'Unknown User';
    }

    private function sanitizeDisplayName(string $name): string
    {
        $cleanName = trim($name);

        if ($cleanName === '' || $this->isFallbackDisplayName($cleanName)) {
            return '';
        }

        return $cleanName;
    }

    private function sessionDisplayName(Request $request): string
    {
        $name = trim((string) $request->session()->get('user_first_name', '') . ' ' . (string) $request->session()->get('user_last_name', ''));

        if ($name === '') {
            $name = trim((string) $request->session()->get('user_username', ''));
        }

        return $name !== '' ? $name : 'You';
    }
}
