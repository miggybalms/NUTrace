<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - University Asset Management</title>
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
                            placeholder="Create a strong password"
                            minlength="8"
                            required
                        />
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" onclick="togglePasswordVisibility('password')">
                            <i class="ri-eye-line"></i>
                        </button>
                    </div>
                    @include('auth.partials.password-rules')
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
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" onclick="togglePasswordVisibility('password_confirmation')">
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

            <div class="back-row">
                <a href="/login" class="btn-back">
                    <i class="ri-arrow-left-line"></i> Back to Login
                </a>
            </div>

        </div>
    </main>

</body>
</html>
