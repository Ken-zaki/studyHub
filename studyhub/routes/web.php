<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FocusModeController;
use App\Http\Controllers\FriendRequestController;
use App\Http\Controllers\DiagnosticsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NewsfeedController;
use App\Http\Controllers\StudyGroupController;
use App\Http\Controllers\CalendarShareController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\OgPreviewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\TaskController;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Providers\SupabaseServiceProvider;


// ──────────────────────────────────────────────────────────────
// ROLE & AUTH HELPERS
// ──────────────────────────────────────────────────────────────

function requireAuth() {
    if (!session('user_id') || empty(session('user_id'))) {
        return redirect()->route('login');
    }
    if (session('is_banned')) {
        session()->flush();
        return redirect()->route('login')
            ->with('error', 'Your account has been suspended.');
    }
    return null;
}

function requireAdmin() {
    $redirect = requireAuth();
    if ($redirect) return $redirect;
    if (session('user_role') !== 'admin') {
        abort(403, 'Admin access required.');
    }
    return null;
}

function requireModeratorOrAdmin() {
    $redirect = requireAuth();
    if ($redirect) return $redirect;
    if (!in_array(session('user_role'), ['admin', 'moderator'])) {
        abort(403, 'Insufficient permissions.');
    }
    return null;
}

function resolveFriendProfileEntry(SupabaseServiceProvider $provider, string $userId): array
{
    $userId = trim($userId);
    $profile = $userId !== '' ? $provider->getProfileById($userId) : null;

    if (!is_array($profile) || empty($profile)) {
        if ($userId !== '' && $userId === (string) session('user_id', '')) {
            $profile = [
                'id' => $userId,
                'first_name' => (string) session('user_first_name', ''),
                'last_name' => (string) session('user_last_name', ''),
                'username' => (string) session('user_username', ''),
                'profile_photo_url' => (string) session('user_profile_photo', ''),
            ];
        } else {
            $profile = ['id' => $userId];
        }
    }

    $firstName = trim((string) ($profile['first_name'] ?? ''));
    $lastName  = trim((string) ($profile['last_name'] ?? ''));
    $name = trim($firstName . ' ' . $lastName);

    if ($name === '') {
        $name = trim((string) ($profile['username'] ?? '')) ?: 'Friend';
    }

    $status = strtolower((string) ($profile['status'] ?? ''));
    $isActive = (bool) ($profile['is_online'] ?? false)
        || (bool) ($profile['is_active'] ?? false)
        || in_array($status, ['online', 'active'], true);

    return [
        'id' => (string) ($profile['id'] ?? $userId),
        'name' => $name,
        'username' => (string) ($profile['username'] ?? ''),
        'photo' => (string) ($profile['profile_photo_url'] ?? ''),
        'initials' => strtoupper(substr($firstName ?: $name, 0, 1) . substr($lastName, 0, 1)),
        'is_active' => $isActive,
    ];
}

// ──────────────────────────────────────────────────────────────
// PUBLIC ROUTES (guests can access)
// ──────────────────────────────────────────────────────────────

// GUEST ROUTES
Route::get('/browse', fn() => redirect()->route('guest.newsfeed'))->name('guest');
Route::get('/browse/newsfeed',   fn() => view('home.guest-newsfeed'))  ->name('guest.newsfeed');
Route::get('/browse/resources',  fn() => view('home.guest-resources')) ->name('guest.resources');
Route::get('/browse/settings',   fn() => view('home.guest-settings'))  ->name('guest.settings');

// DEBUG ROUTE - Check all friend requests in database
Route::get('/debug/all-requests', function () {
    $allRequests = FriendRequest::all();
    $schemaColumns = \DB::getSchemaBuilder()->getColumnListing('friend_requests');
    $userId = trim((string) session('user_id', ''));

    $myIncoming = FriendRequest::where('receiver_id', $userId)->get();
    $myOutgoing = FriendRequest::where('sender_id', $userId)->get();

    return response()->json([
        'current_session_user_id' => $userId,
        'db_columns' => $schemaColumns,
        'total_requests_in_db' => count($allRequests),
        'all_requests_raw' => $allRequests->toArray(),
        'my_incoming_count' => count($myIncoming),
        'my_incoming' => $myIncoming->toArray(),
        'my_outgoing_count' => count($myOutgoing),
        'my_outgoing' => $myOutgoing->toArray(),
        'sample_raw_query' => \DB::table('friend_requests')->get()->toArray(),
    ]);
});

