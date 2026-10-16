{{--
    The verification gate.

    Reached with a valid, outstanding link from a browser that has not yet
    proved it holds the official mailbox. Nothing about the transfer is on this
    page — no asset, no custodian, no reference beyond the invitation itself —
    because none of it may be shown before the code is entered.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Transfer Review Verification - NU Trace</title>
    @include('auth.partials.theme')
    @include('executive_review.partials.theme')
</head>
<body>
    <main class="wrapper">
        @include('auth.partials.brand')

        <div class="card">
            @if (! empty($status))
                <div class="success-msg">{{ $status }}</div>
            @endif

            @if (! empty($error))
                <div class="error-msg">{{ $error }}</div>
            @endif

            @if ($errors->any())
                <div class="error-msg">
                    @foreach ($errors->all() as $error)
                        <span>{{ $error }}</span>
                    @endforeach
                </div>
            @endif

            {{-- Local development only: no reachable mail server, so the flow
                 would otherwise be untestable. Never shown elsewhere. --}}
            @if (! empty($devCode))
                <div class="error-msg" style="background: var(--gold-100); border-color: #EAD9B4; color: #8F5F16;">
                    <strong>Dev notice:</strong> email delivery is not configured, so the code is shown here instead —
                    <span style="font-weight:800; letter-spacing:2px;">{{ $devCode }}</span>
                </div>
            @endif

            <h2 class="step-title">Confirm It’s You</h2>

            <p class="step-desc">
                This link opens <strong>one asset transfer review</strong>. Before any transfer details are shown,
                enter the 6-digit code from the email that carried this link — that is how the system knows the
                message reached the mailbox the Asset Management Office sent it to:
                <strong>{{ $review->recipient_email ?: 'the Executive Director’s mailbox' }}</strong>.
            </p>

            <form method="POST" action="/executive-review/{{ $token }}/verify">
                @csrf

                <div class="form-group">
                    <label for="code">6-digit verification code <span class="required">*</span></label>
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
                        The code is in the invitation email, is valid for
                        {{ \App\Support\TransferReviews::codeTtlMinutes() }} minutes and can be used once.
                    </span>
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn-primary">
                        <i class="ri-check-double-line"></i> Verify &amp; Open the Review
                    </button>
                </div>
            </form>

            <div class="resend-note">
                Didn’t receive the code?
                <form method="POST" action="/executive-review/{{ $token }}/code">
                    @csrf
                    <button type="submit">Send a new code</button>
                </form>
            </div>

            <div class="note">
                <strong>This link is not a NU Trace account.</strong>
                It opens this one transfer review and nothing else — no inventory list, no other request, and no
                access to the rest of the system. It stops working once you submit a decision, when it expires,
                or if the Asset Management Office withdraws it. Nobody from NU Lipa will ask you for the code.
            </div>
        </div>

        <p class="footer-note">
            NU Trace &middot; NU Lipa University Asset Management<br>
            If you were not expecting this message, contact the NU Lipa Asset Management Office.
        </p>
    </main>
</body>
</html>
