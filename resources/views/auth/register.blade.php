<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - University Asset Management</title>
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

            {{-- Error messages --}}
            @if ($errors->any())
                <div class="error-msg">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <h2 class="step-title">Activate Your Account</h2>

            <form method="POST" action="/register" enctype="multipart/form-data">
                @csrf

                {{-- Employee Number (used to lookup employee details) --}}
                <div class="form-group">
                    <label for="unit_heads_number">Employee Number <span class="required">*</span></label>
                    <input
                        type="text"
                        id="unit_heads_number"
                        name="unit_heads_number"
                        value="{{ old('unit_heads_number') }}"
                        placeholder="Enter your employee number"
                        required
                    />
                    @error('unit_heads_number')
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
                            minlength="8"
                            required
                        />
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" onclick="togglePasswordVisibility('password')">
                            <i class="ri-eye-line"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                @include('auth.partials.password-rules')

                {{-- Confirm Password --}}
                <div class="form-group">
                    <label for="password_confirmation">Confirm Password</label>
                    <div class="password-input-wrapper">
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        />
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" onclick="togglePasswordVisibility('password_confirmation')">
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
    </main>

</body>
</html>