// DEBUG — raw Supabase profile data
Route::get('/debug/profiles', function () {
    $provider = new SupabaseServiceProvider();
    $profiles = $provider->getAllProfiles();

    return response()->json([
        'session_user_id'  => session('user_id'),
        'supabase_url_set' => !empty(env('SUPABASE_URL')),
        'anon_key_set'     => !empty(env('SUPABASE_ANON_KEY')),
        'service_key_set'  => !empty(env('SUPABASE_SERVICE_KEY')),
        'profile_count'    => count($profiles),
        'profiles_sample'  => array_map(function ($p) {
            return [
                'id'         => $p['id']         ?? '(missing)',
                'first_name' => $p['first_name'] ?? '(missing)',
                'last_name'  => $p['last_name']  ?? '(missing)',
                'username'   => $p['username']   ?? '(missing)',
                'email'      => $p['email']      ?? '(missing)',
                'all_keys'   => array_keys($p),
            ];
        }, array_slice($profiles, 0, 5)),
    ]);
});

// DEBUG — simulates friend-request send step by step
Route::get('/debug/send/{receiverId}', function (string $receiverId) {
    $senderId   = trim((string) session('user_id', ''));
    $receiverId = trim($receiverId);

    $isValidUuid = (bool) preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
        $receiverId
    );

    $provider    = new SupabaseServiceProvider();
    $allProfiles = $provider->getAllProfiles();

    $matchedProfile = null;
    foreach ($allProfiles as $p) {
        if ((string) ($p['id'] ?? '') === $receiverId) {
            $matchedProfile = $p;
            break;
        }
    }

    return response()->json([
        'sender_id'           => $senderId,
        'receiver_id_param'   => $receiverId,
        'is_valid_uuid'       => $isValidUuid,
        'total_profiles'      => count($allProfiles),
        'matched_profile'     => $matchedProfile,
        'first_profile_id'    => $allProfiles[0]['id'] ?? '(none)',
        'first_profile_keys'  => isset($allProfiles[0]) ? array_keys($allProfiles[0]) : [],
    ]);
});

Route::post('/debug/study-groups-test', function (Request $request) {
    return response()->json([
        'session_user_id' => session('user_id'),
        'body'            => $request->all(),
        'csrf_ok'         => true,
    ]);
});

Route::get('/debug/my-friends-for-groups', function () {
    $userId = session('user_id');

    $friendsAsUser = \DB::table('friends')
        ->where('user_id', $userId)
        ->get();

    $friendsAsFriend = \DB::table('friends')
        ->where('friend_id', $userId)
        ->get();

    return response()->json([
        'session_user_id'   => $userId,
        'friends_as_user'   => $friendsAsUser,
        'friends_as_friend' => $friendsAsFriend,
        'total'             => $friendsAsUser->count() + $friendsAsFriend->count(),
    ]);
});

Route::get('/debug/supabase-friends-raw', function () {
    $provider = new \App\Providers\SupabaseServiceProvider();
    $userId = session('user_id');

    $r1 = $provider->queryTable('friends',      ['select' => '*', 'limit' => '5']);
    $r2 = $provider->queryTable('user_friends', ['select' => '*', 'limit' => '5']);

    return response()->json([
        'friends_any_rows'      => $r1,
        'user_friends_any_rows' => $r2,
    ]);
});

Route::get('/debug/supabase-friends-service', function () {
    $provider = new \App\Providers\SupabaseServiceProvider();
    $userId = session('user_id');

    $f1 = $provider->queryTable('friends', ['select' => '*', 'user_id' => 'eq.' . $userId], true);
    $f2 = $provider->queryTable('friends', ['select' => '*', 'friend_id' => 'eq.' . $userId], true);
    $f3 = $provider->queryTable('user_friends', ['select' => '*', 'user_id' => 'eq.' . $userId], true);
    $f4 = $provider->queryTable('user_friends', ['select' => '*', 'friend_id' => 'eq.' . $userId], true);

    // Also get ALL rows from friends table regardless of user
    $allFriends     = $provider->queryTable('friends',      ['select' => '*', 'limit' => '10'], true);
    $allUserFriends = $provider->queryTable('user_friends', ['select' => '*', 'limit' => '10'], true);

    return response()->json([
        'session_user_id'        => $userId,
        'friends_as_user'        => $f1,
        'friends_as_friend'      => $f2,
        'user_friends_as_user'   => $f3,
        'user_friends_as_friend' => $f4,
        'all_friends_rows'       => $allFriends,
        'all_user_friends_rows'  => $allUserFriends,
    ]);
});

