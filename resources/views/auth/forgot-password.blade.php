<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - StudyHub</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>
<body>
<div class="container">
    <div class="card">
        <div class="logo">
            <h1>StudyHub</h1>
            <p>Reset your password</p>
        </div>

        <div class="step-indicator">
            <div class="step active" id="step1">1</div>
            <div class="step"        id="step2">2</div>
            <div class="step"        id="step3">3</div>
        </div>

        {{-- Step 1: Email --}}
        <div class="form-section active" id="emailSection">
            <form id="emailForm">
                @csrf
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email">
                    <div class="error-message" id="emailError"></div>
                </div>
                <button type="submit" class="btn btn-primary">Send Reset Code</button>
                <div class="loading" id="emailLoading">Sending code...</div>
            </form>
        </div>

        {{-- Step 2: OTP --}}
        <div class="form-section" id="otpSection">
            <div class="alert-success">A reset code has been sent to your email!</div>
            <form id="otpForm">
                @csrf
                <div class="form-group">
                    <label for="otp">Enter Reset Code</label>
                    <input type="text" id="otp" name="otp" required placeholder="Enter 6-digit code" maxlength="6">
                    <div class="error-message" id="otpError"></div>
                </div>
                <button type="submit" class="btn btn-primary">Verify Code</button>
                <div class="loading" id="otpLoading">Verifying...</div>
            </form>
        </div>

        {{-- Step 3: New Password --}}
        <div class="form-section" id="passwordSection">
            <div class="alert-success">Code verified! Enter your new password.</div>
            <form id="passwordForm">
                @csrf
                <div class="form-group">
                    <label for="newPassword">New Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="newPassword" name="newPassword" required placeholder="Enter new password">
                        <button type="button" class="toggle-password" onclick="togglePassword('newPassword')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="password-strength"><div class="strength-bar" id="strengthBar"></div></div>
                    <div class="strength-text" id="strengthText"></div>
                    <div class="error-message"  id="passwordError"></div>
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirmPassword" name="confirmPassword" required placeholder="Re-enter password">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="error-message" id="confirmError"></div>
                </div>
                <button type="submit" class="btn btn-primary">Reset Password</button>
                <div class="loading" id="passwordLoading">Resetting password...</div>
            </form>
        </div>

        <div class="back-link">
            <a href="{{ route('login') }}">← Back to login</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script>
    const SUPABASE_URL        = '{{ env("SUPABASE_URL") }}';
    const SUPABASE_ANON_KEY   = '{{ env("SUPABASE_ANON_KEY") }}';
    const LOGIN_URL           = '{{ route("login") }}';
    const RESET_PASSWORD_URL  = '{{ url("/reset-password") }}';
</script>
<script src="{{ asset('js/forgot-password.js') }}"></script>
</body>
</html>
