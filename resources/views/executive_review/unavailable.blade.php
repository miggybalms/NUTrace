{{--
    A review link that cannot take a decision.

    Four ways to arrive here, and each says which one it was: the link expired,
    the Asset Management Office withdrew it, a decision was already submitted,
    or the link is not one of ours at all (mistyped, truncated, from a database
    that no longer has it).

    No asset list, no custodian, no reference is shown for a link that is not
    valid — a page that confirms "there is a transfer behind this token" would
    be exactly the leak the token is there to prevent. The one exception is a
    link that was already decided: the person holding it is the one who decided,
    so the outcome is read back to them.
--}}
@php
    $items = $items ?? collect();
    $decided = $review && $review->is_decided;
    $outcomeClass = $decided
        ? ($review->decision === \App\Support\TransferReviews::DECISION_REJECTED ? 'outcome--red' : 'outcome--green')
        : '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Transfer Review Unavailable - NU Trace</title>
    @include('auth.partials.theme')
    @include('executive_review.partials.theme')
</head>
<body>
    <main class="wrapper">
        @include('auth.partials.brand')

        <div class="card">
            <h2 class="step-title">
                {{ $decided ? 'This Review Is Complete' : 'This Review Link Is No Longer Usable' }}
            </h2>

            <p class="step-desc">{{ $reason }}</p>

            @if ($decided)
                <div class="outcome {{ $outcomeClass }}">
                    <h3>{{ $review->decision_label }}</h3>

                    <dl>
                        <div>
                            <dt>Transfer request</dt>
                            <dd>TR-{{ str_pad((string) $review->request_id, 4, '0', STR_PAD_LEFT) }}</dd>
                        </div>
                        <div>
                            <dt>Decision recorded</dt>
                            <dd>{{ $review->decided_at ? \Illuminate\Support\Carbon::parse($review->decided_at)->format('F j, Y g:i A') : '—' }}</dd>
                        </div>
                        <div>
                            <dt>Assets approved</dt>
                            <dd>{{ (int) $review->approved_count }} of {{ (int) $review->approved_count + (int) $review->declined_count }}</dd>
                        </div>
                        @if (! empty($review->rejection_reason))
                            <div>
                                <dt>Reason given</dt>
                                <dd>{{ $review->rejection_reason }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($items->isNotEmpty())
                        <p>
                            @foreach ($items as $item)
                                <span class="muted">{{ $item->label }}{{ $item->asset_name ? ' — ' . $item->asset_name : '' }}: {{ $item->decision_label }}</span>
                                @if (! $loop->last)<br>@endif
                            @endforeach
                        </p>
                    @endif

                    <p>
                        No further action is needed from you. The Asset Management Office and the employee who
                        requested the transfer have been notified of the outcome.
                    </p>
                </div>
            @else
                <div class="note">
                    A review link is issued for one transfer request, expires on its own, and stops working when it is
                    used, replaced or withdrawn. The NU Lipa Asset Management Office can send a fresh invitation if the
                    transfer still needs your decision.
                </div>
            @endif

            <p class="footer-note">
                NU Trace &middot; NU Lipa University Asset Management<br>
                {{ $contact ? 'Contact the Asset Management Office at ' . $contact . '.' : 'Contact the NU Lipa Asset Management Office for a new invitation.' }}
            </p>
        </div>
    </main>
</body>
</html>