Route::get('/debug/study-group-friends', function () {
    $userId = session('user_id');
    $provider = new \App\Providers\SupabaseServiceProvider();

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

    // Try to resolve profiles
    $friends = [];
    $failedIds = [];
    foreach (array_keys($friendIds) as $friendId) {
        $profile = $provider->getProfileById($friendId);

        if (!is_array($profile) || empty($profile)) {
            $failedIds[] = $friendId;
            continue;
        }

        $firstName = trim((string) ($profile['first_name'] ?? ''));
        $lastName  = trim((string) ($profile['last_name']  ?? ''));
        $name      = trim($firstName . ' ' . $lastName);

        if ($name === '') {
            $name = trim((string) ($profile['username'] ?? '')) ?: 'Friend';
        }

        $friends[] = [
            'id'       => (string) ($profile['id'] ?? $friendId),
            'name'     => $name,
            'username' => (string) ($profile['username'] ?? ''),
            'photo'    => (string) ($profile['profile_photo_url'] ?? ''),
        ];
    }

    return response()->json([
        'session_user_id'      => $userId,
        'friend_ids_found'     => array_keys($friendIds),
        'friends_resolved'     => $friends,
        'friends_failed_count' => count($failedIds),
        'friends_failed_ids'   => $failedIds,
        'raw_friends_as_user'  => $friendsAsUser,
        'raw_friends_as_friend' => $friendsAsFriend,
    ]);
});

