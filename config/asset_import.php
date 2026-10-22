<?php

/**
 * Limits for the existing-asset inventory import.
 *
 * A fixed-asset registry is read in windows: each request checks or imports a
 * bounded number of rows and hands back the byte offset to continue from, so a
 * large workbook never has to fit in one request. The numbers below are the
 * size of one window, not a limit on the whole file.
 *
 * `max_bytes` still matters, because the file has to be uploaded before it can
 * be streamed. PHP's own upload_max_filesize / post_max_size must be at least
 * this large (see the Import page's note, and .env.example); when it is not,
 * PHP discards the upload before Laravel sees it and the page says so.
 */
return [

    /* Largest upload accepted, in bytes. 0 disables the check (not advised). */
    'max_bytes' => ((int) env('ASSET_IMPORT_MAX_MB', 200)) * 1024 * 1024,

    /* Rows checked per request while reviewing a file. */
    'check_rows' => (int) env('ASSET_IMPORT_CHECK_ROWS', 400),

    /* Seconds of checking per request before the next window is requested. */
    'check_seconds' => (int) env('ASSET_IMPORT_CHECK_SECONDS', 8),

    /* Rows written to `assets` per request. */
    'import_rows' => (int) env('ASSET_IMPORT_IMPORT_ROWS', 100),

    /* Seconds of importing per request. */
    'import_seconds' => (int) env('ASSET_IMPORT_IMPORT_SECONDS', 12),

    /* Rows shown per page in the review table. */
    'preview_rows' => 50,

    /* How many codes the "print stickers" link after an import may name. */
    'label_batch_limit' => 200,

    /* A staging directory older than this is removed when a new import starts. */
    'stale_hours' => 48,
];
