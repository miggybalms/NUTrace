<?php

/*
|--------------------------------------------------------------------------
| Disposal policy
|--------------------------------------------------------------------------
|
| The only number here is the proposed three-year service-life mark used by
| the Service-life review tab on the Disposal page.
|
| It is ADVISORY. Nothing in NU Trace approves a disposal, generates an
| authorization, or changes an asset's lifecycle status because of this age.
| The Asset Management Office decides, through a disposal request, whether an
| asset that old should actually be retired.
|
| The university has not confirmed three years as an enforced disposal rule,
| so keep this as a review prompt until that policy is settled. Raising it (or
| lowering it) re-files which assets appear on the review list; it never
| changes a disposal that has already been recorded.
|
*/

return [

    'age_review' => [

        /*
         | Proposed age, in years, at which an asset is offered for review.
         |
         | Read from the environment so a deployment can set the university's
         | confirmed policy without a code change. The default is the figure
         | from the recommendation, not a confirmed university rule.
         */
        'years' => env('DISPOSAL_AGE_REVIEW_YEARS', 3),

        /*
         | Whether the review list is shown at all. Turning it off hides the
         | tab; it does not touch any asset, request or disposal record.
         */
        'enabled' => (bool) env('DISPOSAL_AGE_REVIEW_ENABLED', true),

    ],

];
