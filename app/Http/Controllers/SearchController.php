<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Providers\SupabaseServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function universal(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $currentUserId = (string) $request->session()->get('user_id', '');

        if ($query === '') {
            return response()->json([
                'users' => [],
                'resources' => [],
            ]);
        }

        $profiles = $this->loadProfiles();
        $terms = preg_split('/\s+/', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $users = collect($profiles)
            ->filter(function (array $profile) use ($terms, $currentUserId) {
                $profileId = (string) ($profile['id'] ?? '');

                if ($profileId !== '' && $profileId === $currentUserId) {
                    return false;
                }

                $haystack = mb_strtolower(implode(' ', [
                    (string) ($profile['first_name'] ?? ''),
                    (string) ($profile['last_name'] ?? ''),
                    (string) ($profile['name'] ?? ''),
                    (string) ($profile['username'] ?? ''),
                    (string) ($profile['email'] ?? ''),
                ]));

                foreach ($terms as $term) {
                    if ($term !== '' && !str_contains($haystack, $term)) {
                        return false;
                    }
                }

                return true;
            })
            ->take(8)
            ->map(fn (array $profile) => $this->profileToArray($profile))
            ->values()
            ->all();

        return response()->json([
            'users' => $users,
            'resources' => [],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadProfiles(): array
    {
        $supabase = new SupabaseServiceProvider();
        $profiles = $supabase->getAllProfiles();

        if (is_array($profiles) && !empty($profiles)) {
            return $profiles;
        }

        $authUsers = $supabase->getAllAuthUsers();

        if (!empty($authUsers)) {
            return collect($authUsers)
                ->map(function (array $user): array {
                    $metadata = is_array($user['user_metadata'] ?? null) ? $user['user_metadata'] : [];

                    return [
                        'id' => (string) ($user['id'] ?? ''),
                        'name' => trim((string) (($metadata['first_name'] ?? '') . ' ' . ($metadata['last_name'] ?? ''))),
                        'first_name' => (string) ($metadata['first_name'] ?? ''),
                        'last_name' => (string) ($metadata['last_name'] ?? ''),
                        'username' => (string) ($metadata['username'] ?? ''),
                        'profile_photo_url' => '',
                        'email' => (string) ($user['email'] ?? ''),
                    ];
                })
                ->all();
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
     * @return array<string, string>
     */
    private function profileToArray(array $profile): array
    {
        $firstName = trim((string) ($profile['first_name'] ?? ''));
        $lastName = trim((string) ($profile['last_name'] ?? ''));
        $name = trim($firstName . ' ' . $lastName);

        if ($name === '') {
            $name = trim((string) ($profile['name'] ?? $profile['username'] ?? 'User'));
        }

        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = strtoupper(substr($parts[0] ?? 'U', 0, 1) . substr($parts[1] ?? '', 0, 1));

        return [
            'id' => (string) ($profile['id'] ?? ''),
            'name' => $name,
            'username' => (string) ($profile['username'] ?? ''),
            'email' => (string) ($profile['email'] ?? ''),
            'profile_photo_url' => (string) ($profile['profile_photo_url'] ?? ''),
            'initials' => $initials,
        ];
    }
}