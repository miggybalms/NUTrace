<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - University Asset Management</title>
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
            align-items: flex-start;
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
            margin-top: 1rem;
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
            margin-bottom: 2rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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

        label .required {
            color: var(--gold);
            margin-left: 2px;
        }

        input, select {
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

        input:focus, select:focus {
            border-color: var(--gold);
        }

        select {
            cursor: pointer;
            appearance: auto;
        }

        input[type="file"] {
            padding: 0.5rem;
            cursor: pointer;
            background: var(--white);
        }

        .password-input-wrapper {
            position: relative;
        }

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

        .password-toggle:hover {
            color: var(--blue);
        }

        .password-input-wrapper input {
            padding-right: 40px;
        }

        .btn-row {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .btn-signup {
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

        .btn-signup:hover {
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

        .login-link {
            display: block;
            text-align: center;
            margin-top: 1.2rem;
            color: var(--muted);
            font-size: 0.88rem;
            text-decoration: none;
            transition: color 0.2s;
        }

        .login-link:hover { color: var(--gold); }

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

        .field-error {
            color: #ff8a95;
            font-size: 0.8rem;
            margin-top: 0.3rem;
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

            <form method="POST" action="/register" enctype="multipart/form-data">
                @csrf

                {{-- Full Name --}}
                <div class="form-group">
                    <label for="name">Full name <span class="required">*</span></label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name') }}"
                        required
                    />
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Email --}}
                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                    />
                    @error('email')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Department --}}
                <div class="form-group">
                    <label for="department">Department <span class="required">*</span></label>
                    
                    {{-- First user types their own department --}}
                    @if($isFirstUser ?? false)
                        <input
                            type="text"
                            id="department"
                            name="department"
                            value="{{ old('department') }}"
                            placeholder="Enter your department name"
                            required
                        />
                    @else
                        {{-- Subsequent users select from existing departments --}}
                        <select id="department" name="department" required>
                            <option value="" disabled selected>Select department</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept }}" {{ old('department') == $dept ? 'selected' : '' }}>{{ $dept }}</option>
                            @endforeach
                        </select>
                    @endif
                    
                    @error('department')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Employee Number --}}
                <div class="form-group">
                    <label for="unit_heads_number">Employee Number <span class="required">*</span></label>
                    <input
                        type="text"
                        id="unit_heads_number"
                        name="unit_heads_number"
                        value="{{ old('unit_heads_number') }}"
                        required
                    />
                    @error('unit_heads_number')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Profile Photo --}}
                <div class="form-group">
                    <label for="profile_photo">Profile photo <span class="required">*</span></label>
                    <input
                        type="file"
                        id="profile_photo"
                        name="profile_photo"
                        accept="image/*"
                    />
                    @error('profile_photo')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="new-password"
                        />
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password')">
                            <i class="ri-eye-line"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Confirm Password --}}
                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <div class="password-input-wrapper">
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            autocomplete="new-password"
                        />
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password_confirmation')">
                            <i class="ri-eye-line"></i>
                        </button>
                    </div>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn-signup">Sign Up</button>
                    <a href="/" class="btn-back">Back</a>
                </div>

                <a href="/login" class="login-link">Already registered?</a>

            </form>
        </div>
    </div>

</body>
</html>