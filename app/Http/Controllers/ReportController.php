<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Access;
use App\Support\Csv;
use App\Support\Reports;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Reports page, its CSV export and its print sheet.
 *
 * One controller behind both reporting roles, because the reports themselves
 * are one set: the Asset Management Office reads the whole university and may
 * narrow it to a department, a Department Head reads their own department and
 * nothing else. Which of the two a request belongs to is decided by the URL it
 * arrived on (and enforced again here), and the scope of the data is decided by
 * the signed-in account inside Support\Reports — never by anything the browser
 * sent.
 *
 * The page is driven by the query string, so a report is a link that can be
 * bookmarked, exported and printed and always describes the same records. The
 * Apply Filters button posts, and a successful attempt redirects to that link;
 * a failed one renders the previous report with the failure explained, so a
 * half-generated result can never be mistaken for the one that was asked for.
 */
class ReportController extends Controller
{
    /** The page. */
    public function show(Request $request): View
    {
        $viewer  = $this->viewer($request);
        $filters = Reports::filtersFrom($request->query(), $viewer);

        $report = Reports::build($viewer, $filters);

        Reports::remember($filters, $report['generated_at']);

        return $this->page($viewer, $report);
    }

    /**
     * Apply Filters / Generate Report.
     *
     * A successful attempt is remembered and redirected to the page's own URL
     * for those criteria. A failed one is not: the report already on the screen
     * stays there, labelled with the criteria and time it really has, and the
     * page says plainly that the attempt failed.
     */
    public function generate(Request $request): View|RedirectResponse
    {
        $viewer  = $this->viewer($request);
        $filters = Reports::filtersFrom($request->all(), $viewer);

        try {
            $report = Reports::build($viewer, $filters);
        } catch (\Throwable $exception) {
            $failure = Reports::failed($viewer, $filters, $exception);

            return $this->page($viewer, $failure['report'], $failure['error']);
        }

        Reports::remember($filters, $report['generated_at']);

        // A control that could not apply to this report is not silently turned
        // into something else: the reason travels with the redirect and is shown
        // on the page it lands on.
        if (! empty($filters['dropped'])) {
            session()->flash('report_notes', $filters['dropped']);
        }

        return redirect()->to($this->url($viewer, 'page', Reports::queryFor($filters)));
    }

    /**
     * The CSV of the applied report: every matching record, not the page on
     * screen.
     *
     * The criteria come from the URL the page's own export button was built
     * with, and the authorization applied is the same one the page used, so the
     * file can never hold a record its reader is not allowed to see.
     */
    public function export(Request $request): StreamedResponse
    {
        $viewer  = $this->viewer($request);
        $filters = Reports::filtersFrom($request->query(), $viewer);
        $export  = Reports::csv($viewer, $filters);

        $header = $export['header'];
        $width  = count($header);

        // Streamed rather than assembled in memory: an inventory export is a
        // few thousand rows and must not be cut off by a memory limit.
        $lines = function () use ($export, $width): \Generator {
            foreach ($export['meta'] as $line) {
                yield array_pad($line, $width, '');
            }

            yield array_fill(0, $width, '');

            foreach ($export['rows'] as $row) {
                yield $row;
            }
        };

        return Csv::download($export['filename'], $header, $lines());
    }

    /**
     * The print sheet: the same records, laid out for paper, with the criteria
     * they were generated with.
     */
    public function print(Request $request): View
    {
        $viewer  = $this->viewer($request);
        $filters = Reports::filtersFrom($request->query(), $viewer);
        $sheet   = Reports::sheet($viewer, $filters);

        return view('reports.print', [
            'filters' => $filters,
            'columns' => $sheet['columns'],
            'rows'    => $sheet['rows'],
            'summary' => $sheet['summary'],
            'title'   => $sheet['meta']['label'],
        ]);
    }

    /* ─────────────────────────── the page ─────────────────────────── */

    /**
     * Render the page.
     *
     * `$report` is the report to display — the one just generated, or the last
     * one that worked when this attempt failed (in which case `$error` explains
     * the failure and `$report['stale']` marks it as the earlier one). It is
     * null only when a failed attempt had no earlier report to fall back on.
     */
    private function page(User $viewer, ?array $report, ?string $error = null): View
    {
        $isAdmin = Access::isAssetOffice($viewer);

        $applied = $report['filters'] ?? null;

        return view('reports.index', [
            'report'        => $report,
            'error'         => $error,
            'notices'       => array_merge(
                is_array($applied['dropped'] ?? null) ? $applied['dropped'] : [],
                (array) session()->pull('report_notes', [])
            ),
            'filters'       => $applied ?? Reports::filtersFrom([], $viewer),
            'isAdmin'       => $isAdmin,
            'layout'        => $isAdmin ? 'layouts.admin_sidebar' : 'layouts.department_head_sidebar',
            'scopeProblem'  => Reports::scopeProblem($viewer),
            'categoryCards' => Reports::catalogueCards(),
            'departments'   => $isAdmin ? Reports::departmentOptions() : [],
            'viewer'        => $viewer,
            'endpoints'     => [
                'page'   => $this->url($viewer, 'page'),
                'apply'   => $this->url($viewer, 'generate'),
                'export' => $applied ? $this->url($viewer, 'export', Reports::queryFor($applied)) : null,
                'print'   => $applied ? $this->url($viewer, 'print', Reports::queryFor($applied)) : null,
            ],
        ]);
    }

    /* ──────────────────────── who is asking ──────────────────────── */

    /**
     * The signed-in reporter, or a refusal.
     *
     * The prefix decides which of the two reporting roles this request must be:
     * /admin/reports belongs to the Asset Management Office, /department-head/
     * reports to a Department Head. The other role is refused rather than
     * silently shown its own scope — an office account has no department of its
     * own, and a department head's page must never be reachable by it.
     */
    private function viewer(Request $request): User
    {
        $viewer = Auth::user();

        $allowed = $request->is('admin/*')
            ? Access::isAssetOffice($viewer)
            : Access::isDepartmentHead($viewer);

        abort_unless($allowed && Reports::canReadReports($viewer), 403, self::refusal());

        return $viewer;
    }

    public static function refusal(): string
    {
        return 'Reports are available to the Asset Management Office and to Department Heads.';
    }

    /** One URL per endpoint, all built from applied criteria only. */
    private function url(User $viewer, string $endpoint, array $query = []): string
    {
        $base = Access::isAssetOffice($viewer) ? '/admin/reports' : '/department-head/reports';

        $base .= match ($endpoint) {
            'generate' => '/generate',
            'export'   => '/export',
            'print'    => '/print',
            default    => '',
        };

        return $query ? $base . '?' . http_build_query($query) : $base;
    }
}
