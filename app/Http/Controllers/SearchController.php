<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function users(Request $request)
    {
        if ($r = requireAuth()) return $r;

        $q = strtolower(trim((string) $request->query('q', '')));

        if ($q === '') {
            return response()->json(['users' => []]);
        }

        $provider = new \App\Providers\SupabaseServiceProvider();
        $profiles = $provider->getAllProfiles();
        $currentUserId = (string) session('user_id');

        $results = [];

        foreach ($profiles as $profile) {
            $id = (string) ($profile['id'] ?? '');

            if ($id === '' || $id === $currentUserId) {
                continue;
            }

            $first = trim((string) ($profile['first_name'] ?? ''));
            $last = trim((string) ($profile['last_name'] ?? ''));
            $username = trim((string) ($profile['username'] ?? ''));
            $name = trim($first . ' ' . $last) ?: $username ?: 'User';

            $searchText = strtolower($name . ' ' . $username);

            if (!str_contains($searchText, $q)) {
                continue;
            }

            $results[] = [
                'id' => $id,
                'name' => $name,
                'username' => $username,
                'photo' => (string) ($profile['profile_photo_url'] ?? ''),
                'url' => route('profile.view', ['userId' => $id]),
                'is_friend' => \App\Models\Friendship::areFriends($currentUserId, $id),
            ];

            if (count($results) >= 12) break;
        }

        return response()->json(['users' => $results]);
    }
}
