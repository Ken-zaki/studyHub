<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Friends - StudyHub</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
</head>
<body>

@include('layouts.sidebar', ['activeNav' => 'friends'])

<main class="main-content">
	<div class="feed-column">
		<header class="page-header">
			<h1 class="page-title">Friends</h1>
			<p class="page-subtitle">People you are connected with. Online friends appear in the right sidebar.</p>
		</header>

		@if (session('status'))
			<div class="friend-status-banner">{{ session('status') }}</div>
		@endif

		@if ($errors->any())
			<div class="friend-status-banner" style="background:#fef2f2;color:#991b1b;border-color:#fecaca;">
				{{ $errors->first() }}
			</div>
		@endif

		<div class="widget-card">
			<div class="widget-title">Your Friends</div>
			@if (empty($friends))
				<p class="page-subtitle">You do not have any friends yet.</p>
			@else
				<div class="friends-list" style="margin-top:12px;">
					@foreach ($friends as $friend)
						<div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border-radius: 14px; border: 1px solid var(--border); margin-bottom: 8px; transition: background 0.2s ease;">
							<a href="{{ route('profile.view', ['userId' => $friend['id'], 'name' => $friend['name'], 'photo' => $friend['photo']]) }}" class="friend-item" style="flex: 1; margin: 0; padding: 0; border-radius: 0; border: none;">
								<div class="friend-avatar">
									@if($friend['photo'])
										<img src="{{ $friend['photo'] }}" alt="{{ $friend['name'] }}">
									@else
										{{ $friend['initials'] }}
									@endif
								</div>
								<div class="friend-meta">
									<div class="friend-name">{{ $friend['name'] }}</div>
									<div class="friend-status-row">
										<span class="friend-status-dot {{ $friend['is_active'] ? 'online' : 'offline' }}"></span>
										<span class="friend-status-text">{{ $friend['is_active'] ? 'Online' : 'Offline' }}</span>
									</div>
								</div>
							</a>
							<form method="POST" action="{{ route('friends.remove', ['friendId' => $friend['id']]) }}" style="margin-left: 12px;">
								@csrf
								<button type="submit" class="profile-upload-btn profile-add-friend-btn" style="background:#f3f4f6;color:#374151; padding: 8px 16px; font-size: 13px;">Remove</button>
							</form>
						</div>
					@endforeach
				</div>
			@endif
		</div>
	</div>
</main>
@include('layouts.admin_bar')
</body>
</html>
