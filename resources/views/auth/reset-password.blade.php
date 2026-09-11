<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - University Asset Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
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
            padding: 2.25rem 2rem;
            width: 100%;
        }

        .step-title {
            color: var(--white);
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
            letter-spacing: 0.01em;
        }

        .step-desc {
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1.4rem;
        }

        .step-desc strong {
            color: var(--gold-light);
            font-weight: 600;
            word-break: break-all;
        }

        .form-group { margin-bottom: 1.25rem; }

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

        input:focus { border-color: var(--gold); }

        .password-input-wrapper { position: relative; }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 4px 8px;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle:hover { color: var(--blue); }

        .password-input-wrapper input { padding-right: 40px; }

        .hint {
            display: block;
            color: var(--muted);
            font-size: 0.8rem;
            line-height: 1.5;
            margin-top: 0.4rem;
        }

        .btn-row { margin-top: 1.4rem; }

        .btn-primary {
            width: 100%;
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
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }

        .btn-primary:hover {
            background: var(--gold-light);
            transform: translateY(-1px);
        }

        .btn-back {
            display: block;
            text-align: center;
            margin-top: 1.2rem;
            color: var(--muted);
            font-size: 0.88rem;
            text-decoration: none;
            transition: color 0.2s;
        }

        .btn-back:hover { color: var(--gold); }

        /* Success / error messages */
        .success-msg {
            background: rgba(38, 179, 120, 0.15);
            border: 1px solid rgba(38, 179, 120, 0.4);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            color: #8ee6bf;
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .error-msg {
            background: rgba(220, 53, 69, 0.15);
            border: 1px solid rgba(220, 53, 69, 0.4);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            color: #ff8a95;
            font-size: 0.88rem;
            line-height: 1.5;
        }
    </style>
    <script>
        function togglePasswordVisibility(fieldId) {
            const input = document.getElementById(fieldId);
            const button = event.currentTarget;
            const icon = button.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'ri-eye-off-line';
            } else {
                input.type = 'password';
                icon.className = 'ri-eye-line';
            }
        }
    </script>
</head>
<body>

    <div class="wrapper">
        <span class="logo-caption">Logo of System</span>
        <h1 class="site-name">NU Trace</h1>

        <div class="card">

            {{-- Success messages --}}
            @if (session('success'))
                <div class="success-msg">{{ session('success') }}</div>
            @endif

            {{-- Error messages --}}
            @if ($errors->any())
                <div class="error-msg">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <h2 class="step-title">Create a New Password</h2>
            <p class="step-desc">
                For <strong>{{ $email ?? '' }}</strong>. Choose a new password you haven't
                used before and confirm it below.
            </p>

            <form method="POST" action="/forgot-password/reset">
                @csrf

                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="password-input-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="new-password"
                            placeholder="At least 6 characters"
                            required
                        />
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password')">
                            <i class="ri-eye-line"></i>
                        </button>
                    </div>
                    <span class="hint">Your password must be at least 6 characters long.</span>
                </div>

                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <div class="password-input-wrapper">
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            autocomplete="new-password"
                            placeholder="Re-enter your new password"
                            required
                        />
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password_confirmation')">
                            <i class="ri-eye-line"></i>
                        </button>
                    </div>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn-primary">
                        <i class="ri-lock-password-line"></i> Update Password
                    </button>
                </div>
            </form>

            <a href="/login" class="btn-back">← Back to Login</a>

        </div>
    </div>

</body>
</html>
