<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - StudyHub</title>
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

        .forgot-password {
            text-align: right;
            margin-top: -12px;
            margin-bottom: 24px;
        }

        .forgot-password a {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: var(--primary-dark);
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

        .signup-link {
            text-align: center;
            color: var(--text-medium);
            font-size: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--border);
        }

        .signup-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            transition: color 0.3s ease;
        }

        .signup-link a:hover {
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
                <p>Welcome back! Ready to study?</p>
            </div>

            <form id="loginForm">
                @csrf
                <div class="form-group">
                    <label for="email">Email or Username</label>
                    <input type="text" id="email" name="email" required placeholder="Enter your email or username">
                    <div class="error-message" id="emailError"></div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required placeholder="Enter your password">
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" id="eyeIcon">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                    <div class="error-message" id="passwordError"></div>
                </div>

                <div class="forgot-password">
                    <a href="{{ route('password.request') }}">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-primary">Log In</button>
                <div class="loading" id="loading">Logging in...</div>
            </form>

            <div class="signup-link">
                Don't have an account? <a href="{{ route('signup') }}">Sign up</a>
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

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                `;
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                `;
            }
        }

        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            // Clear previous errors
            document.getElementById('emailError').textContent = '';
            document.getElementById('passwordError').textContent = '';
            document.getElementById('email').classList.remove('error');
            document.getElementById('password').classList.remove('error');

            const emailOrUsername = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            // Show loading
            document.getElementById('loading').style.display = 'block';

            try {
                let email = emailOrUsername;

                // If input doesn't contain @, assume it's a username and look up email
                if (!emailOrUsername.includes('@')) {
                    const { data: profile, error: profileError } = await supabaseClient
                        .from('profiles')
                        .select('email')
                        .eq('username', emailOrUsername)
                        .single();

                    if (profileError || !profile) {
                        throw new Error('Username not found');
                    }

                    email = profile.email;
                }

                // Sign in with email and password
                const { data, error } = await supabaseClient.auth.signInWithPassword({
                    email: email,
                    password: password
                });

                if (error) {
                    throw error;
                }

                // Success! Redirect to dashboard
                console.log('Login successful:', data);
                alert('Login successful! Redirecting to dashboard...');

                // Redirect to your dashboard
                window.location.href = '{{ url('/dashboard') }}';

            } catch (error) {
                console.error('Login error:', error);

                // Hide loading
                document.getElementById('loading').style.display = 'none';

                // Show error message
                if (error.message.includes('Username not found') || error.message.includes('Invalid login')) {
                    document.getElementById('emailError').textContent = 'Invalid email/username or password';
                    document.getElementById('email').classList.add('error');
                    document.getElementById('password').classList.add('error');
                } else {
                    document.getElementById('emailError').textContent = error.message;
                    document.getElementById('email').classList.add('error');
                }
            }
        });
    </script>
</body>
</html>
