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

		<div class="widget-card">
			<div class="widget-title">Your Friends</div>
			@if (empty($friends))
				<p class="page-subtitle">You do not have any friends yet.</p>
			@else
				<div class="friends-list" style="margin-top:12px;">
					@foreach ($friends as $friend)
						<a href="{{ route('profile.view', ['userId' => $friend['id'], 'name' => $friend['name'], 'photo' => $friend['photo']]) }}" class="friend-item">
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
					@endforeach
				</div>
			@endif
		</div>
	</div>
</main>

</body>
</html>
