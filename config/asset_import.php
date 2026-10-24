<?php

/**
 * Limits for the existing-asset inventory import.
 *
 * A fixed-asset registry is read in windows: each request checks or imports a
 * bounded number of rows and hands back the byte offset to continue from, so a
 * large workbook never has to fit in one request. The numbers below are the
 * size of one window, not a limit on the whole file.
 *
 * The upload itself has no size limit: the Import page sends the file in parts
 * of `part_bytes`, so PHP's upload_max_filesize / post_max_size never decide
 * how big a registry may be. `part_bytes` is also capped at whatever this
 * server will accept in one request, so the default works unmodified.
 */
return [

    /*
    | Size of one uploaded slice. The page splits a file into slices of this
    | size and the server joins them, so this is a request size, not a limit on
    | the workbook. Capped at PHP's own upload limits when they are lower.
    */

    'part_bytes' => ((int) env('ASSET_IMPORT_PART_MB', 8)) * 1024 * 1024,

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
