<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// ──────────────────────────────────────────────
// HOME → redirect to login
// ──────────────────────────────────────────────
Route::get('/', function () {
    return redirect()->route('login');
});

// ──────────────────────────────────────────────
// AUTH ROUTES
// ──────────────────────────────────────────────
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::get('/signup', function () {
    return view('auth.signup');
})->name('signup');

Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->name('forgot-password');

Route::post('/logout', function () {
    session()->flush();
    return redirect()->route('login');
})->name('logout');

// ──────────────────────────────────────────────
// SET SESSION (called from JS after Supabase login)
// ──────────────────────────────────────────────
Route::post('/set-session', function (Request $request) {
    session([
        'user_id'            => $request->input('user_id'),
        'user_first_name'    => $request->input('first_name'),
        'user_last_name'     => $request->input('last_name'),
        'user_username'      => $request->input('username'),
        'user_profile_photo' => $request->input('profile_photo'),
    ]);
    return response()->json(['success' => true]);
})->name('set-session');

// ──────────────────────────────────────────────
// PAGE ROUTES
// Order: Dashboard → Newsfeed → Study Groups →
//        Resources → Focus Mode → Messages → Settings
// ──────────────────────────────────────────────

// Dashboard (Coming Soon)
Route::get('/dashboard', function () {
    return view('home.dashboard', ['activeNav' => 'dashboard']);
})->name('dashboard');

// Newsfeed (the main feed page)
Route::get('/newsfeed', function () {
    return view('home.newsfeed', ['activeNav' => 'newsfeed']);
})->name('newsfeed');

// Study Groups
Route::get('/study-groups', function () {
    return view('home.study-groups', ['activeNav' => 'study-groups']);
})->name('study-groups');

// Resources
Route::get('/resources', function () {
    return view('home.resources', ['activeNav' => 'resources']);
})->name('resources');

// Focus Mode
Route::get('/focus-mode', function () {
    return view('home.focus-mode', ['activeNav' => 'focus-mode']);
})->name('focus-mode');

// Messages
Route::get('/messages', function () {
    return view('home.messages', ['activeNav' => 'messages']);
})->name('messages');

// Settings
Route::get('/settings', function () {
    return view('home.settings', ['activeNav' => 'settings']);
})->name('settings');

// ── STILL ACCESSIBLE BUT NOT IN SIDEBAR ──────
Route::get('/notifications', function () {
    return view('home.notifications', ['activeNav' => 'notifications']);
})->name('notifications');

Route::get('/profile', function () {
    return view('home.profile', ['activeNav' => 'profile']);
})->name('profile');

Route::get('/calendar', function () {
    return view('home.calendar', ['activeNav' => 'calendar']);
})->name('calendar');
