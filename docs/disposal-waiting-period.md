# The three-year disposal waiting period

An approved disposal is not a disposed asset. Between the Asset Management
Office's approval and the act of disposing, NU Trace now waits
`config('disposal.hold_years')` years (default 3) and then *tells* the office the
request is eligible. It never disposes of anything by itself.

## The clock

| Fact | Where it lives |
| --- | --- |
| When the disposal was approved | `requests.approved_at` — written once, inside the approval transaction |
| When it becomes eligible | derived: `approved_at + hold_years` — not stored |
| Whether the office has been told | `requests.disposal_eligibility_notified_at` |

`approved_at` is set only by `POST /admin/requests/{id}/approve`, only for a
`Disposal` request, and only when the request is still `Pending` (the route
refuses anything else). Editing, saving, printing or signing the official form
cannot touch it, so the clock cannot be restarted from the form.

The rule is enforced in [app/Support/DisposalEligibility.php](../app/Support/DisposalEligibility.php),
which every screen shares.

Calendar arithmetic is Carbon's `addYears()`, the same library the rest of the
application uses for dates. A request approved on **29 February 2028** is
eligible on **1 March 2031**: three years later is not a leap year, so the
anniversary overflows to the following day rather than silently becoming
28 February.

## What the waiting period does and does not restrict

| Step | Inside the waiting period |
| --- | --- |
| Employee/department head submits the request | allowed |
| Admin approves (form generated) | allowed — this is what starts the clock |
| Office completes the form | **allowed** |
| Form printed, print count recorded | **allowed** |
| Signed copy recorded, scan uploaded | **allowed** |
| **Finalization (asset becomes `Disposal`)** | **refused** |

The refusal comes from the service, not the page:

> This disposal cannot be finalized yet: the three-year waiting period for
> disposal request #12 has not ended. It was approved on October 25, 2026
> 9:30 AM, so final disposal becomes available on October 25, 2029 9:30 AM.
> Nothing was changed.

## The notification

`php artisan disposal:check-eligibility` runs daily at 02:15 (registered in
[bootstrap/app.php](../bootstrap/app.php)). It:

1. selects disposal requests that are `Approved`, have an `approved_at`, have no
   `disposal_eligibility_notified_at`, and whose approval is older than the
   holding period;
2. re-checks the exact eligibility boundary in PHP (the SQL cutoff is generous);
3. notifies every active `Admin` through the existing `notifications` table,
   using `Reviewers::notifyOnce()` so an account cannot be told twice;
4. stamps `disposal_eligibility_notified_at` and writes **one** audit entry for
   the request;
5. never writes a disposal record, never changes an asset status, never
   finalizes.

Options: `--request=ID` (one request, for testing — the eligibility date is still
respected) and `--dry-run` (print what would be sent, write nothing).

The job takes no shared lock. It is idempotent and de-duplicated per account, and
the deployment's `cache_locks` table is not provisioned, so a lock would abort the
run rather than protect it.

## Other ways to dispose

The official form is not the only screen that can retire an asset. Each of these
now goes through the same `DisposalEligibility::refusalForAssets()` check, but
only when the asset is covered by an approved disposal request that is still
inside its waiting period — they are otherwise unchanged:

- `POST /admin/disposals/create` (repair → disposal)
- `POST /admin/api/assets/{id}/evaluate` with `proceed_disposal` (lifespan review)
- `POST /admin/pullout/{id}/dispose` (pullout → disposal)
- `POST /admin/disposal/record` (legacy untied disposal record)
- `POST /admin/api/assets/{id}/status` with `status=Disposal` (status card)
- `POST /admin/replacements/{id}/status` → `Complete` (retires the old asset)

Paths that have **no** approved disposal request behind them get no invented
approval date and keep working exactly as before; the rule only stops an approved
request being walked around.

## Legacy requests

Requests approved before this feature have `approved_at = NULL`. They are shown
as *“Approval date not recorded”*, are **not** given a synthetic start date, and
are **not** blocked from finalization — blocking them would strand records the
office is still working through.

If the office wants the waiting period applied to them, the honest sources are,
in order: `disposal_forms.prepared_at` (same transaction as the approval for
requests approved after forms existed), then `requests.updated_at`. Any backfill
must be an explicit, reviewed `UPDATE`; nothing here does it automatically.

## Running it

```bash
php artisan migrate                 # adds the two nullable columns
php artisan disposal:check-eligibility --dry-run   # see what would be sent
php artisan disposal:check-eligibility             # send it
php artisan schedule:list           # both daily jobs should be listed
```

Railway needs a process running `php artisan schedule:work` (or a cron hitting
`php artisan schedule:run` every minute) for either scheduled job to fire.

## Known limits

- Reaching the eligibility date does not dispose of anything; the office must
  still confirm, which is the point.
- The rule is a policy setting, not a legal determination: lowering
  `DISPOSAL_HOLD_YEARS` immediately makes older approvals eligible.
- Duplicate *requests* for one asset are still possible; finalization now refuses
  to write a second disposal record for an asset and names the existing one, but
  preventing the duplicate request at submission was deliberately not added
  (it would be a new business rule).
- `AssetAgeReview` still lists assets by acquisition age, so an approved asset
  keeps appearing there until it is disposed of.
