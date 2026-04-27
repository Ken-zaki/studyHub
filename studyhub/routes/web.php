<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\FocusModeController;
use App\Http\Controllers\CalendarController;

// All use statements at the TOP ↑

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
})->name('password.request');

Route::get('/dashboard', function () {
    return view('home.dashboard');
})->name('dashboard');

// ✅ Single definition, no auth middleware (your controller handles the session check)
Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');

Route::get('/study-groups', function () {
    return view('home.study-groups');
})->name('study-groups');

Route::get('/resources', function () {
    return view('home.resources');
})->name('resources');

Route::get('/notifications', function () {
    return view('home.notifications');
})->name('notifications');

Route::get('/messages', function () {
    return view('home.messages');
})->name('messages');

Route::get('/profile', function () {
    return view('home.profile');
})->name('profile');

Route::get('/settings', function () {
    return view('home.settings');
})->name('settings');

Route::post('/set-session', function (Request $request) {
    session([
        'user_id'           => $request->input('user_id'),
        'user_first_name'   => $request->input('first_name'),
        'user_last_name'    => $request->input('last_name'),
        'user_username'     => $request->input('username'),
        'user_profile_photo'=> $request->input('profile_photo'),
    ]);
    return response()->json(['success' => true]);
})->name('set-session');

Route::post('/logout', function () {
    session()->flush();
    return redirect()->route('login');
})->name('logout');

Route::get('/focus-mode', [FocusModeController::class, 'index'])->name('focus-mode');
Route::post('/focus-mode/session', [FocusModeController::class, 'storeSession'])->name('focus-mode.session');
Route::post('/focus-mode/materials', [FocusModeController::class, 'storeMaterial'])->name('focus-mode.materials');
Route::post('/focus-mode/flashcards', [FocusModeController::class, 'storeFlashcard'])->name('focus-mode.flashcards');
