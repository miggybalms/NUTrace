@php
    $catalogue = \App\Support\Reports::catalogue();

    // Every status the system records, grouped by what it describes. One select
    // rather than four: the reader can see all of them at once, and a value that
    // does not belong to the chosen report is refused by the server — and said
    // so — instead of silently changing the report.
    $statusGroups = [
        'Current asset status'  => \App\Support\Reports::ASSET_STATUSES,
        'Repair status'         => \App\Support\Reports::REPAIR_STATUSES,
        'Request status'        => \App\Support\Reports::REQUEST_STATUSES,
        'Disposal record state' => \App\Support\Reports::DISPOSAL_STATES,
    ];

    $applied = $report['filters'] ?? $filters;
    $meta    = $report['meta'] ?? \App\Support\Reports::category($filters['category']);
@endphp

@if($scopeProblem)

    {{-- An account with no department recorded has no records it may read. It is
         told so, rather than shown an empty report that looks broken. --}}
    <div class="rounded-xl border border-[#EAD9B4] bg-[#FBF1DE] p-5 sm:p-6 max-w-3xl">
        <div class="flex items-start gap-3">
            <i class="ri-error-warning-line text-2xl text-[#8F5F16] flex-shrink-0"></i>
            <div>
                <h2 class="font-semibold text-[#0A1830]">No department is recorded for your account</h2>
                <p class="text-sm text-[#5B6678] mt-1">{{ $scopeProblem }}</p>
            </div>
        </div>
    </div>

