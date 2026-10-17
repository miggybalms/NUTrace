{{--
    The print sheet.

    A report that is leaving the screen and going onto paper: the title, when it
    was generated, the criteria it was generated with, its totals, and every
    matching record — not only the page that happened to be on screen. This is a
    standalone document, so no navigation, sidebar, buttons or filters come with
    it; the only control is the one that opens the printer, and that control
    does not print.

    The criteria block is deliberately part of the sheet: a page of numbers
    without them can be read as any report at all.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — NU Trace</title>
    <style>
        /* A wide table on A4 portrait loses its right-hand columns, so the sheet
           asks for landscape by default. */
        @page { size: A4 landscape; margin: 12mm; }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            color: #1A2233;
            background: #fff;
            font-size: 11px;
            line-height: 1.45;
        }

        .sheet { max-width: 1100px; margin: 0 auto; padding: 20px 24px 40px; }

        /* ---- Official header ----
           The same centred document header the official disposal form prints
           with: institution, office, then the document title. A report that
           leaves the building should look like the institution's paperwork, not
           like a screenshot of the screen it came from. */
        .doc-head { text-align: center; border-bottom: 2px solid #0A1830; padding-bottom: 10px; margin-bottom: 12px; }
        .doc-head .school { font-family: Georgia, 'Times New Roman', serif; font-size: 15px; font-weight: 700; letter-spacing: .02em; }
        .doc-head .office { font-size: 10.5px; color: #5B6678; margin-top: 2px; }
        .doc-head h1 { font-family: Georgia, 'Times New Roman', serif; font-size: 18px; font-weight: 700;
                       letter-spacing: .08em; margin-top: 8px; text-transform: uppercase; }

        .footnote { margin-top: 16px; border-top: 1px solid #EFE9D8; padding-top: 8px;
                    font-size: 9.5px; color: #5B6678; line-height: 1.5; }

        h1 { font-size: 17px; margin: 14px 0 2px; letter-spacing: -.01em; }
        .subtitle { color: #5B6678; font-size: 10.5px; }

        /* ---- Criteria ---- */
        .criteria { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px 18px;
                    margin: 12px 0 14px; padding: 10px 12px; background: #F5F0E2; border: 1px solid #EFE9D8; border-radius: 8px; }
        .criteria dt { font-size: 9px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: #5C6474; }
        .criteria dd { font-size: 10.5px; color: #24334F; }

        /* ---- Totals ---- */
        .totals { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-bottom: 12px; }
        .total { border: 1px solid #EFE9D8; border-radius: 8px; padding: 8px 10px; }
        .total dt { font-size: 9px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #5C6474; }
        .total dd { font-size: 15px; font-weight: 700; margin-top: 2px; }
        .total p { font-size: 9px; color: #5C6474; margin-top: 2px; }

        /* ---- Table ---- */
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        th { font-size: 9px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: #46536B;
             background: #EFE9D8; text-align: left; padding: 6px 7px; border-bottom: 1px solid #DED2AE; white-space: nowrap; }
        td { padding: 5px 7px; border-bottom: 1px solid #F0EADA; vertical-align: top; }
        tr { page-break-inside: avoid; }
        tbody tr:nth-child(even) td { background: #FBFAF6; }

        .empty { padding: 22px 12px; text-align: center; border: 1px solid #EFE9D8; border-radius: 8px; color: #5B6678; }

        /* ---- Notes ---- */
        .notes { margin-top: 14px; padding: 10px 12px; border: 1px solid #EFE9D8; border-radius: 8px; background: #FBFAF6; }
        .notes h2 { font-size: 10px; text-transform: uppercase; letter-spacing: .07em; color: #5C6474; margin-bottom: 5px; }
        .notes li { margin-left: 14px; font-size: 10px; color: #46536B; }

        .signoff { margin-top: 22px; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 26px; }
        .signoff div { border-top: 1px solid #1A2233; padding-top: 4px; font-size: 9.5px; color: #5B6678; }

        .toolbar { display: flex; gap: 8px; align-items: center; margin-bottom: 14px; }
        .toolbar button { background: #C9A227; color: #0A1830; border: 0; border-radius: 8px; padding: 8px 14px;
                          font-size: 12px; font-weight: 600; cursor: pointer; }
        .toolbar a { font-size: 12px; color: #5B6678; }

        @media print { .no-print { display: none !important; } .sheet { padding: 0; max-width: none; } }

        @media screen {
            body { background: #F3EEE0; padding: 24px 0; }
            .sheet { background: #fff; box-shadow: 0 8px 24px rgba(10,24,48,.12); border-radius: 10px; }
        }
    </style>
</head>
<body>
    <div class="sheet">

        <div class="toolbar no-print">
            <button type="button" onclick="window.print()">
                Print / Save as PDF
            </button>
            <a href="{{ url()->previous() }}">Back to the report</a>
            <span style="font-size:11px;color:#5C6474;">
                Choose “Save as PDF” as the destination to keep a copy.
            </span>
        </div>

        <div class="doc-head">
            <div class="school">NATIONAL UNIVERSITY – LIPA</div>
            <div class="office">Asset Management Office · Property and Supply</div>
            <h1>{{ $title }}</h1>
            <div class="office" style="margin-top:4px;">
                Generated {{ now()->format('M j, Y g:i A') }}
                @if($filters['department_label'])
                    · {{ $filters['department_label'] }}
                @endif
            </div>
        </div>

        <h1>{{ $title }}</h1>
        <p class="subtitle">
            {{ \App\Support\Reports::rangeLabel($filters) }}
            — {{ number_format($rows->count()) }}
            {{ \Illuminate\Support\Str::plural(\App\Support\Reports::category($filters['category'])['singular'], $rows->count()) }}
            listed on this sheet.
        </p>

        <dl class="criteria">
            @foreach(\App\Support\Reports::describe($filters) as $label => $value)
                <div>
                    <dt>{{ $label }}</dt>
                    <dd>{{ $value }}</dd>
                </div>
            @endforeach
            <div>
                <dt>Generated at</dt>
                <dd>{{ now()->format('M j, Y g:i A') }}</dd>
            </div>
        </dl>

        @if(! empty($summary['cards']))
            <dl class="totals">
                @foreach($summary['cards'] as $card)
                    <div class="total">
                        <dt>{{ $card['label'] }}</dt>
                        <dd>{{ $card['value'] }}</dd>
                        @if(! empty($card['note']))
                            <p>{{ $card['note'] }}</p>
                        @endif
                    </div>
                @endforeach
            </dl>
        @endif

        @if($rows->isEmpty())
            <div class="empty">
                No records found for the selected filters. 0 matching records.
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        @foreach($columns as $column)
                            <th>{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            @foreach($columns as $column)
                                @php $value = (string) ($row[$column['key']] ?? ''); @endphp
                                <td>{{ $value !== '' ? $value : '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if(! empty($summary['notes']))
            <div class="notes">
                <h2>About this report</h2>
                <ul>
                    @foreach($summary['notes'] as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(! empty($summary['breakdowns']))
            @foreach($summary['breakdowns'] as $breakdown)
                @if(! empty($breakdown['rows']))
                    <div class="notes">
                        <h2>{{ $breakdown['title'] }}</h2>
                        <ul>
                            @foreach($breakdown['rows'] as $row)
                                <li>{{ $row['label'] }} — {{ number_format($row['total']) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach
        @endif

        <div class="signoff">
            <div>Prepared by (Asset Management Office)</div>
            <div>Reviewed by</div>
            <div>Received by (Accountancy)</div>
        </div>

        <p class="footnote">
            Generated by NU Trace — University Asset Management, NU Lipa. The figures are a count of the records
            held in the system on the date above; no signature is recorded electronically.
        </p>
    </div>

    <script>
        // The sheet exists to be printed, so the dialog opens with it. The button
        // above is there for the browser that blocks it.
        window.addEventListener('load', function () { window.print(); });
    </script>
</body>
</html>
