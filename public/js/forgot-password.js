/* ============================================================
   resources/js/forgot-password.js
   ============================================================ */

const supabaseClient = supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);
let userEmail = '';

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const btn   = input.nextElementSibling;
    if (input.type === 'password') { input.type = 'text';     btn.textContent = '🙈'; }
    else                           { input.type = 'password'; btn.textContent = '👁️'; }
}

// Step 1 — send reset email
document.getElementById('emailForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    document.getElementById('emailError').textContent = '';
    document.getElementById('email').classList.remove('error');
    userEmail = document.getElementById('email').value.trim();
    document.getElementById('emailLoading').style.display = 'block';

    try {
        const { error } = await supabaseClient.auth.resetPasswordForEmail(userEmail, {
            redirectTo: RESET_PASSWORD_URL
        });
        if (error) throw error;

        document.getElementById('emailSection').classList.remove('active');
        document.getElementById('otpSection').classList.add('active');
        document.getElementById('step1').classList.replace('active', 'completed');
        document.getElementById('step2').classList.add('active');
    } catch (error) {
        document.getElementById('emailError').textContent = error.message;
        document.getElementById('email').classList.add('error');
    } finally {
        document.getElementById('emailLoading').style.display = 'none';
    }
});

// Step 2 — verify OTP (advance to step 3)
document.getElementById('otpForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    document.getElementById('otpError').textContent = '';
    document.getElementById('otpLoading').style.display = 'block';

    try {
        document.getElementById('otpSection').classList.remove('active');
        document.getElementById('passwordSection').classList.add('active');
        document.getElementById('step2').classList.replace('active', 'completed');
        document.getElementById('step3').classList.add('active');
    } catch (error) {
        document.getElementById('otpError').textContent = 'Invalid or expired code';
        document.getElementById('otp').classList.add('error');
    } finally {
        document.getElementById('otpLoading').style.display = 'none';
    }
});

// Step 3 — set new password
document.getElementById('passwordForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    document.getElementById('passwordError').textContent = '';
    document.getElementById('confirmError').textContent  = '';

    const newPassword     = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    if (newPassword.length < 8)         { document.getElementById('passwordError').textContent = 'Password must be at least 8 characters'; return; }
    if (newPassword !== confirmPassword) { document.getElementById('confirmError').textContent  = 'Passwords do not match'; return; }

    document.getElementById('passwordLoading').style.display = 'block';

    try {
        const { error } = await supabaseClient.auth.updateUser({ password: newPassword });
        if (error) throw error;
        alert('Password reset successful! Redirecting to login...');
        window.location.href = LOGIN_URL;
    } catch (error) {
        alert('Error resetting password: ' + error.message);
    } finally {
        document.getElementById('passwordLoading').style.display = 'none';
    }
});

// Password strength checker
document.getElementById('newPassword').addEventListener('input', function() {
    const p = this.value;
    let strength = 0;
    if (p.length >= 8) strength++;
    if (p.match(/[a-z]/) && p.match(/[A-Z]/)) strength++;
    if (p.match(/[0-9]/)) strength++;
    if (p.match(/[^a-zA-Z0-9]/)) strength++;

    const bar  = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    bar.className = 'strength-bar';

    if (strength <= 1) { bar.classList.add('weak');   text.textContent = 'Weak password';   text.style.color = 'var(--error)'; }
    else if (strength <= 2) { bar.classList.add('medium'); text.textContent = 'Medium password'; text.style.color = 'var(--secondary)'; }
    else               { bar.classList.add('strong'); text.textContent = 'Strong password'; text.style.color = 'var(--success)'; }
});
