<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - University Asset Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --blue: #1a3a6b;
            --blue-mid: #2148a0;
            --blue-light: #2e5bbf;
            --gold: #f0b429;
            --gold-light: #f5c842;
            --dark: #0d1f3c;
            --white: #ffffff;
            --muted: #a8bcd4;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--blue) 0%, var(--blue-mid) 50%, var(--blue-light) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .wrapper {
            width: 100%;
            max-width: 480px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .logo-caption {
            color: var(--white);
            font-size: 0.85rem;
            font-weight: 500;
            letter-spacing: 0.05em;
            opacity: 0.85;
        }

        .site-name {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(2.5rem, 6vw, 3.5rem);
            color: var(--gold);
            letter-spacing: 3px;
            text-align: center;
            margin-bottom: 1rem;
            -webkit-text-stroke: 1px #c9850a;
        }

        .card {
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            width: 100%;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            color: var(--white);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            letter-spacing: 0.02em;
        }

        input {
            width: 100%;
            padding: 0.85rem 1rem;
            background: var(--white);
            border: 2px solid transparent;
            border-radius: 10px;
            font-size: 0.95rem;
            color: var(--dark);
            outline: none;
            transition: border-color 0.2s;
            font-family: 'Inter', sans-serif;
        }

        input:focus {
            border-color: var(--gold);
        }

        .forgot {
            display: block;
            text-align: right;
            color: var(--muted);
            font-size: 0.85rem;
            text-decoration: none;
            margin-top: 0.4rem;
            transition: color 0.2s;
        }

        .forgot:hover { color: var(--gold); }

        .btn-row {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .btn-login {
            flex: 1;
            background: var(--gold);
            color: var(--dark);
            font-weight: 700;
            font-size: 1rem;
            padding: 0.85rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
            font-family: 'Inter', sans-serif;
            letter-spacing: 0.03em;
        }

        .btn-login:hover {
            background: var(--gold-light);
            transform: translateY(-1px);
        }

        .btn-back {
            flex: 1;
            background: rgba(255,255,255,0.2);
            color: var(--white);
            font-weight: 600;
            font-size: 1rem;
            padding: 0.85rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.2s;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
            text-align: center;
        }

        .btn-back:hover { background: rgba(255,255,255,0.28); }

        .register-link {
            display: block;
            text-align: center;
            margin-top: 1.2rem;
            color: var(--muted);
            font-size: 0.88rem;
            text-decoration: none;
            transition: color 0.2s;
        }

        .register-link:hover { color: var(--gold); }

        /* Error messages */
        .error-msg {
            background: rgba(220, 53, 69, 0.15);
            border: 1px solid rgba(220, 53, 69, 0.4);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            color: #ff8a95;
            font-size: 0.88rem;
        }
    </style>
</head>
<body>

    <div class="wrapper">
        <span class="logo-caption">Logo of System</span>
        <h1 class="site-name">WEBSITE NAME</h1>

        <div class="card">

            {{-- Error messages --}}
            @if ($errors->any())
                <div class="error-msg">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="/login">
                @csrf

                <div class="form-group">
                    <label for="email">Username or Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        autofocus
                    />
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                    />
                    <a href="#" class="forgot">Forgot Password?</a>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn-login">Login</button>
                    <a href="/" class="btn-back">Back</a>
                </div>

                <a href="/register" class="register-link">Don't Have An Account?</a>

            </form>
        </div>
    </div>

</body>
</html>