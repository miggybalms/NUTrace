<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - University Asset Management</title>
    @include('auth.partials.theme')
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

            <h2 class="step-title">Forgot Password?</h2>
            <p class="step-desc">
                No worries — enter the email address you used to register and we'll send you a
                one-time <strong>6-digit verification code</strong> to reset your password.
            </p>

            <form method="POST" action="/forgot-password">
                @csrf

                <div class="form-group">
                    <label for="email">University Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="you@nu-lipa.edu.ph"
                        autocomplete="email"
                        autofocus
                        required
                    />
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn-primary">
                        <i class="ri-mail-send-line"></i> Send Verification Code
                    </button>
                </div>
            </form>

            {{-- Single way back to the sign-in screen (there used to be two) --}}
            <div class="back-row">
                <a href="/login" class="btn-back">
                    <i class="ri-arrow-left-line"></i> Back to Login
                </a>
            </div>

        </div>
    </div>

</body>
</html>
