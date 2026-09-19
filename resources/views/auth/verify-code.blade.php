<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code - University Asset Management</title>
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

            {{-- Local-dev only: email server unavailable, show the generated code --}}
            @if (session('dev_code'))
                <div class="error-msg" style="background: var(--gold-100); border-color: #EAD9B4; color: #8F5F16;">
                    <strong>Dev notice:</strong> email delivery failed, so your code is shown here instead —
                    <span style="font-weight:800; letter-spacing:2px;">{{ session('dev_code') }}</span>
                </div>
            @endif

            {{-- Mail delivery failure notice --}}
            @if (session('mail_error'))
                <div class="error-msg">{{ session('mail_error') }}</div>
            @endif

            {{-- Error messages --}}
            @if ($errors->any())
                <div class="error-msg">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <h2 class="step-title">Check Your Email</h2>
            <p class="step-desc">
                We sent a <strong>6-digit verification code</strong> to
                <strong>{{ $email ?? '' }}</strong>. Enter it below to continue.
            </p>

            <form method="POST" action="/forgot-password/verify">
                @csrf

                <div class="form-group">
                    <label for="code">Verification Code</label>
                    <input
                        type="text"
                        id="code"
                        name="code"
                        class="code-input"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        maxlength="6"
                        placeholder="••••••"
                        value="{{ old('code') }}"
                        autocomplete="one-time-code"
                        autofocus
                        required
                    />
                    <span class="hint">
                        The code is valid for 15 minutes and can only be used once.
                    </span>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn-primary">
                        <i class="ri-check-double-line"></i> Verify Code
                    </button>
                </div>
            </form>

            <div class="resend-note">
                Didn't receive the code?
                <form method="POST" action="/forgot-password" style="display:inline;">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email ?? '' }}">
                    <button type="submit">Resend code</button>
                </form>
            </div>

            <a href="/forgot-password" class="back-link">← Use a different email</a>

        </div>
    </div>

</body>
</html>
