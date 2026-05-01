<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Friend Requests - StudyHub</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="{{ asset('css/studyhub.css') }}">
</head>
<body>

@include('layouts.sidebar', ['activeNav' => 'friend-requests'])

<main class="main-content">
	<div class="feed-column">
		<header class="page-header">
			<h1 class="page-title">Friend Requests</h1>
			<p class="page-subtitle">Incoming requests can be accepted or declined. Outgoing requests stay pending until the other user responds.</p>
		</header>

		@if (session('status'))
			<div class="widget-card">
				<div class="widget-title">Update</div>
				<p class="page-subtitle">{{ session('status') }}</p>
			</div>
		@endif

		<div class="widget-card">
			<div class="widget-title">Incoming Requests</div>
			@if (empty($incomingRequests))
				<p class="page-subtitle">No incoming requests right now.</p>
			@else
				@foreach ($incomingRequests as $item)
					@php($request = $item['request'])
					<div class="profile-friend-row" style="margin: 10px 0;">
						<div class="profile-friend-main">
							<div class="profile-friend-avatar">
								@if(!empty($item['photo']))
									<img src="{{ $item['photo'] }}" alt="{{ $item['name'] }}">
								@else
									{{ $item['initials'] }}
								@endif
							</div>
							<div>
								<div class="profile-friend-name">{{ $item['name'] }}</div>
								<div class="page-subtitle" style="margin:0;">Sent a friend request</div>
							</div>
						</div>
						<div style="display:flex; gap:8px; flex-wrap:wrap;">
							<form method="POST" action="{{ route('friend-requests.accept', ['friendRequest' => $request->id]) }}">
								@csrf
								<button type="submit" class="profile-upload-btn profile-add-friend-btn">Accept</button>
							</form>
							<form method="POST" action="{{ route('friend-requests.decline', ['friendRequest' => $request->id]) }}">
								@csrf
								<button type="submit" class="profile-upload-btn profile-add-friend-btn" style="background:#f3f4f6;color:#374151;">Decline</button>
							</form>
						</div>
					</div>
				@endforeach
			@endif
		</div>

		<div class="widget-card" style="margin-top:16px;">
			<div class="widget-title">Outgoing Requests</div>
			@if (empty($outgoingRequests))
				<p class="page-subtitle">No outgoing requests waiting for a response.</p>
			@else
				@foreach ($outgoingRequests as $item)
					<div class="profile-friend-row" style="margin: 10px 0;">
						<div class="profile-friend-main">
							<div class="profile-friend-avatar">
								@if(!empty($item['photo']))
									<img src="{{ $item['photo'] }}" alt="{{ $item['name'] }}">
								@else
									{{ $item['initials'] }}
								@endif
							</div>
							<div>
								<div class="profile-friend-name">{{ $item['name'] }}</div>
								<div class="page-subtitle" style="margin:0;">Request sent</div>
							</div>
						</div>
						<span class="profile-meta-pill">Pending</span>
					</div>
				@endforeach
			@endif
		</div>
	</div>
</main>

</body>
</html>
