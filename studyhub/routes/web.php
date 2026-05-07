<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\FocusModeController;
use App\Http\Controllers\FriendRequestController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Models\FriendRequest;
use App\Models\Friendship;
use App\Providers\SupabaseServiceProvider;

// ══════════════════════════════════════════════════════════════
// ROLE & AUTH HELPERS
// ══════════════════════════════════════════════════════════════

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
    if (!in_array(session('user_role'), ['admin', 'moderator'])) {
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
    $userId  = trim($userId);
    $profile = $userId !== '' ? $provider->getProfileById($userId) : null;

    if (!is_array($profile) || empty($profile)) {
        if ($userId !== '' && $userId === (string) session('user_id', '')) {
            $profile = [
                'id'                => $userId,
                'first_name'        => (string) session('user_first_name', ''),
                'last_name'         => (string) session('user_last_name', ''),
                'username'          => (string) session('user_username', ''),
                'profile_photo_url' => (string) session('user_profile_photo', ''),
            ];
        } else {
            $profile = ['id' => $userId];
        }
    }

    $firstName = trim((string) ($profile['first_name'] ?? ''));
    $lastName  = trim((string) ($profile['last_name']  ?? ''));
    $name      = trim($firstName . ' ' . $lastName);
    if ($name === '') {
        $name = trim((string) ($profile['username'] ?? '')) ?: 'Friend';
    }

    $status   = strtolower((string) ($profile['status'] ?? ''));
    $isActive = (bool) ($profile['is_online'] ?? false)
        || (bool) ($profile['is_active']      ?? false)
        || in_array($status, ['online', 'active'], true);

    return [
        'id'       => (string) ($profile['id'] ?? $userId),
        'name'     => $name,
        'username' => (string) ($profile['username'] ?? ''),
        'photo'    => (string) ($profile['profile_photo_url'] ?? ''),
        'initials' => strtoupper(
            substr($firstName ?: $name, 0, 1) . substr($lastName, 0, 1)
        ),
        'is_active' => $isActive,
    ];
}

// ══════════════════════════════════════════════════════════════
// PUBLIC ROUTES (no auth required)
// ══════════════════════════════════════════════════════════════

Route::get('/', fn() => redirect()->route('login'));

Route::get('/login',           fn() => view('auth.login'))          ->name('login');
Route::get('/signup',          fn() => view('auth.signup'))         ->name('signup');
Route::get('/forgot-password', fn() => view('auth.forgot-password'))->name('forgot-password');

// Public resource browsing (read-only, logic handled in JS)
Route::get('/resources/public', fn() => view('home.resources', ['activeNav' => 'resources']))
    ->name('resources.public');

// ── SET SESSION ───────────────────────────────────────────────
// Called from JS after Supabase login — stores user data + role

