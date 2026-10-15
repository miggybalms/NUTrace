{{--
    The official NU Lipa Disposal Form, as it prints.

    Rendered from the recorded data only. Two things the document deliberately
    does not do:

      · It does not print anyone's name in a signature block. The system does not
        capture electronic signatures, so every signature line is left blank for
        a person to sign. Nothing on this page claims a signatory agreed to
        anything.
      · It does not say the asset is disposed of. The form is the paperwork; the
        disposal is finalized separately, in the system.

    Reached two ways: as a preview (no printing recorded) and from the print
    action (printing recorded, and this page calls the browser's print dialog).
--}}
@php
    $forms    = $forms ?? collect();
    $refused  = $refused ?? [];
    $preview  = $preview ?? false;
    $forPrint = $forPrint ?? false;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $preview ? 'Disposal Form Preview' : 'Official Disposal Form' }} - NU Trace</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --navy:#0A1830; --gold:#C9A227; --paper:#F3EEE0; --ink:#1A2233;
            --line:#DED2AE; --muted:#5B6678;
        }

        *{ margin:0; padding:0; box-sizing:border-box; }

        body{
            font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
            color:var(--ink);
            background:var(--paper);
        }

        /* ── The on-screen toolbar never prints ─────────────────────── */
        .toolbar{
            position:sticky; top:0; z-index:10; background:#fff;
            border-bottom:1px solid var(--line); padding:.85rem 1.25rem;
            display:flex; flex-wrap:wrap; gap:.6rem; align-items:center; justify-content:space-between;
        }
        .btn-gold{ background:var(--gold); color:var(--navy); font-weight:600; padding:.55rem 1.05rem; border-radius:.55rem; display:inline-flex; align-items:center; gap:.4rem; font-size:.875rem; }
        .btn-ghost{ background:#fff; color:var(--muted); border:1px solid var(--line); font-weight:600; padding:.55rem 1.05rem; border-radius:.55rem; display:inline-flex; align-items:center; gap:.4rem; font-size:.875rem; }

        .sheet{
            background:#fff; margin:1.25rem auto; padding:26px 30px 30px;
            width:210mm; max-width:100%;
            box-shadow:0 1px 2px rgba(10,24,48,.08), 0 18px 36px -22px rgba(10,24,48,.35);
        }

        .doc-head{ text-align:center; border-bottom:2px solid var(--navy); padding-bottom:10px; margin-bottom:14px; }
        .doc-head .school{ font-family:'Fraunces',Georgia,serif; font-size:15px; font-weight:600; letter-spacing:.02em; }
        .doc-head .office{ font-size:11px; color:var(--muted); margin-top:2px; }
        .doc-head h1{ font-family:'Fraunces',Georgia,serif; font-size:20px; font-weight:700; letter-spacing:.08em; margin-top:8px; text-transform:uppercase; }

        .meta{ display:flex; flex-wrap:wrap; gap:6px 22px; font-size:11px; color:var(--muted); margin-bottom:12px; }
        .meta b{ color:var(--ink); font-weight:600; }

        table.items{ width:100%; border-collapse:collapse; font-size:10.5px; margin-bottom:12px; }
        table.items th, table.items td{ border:1px solid #8C8676; padding:5px 6px; vertical-align:top; text-align:left; }
        table.items th{ background:#F0EBDD; font-size:9.5px; text-transform:uppercase; letter-spacing:.04em; }
        table.items td.num{ text-align:center; }
        .tag{ font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:10px; }

        .blocks{ display:flex; flex-wrap:wrap; gap:14px; margin-bottom:14px; }
        .block{ flex:1 1 46%; border:1px solid var(--line); border-radius:6px; padding:9px 11px; }
        .block h3{ font-size:9.5px; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); margin-bottom:6px; font-weight:700; }
        .block .row{ font-size:11px; margin-bottom:3px; display:flex; gap:6px; }
        .block .row span:first-child{ color:var(--muted); min-width:118px; display:inline-block; }
        .block .row span:last-child{ font-weight:600; flex:1; }
        .blank{ color:#9AA0AC; font-weight:500; }

        .legend{ border:1px solid var(--line); border-radius:6px; padding:9px 11px; margin-bottom:14px; }
        .legend h3{ font-size:9.5px; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); margin-bottom:6px; font-weight:700; }
        .legend ul{ list-style:none; display:flex; flex-wrap:wrap; gap:3px 18px; font-size:10.5px; }
        .legend .methods{ margin-top:6px; padding-top:6px; border-top:1px dashed var(--line); }

        .signatures{ display:flex; flex-wrap:wrap; gap:18px 0; margin-top:6px; }
        .signature{ flex:1 1 50%; padding:0 14px 0 0; }
        .signature .line{ border-bottom:1px solid #6E6A5F; height:30px; margin-bottom:4px; }
        .signature .role{ font-size:10.5px; font-weight:600; }
        .signature .hint{ font-size:9.5px; color:var(--muted); }

        .footnote{ margin-top:16px; border-top:1px solid var(--line); padding-top:8px; font-size:9.5px; color:var(--muted); line-height:1.5; }

        .notice{ margin:0 1.25rem 0; padding:.7rem .9rem; border-radius:.5rem; font-size:.8rem; display:flex; gap:.5rem; align-items:flex-start; }
        .notice-warn{ background:#FBF1DE; color:#8F5F16; border-left:4px solid var(--gold); }

        .preview-chip{ background:#EAF4EE; color:#245C3B; font-weight:700; font-size:.72rem; padding:.25rem .55rem; border-radius:999px; letter-spacing:.05em; text-transform:uppercase; }

        /* ── Paper ──────────────────────────────────────────────────── */
        @page{ size:A4 portrait; margin:12mm; }

        @media print{
            body{ background:#fff; }
            .toolbar, .notice, .no-print{ display:none !important; }
            .sheet{
                margin:0; padding:0; width:auto; max-width:none; box-shadow:none;
                page-break-after:always;
            }
            .sheet:last-child{ page-break-after:auto; }
        }
    </style>
</head>
<body>

    <div class="toolbar no-print">
        <div class="flex items-center gap-2" style="display:flex; align-items:center; gap:.5rem;">
            @if($preview)
                <span class="preview-chip"><i class="ri-eye-line"></i> Preview</span>
                <span style="font-size:.8rem; color:var(--muted);">
                    Nothing is recorded by opening this page. Printing is recorded only on the print action.
                </span>
            @else
                <span class="preview-chip" style="background:#E9F0F7; color:#234869;">
                    <i class="ri-printer-line"></i> Printing recorded
                </span>
                <span style="font-size:.8rem; color:var(--muted);">
                    {{ count($forms) }} form{{ count($forms) === 1 ? '' : 's' }} sent to the print dialog.
                    Printing does not dispose of any asset.
                </span>
            @endif
        </div>
        <div style="display:flex; gap:.5rem;">
            <button type="button" class="btn-gold" onclick="window.print()">
                <i class="ri-printer-line"></i> Print
            </button>
            <a href="{{ url('/admin/disposal?tab=forms') }}" class="btn-ghost">
                <i class="ri-arrow-left-line"></i> Back to Disposal Forms
            </a>
        </div>
    </div>

    @if($refused)
        <div class="notice notice-warn no-print" style="margin-top:1rem;">
            <i class="ri-error-warning-line"></i>
            <div>
                <p style="font-weight:600;">Some forms were not printed.</p>
                @foreach($refused as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        </div>
    @endif

    @forelse($forms as $form)
        <section class="sheet">
            @php
                $blank = fn ($value) => ($value === null || trim((string) $value) === '')
                    ? '<span class="blank">—</span>'
                    : e((string) $value);
            @endphp

            <div class="doc-head">
                <div class="school">NATIONAL UNIVERSITY – LIPA</div>
                <div class="office">Asset Management Office · Property and Supply</div>
                <h1>Disposal Form</h1>
                <div class="office" style="margin-top:4px;">
                    NU Trace disposal form #{{ $form->id }} · Request #{{ $form->request_id }}
                    @if($form->actual_disposal_date)
                        · Disposal date {{ \Illuminate\Support\Carbon::parse($form->actual_disposal_date)->format('F j, Y') }}
                    @endif
                </div>
            </div>

            <div class="meta">
                <span><b>Office/Department:</b> {!! $blank($form->office_department) !!}</span>
                <span><b>Expected Date of Disposal:</b>
                    {!! $blank($form->expected_disposal_date ? \Illuminate\Support\Carbon::parse($form->expected_disposal_date)->format('F j, Y') : null) !!}
                </span>
                <span><b>Prepared by:</b> {!! $blank($form->prepared_by_name ?? $form->prepared_by_email ?? null) !!}</span>
                <span><b>Form generated:</b> {{ \Illuminate\Support\Carbon::parse($form->created_at)->format('F j, Y') }}</span>
            </div>

            <table class="items">
                <thead>
                    <tr>
                        <th style="width:26px;">#</th>
                        <th style="width:12%;">Tag No.</th>
                        <th style="width:34px;">Qty</th>
                        <th>Description</th>
                        <th style="width:15%;">Serial Number</th>
                        <th style="width:11%;">Date Acquired</th>
                        <th style="width:12%;">Location</th>
                        <th style="width:14%;">Custodian</th>
                        <th style="width:46px;">Condition Code</th>
                        <th style="width:52px;">Reason for Disposal Code</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($form->items as $index => $item)
                        <tr>
                            <td class="num">{{ $index + 1 }}</td>
                            <td class="tag">{!! $blank($item->tag_no) !!}</td>
                            <td class="num">{{ $item->quantity }}</td>
                            <td>{!! $blank($item->description) !!}</td>
                            <td class="tag">{!! $blank($item->serial_number) !!}</td>
                            <td>{!! $blank($item->date_acquired ? \Illuminate\Support\Carbon::parse($item->date_acquired)->format('m/d/Y') : null) !!}</td>
                            <td>{!! $blank($item->location) !!}</td>
                            <td>{!! $blank($item->custodian) !!}</td>
                            <td class="num">{{ $item->condition_code ?: '—' }}</td>
                            <td class="num">{{ $item->reason_code ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="blocks">
                <div class="block">
                    <h3>Method of Disposal Code</h3>
                    <div class="row"><span>Method</span><span>{!! $blank($form->method_text) !!}</span></div>
                </div>
                <div class="block">
                    <h3>Additional Fields for Sold Items</h3>
                    <div class="row"><span>Amount</span><span>{!! $blank($form->sold_amount !== null ? number_format((float) $form->sold_amount, 2) : null) !!}</span></div>
                    <div class="row"><span>OR No.</span><span>{!! $blank($form->sold_or_no) !!}</span></div>
                    <div class="row"><span>Date</span><span>{!! $blank($form->sold_date ? \Illuminate\Support\Carbon::parse($form->sold_date)->format('m/d/Y') : null) !!}</span></div>
                    <div class="row"><span>Gate Pass No.</span><span>{!! $blank($form->gate_pass_no) !!}</span></div>
                    <div class="row"><span>Date</span><span>{!! $blank($form->gate_pass_date ? \Illuminate\Support\Carbon::parse($form->gate_pass_date)->format('m/d/Y') : null) !!}</span></div>
                </div>
            </div>

            @if(trim((string) $form->remarks) !== '')
                <div class="legend">
                    <h3>Remarks</h3>
                    <div style="font-size:10.5px; white-space:pre-line;">{{ $form->remarks }}</div>
                </div>
            @endif

            <div class="legend">
                <h3>Reason / Condition Codes</h3>
                <ul>
                    @foreach(\App\Support\DisposalCodes::REASON_CODES as $code => $label)
                        <li><b>{{ $code }}</b> — {{ $label }}</li>
                    @endforeach
                </ul>
                <div class="methods">
                    <h3>Method of Disposal Codes</h3>
                    <ul>
                        @foreach(\App\Support\DisposalCodes::METHOD_CODES as $method)
                            <li><b>{{ $method['code'] }}</b> — {{ $method['label'] }}</li>
                        @endforeach
                    </ul>
                    <p style="font-size:9.5px; color:var(--muted); margin-top:5px;">Kept exactly as the official form prints it: the code T appears twice, for Tender/sold and for Trade-in. The method recorded for this form is shown above.</p>
                </div>
            </div>

            <div class="signatures">
                @foreach([
                    'Asset Management Specialist',
                    'Department Head',
                    'AB Director, President/Vice President',
                    'Property Asset Custodian',
                ] as $role)
                    <div class="signature">
                        <div class="line"></div>
                        <div class="role">{{ $role }}</div>
                        <div class="hint">Signature over printed name · Date</div>
                    </div>
                @endforeach
            </div>

            <div class="footnote">
                Generated by NU Trace from the recorded disposal request and asset records.
                The signature blocks are intentionally blank: this system does not capture
                electronic signatures, and no signatory is recorded as having approved this
                disposal merely because the form was generated or printed.
                Request #{{ $form->request_id }} · requester's note:
                “{{ \Illuminate\Support\Str::limit(trim((string) $form->request_note) ?: '—', 240) }}”
            </div>
        </section>
    @empty
        <div class="notice notice-warn" style="margin-top:1rem;">
            <i class="ri-error-warning-line"></i>
            <p>No form was loaded.</p>
        </div>
    @endforelse

    @if($forPrint)
        <script>
            // The print dialog is opened once the page has laid out, so the
            // printed sheet is the finished form rather than a blank frame.
            window.addEventListener('load', function () {
                window.setTimeout(function () { window.print(); }, 250);
            });
        </script>
    @endif
</body>
</html>
