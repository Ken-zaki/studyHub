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
    // ──────────────────────────────────────────────
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $sbUrl  = config('services.supabase.url');
        $sbAnon = config('services.supabase.anon_key');
        $sbSvc  = config('services.supabase.service_key');

        // 1. Sign in via Supabase Auth REST API
        $response = Http::withHeaders([
            'apikey'       => $sbAnon,
            'Content-Type' => 'application/json',
        ])->post("{$sbUrl}/auth/v1/token?grant_type=password", [
            'email'    => $request->email,
            'password' => $request->password,
        ]);

        if ($response->failed()) {
            return back()->withErrors(['email' => 'Invalid credentials. Please try again.']);
        }

        $authData = $response->json();
        $userId   = $authData['user']['id'] ?? null;

        if (!$userId) {
            return back()->withErrors(['email' => 'Authentication failed.']);
        }

        // 2. Fetch profile from public.profiles (use service key to bypass RLS)
        $profileResponse = Http::withHeaders([
            'apikey'        => $sbSvc,
            'Authorization' => "Bearer {$sbSvc}",
        ])->get("{$sbUrl}/rest/v1/profiles?id=eq.{$userId}&select=*");

        $profile = $profileResponse->json()[0] ?? [];

        // 3. Store everything in Laravel session — including role
        Session::put([
            'user_id'            => $userId,
            'user_first_name'    => $profile['first_name']        ?? '',
            'user_last_name'     => $profile['last_name']         ?? '',
            'user_username'      => $profile['username']          ?? '',
            'user_profile_photo' => $profile['profile_photo_url'] ?? '',
            'user_role'          => $profile['role']              ?? 'student', // FIX: was never stored
            'supabase_token'     => $authData['access_token']     ?? '',
        ]);

        // 4. Redirect admins/moderators to admin dashboard, others to user dashboard
        $role = $profile['role'] ?? 'student';
        if (in_array($role, ['admin', 'moderator'])) {
            return redirect()->route('admin.dashboard');
        }

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

        $sbUrl  = config('services.supabase.url');
        $sbAnon = config('services.supabase.anon_key');
        $sbSvc  = config('services.supabase.service_key');

        // 1. Create auth user via Supabase
        $authResponse = Http::withHeaders([
            'apikey'       => $sbAnon,
            'Content-Type' => 'application/json',
        ])->post("{$sbUrl}/auth/v1/signup", [
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
            'apikey'        => $sbSvc,
            'Authorization' => "Bearer {$sbSvc}",
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ])->post("{$sbUrl}/rest/v1/profiles", [
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
    // ──────────────────────────────────────────────
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $sbUrl  = config('services.supabase.url');
        $sbAnon = config('services.supabase.anon_key');

        Http::withHeaders([
            'apikey'       => $sbAnon,
            'Content-Type' => 'application/json',
        ])->post("{$sbUrl}/auth/v1/otp", [
            'email'       => $request->email,
            'create_user' => false,
        ]);

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
    // ──────────────────────────────────────────────
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
        ]);

        $sbUrl  = config('services.supabase.url');
        $sbAnon = config('services.supabase.anon_key');

        $response = Http::withHeaders([
            'apikey'       => $sbAnon,
            'Content-Type' => 'application/json',
        ])->post("{$sbUrl}/auth/v1/verify", [
            'type'  => 'recovery',
            'token' => $request->token,
            'email' => $request->email,
        ]);

        if ($response->failed()) {
            return back()->withErrors(['token' => 'Invalid or expired code. Please try again.']);
        }

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
    // ──────────────────────────────────────────────
    public function resetPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|min:8|confirmed',
        ]);

        $sbUrl      = config('services.supabase.url');
        $sbAnon     = config('services.supabase.anon_key');
        $accessToken = Session::get('reset_access_token');

        if (!$accessToken) {
            return redirect()->route('login')->withErrors(['token' => 'Session expired. Please start again.']);
        }

        $response = Http::withHeaders([
            'apikey'        => $sbAnon,
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type'  => 'application/json',
        ])->put("{$sbUrl}/auth/v1/user", [
            'password' => $request->password,
        ]);

        if ($response->failed()) {
            return back()->withErrors(['password' => 'Failed to reset password. Please try again.']);
        }

        Session::forget(['reset_access_token', 'reset_email']);

        return redirect()->route('login')->with('success', 'Password reset! Please log in.');
    }
}
