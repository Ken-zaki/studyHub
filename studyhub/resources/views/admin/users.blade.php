<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - StudyHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary:#1a5f7a; --text-primary:#1a1a1a; --text-secondary:#6b7280; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DM Sans',sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; background:#fafbfc; }
        .wrap { text-align:center; padding:48px; }
        .wrap h1 { font-family:'Crimson Pro',serif; font-size:48px; font-weight:700; color:var(--primary); margin-bottom:8px; }
        .wrap p  { color:var(--text-secondary); margin-bottom:24px; }
        .back-btn { padding:10px 24px; border-radius:10px; border:1.5px solid var(--primary); background:white; color:var(--primary); font-weight:600; cursor:pointer; text-decoration:none; font-family:'DM Sans',sans-serif; }
        .back-btn:hover { background:var(--primary); color:white; }
    </style>
</head>
<body>
<div class="wrap">
    <div style="font-size:48px;margin-bottom:16px;">🚧</div>
    <h1>Coming Soon!</h1>
    <p>This admin section is under construction.</p>
    <a href="{{ route('admin.dashboard') }}" class="back-btn">← Back to Admin Dashboard</a>
</div>
</body>
</html>
