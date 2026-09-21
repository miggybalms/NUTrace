<?php

$supabaseUrl    = env('SUPABASE_URL');
$supabaseBucket = env('SUPABASE_BUCKET', 'assets');
$supabaseKey    = env('SUPABASE_SERVICE_ROLE_KEY') ?: env('SUPABASE_ANON_KEY');

// Supabase Storage holds every uploaded file (profile photos, asset photos,
// request attachments and QR images) as soon as the project URL and a key are
// configured. Without them the app keeps using the local public disk, which is
// what development and the test suite rely on.
$useSupabase = ! empty($supabaseUrl) && ! empty($supabaseKey);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', $useSupabase ? 'public' : 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3", "supabase"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
         | The app's media disk. Always referenced as the "public" disk by the
         | application code, but backed by Supabase Storage once configured so
         | uploads survive deployments and are visible to every device.
         */
        'public' => $useSupabase ? [
            'driver' => 'supabase',
            'bucket' => $supabaseBucket,
            'endpoint' => rtrim((string) $supabaseUrl, '/'),
            'key' => $supabaseKey,
            'url' => rtrim((string) $supabaseUrl, '/').'/storage/v1/object/public/'.$supabaseBucket,
            'timeout' => (int) env('SUPABASE_TIMEOUT', 30),
            'throw' => false,
            'report' => false,
        ] : [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Friendly alias for the same disk, for anything that prefers to name
        // the backend explicitly.
        'supabase' => [
            'driver' => 'supabase',
            'bucket' => $supabaseBucket,
            'endpoint' => rtrim((string) $supabaseUrl, '/'),
            'key' => $supabaseKey,
            'url' => rtrim((string) $supabaseUrl, '/').'/storage/v1/object/public/'.$supabaseBucket,
            'timeout' => (int) env('SUPABASE_TIMEOUT', 30),
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
