/* ============================================================
   public/js/signup.js
   Two-step signup:
     Step 1 — validate form → signUp() → Supabase sends 6-digit OTP
     Step 2 — user enters OTP → verifyOtp() → profile row created → login
   Profile row is ONLY created after OTP is verified, so unverified
   auth users have no profile and are blocked at login.
   ============================================================ */

const supabaseClient = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Holds form data between step 1 and step 2
let pendingFormData = null;

// ── CHECKBOX ──────────────────────────────────────────────────
document.getElementById('termsAgreement').addEventListener('change', function () {
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
document.getElementById('confirmPassword').addEventListener('input', function () {
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
    if (username.toLowerCase() === 'useradmin') {
        showError('username', 'This username is reserved.'); valid = false;
    } else if (username.length < 3) {
        showError('username', 'Username must be at least 3 characters.'); valid = false;
    }

    if (document.getElementById('firstName').value.trim().length < 2)
        { showError('firstName', 'Please enter a valid first name.'); valid = false; }
    if (document.getElementById('lastName').value.trim().length < 2)
        { showError('lastName',  'Please enter a valid last name.');  valid = false; }

    const bday = document.getElementById('birthday').value;
    if (!bday)
        { showError('birthday', 'Please select your birthday.'); valid = false; }
    else if (calculateAge(bday) < 13)
        { showError('birthday', 'You must be at least 13 years old.'); valid = false; }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(document.getElementById('email').value.trim()))
        { showError('email', 'Please enter a valid email.'); valid = false; }

    const pass = document.getElementById('password').value;
    if (pass.length < 8)
        { showError('password', 'Password must be at least 8 characters.'); valid = false; }
    if (pass !== document.getElementById('confirmPassword').value)
        { showError('confirmPassword', 'Passwords do not match.'); valid = false; }

    if (!document.getElementById('termsAgreement').checked)
        { showError('terms', 'You must agree to the Terms and Conditions.'); valid = false; }

    return valid;
}

// ── STEP 1: SUBMIT FORM → SEND OTP ───────────────────────────
document.getElementById('signupForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!validateForm()) return;

    pendingFormData = {
        username:  document.getElementById('username').value.trim(),
        firstName: document.getElementById('firstName').value.trim(),
        lastName:  document.getElementById('lastName').value.trim(),
        birthday:  document.getElementById('birthday').value,
        email:     document.getElementById('email').value.trim(),
        password:  document.getElementById('password').value,
    };

    document.getElementById('signupLoading').style.display = 'block';
    document.getElementById('submitBtn').disabled = true;

    try {
        // Check username not already taken by a verified user
        const { data: existing } = await supabaseClient
            .from('profiles').select('username')
            .eq('username', pendingFormData.username).maybeSingle();
        if (existing) throw new Error('Username already taken.');

        // Create auth user — Supabase sends a 6-digit OTP to the email
        // because email verification is enabled in Auth → Providers → Email.
        // We do NOT insert a profile row here; that only happens after OTP
        // is verified, so abandoned signups never become real users.
        const { data: authData, error: authError } = await supabaseClient.auth.signUp({
            email:    pendingFormData.email,
            password: pendingFormData.password,
        });
        if (authError) throw authError;

        // Advance to step 2
        document.getElementById('otpEmailDisplay').textContent = pendingFormData.email;
        document.getElementById('signupSection').classList.remove('active');
        document.getElementById('otpSection').classList.add('active');
        document.getElementById('step1').classList.replace('active', 'completed');
        document.getElementById('step2').classList.add('active');

    } catch (error) {
        document.getElementById('signupLoading').style.display = 'none';
        document.getElementById('submitBtn').disabled = false;

        const msg = error.message || '';
        if (msg.includes('Username already taken'))
            showError('username', msg);
        else if (msg.toLowerCase().includes('already registered') || msg.includes('already been registered'))
            showError('email', 'This email is already registered.');
        else
            showError('email', msg || 'Signup failed. Please try again.');
    } finally {
        document.getElementById('signupLoading').style.display = 'none';
    }
});

// ── STEP 2: VERIFY OTP → CREATE PROFILE ──────────────────────
document.getElementById('otpForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    document.getElementById('otpError').textContent = '';
    document.getElementById('otpLoading').style.display = 'block';

    const otp = document.getElementById('otp').value.trim();

    try {
        // Verify the OTP — type 'signup' matches the email sent by signUp()
        const { data, error } = await supabaseClient.auth.verifyOtp({
            email: pendingFormData.email,
            token: otp,
            type:  'signup',
        });
        if (error) throw error;

        const userId = data.user?.id;
        if (!userId) throw new Error('Verification succeeded but no user ID returned.');

        // OTP verified — NOW create the profile row.
        // This is the gate: only verified users get a profile,
        // so login will reject anyone without one.
        const { error: profileError } = await supabaseClient.from('profiles').insert({
            id:         userId,
            username:   pendingFormData.username,
            first_name: pendingFormData.firstName,
            last_name:  pendingFormData.lastName,
            birthday:   pendingFormData.birthday,
            email:      pendingFormData.email,
            role:       'student',
            is_banned:  false,
        });
        if (profileError) throw profileError;

        // Sign out the auto-session Supabase creates after verifyOtp,
        // so the user is taken to login fresh (matching your existing flow).
        await supabaseClient.auth.signOut();

        // Mark step 2 completed and redirect
        document.getElementById('step2').classList.replace('active', 'completed');

        alert('Email verified! Your account is ready. Please log in.');
        window.location.href = LOGIN_URL;

    } catch (error) {
        const msg = error.message || '';
        if (msg.toLowerCase().includes('invalid') || msg.toLowerCase().includes('expired') || msg.toLowerCase().includes('token')) {
            document.getElementById('otpError').textContent = 'Invalid or expired code. Please try again or resend.';
        } else if (msg.includes('violates row-level security') || msg.includes('duplicate key')) {
            // Profile already exists (e.g. user retried after partial failure) — still succeed
            await supabaseClient.auth.signOut();
            alert('Account already verified! Please log in.');
            window.location.href = LOGIN_URL;
        } else {
            document.getElementById('otpError').textContent = msg || 'Verification failed. Please try again.';
        }
    } finally {
        document.getElementById('otpLoading').style.display = 'none';
    }
});

// ── RESEND CODE ───────────────────────────────────────────────
async function resendCode() {
    if (!pendingFormData?.email) return;

    const btn = document.getElementById('resendBtn');
    btn.disabled    = true;
    btn.textContent = 'Sending…';

    try {
        const { error } = await supabaseClient.auth.resend({
            type:  'signup',
            email: pendingFormData.email,
        });
        if (error) throw error;
        btn.textContent = 'Sent!';
    } catch (err) {
        btn.textContent = 'Failed — try again';
        btn.disabled    = false;
        return;
    }

    // Re-enable after 60 seconds
    setTimeout(() => {
        btn.disabled    = false;
        btn.textContent = 'Resend code';
    }, 60000);
}

function showTerms(e)   { e.preventDefault(); alert('Terms and Conditions — coming soon!'); }
function showPrivacy(e) { e.preventDefault(); alert('Privacy Policy — coming soon!'); }

updateSubmitButton();
