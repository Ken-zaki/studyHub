<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - StudyHub</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>
<body>
<div class="container">
    <div class="card">
        <div class="logo">
            <h1>StudyHub</h1>
            <p>Welcome back! Ready to study?</p>
        </div>

        @if(session('success'))
            <div class="alert-success" style="margin-bottom:16px;">
                {{ session('success') }}
            </div>
        @endif

        <form id="loginForm">
            @csrf
            <div class="form-group">
                <label for="email">Email or Username</label>
                <input type="text" id="email" name="email" required
                       placeholder="Enter your email or username">
                <div class="error-message" id="emailError"></div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required
                           placeholder="Enter your password">
                    <button type="button" class="toggle-password" onclick="togglePassword()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" id="eyeIcon">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5
                                   c4.478 0 8.268 2.943 9.542 7
                                   -1.274 4.057-5.064 7-9.542 7
                                   -4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>
                <div class="error-message" id="passwordError"></div>
            </div>

            <div class="forgot-password">
                <a href="{{ route('forgot-password') }}">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-primary">Log In</button>
            <div class="loading" id="loading">Logging in...</div>
        </form>

        <div class="bottom-link">
            Don't have an account? <a href="{{ route('signup') }}">Sign up</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script>
    const SUPABASE_URL      = '{{ env("SUPABASE_URL") }}';
    const SUPABASE_ANON_KEY = '{{ env("SUPABASE_ANON_KEY") }}';
    const SET_SESSION_URL   = '{{ route("set-session") }}';
    const NEWSFEED_URL      = '{{ route("newsfeed") }}';
    const ADMIN_URL         = '{{ route("admin.dashboard") }}';
    const CSRF_TOKEN        = '{{ csrf_token() }}';
</script>
<script src="{{ asset('js/login.js') }}"></script>
@include('layouts.admin_bar')
</body>
</html>
