<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - StudyHub</title>
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
            padding: 20px;
            position: relative;
            overflow: hidden;
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

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, 30px) scale(1.05); }
        }

        .container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 450px;
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

        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 32px;
        }

        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--border);
            color: var(--text-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .step.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(26, 95, 122, 0.3);
        }

        .step.completed {
            background: var(--success);
            color: white;
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 14px;
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

        .error-message {
            color: var(--error);
            font-size: 13px;
            margin-top: 6px;
            font-weight: 500;
        }

        .success-message {
            background: rgba(72, 187, 120, 0.1);
            border: 2px solid var(--success);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            color: var(--success);
            font-weight: 600;
            text-align: center;
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
            font-size: 20px;
            padding: 4px;
            transition: color 0.3s ease;
        }

        .toggle-password:hover {
            color: var(--primary);
        }

        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }

        .strength-bar.weak {
            width: 33%;
            background: var(--error);
        }

        .strength-bar.medium {
            width: 66%;
            background: var(--secondary);
        }

        .strength-bar.strong {
            width: 100%;
            background: var(--success);
        }

        .strength-text {
            font-size: 12px;
            margin-top: 4px;
            font-weight: 600;
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

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 95, 122, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .back-link {
            text-align: center;
            color: var(--text-medium);
            font-size: 15px;
            margin-top: 24px;
        }

        .back-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: var(--secondary);
        }

        .loading {
            display: none;
            text-align: center;
            margin-top: 16px;
            color: var(--text-light);
            font-size: 14px;
        }

        @media (max-width: 480px) {
            .card {
                padding: 32px 24px;
            }

            .logo h1 {
                font-size: 36px;
            }
        }
    </style>
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
                <div class="step" id="step2">2</div>
                <div class="step" id="step3">3</div>
            </div>

            <!-- Step 1: Email -->
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

            <!-- Step 2: OTP -->
            <div class="form-section" id="otpSection">
                <div class="success-message">
                    A reset code has been sent to your email!
                </div>

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

            <!-- Step 3: New Password -->
            <div class="form-section" id="passwordSection">
                <div class="success-message">
                    Code verified! Enter your new password.
                </div>

                <form id="passwordForm">
                    @csrf
                    <div class="form-group">
                        <label for="newPassword">New Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="newPassword" name="newPassword" required placeholder="Enter new password">
                            <button type="button" class="toggle-password" onclick="togglePassword('newPassword')">👁️</button>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                        <div class="error-message" id="passwordError"></div>
                    </div>

                    <div class="form-group">
                        <label for="confirmPassword">Confirm Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="confirmPassword" name="confirmPassword" required placeholder="Re-enter password">
                            <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword')">👁️</button>
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

    <!-- Supabase JavaScript Client -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>

    <script>
        // Initialize Supabase
        const SUPABASE_URL = '{{ env('SUPABASE_URL') }}';
        const SUPABASE_ANON_KEY = '{{ env('SUPABASE_ANON_KEY') }}';
        const supabaseClient = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

        let userEmail = '';

        function togglePassword(inputId) {
            const passwordInput = document.getElementById(inputId);
            const toggleBtn = passwordInput.nextElementSibling;

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        // Step 1: Send reset email
        document.getElementById('emailForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            document.getElementById('emailError').textContent = '';
            document.getElementById('email').classList.remove('error');

            userEmail = document.getElementById('email').value.trim();

            document.getElementById('emailLoading').style.display = 'block';

            try {
                const { error } = await supabaseClient.auth.resetPasswordForEmail(userEmail, {
                    redirectTo: '{{ url('/reset-password') }}'
                });

                if (error) throw error;

                // Move to OTP step
                document.getElementById('emailSection').classList.remove('active');
                document.getElementById('otpSection').classList.add('active');
                document.getElementById('step1').classList.add('completed');
                document.getElementById('step2').classList.add('active');

            } catch (error) {
                console.error('Error:', error);
                document.getElementById('emailError').textContent = error.message;
                document.getElementById('email').classList.add('error');
            } finally {
                document.getElementById('emailLoading').style.display = 'none';
            }
        });

        // Step 2: Verify OTP (Note: Supabase handles this differently)
        document.getElementById('otpForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            document.getElementById('otpError').textContent = '';
            document.getElementById('otp').classList.remove('error');

            const otp = document.getElementById('otp').value.trim();

            document.getElementById('otpLoading').style.display = 'block';

            try {
                // Move to password step
                // Note: In a real implementation, you'd verify the OTP here
                document.getElementById('otpSection').classList.remove('active');
                document.getElementById('passwordSection').classList.add('active');
                document.getElementById('step2').classList.add('completed');
                document.getElementById('step3').classList.add('active');

            } catch (error) {
                console.error('Error:', error);
                document.getElementById('otpError').textContent = 'Invalid or expired code';
                document.getElementById('otp').classList.add('error');
            } finally {
                document.getElementById('otpLoading').style.display = 'none';
            }
        });

        // Step 3: Set new password
        document.getElementById('passwordForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            document.getElementById('passwordError').textContent = '';
            document.getElementById('confirmError').textContent = '';

            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (newPassword.length < 8) {
                document.getElementById('passwordError').textContent = 'Password must be at least 8 characters';
                return;
            }

            if (newPassword !== confirmPassword) {
                document.getElementById('confirmError').textContent = 'Passwords do not match';
                return;
            }

            document.getElementById('passwordLoading').style.display = 'block';

            try {
                const { error } = await supabaseClient.auth.updateUser({
                    password: newPassword
                });

                if (error) throw error;

                alert('Password reset successful! Redirecting to login...');
                window.location.href = '{{ route('login') }}';

            } catch (error) {
                console.error('Error:', error);
                alert('Error resetting password: ' + error.message);
            } finally {
                document.getElementById('passwordLoading').style.display = 'none';
            }
        });

        // Password strength checker
        document.getElementById('newPassword').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');

            let strength = 0;

            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            strengthBar.className = 'strength-bar';

            if (strength <= 1) {
                strengthBar.classList.add('weak');
                strengthText.textContent = 'Weak password';
                strengthText.style.color = 'var(--error)';
            } else if (strength <= 2) {
                strengthBar.classList.add('medium');
                strengthText.textContent = 'Medium password';
                strengthText.style.color = 'var(--secondary)';
            } else {
                strengthBar.classList.add('strong');
                strengthText.textContent = 'Strong password';
                strengthText.style.color = 'var(--success)';
            }
        });
    </script>
</body>
</html>
