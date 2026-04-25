<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard</title>
  <style>body{font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:2rem}</style>
</head>
<body>
  <h1>Admin Dashboard</h1>
  @if (auth()->check())
    <p>Welcome, {{ auth()->user()->full_name }} — role: {{ auth()->user()->role }}</p>
  @else
    <p>Welcome, admin. <a href="/login">Login</a></p>
  @endif
  <p><a href="/">Back to Home</a></p>
</body>
</html>
