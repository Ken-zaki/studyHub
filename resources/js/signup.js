/* ============================================================
   public/js/signup.js
   All new users get role = 'student' by default.
   Admin accounts must be set manually via SQL.
   ============================================================ */

const supabaseClient = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// ── CHECKBOX ──────────────────────────────────────────────────
document.getElementById('termsAgreement').addEventListener('change', function() {
    document.getElementById('termsWrapper').classList.toggle('checked', this.checked);
    updateSubmitButton();
});
function updateSubmitButton() {
    document.getElementById('submitBtn').disabled =
        !document.getElementById('termsAgreement').checked;
}

// ── PASSWORD TOGGLE ───────────────────────────────────────────
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const svg   = input.nextElementSibling.querySelector('svg');
    if (input.type === 'password') {
        input.type = 'text';
        svg.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7
               a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243
               M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29
               m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0
               A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7
               a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>`;
    } else {
        input.type = 'password';
        svg.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M2.458 12C3.732 7.943 7.523 5 12 5
                   c4.478 0 8.268 2.943 9.542 7
                   -1.274 4.057-5.064 7-9.542 7
                   -4.477 0-8.268-2.943-9.542-7z"/>`;
    }
}

// ── PASSWORD MATCH ────────────────────────────────────────────
document.getElementById('confirmPassword').addEventListener('input', function() {
    const match = document.getElementById('password').value === this.value;
    document.getElementById('confirmPasswordError').textContent =
        this.value && !match ? 'Passwords do not match' : '';
    document.getElementById('confirmSuccess').textContent =
        this.value && match ? '✓ Passwords match' : '';
});

// ── VALIDATION ────────────────────────────────────────────────
function showError(field, msg) {
    const err = document.getElementById(field + 'Error');
    const inp = document.getElementById(field);
    if (err) err.textContent = msg;
    if (inp) inp.classList.add('error');
}
function calculateAge(birthday) {
    const b = new Date(birthday), t = new Date();
    let age = t.getFullYear() - b.getFullYear();
    if (t.getMonth() < b.getMonth() ||
       (t.getMonth() === b.getMonth() && t.getDate() < b.getDate())) age--;
    return age;
}
function validateForm() {
    let valid = true;
    document.querySelectorAll('.error-message').forEach(el => el.textContent = '');
    document.querySelectorAll('input').forEach(el => el.classList.remove('error'));

    const username = document.getElementById('username').value.trim();

    // ── BLOCK reserved admin username on signup ───────────────
    if (username.toLowerCase() === 'useradmin') {
        showError('username', 'This username is reserved.');
        valid = false;
    } else if (username.length < 3) {
        showError('username', 'Username must be at least 3 characters.');
        valid = false;
    }

    if (document.getElementById('firstName').value.trim().length < 2)
        { showError('firstName', 'Please enter a valid first name.'); valid = false; }
    if (document.getElementById('lastName').value.trim().length < 2)
        { showError('lastName',  'Please enter a valid last name.');  valid = false; }

    const bday = document.getElementById('birthday').value;
    if (!bday)                        { showError('birthday', 'Please select your birthday.'); valid = false; }
    else if (calculateAge(bday) < 13) { showError('birthday', 'You must be at least 13 years old.'); valid = false; }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(document.getElementById('email').value.trim()))
        { showError('email', 'Please enter a valid email.'); valid = false; }

    const pass = document.getElementById('password').value;
    if (pass.length < 8) { showError('password', 'Password must be at least 8 characters.'); valid = false; }
    if (pass !== document.getElementById('confirmPassword').value)
        { showError('confirmPassword', 'Passwords do not match.'); valid = false; }

    if (!document.getElementById('termsAgreement').checked)
        { showError('terms', 'You must agree to the Terms and Conditions.'); valid = false; }

    return valid;
}

// ── SUBMIT ────────────────────────────────────────────────────
document.getElementById('signupForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    if (!validateForm()) return;

    const formData = {
        username:  document.getElementById('username').value.trim(),
        firstName: document.getElementById('firstName').value.trim(),
        lastName:  document.getElementById('lastName').value.trim(),
        birthday:  document.getElementById('birthday').value,
        email:     document.getElementById('email').value.trim(),
        password:  document.getElementById('password').value
    };

    document.getElementById('loading').style.display = 'block';
    document.getElementById('submitBtn').disabled = true;

    try {
        // Check username not taken
        const { data: existing } = await supabaseClient
            .from('profiles').select('username')
            .eq('username', formData.username).maybeSingle();
        if (existing) throw new Error('Username already taken.');

        // Create Supabase auth user
        const { data: authData, error: authError } = await supabaseClient.auth.signUp({
            email:    formData.email,
            password: formData.password,
            options:  { data: {
                username:   formData.username,
                first_name: formData.firstName,
                last_name:  formData.lastName,
                birthday:   formData.birthday
            }}
        });
        if (authError) throw authError;

        await new Promise(r => setTimeout(r, 500));

        // Create profile row — role defaults to 'student'
        const { error: profileError } = await supabaseClient.from('profiles').insert({
            id:         authData.user.id,
            username:   formData.username,
            first_name: formData.firstName,
            last_name:  formData.lastName,
            birthday:   formData.birthday,
            email:      formData.email,
            role:       'student',     // ← always student on signup
            is_banned:  false
        });
        if (profileError) throw profileError;

        alert('Account created successfully! You can now log in.');
        window.location.href = LOGIN_URL;

    } catch (error) {
        console.error('Signup error:', error);
        document.getElementById('loading').style.display = 'none';
        document.getElementById('submitBtn').disabled = false;

        const msg = error.message || '';
        if (msg.includes('Username already taken') || msg.includes('reserved'))
            showError('username', msg);
        else if (msg.includes('already registered'))
            showError('email', 'This email is already registered.');
        else if (msg.includes('violates row-level security'))
            alert('Profile creation failed. Please try again.');
        else
            alert('Error: ' + msg);
    }
});

function showTerms(e)   { e.preventDefault(); alert('Terms and Conditions — coming soon!'); }
function showPrivacy(e) { e.preventDefault(); alert('Privacy Policy — coming soon!'); }

updateSubmitButton();
