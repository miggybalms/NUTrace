<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - University Asset Management</title>
    @include('auth.partials.theme')
    <script>
                /* The eye button is icon-only, so it needs a name of its own; the label
           follows the state so a screen reader says what the button will do. */
        function togglePasswordVisibility(fieldId) {
            const input = document.getElementById(fieldId);
            const button = event.currentTarget;
            const icon = button.querySelector('i');
            const revealing = input.type === 'password';

            input.type = revealing ? 'text' : 'password';
            icon.className = revealing ? 'ri-eye-off-line' : 'ri-eye-line';
            button.setAttribute('aria-label', revealing ? 'Hide password' : 'Show password');
            button.setAttribute('aria-pressed', revealing ? 'true' : 'false');
        }
    </script>
    @include('partials.ui')
</head>
<body>

    <main class="wrapper">
        @include('auth.partials.brand')

        <div class="card">

            {{-- Success messages (e.g. account registered / password reset) --}}
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

            <h2 class="step-title">Sign In</h2>

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
                    <div class="password-input-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                        />
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" onclick="togglePasswordVisibility('password')">
                            <i class="ri-eye-line"></i>
                        </button>
                    </div>
                    <a href="/forgot-password" class="forgot">Forgot Password?</a>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn-login">Login</button>
                    <a href="/" class="btn-back">Back</a>
                </div>

                <a href="/register" class="register-link">Activate Your Account</a>

            </form>
        </div>
    </main>

</body>
</html>