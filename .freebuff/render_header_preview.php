<?php

/**
 * Render the shared admin header on its own so the profile menu can be clicked
 * in a browser without signing in to the live site. Writes a throwaway HTML
 * preview; it is not part of the app.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = (new App\Models\User())->forceFill([
    'id'         => 1,
    'email'      => 'alex@nu-lipa.edu.ph',
    'role'       => 'Admin',
    'department_id' => null,
]);

Illuminate\Support\Facades\Auth::setUser($user);

$header = view('admin.partials.header', [
    'adminHeaderPage'  => 'dashboard',
    'adminHeaderTitle' => 'Dashboard',
    'adminHeaderIcon'  => 'ri-dashboard-line',
    'adminHeaderBadge' => 'Admin',
])->render();

$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin header preview</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet"/>
<style>
    :root{
        --navy-950:#0A1830; --navy-900:#0F2143; --navy-800:#15305B; --navy-700:#1D3F73;
        --gold-500:#C9A227; --gold-600:#A8841E; --gold-ink:#7E5E0E; --gold-100:#F3E7C4;
        --paper:#F3EEE0; --paper-2:#EAE2C9; --bronze:#B4791E; --bronze-dark:#8F5F16; --bronze-tint:#FBF1DE;
        --brick:#A23B32; --brick-dark:#7E2E27; --brick-tint:#F7E9E6;
        --ink-900:#1A2233; --ink-600:#4B5468; --ink-400:#5C6474; --line:#DED2AE;
        --forest:#2F7A4D; --forest-dark:#245C3B; --forest-tint:#EAF4EE;
    }
    body{ margin:0; background:var(--paper); font-family:ui-sans-serif,system-ui,sans-serif; }
    .font-display{ font-family:Georgia,serif; }
</style>
</head>
<body>
{$header}
<div style="padding:2rem;color:var(--ink-600)">
    <p>Preview harness: this is the shared admin header exactly as a page includes it.</p>
</div>
<script>
    // Stand-in for the dashboard's alert dropdowns, so the mutual-exclusion hook
    // in the profile menu can be observed here too.
    window.closeAllAlertDropdowns = function () {
        document.querySelectorAll('[data-preview-alert]').forEach(el => el.classList.add('hidden'));
    };
</script>
</body>
</html>
HTML;

file_put_contents(__DIR__ . '/admin_header_preview.html', $html);

echo 'written: ' . __DIR__ . '/admin_header_preview.html' . PHP_EOL;
