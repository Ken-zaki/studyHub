<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\FocusModeController;

// ── HELPERS ───────────────────────────────────────────────────
// Reusable auth + role check closures
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

// ──────────────────────────────────────────────────────────────
// PUBLIC ROUTES (guests can access)
// ──────────────────────────────────────────────────────────────
Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::get('/signup', function () {
    return view('auth.signup');
})->name('signup');

Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->name('forgot-password');

// Guests can browse public resources (read-only, handled in JS)
Route::get('/resources/public', function () {
    return view('home.resources', ['activeNav' => 'resources']);
})->name('resources.public');

// ──────────────────────────────────────────────────────────────
// SET SESSION (called from JS after Supabase login)
// ──────────────────────────────────────────────────────────────
Route::post('/set-session', function (Request $request) {
    session([
        'user_id'            => $request->input('user_id'),
        'user_first_name'    => $request->input('first_name'),
        'user_last_name'     => $request->input('last_name'),
        'user_username'      => $request->input('username'),
        'user_profile_photo' => $request->input('profile_photo'),
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
Route::get('/dashboard', function () {
    if ($r = requireAuth()) return $r;
    return view('home.dashboard', ['activeNav' => 'dashboard']);
})->name('dashboard');

Route::get('/newsfeed', function () {
    if ($r = requireAuth()) return $r;
    return view('home.newsfeed', ['activeNav' => 'newsfeed']);
})->name('newsfeed');

Route::get('/study-groups', function () {
    if ($r = requireAuth()) return $r;
    return view('home.study-groups', ['activeNav' => 'study-groups']);
})->name('study-groups');

Route::get('/resources', function () {
    if ($r = requireAuth()) return $r;
    return view('home.resources', ['activeNav' => 'resources']);
})->name('resources');

Route::get('/messages', function () {
    if ($r = requireAuth()) return $r;
    return view('home.messages', ['activeNav' => 'messages']);
})->name('messages');

Route::get('/settings', function () {
    if ($r = requireAuth()) return $r;
    return view('home.settings', ['activeNav' => 'settings']);
})->name('settings');

Route::get('/notifications', function () {
    if ($r = requireAuth()) return $r;
    return view('home.notifications', ['activeNav' => 'notifications']);
})->name('notifications');

Route::get('/profile', function () {
    if ($r = requireAuth()) return $r;
    return view('home.profile', ['activeNav' => 'profile']);
})->name('profile');

Route::get('/calendar', function () {
    if ($r = requireAuth()) return $r;
    return view('home.calendar', ['activeNav' => 'calendar']);
})->name('calendar');

// Focus Mode (controller handles it)
Route::get('/focus-mode',             [FocusModeController::class, 'index'])->name('focus-mode');
Route::post('/focus-mode/session',    [FocusModeController::class, 'storeSession']);
Route::post('/focus-mode/materials',  [FocusModeController::class, 'storeMaterial']);
Route::post('/focus-mode/flashcards', [FocusModeController::class, 'storeFlashcard']);

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

});
