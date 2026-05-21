<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - StudyHub</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <style>
        /* ── Step indicator (reused from forgot-password) ──── */
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 28px;
        }
        .step-indicator .step {
            width: 32px; height: 32px;
            border-radius: 50%;
            border: 2px solid var(--border, #e2e8f0);
            background: white;
            color: var(--text-muted, #64748b);
            font-size: 13px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            transition: all .25s;
        }
        .step-indicator .step.active    { border-color: var(--primary, #4f46e5); color: var(--primary, #4f46e5); background: rgba(79,70,229,.07); }
        .step-indicator .step.completed { border-color: var(--success, #16a34a); background: var(--success, #16a34a); color: white; }
        .step-indicator .step-line      { flex: 1; max-width: 40px; height: 2px; background: var(--border, #e2e8f0); border-radius: 2px; }
        .form-section                   { display: none; }
        .form-section.active            { display: block; }
        .alert-success {
            background: #f0fdf4; border: 1px solid #bbf7d0;
            color: #15803d; border-radius: 8px;
            padding: 10px 14px; font-size: 13px; margin-bottom: 18px;
        }

        /* ── OTP input ── */
        .otp-hint {
            font-size: 13px; color: var(--text-muted, #64748b);
            margin-bottom: 18px; line-height: 1.6;
        }
        .otp-hint strong { color: var(--text, #1e293b); }
        .resend-row {
            text-align: center; margin-top: 14px;
            font-size: 13px; color: var(--text-muted, #64748b);
        }
        .resend-btn {
            background: none; border: none; padding: 0;
            color: var(--primary, #4f46e5); font-size: 13px;
            cursor: pointer; text-decoration: underline;
            font-family: inherit;
        }
        .resend-btn:disabled { color: var(--text-muted, #94a3b8); text-decoration: none; cursor: default; }
    </style>
</head>
<body>
<div class="container wide">
    <div class="card">
        <div class="logo">
            <h1>StudyHub</h1>
            <p>Start your learning journey today</p>
        </div>

        <div class="step-indicator">
            <div class="step active"    id="step1">1</div>
            <div class="step-line"></div>
            <div class="step"           id="step2">2</div>
        </div>

        {{-- ── Step 1: Registration form ──────────────────────── --}}
        <div class="form-section active" id="signupSection">
            <form id="signupForm">
                @csrf
                <div class="form-group">
                    <label for="username">Username <span class="required">*</span></label>
                    <input type="text" id="username" name="username" required placeholder="Choose a unique username">
                    <div class="error-message" id="usernameError"></div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">First Name <span class="required">*</span></label>
                        <input type="text" id="firstName" name="firstName" required placeholder="John">
                        <div class="error-message" id="firstNameError"></div>
                    </div>
                    <div class="form-group">
                        <label for="lastName">Last Name <span class="required">*</span></label>
                        <input type="text" id="lastName" name="lastName" required placeholder="Doe">
                        <div class="error-message" id="lastNameError"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="birthday">Birthday <span class="required">*</span></label>
                    <input type="date" id="birthday" name="birthday" required>
                    <div class="error-message" id="birthdayError"></div>
                </div>

                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required placeholder="your.email@example.com">
                    <div class="error-message" id="emailError"></div>
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required placeholder="Create a strong password">
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="error-message" id="passwordError"></div>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="confirmPassword" name="confirmPassword" required placeholder="Re-enter your password">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword')">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="error-message"  id="confirmPasswordError"></div>
                    <div class="success-message" id="confirmSuccess"></div>
                </div>

                <div class="checkbox-group">
                    <div class="checkbox-wrapper" id="termsWrapper">
                        <input type="checkbox" id="termsAgreement" name="termsAgreement" required>
                        <label for="termsAgreement" class="checkbox-label">
                            I agree to the <a href="#" onclick="showTerms(event)">Terms and Conditions</a>
                            and <a href="#" onclick="showPrivacy(event)">Privacy Policy</a>
                        </label>
                    </div>
                    <div class="error-message" id="termsError"></div>
                </div>

                <button type="submit" class="btn btn-primary" id="submitBtn">Create Account</button>
                <div class="loading" id="signupLoading">Sending verification code…</div>
            </form>
        </div>

        {{-- ── Step 2: OTP verification ────────────────────────── --}}
        <div class="form-section" id="otpSection">
            <div class="alert-success">A 6-digit verification code has been sent to your email!</div>
            <form id="otpForm">
                @csrf
                <div class="otp-hint">
                    Enter the code sent to <strong id="otpEmailDisplay"></strong>.
                    The code expires in 10 minutes.
                </div>
                <div class="form-group">
                    <label for="otp">Verification Code</label>
                    <input type="text" id="otp" name="otp" required
                           placeholder="Enter 6-digit code" maxlength="6"
                           inputmode="numeric" autocomplete="one-time-code">
                    <div class="error-message" id="otpError"></div>
                </div>
                <button type="submit" class="btn btn-primary">Verify & Complete Sign Up</button>
                <div class="loading" id="otpLoading">Verifying code…</div>

                <div class="resend-row">
                    Didn't get it?
                    <button type="button" class="resend-btn" id="resendBtn" onclick="resendCode()">
                        Resend code
                    </button>
                </div>
            </form>
        </div>

        <div class="bottom-link" id="bottomLink">
            Already have an account? <a href="{{ route('login') }}">Log in</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script>
    const SUPABASE_URL      = '{{ env("SUPABASE_URL") }}';
    const SUPABASE_ANON_KEY = '{{ env("SUPABASE_ANON_KEY") }}';
    const LOGIN_URL         = '{{ route("login") }}';
</script>
<script src="{{ asset('js/signup.js') }}"></script>
</body>
</html>
