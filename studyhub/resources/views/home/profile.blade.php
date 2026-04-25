@extends('layouts.app')

@section('title', 'StudyHub - Profile')

@push('styles')
<style>
    :root {
        --primary: #1a5f7a;
        --primary-dark: #144d61;
        --secondary: #f59e42;
        --bg-main: #fafbfc;
        --bg-card: #ffffff;
        --text-primary: #1a1a1a;
        --text-secondary: #6b7280;
        --border: #e5e7eb;
        --shadow-md: rgba(0, 0, 0, 0.08);
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: 'DM Sans', sans-serif;
        background: var(--bg-main);
        color: var(--text-primary);
    }

    .profile-page {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 48px 24px;
    }

    .profile-card {
        width: min(720px, 100%);
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 24px;
        box-shadow: 0 18px 50px var(--shadow-md);
        padding: 40px;
    }

    .profile-eyebrow {
        text-transform: uppercase;
        letter-spacing: 0.14em;
        font-size: 12px;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 12px;
    }

    .profile-title {
        margin: 0 0 12px;
        font-family: 'Crimson Pro', serif;
        font-size: clamp(36px, 5vw, 54px);
        line-height: 1;
    }

    .profile-text {
        margin: 0 0 28px;
        color: var(--text-secondary);
        font-size: 16px;
        line-height: 1.7;
    }

    .profile-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .profile-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 12px 18px;
        border-radius: 999px;
        text-decoration: none;
        font-weight: 600;
        transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }

    .profile-link:hover {
        transform: translateY(-1px);
    }

    .profile-link.primary {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        box-shadow: 0 10px 24px rgba(26, 95, 122, 0.18);
    }

    .profile-link.secondary {
        border: 1px solid var(--border);
        color: var(--text-primary);
        background: white;
    }
</style>
@endpush

@section('content')
<main class="profile-page">
    <section class="profile-card">
        <div class="profile-eyebrow">StudyHub</div>
        <h1 class="profile-title">Profile</h1>
        <p class="profile-text">
            Your profile page is now reachable from the StudyHub navigation. It can be expanded later with account details, preferences, and study stats.
        </p>
        <div class="profile-actions">
            <a href="{{ route('dashboard') }}" class="profile-link primary">Back to Dashboard</a>
            <a href="{{ route('focus-mode') }}" class="profile-link secondary">Open Focus Mode</a>
        </div>
    </section>
</main>
@endsection