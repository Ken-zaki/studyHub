/* ============================================================
   resources/js/login.js
   ============================================================ */

const supabaseClient = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

function togglePassword() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>`;
    } else {
        input.type = 'password';
        icon.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>`;
    }
}

document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    document.getElementById('emailError').textContent    = '';
    document.getElementById('passwordError').textContent = '';
    document.getElementById('email').classList.remove('error');
    document.getElementById('password').classList.remove('error');

    const emailOrUsername = document.getElementById('email').value.trim();
    const password        = document.getElementById('password').value;

    document.getElementById('loading').style.display = 'block';

    try {
        let email = emailOrUsername;

        // If no @ symbol, treat as username — look up the real email
        if (!emailOrUsername.includes('@')) {
            const { data: profile, error: profileError } = await supabaseClient
                .from('profiles')
                .select('email')
                .eq('username', emailOrUsername)
                .single();

            if (profileError || !profile) throw new Error('Username not found');
            email = profile.email;
        }

        const { data, error } = await supabaseClient.auth.signInWithPassword({ email, password });
        if (error) throw error;

        const { data: profile, error: profileError } = await supabaseClient
            .from('profiles')
            .select('*')
            .eq('id', data.user.id)
            .single();

        if (profileError) throw new Error('Failed to load user profile');

        // Store user in Laravel session
        await fetch(SET_SESSION_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({
                user_id:       data.user.id,
                first_name:    profile.first_name,
                last_name:     profile.last_name,
                username:      profile.username,
                profile_photo: profile.profile_photo_url
            })
        });

        window.location.href = DASHBOARD_URL;

    } catch (error) {
        console.error('Login error:', error);
        document.getElementById('loading').style.display = 'none';

        const msg = error.message || '';
        if (msg.includes('Username not found') || msg.includes('Invalid login') || msg.includes('Invalid email')) {
            document.getElementById('emailError').textContent = 'Invalid email/username or password';
            document.getElementById('email').classList.add('error');
            document.getElementById('password').classList.add('error');
        } else {
            document.getElementById('emailError').textContent = msg;
            document.getElementById('email').classList.add('error');
        }
    }
});
