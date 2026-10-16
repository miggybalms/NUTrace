{{--
    The transfer review itself.

    Shown only to a browser that has verified the official mailbox against an
    invitation that is still outstanding. Everything on it is read from the
    database for the one request the token points at: the transfer the
    Executive Director has to decide on, and nothing else in NU Trace.

    A link with no decision left to take never reaches this page — the
    controller sends it to the "unavailable" page instead — so the form at the
    bottom is the only way a decision can be submitted from here.
--}}
@php
    $request = $summary['request'];
    $assets = $summary['assets'];
    $items = $items ?? collect();
    $assetCount = $assets->count();
    $canDecide = (bool) $review->can_decide;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Transfer Review {{ $summary['reference'] }} - NU Trace</title>
    @include('auth.partials.theme')
    @include('executive_review.partials.theme')
</head>
<body>
    <main class="wrapper wrapper--wide">
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

            <div class="review-head">
                <div>
                    <h2 class="step-title">Transfer Review {{ $summary['reference'] }}</h2>
                    <p class="step-desc" style="margin-bottom:0;">
                        Submitted {{ $request->created_at ? \Illuminate\Support\Carbon::parse($request->created_at)->format('F j, Y g:i A') : '—' }}
                        · {{ $assetCount }} {{ \Illuminate\Support\Str::plural('asset', $assetCount) }} in this transfer
                    </p>
                </div>
                <span class="pill pill--gold">{{ $review->status_label }}</span>
            </div>

            <dl class="meta">
                <div class="meta-item">
                    <dt>Requesting employee</dt>
                    <dd>
                        {{ $summary['from']['name'] ?? 'Not recorded' }}
                        @if (! empty($summary['from']['department']))
                            <span>{{ $summary['from']['department'] }}</span>
                        @endif
                    </dd>
                </div>

                <div class="meta-item">
                    <dt>Intended recipient</dt>
                    <dd>
                        {{ $summary['to']['name'] ?? 'Not recorded' }}
                        @if (! empty($summary['to']['department']))
                            <span>{{ $summary['to']['department'] }}</span>
                        @endif
                    </dd>
                </div>

                <div class="meta-item">
                    <dt>Administrative approval</dt>
                    <dd>
                        Approved by the Asset Management Office
                        @if (! empty($summary['approved_by']['name']) || ! empty($summary['approved_at']))
                            <span>
                                {{ $summary['approved_by']['name'] ?? '' }}@if (! empty($summary['approved_by']['name']) && ! empty($summary['approved_at'])) · @endif{{ $summary['approved_at'] ? \Illuminate\Support\Carbon::parse($summary['approved_at'])->format('F j, Y') : '' }}
                            </span>
                        @endif
                    </dd>
                </div>

                <div class="meta-item">
                    <dt>This invitation</dt>
                    <dd>
                        Expires {{ $review->expires_at ? \Illuminate\Support\Carbon::parse($review->expires_at)->format('F j, Y g:i A') : '—' }}
                        <span>Sent to {{ $review->recipient_email ?: 'the Executive Director’s mailbox' }} · single use</span>
                    </dd>
                </div>

                @if (! empty($request->Note))
                    <div class="meta-item" style="grid-column: 1 / -1;">
                        <dt>Requester’s note</dt>
                        <dd>{{ $request->Note }}</dd>
                    </div>
                @endif

                @if (! empty($summary['attachment']))
                    <div class="meta-item" style="grid-column: 1 / -1;">
                        <dt>Supporting document</dt>
                        <dd><a href="{{ $summary['attachment'] }}" target="_blank" rel="noopener noreferrer">Open the file attached to this request</a></dd>
                    </div>
                @endif
            </dl>

            {{--
                The form opens here, above the table, and closes after the submit
                button: the asset boxes are part of the decision, so they have to
                be inside the form to travel with it. A checkbox outside the form
                is drawn but never submitted — the reviewer would tick an asset
                and the request would arrive with nothing ticked.
            --}}
            <form method="POST" action="/executive-review/{{ $token }}/decide" id="decision-form">
            @csrf

            <h3 class="section-title">Assets in this transfer</h3>

            <div class="table-wrap">
                <table class="assets">
                    <thead>
                        <tr>
                            @if ($canDecide)
                                <th class="pick">
                                    <input type="checkbox" id="check-all" aria-label="Select every asset" disabled>
                                </th>
                            @endif
                            <th>Asset tag</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Condition</th>
                            <th>Serial no.</th>
                            <th>Location</th>
                            <th>Current custodian</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assets as $asset)
                            <tr>
                                @if ($canDecide)
                                    <td class="pick">
                                        <input
                                            type="checkbox"
                                            class="asset-check"
                                            name="asset_ids[]"
                                            value="{{ $asset->id }}"
                                            aria-label="Approve {{ $asset->Asset_code ?: 'this asset' }}"
                                            disabled
                                        >
                                    </td>
                                @endif
                                <td><span class="tag">{{ $asset->Asset_code ?: ('#' . $asset->id) }}</span></td>
                                <td>
                                    {{ $asset->Asset_name ?: '—' }}
                                    @if (! empty($asset->Lifecycle_Status))
                                        <div class="muted">{{ $asset->Lifecycle_Status }}</div>
                                    @endif
                                </td>
                                <td>{{ $asset->Category ?: '—' }}</td>
                                <td>{{ $asset->Condition ?: '—' }}</td>
                                <td>{{ $asset->serial_Number ?: '—' }}</td>
                                <td>{{ $asset->asset_location ?: '—' }}</td>
                                <td>
                                    {{ $asset->holder_name ?: ($asset->holder_email ?: 'Not recorded') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canDecide ? 8 : 7 }}" class="muted">
                                    This request has no assets recorded against it.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($canDecide)
                <div class="note">
                    Each row above is one asset record. <strong>Approving does not complete anything by itself</strong>:
                    it records your decision, and an approved asset is reassigned to
                    {{ $summary['to']['name'] ?? 'the intended recipient' }}. Any asset you do not approve stays with
                    {{ $summary['from']['name'] ?? 'the employee who requested the transfer' }}, and rejecting the
                    transfer changes no asset at all.
                </div>

                <h3 class="section-title">Your decision</h3>

                    <label class="option" data-option="approve_all">
                        <span>
                            <input type="radio" name="decision" value="approve_all" {{ old('decision') === 'approve_all' ? 'checked' : '' }}>
                            <span class="option-title">Approve the transfer in full</span>
                        </span>
                        <p class="option-desc">
                            All {{ $assetCount }} {{ \Illuminate\Support\Str::plural('asset', $assetCount) }} listed above
                            {{ $assetCount === 1 ? 'is' : 'are' }} reassigned to {{ $summary['to']['name'] ?? 'the intended recipient' }}.
                        </p>
                    </label>

                    <label class="option" data-option="approve_selected">
                        <span>
                            <input type="radio" name="decision" value="approve_selected" {{ old('decision') === 'approve_selected' ? 'checked' : '' }}>
                            <span class="option-title">Approve selected assets only</span>
                        </span>
                        <p class="option-desc">
                            Tick the assets above that may be transferred. Only those are reassigned; the rest stay where they are.
                        </p>
                    </label>

                    <label class="option" data-option="reject">
                        <span>
                            <input type="radio" name="decision" value="reject" {{ old('decision') === 'reject' ? 'checked' : '' }}>
                            <span class="option-title">Reject the transfer</span>
                        </span>
                        <p class="option-desc">
                            No asset changes hands. A reason is required and is passed back to the Asset Management Office
                            and to the employee who requested the transfer.
                        </p>
                    </label>

                    <p class="counter" id="counter">No assets selected yet.</p>

                    <div class="form-group">
                        <label for="rejection_reason">Reason for rejection <span class="required">*</span></label>
                        <textarea
                            id="rejection_reason"
                            name="rejection_reason"
                            placeholder="Explain why this transfer should not go ahead."
                            disabled
                        >{{ old('rejection_reason') }}</textarea>
                        <span class="field-note" id="reason-note">
                            Only needed if you reject the transfer.
                        </span>
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn-primary">
                            <i class="ri-send-plane-line"></i> Submit decision
                        </button>
                    </div>

                    <span class="field-note">
                        Submitting ends this review: the link stops working immediately and cannot be used a second time.
                    </span>
                </form>

                <script>
                    /*
                     * The three choices are mutually exclusive, and only the
                     * ones that carry extra input ask for it: the asset boxes
                     * are live only while "selected assets" is chosen (so they
                     * cannot be submitted with either other choice) and the
                     * reason box only while "reject" is chosen. The selection
                     * itself is kept in this array, so switching between the
                     * choices never loses what the reviewer ticked.
                     */
                    (function () {
                        var form = document.getElementById('decision-form');
                        // Inside the form, so a ticked box is really submitted.
                        var checks = Array.prototype.slice.call(form.querySelectorAll('.asset-check'));
                        var all = document.getElementById('check-all');
                        var reason = document.getElementById('rejection_reason');
                        var reasonNote = document.getElementById('reason-note');
                        var counter = document.getElementById('counter');
                        var picked = [];

                        function mode() {
                            var checked = form.querySelector('input[name="decision"]:checked');
                            return checked ? checked.value : '';
                        }

                        function paint() {
                            var current = mode();
                            var picking = current === 'approve_selected';

                            checks.forEach(function (box) {
                                box.disabled = !picking;
                                box.checked = picking && picked.indexOf(box.value) !== -1;
                            });

                            if (all) {
                                all.disabled = !picking;
                                all.checked = picking && picked.length === checks.length && checks.length > 0;
                            }

                            reason.disabled = current !== 'reject';
                            reason.required = current === 'reject';

                            form.querySelectorAll('[data-option]').forEach(function (option) {
                                var value = option.getAttribute('data-option');
                                option.classList.toggle('is-active', value === current);
                                option.classList.toggle('is-muted', current !== '' && value !== current);
                            });

                            reasonNote.textContent = current === 'reject'
                                ? 'Required — the reason is recorded and sent to the requester and the Asset Management Office.'
                                : 'Only needed if you reject the transfer.';

                            counter.textContent = checks.length === 0
                                ? ''
                                : picked.length + ' of ' + checks.length + ' ' +
                                  (checks.length === 1 ? 'asset' : 'assets') + ' selected to be transferred.' +
                                  (picking && picked.length === 0 ? ' Tick at least one, or choose another option.' : '');
                        }

                        form.querySelectorAll('input[name="decision"]').forEach(function (radio) {
                            radio.addEventListener('change', function () {
                                if (radio.value === 'approve_selected') {
                                    picked = checks.filter(function (b) { return b.checked; })
                                        .map(function (b) { return b.value; });
                                }
                                paint();
                            });
                        });

                        checks.forEach(function (box) {
                            box.addEventListener('change', function () {
                                var index = picked.indexOf(box.value);

                                if (box.checked && index === -1) { picked.push(box.value); }
                                if (!box.checked && index !== -1) { picked.splice(index, 1); }

                                paint();
                            });
                        });

                        if (all) {
                            all.addEventListener('change', function () {
                                picked = all.checked ? checks.map(function (b) { return b.value; }) : [];
                                paint();
                            });
                        }

                        paint();
                    })();
                </script>
            @else
                <div class="note">
                    <strong>There is no decision to take from this page.</strong>
                    {{ $review->status_hint }}
                </div>

                {{-- The form opened above the table, so it is closed here too. --}}
                </form>
            @endif

            <p class="footer-note">
                NU Trace &middot; NU Lipa University Asset Management<br>
                This page shows one transfer request only and grants no access to the NU Trace inventory.
                Questions about this transfer go to the NU Lipa Asset Management Office.
            </p>
        </div>
    </main>
</body>
</html>
