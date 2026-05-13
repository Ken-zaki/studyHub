<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;


class AuthController extends Controller
{
    // ──────────────────────────────────────────────
    // AUTH PAGES
    // ──────────────────────────────────────────────

    public function showLogin()
    {
        return view('auth.login');
    }

    public function showSignup()
    {
        return view('auth.signup');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    // ──────────────────────────────────────────────
    // LOGIN  (POST)
    // On success: set session and redirect to dashboard
    // ──────────────────────────────────────────────
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // 1. Sign in via Supabase Auth REST API
        $response = Http::post(env('SUPABASE_URL') . '/auth/v1/token?grant_type=password', [
            'email'    => $request->email,
            'password' => $request->password,
        ], [
            'headers' => [
                'apikey'       => env('SUPABASE_ANON_KEY'),
                'Content-Type' => 'application/json',
            ],
        ]);

        if ($response->failed()) {
            return back()->withErrors(['email' => 'Invalid credentials. Please try again.']);
        }

        $authData = $response->json();
        $userId   = $authData['user']['id'] ?? null;

        if (!$userId) {
            return back()->withErrors(['email' => 'Authentication failed.']);
        }

        // 2. Fetch profile from public.profiles
        $profileResponse = Http::withHeaders([
            'apikey'        => env('SUPABASE_ANON_KEY'),
            'Authorization' => 'Bearer ' . env('SUPABASE_ANON_KEY'),
        ])->get(env('SUPABASE_URL') . '/rest/v1/profiles?id=eq.' . $userId . '&select=*');

        $profiles = $profileResponse->json();
        $profile  = $profiles[0] ?? [];

        // 3. Store everything in Laravel session
        Session::put([
            'user_id'            => $userId,
            'user_first_name'    => $profile['first_name']        ?? '',
            'user_last_name'     => $profile['last_name']         ?? '',
            'user_username'      => $profile['username']          ?? '',
            'user_profile_photo' => $profile['profile_photo_url'] ?? '',
            'supabase_token'     => $authData['access_token']     ?? '',
        ]);

        return redirect()->route('dashboard');
    }

