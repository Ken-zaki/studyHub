<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\FocusModeController;

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

Route::post('/logout', function () {
    session()->flush();
    return redirect()->route('login');
})->name('logout');

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

Route::get('/dashboard', function () {
    return view('home.dashboard', ['activeNav' => 'dashboard']);
})->name('dashboard');

Route::get('/newsfeed', function () {
    return view('home.newsfeed', ['activeNav' => 'newsfeed']);
})->name('newsfeed');

Route::get('/study-groups', function () {
    return view('home.study-groups', ['activeNav' => 'study-groups']);
})->name('study-groups');

Route::get('/resources', function () {
    return view('home.resources', ['activeNav' => 'resources']);
})->name('resources');

Route::get('/focus-mode',            [FocusModeController::class, 'index'])->name('focus-mode');
Route::post('/focus-mode/session', [FocusModeController::class, 'storeSession'])->name('focus-mode.session');
Route::post('/focus-mode/materials', [FocusModeController::class, 'storeMaterial'])->name('focus-mode.materials');
Route::post('/focus-mode/flashcards', [FocusModeController::class, 'storeFlashcard'])->name('focus-mode.flashcards');

Route::get('/messages', function () {
    return view('home.messages', ['activeNav' => 'messages']);
})->name('messages');

Route::get('/settings', function () {
    return view('home.settings', ['activeNav' => 'settings']);
})->name('settings');

Route::get('/notifications', function () {
    return view('home.notifications', ['activeNav' => 'notifications']);
})->name('notifications');

Route::get('/profile', function () {
    return view('home.profile', ['activeNav' => 'profile']);
})->name('profile');

Route::get('/calendar', function () {
    return view('home.calendar', ['activeNav' => 'calendar']);
})->name('calendar');