@else

    {{-- The page title lives in the shell's own header, so it is not repeated
         here: this is only what the page is for. --}}
    <p class="mb-5 sm:mb-6 max-w-3xl text-sm text-[#5B6678]">
        Choose a report, filter it by date, status{{ $isAdmin ? ' and department' : '' }}, then export or print every
        matching record. What is on screen changes only when you press <strong>Apply Filters</strong>.
    </p>

    {{-- ============================================================
         Filters
         Changing a control here changes nothing on its own: this form is
         the only way a report is generated, and everything below continues
         to describe the criteria that were applied last.
         ============================================================ --}}
    <form method="POST" action="{{ $endpoints['apply'] }}" id="report-filters"
          class="bg-white rounded-xl border border-[#DED2AE] shadow-sm p-4 sm:p-5 mb-5 sm:mb-6">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div>
                <label for="report-category" class="block text-xs font-semibold uppercase tracking-wide text-[#5C6474] mb-1.5">Report</label>
                <select name="category" id="report-category" class="w-full px-3 py-2.5 text-sm bg-white">
                    @foreach($catalogue as $key => $entry)
                        <option value="{{ $key }}" @selected($applied['category'] === $key)>{{ $entry['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="report-preset" class="block text-xs font-semibold uppercase tracking-wide text-[#5C6474] mb-1.5">Date range</label>
                <select name="preset" id="report-preset" class="w-full px-3 py-2.5 text-sm bg-white">
                    @foreach(\App\Support\Reports::PRESET_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected($applied['preset'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- The custom range only appears when the reader asks for one. --}}
            <div id="report-custom-range" class="{{ $applied['preset'] === \App\Support\Reports::PRESET_CUSTOM ? '' : 'hidden' }} sm:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wide text-[#5C6474] mb-1.5">Custom dates</label>
                <div class="flex items-center gap-2">
                    <input type="date" name="from" id="report-from" value="{{ $applied['from_date'] }}"
                           aria-label="Custom range start date" class="w-full px-3 py-2.5 text-sm bg-white">
                    <span class="text-sm text-[#5C6474]">to</span>
                    <input type="date" name="to" id="report-to" value="{{ $applied['to_date'] }}"
                           aria-label="Custom range end date" class="w-full px-3 py-2.5 text-sm bg-white">
                </div>
            </div>

            <div>
                <label for="report-status" class="block text-xs font-semibold uppercase tracking-wide text-[#5C6474] mb-1.5">Status</label>
                <select name="status" id="report-status" class="w-full px-3 py-2.5 text-sm bg-white">
                    <option value="all" @selected($applied['status'] === 'all')>All recorded statuses</option>
                    @foreach($statusGroups as $groupLabel => $values)
                        <optgroup label="{{ $groupLabel }}">
                            @foreach($values as $value)
                                <option value="{{ $value }}" @selected($applied['status'] === $value)>{{ $value }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div class="{{ $meta['type_filter'] ? '' : 'hidden' }}">
                <label for="report-type" class="block text-xs font-semibold uppercase tracking-wide text-[#5C6474] mb-1.5">Request type</label>
                <select name="type" id="report-type" class="w-full px-3 py-2.5 text-sm bg-white">
                    <option value="all" @selected($applied['type'] === 'all')>All request types</option>
                    @foreach(\App\Support\Reports::REQUEST_TYPES as $value)
                        <option value="{{ $value }}" @selected($applied['type'] === $value)>{{ $value }}</option>
                    @endforeach
                </select>
            </div>

            @if($isAdmin)
                {{-- A Department Head's reports are fixed to their own department,
                     so this control is not offered to them at all: the scope is
                     their account, not a filter they could change. --}}
                <div>
                    <label for="report-department" class="block text-xs font-semibold uppercase tracking-wide text-[#5C6474] mb-1.5">Department</label>
                    <select name="department" id="report-department" class="w-full px-3 py-2.5 text-sm bg-white">
                        <option value="all" @selected(! $applied['department'])">All departments</option>
                        @foreach($departments as $option)
                            <option value="{{ $option['value'] }}" @selected((string) $applied['department'] === $option['value'])>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-3 mt-4 pt-4 border-t border-[#EFE9D8]">
            <button type="submit" class="btn-gold">
                <i class="ri-filter-3-line mr-1.5"></i>
                Apply Filters
            </button>

            <a href="{{ $endpoints['page'] }}" class="btn-ghost">
                <i class="ri-refresh-line mr-1.5"></i>
                Reset
            </a>

            <p id="report-pending-note" class="hidden text-sm text-[#8F5F16]" role="status">
                <i class="ri-information-line mr-1"></i>
                Filters changed — the report below still shows the criteria last applied.
            </p>
        </div>

        {{-- A filter that could not apply to this report is reported here, so
             nothing looks as though it had been honoured when it was not. --}}
        @foreach($notices ?? [] as $note)
            <p class="mt-3 text-sm text-[#8F5F16]">
                <i class="ri-information-line mr-1"></i>{{ $note }}
            </p>
        @endforeach
    </form>

    {{-- ============================================================
         A failed attempt: said plainly, above everything, and never
         presented as the report that was asked for.
         ============================================================ --}}
    @if($error)
        <div class="rounded-xl border border-[#E7C9C4] bg-[#F7E9E6] p-4 sm:p-5 mb-5" role="alert" data-report-error>
            <div class="flex items-start gap-3">
                <i class="ri-error-warning-line text-xl text-[#7E2E27] flex-shrink-0"></i>
                <p class="text-sm text-[#7E2E27]">{{ $error }}</p>
            </div>
        </div>
    @endif

    @if($report)

        {{-- ========================================================
             What is on the screen: the criteria and the time it was
             generated with, then the export and print of exactly that.
             ======================================================== --}}
        <div class="bg-white rounded-xl border border-[#DED2AE] shadow-sm p-4 sm:p-5 mb-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="font-display text-lg font-semibold text-[#0F2143]">{{ $report['meta']['label'] }}</h2>

                        @if($report['stale'])
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#FBF1DE] text-[#8F5F16] border border-[#EAD9B4]"
                                  data-report-stale>
                                Last successful report — not the attempt that just failed
                            </span>
                        @endif
                    </div>

                    <p class="text-sm text-[#5B6678] mt-1">
                        Generated {{ $report['generated_at']->format('M j, Y g:i A') }}
                        <span class="text-[#8A93A5]">·</span>
                        <span data-report-total>{{ number_format($report['total']) }}</span>
                        {{ \Illuminate\Support\Str::plural($report['meta']['singular'], $report['total']) }} match these criteria
                    </p>

                    <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-2">
                        @foreach(\App\Support\Reports::describe($report['filters']) as $label => $value)
                            <div>
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-[#5C6474]">{{ $label }}</dt>
                                <dd class="text-sm text-[#24334F]">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    {{-- Both actions carry the applied criteria, so neither can
                         ever export or print the attempt that failed. --}}
                    <a href="{{ $endpoints['export'] }}" class="btn-gold" download data-report-export>
                        <i class="ri-download-line mr-1.5"></i>
                        Export CSV
                    </a>
                    <a href="{{ $endpoints['print'] }}" class="btn-ghost" target="_blank" rel="noopener" data-report-print>
                        <i class="ri-printer-line mr-1.5"></i>
                        Print
                    </a>
                </div>
            </div>
        </div>

        {{-- ========================================================
             Summary cards — counted with the same criteria as the table.
             ======================================================== --}}
        @if(! empty($report['summary']['cards']))
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-5">
                @foreach($report['summary']['cards'] as $card)
                    <div class="bg-white rounded-xl border border-[#DED2AE] shadow-sm p-4 sm:p-5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#5C6474]">{{ $card['label'] }}</p>
                        <p class="text-2xl font-semibold text-[#0F2143] mt-1.5">{{ $card['value'] }}</p>
                        @if(! empty($card['note']))
                            <p class="text-xs text-[#5C6474] mt-1.5 leading-relaxed">{{ $card['note'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ========================================================
             Breakdowns — the same records, split by status / type /
             department.
             ======================================================== --}}
        @if(! empty($report['summary']['breakdowns']))
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4 mb-5">
                @foreach($report['summary']['breakdowns'] as $breakdown)
                    <div class="bg-white rounded-xl border border-[#DED2AE] shadow-sm overflow-hidden">
                        <div class="px-4 sm:px-5 py-3.5 border-b border-[#EFE9D8]">
                            <h3 class="font-semibold text-[#0F2143] text-sm">{{ $breakdown['title'] }}</h3>
                        </div>
                        @if(empty($breakdown['rows']))
                            <p class="px-4 sm:px-5 py-4 text-sm text-[#5C6474]">No records found for the selected filters.</p>
                        @else
                            <ul class="divide-y divide-[#F0EADA]">
                                @foreach($breakdown['rows'] as $row)
                                    <li class="flex items-center justify-between px-4 sm:px-5 py-2.5 text-sm">
                                        <span class="text-[#24334F] truncate mr-3">{{ $row['label'] }}</span>
                                        <span class="font-semibold text-[#0F2143] tabular-nums">{{ number_format($row['total']) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if(! empty($breakdown['note']))
                            <p class="px-4 sm:px-5 py-3 text-xs text-[#5C6474] border-t border-[#EFE9D8] leading-relaxed">{{ $breakdown['note'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ========================================================
             The records.
             ======================================================== --}}
        <div class="bg-white rounded-xl border border-[#DED2AE] shadow-sm overflow-hidden mb-5">
            <div class="px-4 sm:px-5 py-3.5 border-b border-[#EFE9D8] flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold text-[#0F2143] text-sm">Records</h3>
                <p class="text-xs text-[#5C6474]">
                    @if($report['total'] > 0)
                        Showing {{ $report['rows']->firstItem() }}–{{ $report['rows']->lastItem() }} of
                        {{ number_format($report['total']) }}. The export and the print sheet hold every one of them.
                    @else
                        0 matching records
                    @endif
                </p>
            </div>

            @if($report['total'] === 0)
                <div class="px-4 sm:px-5 py-10 text-center" data-report-empty>
                    <div class="w-14 h-14 bg-[#0A1830]/5 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="ri-inbox-line text-2xl text-[#C9A227]"></i>
                    </div>
                    <h4 class="font-semibold text-[#33425C]">No records found for the selected filters</h4>
                    <p class="text-sm text-[#5C6474] mt-1">
                        0 matching records. Widen the date range or clear a filter and apply again.
                    </p>
                    <a href="{{ $endpoints['page'] }}" class="inline-flex items-center mt-3 px-3.5 py-1.5 rounded-full text-xs font-medium bg-[#0A1830]/5 text-[#33425C] hover:bg-[#0A1830]/10 transition">
                        <i class="ri-refresh-line mr-1"></i> Show the default report
                    </a>
                </div>
            @else
                <div class="overflow-x-auto table-container">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                @foreach($report['columns'] as $column)
                                    <th class="text-left px-4 py-3 whitespace-nowrap">{{ $column['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['rows'] as $row)
                                <tr data-report-row>
                                    @foreach($report['columns'] as $column)
                                        <td class="px-4 py-3 align-top text-[#24334F] {{ $loop->first ? 'font-medium whitespace-nowrap' : '' }}">
                                            @php $value = (string) ($row[$column['key']] ?? ''); @endphp
                                            {{ $value !== '' ? $value : '—' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @include('partials.asset_pagination', ['assets' => $report['rows']])
            @endif
        </div>

        {{-- ========================================================
             What the numbers do and do not say. Printed with the report
             on purpose: a total travels without its footnote otherwise.
             ======================================================== --}}
        @if(! empty($report['summary']['notes']))
            <div class="bg-[#F5F0E2] rounded-xl border border-[#EFE9D8] p-4 sm:p-5">
                <h3 class="text-sm font-semibold text-[#0F2143] flex items-center gap-2">
                    <i class="ri-information-line text-[#C9A227]"></i>
                    About this report
                </h3>
                <ul class="mt-2.5 space-y-1.5">
                    @foreach($report['summary']['notes'] as $note)
                        <li class="text-sm text-[#46536B] flex gap-2">
                            <span class="text-[#C9A227] leading-6">•</span>
                            <span>{{ $note }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

    @elseif(! $error)

        {{-- No report yet and nothing has failed: first visit, or the page was
             opened without criteria. --}}
        <div class="rounded-xl border border-[#EAD9B4] bg-[#FBF1DE] p-4 sm:p-5">
            <p class="text-sm text-[#8F5F16]">
                <i class="ri-information-line mr-1"></i>
                This account cannot read reports at the moment.
            </p>
        </div>

    @endif

    {{-- ============================================================
         Which reports exist, and what each one actually holds.
         ============================================================ --}}
    <div class="mt-6">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-[#5C6474] mb-3">Available reports</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($categoryCards as $card)
                <a href="{{ $endpoints['page'] }}?category={{ $card['key'] }}"
                   class="bg-white rounded-xl border {{ $applied['category'] === $card['key'] ? 'border-[#C9A227]' : 'border-[#DED2AE]' }} shadow-sm p-4 flex gap-3 hover:border-[#C9A227] transition-colors">
                    <span class="w-9 h-9 rounded-lg bg-[#0A1830]/5 flex items-center justify-center flex-shrink-0">
                        <i class="{{ $card['icon'] }} text-[#0A1830]"></i>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-[#0F2143]">{{ $card['label'] }}</span>
                        <span class="block text-xs text-[#5C6474] mt-0.5 leading-relaxed">{{ $card['description'] }}</span>
                        <span class="inline-block text-[11px] font-semibold uppercase tracking-wide mt-1.5 {{ $card['event'] ? 'text-[#8F5F16]' : 'text-[#245C3B]' }}">
                            {{ $card['event'] ? 'Historical events' : 'Current records' }}
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>

    <div class="text-center text-sm text-[#5C6474] mt-10 pt-6 border-t border-[#DED2AE]">
        © {{ date('Y') }} University Asset Management. All rights reserved.
    </div>

    <script>
        (function () {
            var form    = document.getElementById('report-filters');
            var preset  = document.getElementById('report-preset');
            var custom  = document.getElementById('report-custom-range');
            var hint    = document.getElementById('report-pending-note');

            if (!form) return;

            // The custom range is only useful when it has been asked for.
            preset?.addEventListener('change', function () {
                custom?.classList.toggle('hidden', this.value !== 'custom');
            });

            // Say out loud that a changed control has not been applied yet —
            // the report below is still the last one generated.
            var initial = new URLSearchParams(new FormData(form)).toString();

            form.addEventListener('change', function () {
                var current = new URLSearchParams(new FormData(form)).toString();
                hint?.classList.toggle('hidden', current === initial);
            });
        })();
    </script>

@endif
