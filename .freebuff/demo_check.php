<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo 'connection: ' . config('database.default') . ' -> ' . config('database.connections.sqlite.database') . PHP_EOL;
echo 'users: ' . DB::table('users')->count() . PHP_EOL;

use Illuminate\Support\Facades\Auth;

echo 'attempt: ' . (Auth::attempt(['email' => 'amo@nu-lipa.edu.ph', 'password' => 'DemoPass!2026']) ? 'ok' : 'FAIL') . PHP_EOL;
echo 'guard user: ' . (Auth::id() ?? 'none') . PHP_EOL;

foreach (DB::table('users')->orderBy('id')->get() as $user) {
    echo $user->id . ' | ' . $user->email . ' | ' . $user->role . ' | ' . $user->status
        . ' | hash: ' . (Hash::check('DemoPass!2026', (string) $user->password) ? 'ok' : 'FAIL')
        . ' | len: ' . strlen((string) $user->password)
        . PHP_EOL;
}
