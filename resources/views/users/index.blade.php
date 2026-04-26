<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Users Area</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
  <style>body{font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:2rem}</style>
</head>
<body class="bg-gray-50" style="padding:0;">
  <div class="flex h-screen overflow-hidden">
    @include('users.partials.sidebar')
    <div class="flex-1 overflow-y-auto bg-gray-50 p-8">
      <h1 class="text-2xl font-bold mb-4">Users Area</h1>
      @if (auth()->check())
        <p>Welcome, {{ auth()->user()->full_name }} — role: {{ auth()->user()->role }}</p>
      @else
        <p>Welcome, guest. <a href="/login">Login</a></p>
      @endif
      <p class="mt-3"><a href="/" class="text-blue-600">Back to Home</a></p>
    </div>
  </div>
</body>
</html>