    // ──────────────────────────────────────────────
    // SIGNUP  (POST)
    // ──────────────────────────────────────────────
    public function signup(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'username'   => 'required|string|max:50',
            'email'      => 'required|email',
            'password'   => 'required|min:8|confirmed',
            'birthday'   => 'required|date',
        ]);

        // 1. Create auth user via Supabase
        $authResponse = Http::withHeaders([
            'apikey'       => env('SUPABASE_ANON_KEY'),
            'Content-Type' => 'application/json',
        ])->post(env('SUPABASE_URL') . '/auth/v1/signup', [
            'email'    => $request->email,
            'password' => $request->password,
        ]);

        if ($authResponse->failed()) {
            return back()->withErrors(['email' => 'Signup failed: ' . ($authResponse->json()['msg'] ?? 'Unknown error')]);
        }

        $userId = $authResponse->json()['user']['id'] ?? null;
        if (!$userId) {
            return back()->withErrors(['email' => 'Could not create user account.']);
        }

        // 2. Insert into public.profiles using service key
        $profileResponse = Http::withHeaders([
            'apikey'        => env('SUPABASE_SERVICE_KEY'),
            'Authorization' => 'Bearer ' . env('SUPABASE_SERVICE_KEY'),
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ])->post(env('SUPABASE_URL') . '/rest/v1/profiles', [
            'id'         => $userId,
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'username'   => $request->username,
            'email'      => $request->email,
            'birthday'   => $request->birthday,
        ]);

        if ($profileResponse->failed()) {
            return back()->withErrors(['email' => 'Profile creation failed: ' . ($profileResponse->json()['message'] ?? 'Unknown error')]);
        }

        return redirect()->route('login')->with('success', 'Account created! Please log in.');
    }

    // ──────────────────────────────────────────────
    // LOGOUT
    // ──────────────────────────────────────────────
    public function logout(Request $request)
    {
        Session::flush();
        return redirect()->route('login');
    }

    // ──────────────────────────────────────────────
    // PAGE CONTROLLERS
    // Each method passes $activeNav so the sidebar
    // highlights the correct nav item automatically.
    // ──────────────────────────────────────────────

    public function dashboard()
    {
        $this->requireAuth();
        return view('home.dashboard', ['activeNav' => 'dashboard']);
    }

    public function calendar()
    {
        $this->requireAuth();
        return view('home.calendar', ['activeNav' => 'calendar']);
    }

    public function studyGroups()
    {
        $this->requireAuth();
        return view('home.study-groups', ['activeNav' => 'study-groups']);
    }

    public function resources()
    {
        $this->requireAuth();
        return view('home.resources', ['activeNav' => 'resources']);
    }

    public function notifications()
    {
        $this->requireAuth();
        return view('home.notifications', ['activeNav' => 'notifications']);
    }

    public function messages()
    {
        $this->requireAuth();
        return view('home.messages', ['activeNav' => 'messages']);
    }

    public function profile()
    {
        $this->requireAuth();
        return view('home.profile', ['activeNav' => 'profile']);
    }

    public function settings()
    {
        $this->requireAuth();
        return view('home.settings', ['activeNav' => 'settings']);
    }

    // ──────────────────────────────────────────────
    // HELPER: redirect to login if not authenticated
    // ──────────────────────────────────────────────
    private function requireAuth()
    {
        if (!Session::has('user_id') || empty(Session::get('user_id'))) {
            abort(redirect()->route('login'));
        }
    }

    // ──────────────────────────────────────────────
    // FORGOT PASSWORD (POST)
    // Sends OTP code to user's email via Supabase
    // ──────────────────────────────────────────────
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $response = Http::withHeaders([
            'apikey'       => env('SUPABASE_ANON_KEY'),
            'Content-Type' => 'application/json',
        ])->post(env('SUPABASE_URL') . '/auth/v1/otp', [
            'email' => $request->email,
            'create_user' => false,
        ]);

        // Always show success to avoid email enumeration
        return back()->with('success', 'If that email exists, a reset code has been sent.');
    }

    // ──────────────────────────────────────────────
    // SHOW VERIFY OTP PAGE
    // ──────────────────────────────────────────────
    public function showVerifyOtp()
    {
        return view('auth.verify-otp');
    }

    // ──────────────────────────────────────────────
    // VERIFY OTP (POST)
    // Validates the code and stores access token
    // ──────────────────────────────────────────────
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        $response = Http::withHeaders([
            'apikey'       => env('SUPABASE_ANON_KEY'),
            'Content-Type' => 'application/json',
        ])->post(env('SUPABASE_URL') . '/auth/v1/verify', [
            'type'  => 'recovery',
            'token' => $request->token,
            'email' => $request->email,
        ]);

        if ($response->failed()) {
            return back()->withErrors(['token' => 'Invalid or expired code. Please try again.']);
        }

        // Store access token so user can set a new password
        Session::put('reset_access_token', $response->json()['access_token']);
        Session::put('reset_email', $request->email);

        return redirect()->route('password.reset');
    }

    // ──────────────────────────────────────────────
    // SHOW NEW PASSWORD PAGE
    // ──────────────────────────────────────────────
    public function showResetPassword()
    {
        if (!Session::has('reset_access_token')) {
            return redirect()->route('login');
        }

        return view('auth.reset-password');
    }

    // ──────────────────────────────────────────────
    // RESET PASSWORD (POST)
    // Updates the user's password using the access token
    // ──────────────────────────────────────────────
    public function resetPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|min:8|confirmed',
        ]);

        $accessToken = Session::get('reset_access_token');

        if (!$accessToken) {
            return redirect()->route('login')->withErrors(['token' => 'Session expired. Please start again.']);
        }

        $response = Http::withHeaders([
            'apikey'        => env('SUPABASE_ANON_KEY'),
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type'  => 'application/json',
        ])->put(env('SUPABASE_URL') . '/auth/v1/user', [
            'password' => $request->password,
        ]);

        if ($response->failed()) {
            return back()->withErrors(['password' => 'Failed to reset password. Please try again.']);
        }

        // Clean up reset session keys
        Session::forget(['reset_access_token', 'reset_email']);

        return redirect()->route('login')->with('success', 'Password reset! Please log in.');
    }
}