Route::post('/set-session', function (Request $request) {
    $userId       = (string) $request->input('user_id', '');
    $profilePhoto = (string) $request->input('profile_photo', '');

    if ($userId !== '' && $profilePhoto !== '') {
        try {
            $provider = new SupabaseServiceProvider();
            $provider->updateProfilePhoto($userId, $profilePhoto);
        } catch (\Throwable $e) {
            // Non-fatal — session still works
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

// ── LOGOUT ────────────────────────────────────────────────────

Route::post('/logout', function () {
    session()->flush();
    return redirect()->route('login');
})->name('logout');

// ══════════════════════════════════════════════════════════════
// AUTHENTICATED USER ROUTES
// ══════════════════════════════════════════════════════════════

Route::get('/dashboard', function () {
    if ($r = requireAuth()) return $r;
    return view('home.dashboard', ['activeNav' => 'dashboard']);
})->name('dashboard');

Route::get('/user_dashboard', [DashboardController::class, 'user_dashboard'])
    ->name('user_dashboard');

Route::get('/newsfeed', function () {
    if ($r = requireAuth()) return $r;
    return view('home.newsfeed', ['activeNav' => 'newsfeed']);
})->name('newsfeed');

Route::get('/calendar', function () {
    if ($r = requireAuth()) return $r;
    return view('home.calendar', ['activeNav' => 'calendar']);
})->name('calendar');

Route::get('/study-groups', function () {
    if ($r = requireAuth()) return $r;
    return view('home.study-groups', ['activeNav' => 'study-groups']);
})->name('study-groups');

Route::get('/resources', function () {
    if ($r = requireAuth()) return $r;
    return view('home.resources', ['activeNav' => 'resources']);
})->name('resources');

Route::get('/notifications', function () {
    if ($r = requireAuth()) return $r;
    return view('home.notifications', ['activeNav' => 'notifications']);
})->name('notifications');

Route::get('/messages', function () {
    if ($r = requireAuth()) return $r;
    return view('home.messages', ['activeNav' => 'messages']);
})->name('messages');

Route::get('/settings', function () {
    if ($r = requireAuth()) return $r;
    return view('home.settings', ['activeNav' => 'settings']);
})->name('settings');

// ── FOCUS MODE ────────────────────────────────────────────────

Route::get('/focus-mode',             [FocusModeController::class, 'index'])        ->name('focus-mode');
Route::post('/focus-mode/session',    [FocusModeController::class, 'storeSession']) ->name('focus-mode.session');
Route::post('/focus-mode/materials',  [FocusModeController::class, 'storeMaterial'])->name('focus-mode.materials');
Route::post('/focus-mode/flashcards', [FocusModeController::class, 'storeFlashcard'])->name('focus-mode.flashcards');

// ── FRIENDS ───────────────────────────────────────────────────

Route::get('/friends', function () {
    if ($r = requireAuth()) return $r;

    $currentUserId = (string) session('user_id', '');
    $provider      = new SupabaseServiceProvider();

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

    return view('home.friends', ['activeNav' => 'friends', 'friends' => $friends]);
})->name('friends');

// Friend requests — all handled by FriendRequestController
Route::get('/friend-requests',                          [FriendRequestController::class, 'index'])  ->name('friend-requests');
Route::post('/friend-requests/{receiverId}',            [FriendRequestController::class, 'send'])   ->name('friend-requests.send');
Route::post('/friend-requests/{friendRequest}/accept',  [FriendRequestController::class, 'accept']) ->name('friend-requests.accept');
Route::post('/friend-requests/{friendRequest}/decline', [FriendRequestController::class, 'decline'])->name('friend-requests.decline');
Route::post('/friend-requests/{friendRequest}/cancel',  [FriendRequestController::class, 'cancel']) ->name('friend-requests.cancel');
Route::post('/friends/{friendId}/remove',               [FriendRequestController::class, 'remove']) ->name('friends.remove');

// ── OWN PROFILE ───────────────────────────────────────────────

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

    $firstName = trim((string) ($dbProfile['first_name']        ?? $sessionFirstName));
    $lastName  = trim((string) ($dbProfile['last_name']         ?? $sessionLastName));
    $username  = trim((string) ($dbProfile['username']          ?? $sessionUsername));
    $email     = trim((string) ($dbProfile['email']             ?? ''));
    $photoUrl  = trim((string) ($dbProfile['profile_photo_url'] ?? $sessionPhoto));
    $userId    = trim((string) ($dbProfile['id']                ?? $sessionUserId));
    $joinedAt  = trim((string) ($dbProfile['created_at']        ?? $dbProfile['joined_at'] ?? ''));
    $bio       = trim((string) ($dbProfile['bio']               ?? $dbProfile['about'] ?? ''));
    $studentTypeRaw = trim((string) ($dbProfile['student_type'] ?? $sessionStudentType));

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

        $friendIds = [];
        foreach ($friendRows as $row) {
            $candidate = (string) ($row->user_id === $userId ? $row->friend_id : $row->user_id);
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

    $completedSessions = (int) session('focus_session_count', 0);
    $totalFocusSeconds = (int) session('focus_total_seconds', 0);
    $sessionMaterials  =       session('focus_materials', []);
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

// ── OTHER USER PROFILE ────────────────────────────────────────
// Must be defined AFTER /profile (own profile) to avoid conflicts.
// Accepts both UUID and username slugs.

Route::get('/profile/{userId}', function (string $userId) {
    if ($r = requireAuth()) return $r;

    $userId = trim($userId);
    if ($userId === '' || $userId === session('user_id')) {
        return redirect()->route('profile');
    }

    $currentUserId = (string) session('user_id', '');
    $provider      = new SupabaseServiceProvider();
    $viewedProfile = $provider->getProfileById($userId);

    $relationshipState = 'none';
    $pendingRequestId  = null;

    if (Friendship::areFriends($currentUserId, $userId)) {
        $relationshipState = 'friends';
    } else {
        $pendingRequest = FriendRequest::query()
            ->between($currentUserId, $userId)
            ->where('status', 'pending')
            ->first();

        if ($pendingRequest) {
            $pendingRequestId  = $pendingRequest->id;
            $relationshipState = $pendingRequest->sender_id === $currentUserId
                ? 'pending_outgoing'
                : 'pending_incoming';
        }
    }

    $friendRows = Friendship::query()
        ->where('user_id', $userId)
        ->orWhere('friend_id', $userId)
        ->get(['user_id', 'friend_id']);

    $friendIds = [];
    foreach ($friendRows as $row) {
        $candidate = (string) ($row->user_id === $userId ? $row->friend_id : $row->user_id);
        if ($candidate !== '' && $candidate !== $userId) {
            $friendIds[$candidate] = true;
        }
    }

    $friendProfiles = [];
    foreach (array_keys($friendIds) as $friendId) {
        $friendProfiles[] = resolveFriendProfileEntry($provider, $friendId);
    }

    return view('home.profile-view', [
        'userId'            => $userId,
        'username'          => $viewedProfile['username'] ?? $userId,
        'profileData'       => ['friends' => $friendProfiles, 'profile' => $viewedProfile],
        'relationshipState' => $relationshipState,
        'pendingRequestId'  => $pendingRequestId,
        'activeNav'         => 'profile',
    ]);
})->name('profile.view');

// ══════════════════════════════════════════════════════════════
// ADMIN ROUTES (admin / moderator only)
// ══════════════════════════════════════════════════════════════

Route::prefix('admin')->group(function () {

    Route::get('/', function () {
        if ($r = requireAdmin()) return $r;
        return view('admin.dashboard');
    })->name('admin.dashboard');

    Route::get('/users', function () {
        if ($r = requireAdmin()) return $r;
        return view('admin.users');
    })->name('admin.users');

    Route::get('/reports', function () {
        if ($r = requireModeratorOrAdmin()) return $r;
        return view('admin.reports');
    })->name('admin.reports');

    Route::get('/resources', function () {
        if ($r = requireModeratorOrAdmin()) return $r;
        return view('admin.resources');
    })->name('admin.resources');

    Route::get('/logs', function () {
        if ($r = requireAdmin()) return $r;
        return view('admin.logs');
    })->name('admin.logs');

    // ── NEW: Posts Feed tab ────────────────────────────────────
    Route::get('/posts', [AdminController::class, 'posts'])->name('admin.posts');

    Route::get('/settings', function () {
        if ($r = requireAdmin()) return $r;
        return view('admin.settings');
    })->name('admin.settings');

});
