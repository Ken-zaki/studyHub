<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a5f7a;
            --primary-dark: #144d61;
            --secondary: #f59e42;
            --accent: #ff6b6b;
            --bg-light: #fef9f2;
            --bg-white: #ffffff;
            --text-dark: #2d3748;
            --text-medium: #4a5568;
            --text-light: #718096;
            --border: #e2e8f0;
            --error: #e53e3e;
            --success: #48bb78;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #1a5f7a 0%, #2d7a94 50%, #4a9fb8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(245, 158, 66, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 20s ease-in-out infinite;
        }

        body::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255, 107, 107, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 15s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, 30px) scale(1.05); }
        }

        .container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 550px;
        }

        .card {
            background: var(--bg-white);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo h1 {
            font-family: 'Fraunces', serif;
            font-size: 42px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .logo p {
            color: var(--text-light);
            font-size: 15px;
            font-weight: 500;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 14px;
        }

        label .required {
            color: var(--error);
            margin-left: 2px;
        }

        input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.3s ease;
            background: var(--bg-light);
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--bg-white);
            box-shadow: 0 0 0 4px rgba(26, 95, 122, 0.1);
        }

        input.error {
            border-color: var(--error);
        }

        input.error:focus {
            box-shadow: 0 0 0 4px rgba(229, 62, 62, 0.1);
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-light);
            padding: 8px;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-password:hover {
            color: var(--primary);
        }

        .toggle-password svg {
            width: 20px;
            height: 20px;
        }

        .error-message {
            color: var(--error);
            font-size: 13px;
            margin-top: 6px;
            font-weight: 500;
        }

        .success-message {
            color: var(--success);
            font-size: 13px;
            margin-top: 6px;
            font-weight: 500;
        }

        .checkbox-group {
            margin-bottom: 24px;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            cursor: pointer;
            padding: 16px;
            border-radius: 12px;
            border: 2px solid var(--border);
            background: var(--bg-light);
            transition: all 0.3s ease;
        }

        .checkbox-wrapper:hover {
            border-color: var(--primary);
            background: var(--bg-white);
        }

        .checkbox-wrapper.checked {
            border-color: var(--primary);
            background: rgba(26, 95, 122, 0.05);
        }

        input[type="checkbox"] {
            width: 22px;
            height: 22px;
            min-width: 22px;
            cursor: pointer;
            accent-color: var(--primary);
            margin: 2px 0 0 0;
            padding: 0;
        }

        .checkbox-label {
            font-size: 14px;
            color: var(--text-medium);
            line-height: 1.5;
            user-select: none;
        }

        .checkbox-label a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .checkbox-label a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Plus Jakarta Sans', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(26, 95, 122, 0.3);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 95, 122, 0.4);
        }

        .btn-primary:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .login-link {
            text-align: center;
            color: var(--text-medium);
            font-size: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--border);
        }

        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: var(--secondary);
        }

        .loading {
            display: none;
            text-align: center;
            margin-top: 16px;
            color: var(--text-light);
            font-size: 14px;
        }

        @media (max-width: 600px) {
            .card {
                padding: 32px 24px;
            }

            .logo h1 {
                font-size: 36px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                    <div class="error-message" id="confirmPasswordError"></div>
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

            <div class="login-link">
                Already have an account? <a href="{{ route('login') }}">Log in</a>
            </div>
        </div>
    </div>

    <!-- Supabase JavaScript Client -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>

    <script>
        // Initialize Supabase with ANON key for auth
        const SUPABASE_URL = '{{ env('SUPABASE_URL') }}';
        const SUPABASE_ANON_KEY = '{{ env('SUPABASE_ANON_KEY') }}';

        const supabaseClient = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

        // Update checkbox wrapper styling on change
        document.getElementById('termsAgreement').addEventListener('change', function() {
            const wrapper = document.getElementById('termsWrapper');
            if (this.checked) {
                wrapper.classList.add('checked');
            } else {
                wrapper.classList.remove('checked');
            }
            updateSubmitButton();
        });

        function togglePassword(inputId) {
            const passwordInput = document.getElementById(inputId);
            const toggleBtn = passwordInput.nextElementSibling;
            const svg = toggleBtn.querySelector('svg');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                svg.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                `;
            } else {
                passwordInput.type = 'password';
                svg.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                `;
            }
        }

        function validateForm() {
            let isValid = true;

            // Clear all error messages
            document.querySelectorAll('.error-message').forEach(el => el.textContent = '');
            document.querySelectorAll('input').forEach(el => el.classList.remove('error'));

            // Username validation
            const username = document.getElementById('username').value.trim();
            if (username.length < 3) {
                showError('username', 'Username must be at least 3 characters long');
                isValid = false;
            }

            // First name validation
            const firstName = document.getElementById('firstName').value.trim();
            if (firstName.length < 2) {
                showError('firstName', 'Please enter a valid first name');
                isValid = false;
            }

            // Last name validation
            const lastName = document.getElementById('lastName').value.trim();
            if (lastName.length < 2) {
                showError('lastName', 'Please enter a valid last name');
                isValid = false;
            }

            // Birthday validation
            const birthday = document.getElementById('birthday').value;
            if (!birthday) {
                showError('birthday', 'Please select your birthday');
                isValid = false;
            } else {
                const age = calculateAge(birthday);
                if (age < 13) {
                    showError('birthday', 'You must be at least 13 years old to sign up');
                    isValid = false;
                }
            }

            // Email validation
            const email = document.getElementById('email').value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showError('email', 'Please enter a valid email address');
                isValid = false;
            }

            // Password validation
            const password = document.getElementById('password').value;
            if (password.length < 8) {
                showError('password', 'Password must be at least 8 characters long');
                isValid = false;
            }

            // Confirm password validation
            const confirmPassword = document.getElementById('confirmPassword').value;
            if (password !== confirmPassword) {
                showError('confirmPassword', 'Passwords do not match');
                isValid = false;
            }

            // Terms agreement validation
            const termsAgreed = document.getElementById('termsAgreement').checked;
            if (!termsAgreed) {
                showError('terms', 'You must agree to the Terms and Conditions to proceed');
                isValid = false;
            }

            return isValid;
        }

        function showError(fieldName, message) {
            const errorElement = document.getElementById(fieldName + 'Error');
            const inputElement = document.getElementById(fieldName);

            if (errorElement) {
                errorElement.textContent = message;
            }
            if (inputElement) {
                inputElement.classList.add('error');
            }
        }

        function calculateAge(birthday) {
            const birthDate = new Date(birthday);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();

            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }

            return age;
        }

        function updateSubmitButton() {
            const submitBtn = document.getElementById('submitBtn');
            const termsAgreed = document.getElementById('termsAgreement').checked;
            submitBtn.disabled = !termsAgreed;
        }

        // Password match checker
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const newPassword = document.getElementById('password').value;
            const confirmPassword = this.value;
            const confirmError = document.getElementById('confirmPasswordError');
            const confirmSuccess = document.getElementById('confirmSuccess');

            if (confirmPassword.length === 0) {
                confirmError.textContent = '';
                confirmSuccess.textContent = '';
                return;
            }

            if (newPassword === confirmPassword) {
                confirmError.textContent = '';
                confirmSuccess.textContent = '✓ Passwords match';
            } else {
                confirmError.textContent = 'Passwords do not match';
                confirmSuccess.textContent = '';
            }
        });

        document.getElementById('signupForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            if (!validateForm()) {
                return;
            }

            const formData = {
                username: document.getElementById('username').value.trim(),
                firstName: document.getElementById('firstName').value.trim(),
                lastName: document.getElementById('lastName').value.trim(),
                birthday: document.getElementById('birthday').value,
                email: document.getElementById('email').value.trim(),
                password: document.getElementById('password').value
            };

            // Show loading
            document.getElementById('loading').style.display = 'block';
            document.getElementById('submitBtn').disabled = true;

            try {
                // Check if username already exists
                const { data: existingUser, error: checkError } = await supabaseClient
                    .from('profiles')
                    .select('username')
                    .eq('username', formData.username)
                    .maybeSingle();

                if (existingUser) {
                    throw new Error('Username already taken');
                }

                // Sign up user with Supabase Auth
                const { data: authData, error: authError } = await supabaseClient.auth.signUp({
                    email: formData.email,
                    password: formData.password,
                    options: {
                        data: {
                            username: formData.username,
                            first_name: formData.firstName,
                            last_name: formData.lastName,
                            birthday: formData.birthday
                        }
                    }
                });

                if (authError) throw authError;

                // Wait a moment for auth to complete
                await new Promise(resolve => setTimeout(resolve, 500));

                // Create profile in database
                const { error: profileError } = await supabaseClient
                    .from('profiles')
                    .insert({
                        id: authData.user.id,
                        username: formData.username,
                        first_name: formData.firstName,
                        last_name: formData.lastName,
                        birthday: formData.birthday,
                        email: formData.email
                    });

                if (profileError) {
                    console.error('Profile creation error:', profileError);
                    throw profileError;
                }

                // Success!
                alert('Account created successfully! You can now log in.');
                window.location.href = '{{ route('login') }}';

            } catch (error) {
                console.error('Signup error:', error);

                // Hide loading
                document.getElementById('loading').style.display = 'none';
                document.getElementById('submitBtn').disabled = false;

                // Show error
                if (error.message.includes('Username already taken')) {
                    showError('username', 'This username is already taken');
                } else if (error.message.includes('already registered') || error.message.includes('already been registered')) {
                    showError('email', 'This email is already registered');
                } else if (error.message.includes('violates row-level security')) {
                    alert('There was an issue creating your profile. Please contact support or try again later.');
                } else {
                    alert('Error creating account: ' + error.message);
                }
            }
        });

        function showTerms(e) {
            e.preventDefault();
            alert('Terms and Conditions will be displayed in a modal');
        }

        function showPrivacy(e) {
            e.preventDefault();
            alert('Privacy Policy will be displayed in a modal');
        }

        // Initialize submit button state
        updateSubmitButton();
    </script>
</body>
</html>
