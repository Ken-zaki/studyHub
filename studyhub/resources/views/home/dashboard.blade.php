<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    <style>
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
        }

        .container {
            background: white;
            border-radius: 24px;
            padding: 60px;
            max-width: 800px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        }

        h1 {
            font-family: 'Fraunces', serif;
            font-size: 48px;
            background: linear-gradient(135deg, #1a5f7a 0%, #f59e42 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 16px;
        }

        p {
            font-size: 18px;
            color: #4a5568;
            margin-bottom: 40px;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .feature {
            padding: 24px;
            background: #fef9f2;
            border-radius: 16px;
            border: 2px solid #e2e8f0;
        }

        .feature h3 {
            font-size: 20px;
            color: #1a5f7a;
            margin-bottom: 8px;
        }

        .feature p {
            font-size: 14px;
            color: #718096;
            margin: 0;
        }

        .logout-btn {
            padding: 16px 40px;
            background: linear-gradient(135deg, #1a5f7a 0%, #144d61 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 95, 122, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to StudyHub!</h1>
        <p>Your dashboard is coming soon. Here's what's being built:</p>

        <div class="features">
            <div class="feature">
                <h3>📅 Calendar</h3>
                <p>Track all your classes and deadlines</p>
            </div>
            <div class="feature">
                <h3>✅ Tasks</h3>
                <p>Manage your to-do lists and assignments</p>
            </div>
            <div class="feature">
                <h3>👥 Study Groups</h3>
                <p>Collaborate with fellow students</p>
            </div>
            <div class="feature">
                <h3>📚 Resources</h3>
                <p>Access shared learning materials</p>
            </div>
            <div class="feature">
                <h3>🎯 Focus Mode</h3>
                <p>Pomodoro timer for productive sessions</p>
            </div>
            <div class="feature">
                <h3>📰 Newsfeed</h3>
                <p>Stay updated with study tips</p>
            </div>
        </div>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="logout-btn">Logout</button>
        </form>
    </div>

    <!-- Check if user is authenticated -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script>
        const SUPABASE_URL = '{{ env('SUPABASE_URL') }}';
        const SUPABASE_ANON_KEY = '{{ env('SUPABASE_ANON_KEY') }}';
        const supabaseClient = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

        // Check authentication
        async function checkAuth() {
            const { data: { user } } = await supabaseClient.auth.getUser();

            if (!user) {
                // Not logged in, redirect to login
                window.location.href = '{{ route('login') }}';
            } else {
                console.log('Logged in as:', user.email);
            }
        }

        checkAuth();
    </script>
</body>
</html>
