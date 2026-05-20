<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - StudyHub</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <style>
        .verify-card {
            text-align: center;
            padding: 1.5rem 0;
        }
        .status-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .status-icon.loading-icon {
            background: var(--primary-light, #eef2ff);
            animation: pulse 1.5s ease-in-out infinite;
        }
        .status-icon.success-icon {
            background: #dcfce7;
        }
        .status-icon.error-icon {
            background: #fee2e2;
        }
        .status-icon svg {
            width: 40px;
            height: 40px;
        }
        .status-icon.loading-icon svg { stroke: var(--primary, #4f46e5); }
        .status-icon.success-icon svg { stroke: #16a34a; }
        .status-icon.error-icon  svg  { stroke: #dc2626; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: .5; }
        }
        .verify-card h2 {
            font-size: 1.35rem;
            font-weight: 600;
            color: var(--text, #1e293b);
            margin-bottom: .5rem;
        }
        .verify-card p {
            color: var(--text-muted, #64748b);
            font-size: .925rem;
            margin-bottom: .25rem;
        }
        .spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 3px solid rgba(79,70,229,.2);
            border-top-color: var(--primary, #4f46e5);
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="logo">
            <h1>StudyHub</h1>
        </div>

        {{-- Loading state --}}
        <div class="verify-card" id="loadingState">
            <div class="status-icon loading-icon">
                <div class="spinner"></div>
            </div>
            <h2>Verifying your email…</h2>
            <p>Just a moment while we confirm your account.</p>
        </div>

        {{-- Success state --}}
        <div class="verify-card" id="successState" style="display:none;">
            <div class="status-icon success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h2>Email verified!</h2>
            <p>Your account is confirmed. Redirecting you now…</p>
        </div>

        {{-- Error state --}}
        <div class="verify-card" id="errorState" style="display:none;">
            <div class="status-icon error-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
            </div>
            <h2>Verification failed</h2>
            <p id="errorMessage">The link may have expired or already been used.</p>
            <br>
            <a href="{{ route('login') }}" class="btn btn-primary" style="display:inline-block;margin-top:.5rem;">
                Back to Login
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script>
    const SUPABASE_URL      = '{{ env("SUPABASE_URL") }}';
    const SUPABASE_ANON_KEY = '{{ env("SUPABASE_ANON_KEY") }}';
    const DASHBOARD_URL     = '{{ route("dashboard") }}';
    const LOGIN_URL         = '{{ route("login") }}';

    // This page is loaded after Supabase redirects the user back from the
    // verification email link. Supabase appends the session tokens in the
    // URL hash fragment (#access_token=...&type=signup). We pick those up,
    // send them to our Laravel backend via a POST, which sets the PHP session,
    // then redirect to the dashboard.

    (async function () {
        const supabaseClient = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

        try {
            // getSession() automatically parses the hash from the URL
            // and exchanges it for a valid session.
            const { data, error } = await supabaseClient.auth.getSession();

            if (error || !data.session) {
                throw new Error(error?.message || 'No session found in the verification link.');
            }

            const session = data.session;

            // Hand the tokens to Laravel so it can create a server-side session
            const response = await fetch('{{ route("auth.verify-callback") }}', {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     '{{ csrf_token() }}',
                    'Accept':           'application/json',
                },
                body: JSON.stringify({
                    access_token:  session.access_token,
                    refresh_token: session.refresh_token,
                    user_id:       session.user.id,
                }),
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Server session creation failed.');
            }

            // Show success, then redirect
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('successState').style.display = 'block';

            setTimeout(() => {
                window.location.href = result.redirect ?? DASHBOARD_URL;
            }, 1500);

        } catch (err) {
            console.error('Verification error:', err);
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('errorState').style.display   = 'block';
            document.getElementById('errorMessage').textContent   = err.message;
        }
    })();
</script>
</body>
</html>
