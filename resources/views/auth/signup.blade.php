<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - StudyHub</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>
<body>
<div class="container wide">
    <div class="card">
        <div class="logo">
            <h1>StudyHub</h1>
            <p>Start your learning journey today</p>
        </div>

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
                        I agree to the <a href="#" onclick="showTerms(event)">Terms and Conditions</a> and <a href="#" onclick="showPrivacy(event)">Privacy Policy</a>
                    </label>
                </div>
                <div class="error-message" id="termsError"></div>
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn">Create Account</button>
            <div class="loading" id="loading">Creating your account...</div>
        </form>

        <div class="bottom-link">
            Already have an account? <a href="{{ route('login') }}">Log in</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script>
    const SUPABASE_URL     = '{{ env("SUPABASE_URL") }}';
    const SUPABASE_ANON_KEY = '{{ env("SUPABASE_ANON_KEY") }}';
    const LOGIN_URL        = '{{ route("login") }}';
</script>
<script src="{{ asset('js/signup.js') }}"></script>
</body>
</html>
