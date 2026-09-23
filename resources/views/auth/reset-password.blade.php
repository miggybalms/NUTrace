<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - University Asset Management</title>
    @include('auth.partials.theme')
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
                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password')">
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

            <div class="back-row">
                <a href="/login" class="btn-back">
                    <i class="ri-arrow-left-line"></i> Back to Login
                </a>
            </div>

        </div>
    </div>

</body>
</html>
