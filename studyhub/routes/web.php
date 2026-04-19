<?php

use Illuminate\Support\Facades\Route;

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

// Logout (POST route for security)
Route::post('/logout', function () {
    // Logout logic here
    return redirect()->route('login');
})->name('logout');
