<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\FriendController;

// Home - Redirect to Login
Route::get('/', function () {
    return redirect()->route('login');
});

// Authentication Routes
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::get('/signup', function () {
    return view('auth.signup');
})->name('signup');

Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->name('password.request');

// Dashboard (Protected - will add middleware later)
Route::get('/dashboard', function () {
    return view('home.dashboard');
})->name('dashboard');

Route::get('/calendar', function () {
    return view('home.calendar');
})->name('calendar');

Route::get('/study-groups', function () {
    return view('home.study-groups');
})->name('study-groups');

Route::get('/resources', function () {
    return view('home.resources');
})->name('resources');

Route::get('/notifications', function () {
    return view('home.notifications');
})->name('notifications');

Route::get('/messages', [MessageController::class, 'index'])->name('messages');
Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
Route::get('/search/universal', [SearchController::class, 'universal'])->name('universal-search');

// Friend routes
Route::get('/friends', [FriendController::class, 'index'])->name('friends');
Route::get('/friend-requests', [FriendController::class, 'requests'])->name('friend-requests');
Route::post('/friend-request/send', [FriendController::class, 'sendRequest'])->name('friend-request.send');
Route::post('/friend-request/{requestId}/accept', [FriendController::class, 'acceptRequest'])->name('friend-request.accept');
Route::post('/friend-request/{requestId}/reject', [FriendController::class, 'rejectRequest'])->name('friend-request.reject');
Route::post('/friend/{friendId}/remove', [FriendController::class, 'removeFriend'])->name('friend.remove');

Route::get('/settings', function () {
    return view('home.settings');
})->name('settings');


Route::post('/set-session', function (Request $request) {
    session([
        'user_id' => $request->input('user_id'),
        'user_first_name' => $request->input('first_name'),
        'user_last_name' => $request->input('last_name'),
        'user_username' => $request->input('username'),
        'user_profile_photo' => $request->input('profile_photo')
    ]);

    return response()->json(['success' => true]);
})->name('set-session');

// Logout (POST route for security)
Route::post('/logout', function (Request $request) {
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');