Route::get('/debug/study-groups-friends-detail', function () {
    $userId = session('user_id');
    $provider = new \App\Providers\SupabaseServiceProvider();

    // Load ALL profiles
    $allProfiles  = $provider->getAllProfiles();
    $profilesById = [];
    foreach ($allProfiles as $profile) {
        $id = (string) ($profile['id'] ?? '');
        if ($id !== '') {
            $profilesById[$id] = $profile;
        }
    }

    // Query friends
    $friendsAsUser   = $provider->queryTable('friends', [
        'select'  => 'friend_id',
        'user_id' => 'eq.' . $userId,
    ], true);

    $friendsAsFriend = $provider->queryTable('friends', [
        'select'    => 'user_id',
        'friend_id' => 'eq.' . $userId,
    ], true);

    // Collect friend IDs
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

    // Build friends with details
    $friends = [];
    $notFound = [];
    foreach (array_keys($friendIds) as $friendId) {
        if (!isset($profilesById[$friendId])) {
            $notFound[] = $friendId;
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

    return response()->json([
        'session_user_id'          => $userId,
        'all_profiles_count'       => count($profilesById),
        'friend_ids_count'         => count($friendIds),
        'friend_ids'               => array_keys($friendIds),
        'friends_resolved_count'   => count($friends),
        'friends_resolved'         => $friends,
        'friends_not_found_count'  => count($notFound),
        'friends_not_found'        => $notFound,
        'raw_friends_as_user'      => $friendsAsUser,
        'raw_friends_as_friend'    => $friendsAsFriend,
    ]);
});

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::get('/signup', function () {
    return view('auth.signup');
})->name('signup');

// Forgot Password Routes
Route::get('/forgot-password',  [AuthController::class, 'showForgotPassword'])->name('forgot-password');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.forgot');
Route::get('/verify-otp',       [AuthController::class, 'showVerifyOtp'])->name('password.verify');
Route::post('/verify-otp',      [AuthController::class, 'verifyOtp'])->name('password.verify.post');
Route::get('/reset-password',   [AuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password',  [AuthController::class, 'resetPassword'])->name('password.reset.post');

// Guests can browse public resources (read-only, handled in JS)
Route::get('/resources/public', function () {
    return view('home.resources', ['activeNav' => 'resources']);
})->name('resources.public');

// ──────────────────────────────────────────────────────────────
// SET SESSION
// Called from JS after Supabase login — stores user data + role
// ──────────────────────────────────────────────────────────────

Route::post('/set-session', function (Request $request) {
    $userId       = (string) $request->input('user_id', '');
    $profilePhoto = (string) $request->input('profile_photo', '');

    if ($userId !== '' && $profilePhoto !== '') {
        try {
            $provider = new SupabaseServiceProvider();
            $provider->updateProfilePhoto($userId, $profilePhoto);
        } catch (\Throwable $e) {
            // Non-fatal
        }
    }

    session([
        'user_id'            => $userId,
        'user_first_name'    => $request->input('first_name'),
        'user_last_name'     => $request->input('last_name'),
        'user_username'      => $request->input('username'),
        'user_profile_photo' => $profilePhoto,
        'user_student_type'  => $request->input('student_type'),
        'user_role'          => $request->input('role', 'student'),
        'is_banned'          => $request->input('is_banned', false),
    ]);
    return response()->json(['success' => true]);
})->name('set-session');

// ──────────────────────────────────────────────────────────────
// LOGOUT
// ──────────────────────────────────────────────────────────────

Route::post('/logout', function () {
    session()->flush();
    return redirect()->route('login');
})->name('logout');

// ──────────────────────────────────────────────────────────────
// AUTHENTICATED ROUTES (students, moderators, admins)
// ──────────────────────────────────────────────────────────────

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/calendar',  [CalendarController::class,  'index'])->name('calendar');
Route::get('/tasks',     [TaskController::class,      'index'])->name('tasks');

// Newsfeed page
Route::get('/newsfeed', [NewsfeedController::class, 'index'])->name('newsfeed');

// OG metadata proxy — fetches link previews server-side to
// avoid CORS issues when the composer previews a pasted URL.
Route::get('/api/og-preview', [NewsfeedController::class, 'ogPreview'])->name('og.preview');

Route::get('/calendar', function () {
    if ($r = requireAuth()) return $r;
    return view('home.calendar', ['activeNav' => 'calendar']);
})->name('calendar');

// ── STUDY GROUPS ──────────────────────────────────────────────
Route::get('/study-groups',                    [StudyGroupController::class, 'index'])       ->name('study-groups');
Route::get('/study-groups/api/friends',        [StudyGroupController::class, 'getFriends'])  ->name('study-groups.friends');
Route::get('/study-groups/api/groups',         [StudyGroupController::class, 'getGroupsJson'])->name('study-groups.json');
Route::get('/study-groups/{groupId}/shared-calendars', [StudyGroupController::class, 'getGroupSharedCalendars'])->name('study-groups.shared-calendars');
Route::post('/study-groups',                   [StudyGroupController::class, 'store'])       ->name('study-groups.store');
Route::patch('/study-groups/{groupId}',        [StudyGroupController::class, 'update'])      ->name('study-groups.update');       // ← NEW: rename
Route::post('/study-groups/{groupId}/photo',   [StudyGroupController::class, 'updatePhoto'])->name('study-groups.photo');        // ← NEW: group photo
Route::get('/study-groups/{groupId}/members',  [StudyGroupController::class, 'members'])    ->name('study-groups.members');      // ← NEW: members list
Route::delete('/study-groups/{groupId}',       [StudyGroupController::class, 'destroy'])    ->name('study-groups.destroy');
Route::get('/study-groups/{groupId}/messages', [StudyGroupController::class, 'messages'])   ->name('study-groups.messages');
Route::post('/study-groups/{groupId}/messages',[StudyGroupController::class, 'sendMessage'])->name('study-groups.send');

// ── CALENDAR SHARING ───────────────────────────────────────────
Route::get('/calendar/sharing/friends',           [CalendarShareController::class, 'getFriendsForSharing'])->name('calendar.sharing.friends');
Route::post('/calendar/sharing/request/{friendId}', [CalendarShareController::class, 'requestShare'])     ->name('calendar.sharing.request');
Route::post('/calendar/sharing/requests/{requestId}/accept', [CalendarShareController::class, 'acceptShare']) ->name('calendar.sharing.accept');
Route::post('/calendar/sharing/requests/{requestId}/reject', [CalendarShareController::class, 'rejectShare']) ->name('calendar.sharing.reject');
Route::get('/calendar/sharing/requests',          [CalendarShareController::class, 'getShareRequests'])   ->name('calendar.sharing.requests');
Route::get('/calendar/sharing/calendars',         [CalendarShareController::class, 'getSharedCalendars'])->name('calendar.sharing.calendars');
Route::post('/calendar/sharing/revoke/{recipientId}', [CalendarShareController::class, 'revokeShare'])      ->name('calendar.sharing.revoke');
Route::post('/calendar/sharing/revoke-received/{ownerId}', [CalendarShareController::class, 'revokeReceivedShare'])->name('calendar.sharing.revoke-received');
Route::post('/calendar/sharing/group/{groupId}',  [CalendarShareController::class, 'shareWithGroup'])     ->name('calendar.sharing.group');


// ─────────────────────────────────────────────────────────────
// RESOURCES ROUTES
// ─────────────────────────────────────────────────────────────

// Page
Route::get('/resources', [ResourceController::class, 'index'])->name('resources');

// Feed & discovery
Route::get('/api/resources',              [ResourceController::class, 'list']);
Route::get('/api/resources/trending',     [ResourceController::class, 'trending']);
Route::get('/api/resources/my-uploads',   [ResourceController::class, 'myUploads']);

// ── NEW: must be BEFORE the {id} wildcard ──────────────────────
Route::get ('/api/resources/bookmarks',   [ResourceController::class, 'bookmarks']);
Route::get ('/api/resources/top-rated',   [ResourceController::class, 'topRated']);
// ──────────────────────────────────────────────────────────────

Route::get('/api/resources/{id}',         [ResourceController::class, 'show']);

// CRUD
Route::post  ('/api/resources',           [ResourceController::class, 'store']);
Route::put   ('/api/resources/{id}',      [ResourceController::class, 'update']);
Route::delete('/api/resources/{id}',      [ResourceController::class, 'destroy']);

// Ratings & comments
Route::post  ('/api/resources/{id}/rate',          [ResourceController::class, 'rate']);
Route::get   ('/api/resources/{id}/comments',      [ResourceController::class, 'comments']);
Route::post  ('/api/resources/{id}/comments',      [ResourceController::class, 'addComment']);
Route::put   ('/api/resources/comments/{commentId}',[ResourceController::class, 'updateComment']);
Route::delete('/api/resources/comments/{commentId}',[ResourceController::class, 'deleteComment']);
Route::post  ('/api/resources/comments/{commentId}/upvote', [ResourceController::class, 'upvoteComment']);

// ── NEW: bookmark toggle (must be before {id} or use specific path) ──
Route::post('/api/resources/{id}/bookmark', [ResourceController::class, 'toggleBookmark']);

// Reports & admin
Route::post('/api/resources/{id}/report',  [ResourceController::class, 'report']);
Route::post('/api/resources/{id}/approve', [ResourceController::class, 'approve']);

// Study groups discovery
Route::get('/api/study-groups/active', [ResourceController::class, 'activeGroups']);

Route::get('/notifications', function () {
    if ($r = requireAuth()) return $r;
    return view('home.notifications', ['activeNav' => 'notifications']);
})->name('notifications');

Route::get('/settings', function () {
    if ($r = requireAuth()) return $r;
    return view('home.settings', ['activeNav' => 'settings']);
})->name('settings');

// ── MESSAGES ──────────────────────────────────────────────────
Route::get('/messages', [MessageController::class, 'index'])->name('messages');
Route::get('/messages/conversation/{friendId}', [MessageController::class, 'conversation'])->name('messages.conversation');
Route::post('/messages/send', [MessageController::class, 'send'])->name('messages.send');
Route::get('/messages/poll/{friendId}', [MessageController::class, 'poll'])->name('messages.poll');
Route::get('/messages/unread-counts', [MessageController::class, 'unreadCounts'])->name('messages.unread');

// ── FRIENDS ───────────────────────────────────────────────────
Route::get('/friends', function () {
    if ($r = requireAuth()) return $r;

    $currentUserId = (string) session('user_id', '');
    $provider = new SupabaseServiceProvider();

    $friendRows = Friendship::query()
        ->where('user_id', $currentUserId)
        ->orWhere('friend_id', $currentUserId)
        ->get(['user_id', 'friend_id']);

    $friendIds = [];
    foreach ($friendRows as $row) {
        $candidate = (string) ($row->user_id === $currentUserId ? $row->friend_id : $row->user_id);
        if ($candidate !== '' && $candidate !== $currentUserId) {
            $friendIds[$candidate] = true;
        }
    }

    $friends = [];
    foreach (array_keys($friendIds) as $friendId) {
        $friends[] = resolveFriendProfileEntry($provider, $friendId);
    }

    return view('home.friends', [
        'activeNav' => 'friends',
        'friends' => $friends,
    ]);
})->name('friends');

// Debug route moved outside /friends
Route::get('/debug/my-friends', function () {
    $userId = session('user_id');
    $provider = new \App\Providers\SupabaseServiceProvider();

    $friends1 = $provider->queryTable('friends', [
        'select'  => '*',
        'user_id' => 'eq.' . $userId,
    ]);
    $friends2 = $provider->queryTable('friends', [
        'select'    => '*',
        'friend_id' => 'eq.' . $userId,
    ]);
    $friends3 = $provider->queryTable('user_friends', [
        'select'  => '*',
        'user_id' => 'eq.' . $userId,
    ]);
    $friends4 = $provider->queryTable('user_friends', [
        'select'    => '*',
        'friend_id' => 'eq.' . $userId,
    ]);

    return response()->json([
        'session_user_id'        => $userId,
        'friends_as_user_id'     => $friends1,
        'friends_as_friend_id'   => $friends2,
        'user_friends_as_user'   => $friends3,
        'user_friends_as_friend' => $friends4,
    ]);
});

Route::get('/friend-requests', [FriendRequestController::class, 'index'])->name('friend-requests');
Route::post('/friend-requests/{receiverId}', [FriendRequestController::class, 'send'])->name('friend-requests.send');
Route::post('/friend-requests/{friendRequest}/accept', [FriendRequestController::class, 'accept'])->name('friend-requests.accept');
Route::post('/friend-requests/{friendRequest}/decline', [FriendRequestController::class, 'decline'])->name('friend-requests.decline');
Route::post('/friend-requests/{friendRequest}/cancel', [FriendRequestController::class, 'cancel'])->name('friend-requests.cancel');
Route::post('/friends/{friendId}/remove', [FriendRequestController::class, 'remove'])->name('friends.remove');

// ── DIAGNOSTICS ──────────────────────────────────────────────
Route::get('/diagnostics/friend-requests', [DiagnosticsController::class, 'friendRequests']);

// ── PROFILE ───────────────────────────────────────────────────
Route::get('/profile', function () {
    if ($r = requireAuth()) return $r;

    $sessionUserId      = (string) session('user_id', '');
    $sessionFirstName   = (string) session('user_first_name', '');
    $sessionLastName    = (string) session('user_last_name', '');
    $sessionUsername    = (string) session('user_username', '');
    $sessionPhoto       = (string) session('user_profile_photo', '');
    $sessionStudentType = (string) session('user_student_type', '');

    $provider  = new SupabaseServiceProvider();
    $dbProfile = null;

    if ($sessionUserId !== '') {
        $result = $provider->getProfileById($sessionUserId);
        if (is_array($result) && !isset($result['error'])) {
            $dbProfile = $result;
        }
    }

    if (!$dbProfile && $sessionUsername !== '') {
        $result = $provider->getProfileByUsername($sessionUsername);
        if (is_array($result) && !isset($result['error'])) {
            $dbProfile = $result;
        }
    }

    $firstName      = trim((string) ($dbProfile['first_name']        ?? $sessionFirstName));
    $lastName       = trim((string) ($dbProfile['last_name']         ?? $sessionLastName));
    $username       = trim((string) ($dbProfile['username']          ?? $sessionUsername));
    $email          = trim((string) ($dbProfile['email']             ?? ''));
    $photoUrl       = trim((string) ($dbProfile['profile_photo_url'] ?? $sessionPhoto));
    $userId         = trim((string) ($dbProfile['id']                ?? $sessionUserId));
    $joinedAt       = trim((string) ($dbProfile['created_at']        ?? $dbProfile['joined_at'] ?? ''));
    $bio            = trim((string) ($dbProfile['bio']               ?? $dbProfile['about'] ?? ''));
    $studentTypeRaw = trim((string) ($dbProfile['student_type']      ?? $sessionStudentType));

    $studentTypeLabel = match (strtolower($studentTypeRaw)) {
        'high_school' => 'High School Student',
        'college'     => 'College Student',
        default       => 'Student type not set',
    };

    $displayName = trim($firstName . ' ' . $lastName);
    if ($displayName === '') {
        $displayName = $username !== '' ? $username : 'StudyHub User';
    }

    if ($bio === '') {
        $bio = $studentTypeLabel !== 'Student type not set'
            ? $studentTypeLabel
            : 'StudyHub learner';
    }

    $postCount      = 0;
    $friendIds      = [];
    $friendProfiles = [];
    $recentPosts    = [];

    if ($userId !== '') {
        $postCount = $provider->countTableRows('newsfeed_posts', [
            'user_id'     => 'eq.' . $userId,
            'is_archived' => 'eq.false',
        ]);

        $postsResult = $provider->queryTable('newsfeed_posts', [
            'select'      => 'id,content,created_at,post_type,media_url,link_url,subject_tag',
            'user_id'     => 'eq.' . $userId,
            'is_archived' => 'eq.false',
            'order'       => 'created_at.desc',
            'limit'       => '12',
        ]);

        if (is_array($postsResult) && !isset($postsResult['error'])) {
            $recentPosts = array_map(function (array $post): array {
                $content = trim((string) ($post['content'] ?? ''));
                $preview = mb_strlen($content) > 180
                    ? mb_substr($content, 0, 180) . '…'
                    : $content;

                return [
                    'id'          => (string) ($post['id']          ?? ''),
                    'content'     => $content,
                    'preview'     => $preview,
                    'created_at'  => (string) ($post['created_at']  ?? ''),
                    'post_type'   => (string) ($post['post_type']   ?? 'text'),
                    'media_url'   => (string) ($post['media_url']   ?? ''),
                    'link_url'    => (string) ($post['link_url']    ?? ''),
                    'subject_tag' => (string) ($post['subject_tag'] ?? ''),
                ];
            }, $postsResult);
        }

        $friendRows = Friendship::query()
            ->where('user_id', $userId)
            ->orWhere('friend_id', $userId)
            ->get(['user_id', 'friend_id']);

        foreach ($friendRows as $row) {
            $candidate = (string) (
                $row->user_id === $userId
                    ? $row->friend_id
                    : $row->user_id
            );
            if ($candidate !== '' && $candidate !== $userId) {
                $friendIds[$candidate] = true;
            }
        }

        $allProfiles = $provider->getAllProfiles();
        if (!empty($allProfiles) && !empty($friendIds)) {
            foreach ($allProfiles as $profile) {
                $pid = (string) ($profile['id'] ?? '');
                if (!isset($friendIds[$pid])) continue;

                $friendFirst = trim((string) ($profile['first_name'] ?? ''));
                $friendLast  = trim((string) ($profile['last_name']  ?? ''));
                $friendName  = trim($friendFirst . ' ' . $friendLast);
                if ($friendName === '') {
                    $friendName = trim((string) ($profile['username'] ?? '')) ?: 'Friend';
                }

                $status   = strtolower((string) ($profile['status'] ?? ''));
                $isActive = (bool) ($profile['is_online']  ?? false)
                    || (bool) ($profile['is_active']       ?? false)
                    || in_array($status, ['online', 'active'], true);

                $friendProfiles[] = [
                    'id'        => $pid,
                    'name'      => $friendName,
                    'photo'     => (string) ($profile['profile_photo_url'] ?? ''),
                    'initials'  => strtoupper(
                        substr($friendFirst ?: $friendName, 0, 1) . substr($friendLast, 0, 1)
                    ),
                    'is_active' => $isActive,
                ];
            }
        }
    }

    $completedSessions = (int) session('focus_session_count',   0);
    $totalFocusSeconds = (int) session('focus_total_seconds',   0);
    $sessionMaterials  =       session('focus_materials',       []);
    $resourceCount     = is_array($sessionMaterials) ? count($sessionMaterials) : 0;
    $activeSessions    = (int) ((bool) session('focus_mode_active', false));

    return view('home.profile', [
        'activeNav'   => 'profile',
        'profileData' => [
            'id'                  => $userId,
            'display_name'        => $displayName,
            'first_name'          => $firstName,
            'last_name'           => $lastName,
            'username'            => $username,
            'email'               => $email,
            'profile_photo_url'   => $photoUrl,
            'joined_at'           => $joinedAt,
            'bio'                 => $bio,
            'student_type'        => $studentTypeRaw,
            'student_type_label'  => $studentTypeLabel,
            'source'              => $dbProfile ? 'supabase' : 'session',
            'recent_posts'        => $recentPosts,
            'friends'             => $friendProfiles,
            'stats' => [
                'posts_made'               => $postCount,
                'resources_uploaded'       => $resourceCount,
                'study_sessions_active'    => $activeSessions,
                'study_sessions_completed' => $completedSessions,
                'total_focus_seconds'      => $totalFocusSeconds,
            ],
        ],
    ]);
})->name('profile');

// ── PROFILE VIEW (other users) ────────────────────────────────
Route::get('/profile/{userId}', function ($userId) {
    if ($r = requireAuth()) return $r;

    $userId = (string) trim($userId);
    if ($userId === '' || $userId === session('user_id')) {
        return redirect(route('profile'));
    }

    $currentUserId = (string) session('user_id', '');
    $provider = new SupabaseServiceProvider();
    $viewedProfile = $provider->getProfileById($userId);

    // If the route param is a username (not a UUID), resolve to the actual UUID.
    // The profile card links use /profile/{username}, but Friendship expects UUIDs.
    $resolvedUserId = (string) ($viewedProfile['id'] ?? $userId);

    $relationshipState = 'none';
    $pendingRequestId = null;

    if (Friendship::areFriends($currentUserId, $resolvedUserId)) {
        $relationshipState = 'friends';
    } else {
        $pendingRequest = FriendRequest::query()
            ->between($currentUserId, $resolvedUserId)
            ->where('status', 'pending')
            ->first();

        if ($pendingRequest) {
            $pendingRequestId = $pendingRequest->id;
            $relationshipState = $pendingRequest->sender_id === $currentUserId
                ? 'pending_outgoing'
                : 'pending_incoming';
        }
    }

    $friendRows = Friendship::query()
        ->where('user_id', $resolvedUserId)
        ->orWhere('friend_id', $resolvedUserId)
        ->get(['user_id', 'friend_id']);

    $friendIds = [];
    foreach ($friendRows as $row) {
        $candidate = (string) ($row->user_id === $resolvedUserId ? $row->friend_id : $row->user_id);
        if ($candidate !== '' && $candidate !== $resolvedUserId) {
            $friendIds[$candidate] = true;
        }
    }

    $friendProfiles = [];
    if (!empty($friendIds)) {
        foreach (array_keys($friendIds) as $friendId) {
            $friendProfiles[] = resolveFriendProfileEntry($provider, $friendId);
        }
    }

    return view('home.profile-view', [
        'userId' => $userId,
        'profileData' => [
            'friends' => $friendProfiles,
            'profile' => $viewedProfile,
        ],
        'relationshipState' => $relationshipState,
        'pendingRequestId' => $pendingRequestId,
        'activeNav' => 'profile',
    ]);
})->name('profile.view');

// ── FOCUS MODE ────────────────────────────────────────────────

Route::get('/focus-mode',                              [FocusModeController::class, 'index'])->name('focus-mode');
Route::post('/focus-mode/session',                     [FocusModeController::class, 'storeSession'])->name('focus-mode.session');
Route::post('/focus-mode/materials',                   [FocusModeController::class, 'storeMaterial'])->name('focus-mode.materials');
Route::delete('/focus-mode/materials/{id}',            [FocusModeController::class, 'destroyMaterial'])->name('focus-mode.materials.destroy');
Route::get('/focus-mode/materials/{id}/file',          [FocusModeController::class, 'serveMaterial'])->name('focus-mode.materials.file');
Route::get('/focus-mode/materials/{id}/notes',         [FocusModeController::class, 'showNote'])->name('focus-mode.materials.notes');
Route::post('/focus-mode/materials/{id}/notes',        [FocusModeController::class, 'saveNote'])->name('focus-mode.materials.notes.save');
Route::post('/focus-mode/decks',                       [FocusModeController::class, 'storeDeck'])->name('focus-mode.decks.store');
Route::delete('/focus-mode/decks/{id}',                [FocusModeController::class, 'destroyDeck'])->name('focus-mode.decks.destroy');
Route::post('/focus-mode/flashcards',                  [FocusModeController::class, 'storeFlashcard'])->name('focus-mode.flashcards');
Route::delete('/focus-mode/flashcards/{id}',           [FocusModeController::class, 'destroyFlashcard'])->name('focus-mode.flashcards.destroy');
Route::post('/focus-mode/quiz-sets',                   [FocusModeController::class, 'storeQuizSet'])->name('focus-mode.quiz-sets.store');
Route::delete('/focus-mode/quiz-sets/{id}',            [FocusModeController::class, 'destroyQuizSet'])->name('focus-mode.quiz-sets.destroy');
Route::post('/focus-mode/quizzes',                     [FocusModeController::class, 'storeQuiz'])->name('focus-mode.quizzes');
Route::delete('/focus-mode/quizzes/{id}',              [FocusModeController::class, 'destroyQuiz'])->name('focus-mode.quizzes.destroy');

// Public — no auth needed for background music streaming
Route::get('/focus-mode/music/stream',                 [FocusModeController::class, 'streamMusic'])->name('focus-mode.music.stream');
Route::get('/focus-mode/tracks',                       [FocusModeController::class, 'tracks']);

// ──────────────────────────────────────────────────────────────
// ADMIN ROUTES (admin only)
// ──────────────────────────────────────────────────────────────

Route::prefix('admin')->group(function () {

    Route::get('/', function () {
        if ($r = requireAdmin()) return $r;
        return view('admin.dashboard', ['activeNav' => 'admin']);
    })->name('admin.dashboard');

    Route::get('/users', function () {
        if ($r = requireAdmin()) return $r;
        return view('admin.users', ['activeNav' => 'admin']);
    })->name('admin.users');

    Route::get('/reports', function () {
        if ($r = requireModeratorOrAdmin()) return $r;
        return view('admin.reports', ['activeNav' => 'admin']);
    })->name('admin.reports');

    Route::get('/resources', function () {
        if ($r = requireModeratorOrAdmin()) return $r;
        return view('admin.resources', ['activeNav' => 'admin']);
    })->name('admin.resources');

    Route::get('/logs', function () {
        if ($r = requireAdmin()) return $r;
        return view('admin.logs', ['activeNav' => 'admin']);
    })->name('admin.logs');

    Route::get('/settings', function () {
        if ($r = requireAdmin()) return $r;
        return view('admin.settings', ['activeNav' => 'admin', 'activeAdmin' => 'settings']);
    })->name('admin.settings');

});
