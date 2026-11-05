<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Bulk write batch size
    |--------------------------------------------------------------------------
    |
    | Number of rows written per INSERT / upsert statement during a CSV import.
    | 500 rows x ~11 columns stays far below PostgreSQL's 65,535 bind-parameter
    | ceiling while keeping the number of network round trips tiny.
    |
    */

    'chunk_size' => (int) env('IMPORT_CHUNK_SIZE', 500),

    /*
    |--------------------------------------------------------------------------
    | Maximum rows accepted in one file
    |--------------------------------------------------------------------------
    |
    | A file is written inside a single transaction, so an unbounded file could
    | outlive the 300 s queue job and be rolled back entirely. Set to 0 to
    | disable the check only together with chunked transactions.
    |
    */

    'max_rows' => (int) env('IMPORT_MAX_ROWS', 1000),

    /*
    |--------------------------------------------------------------------------
    | Recalculate exam attendance after a bulk student import
    |--------------------------------------------------------------------------
    |
    | The single-row `StudentService::create()` path recalculates
    | `expected_attempts` for every active exam via the StudentProfile model
    | event. A bulk import bypasses Eloquent and performs the same recalculation
    | once per distinct (class level, class arm) pair instead, which is what the
    | import path did before - only without the per-row cost.
    |
    */

    'recalculate_exam_attendance' => (bool) env('IMPORT_RECALCULATE_EXAMS', true),

    /*
    |--------------------------------------------------------------------------
    | Interactively-created rows per statement in the fallback path
    |--------------------------------------------------------------------------
    |
    | When a batched statement fails (for example a constraint that the bulk
    | pre-flight could not predict) the importer retries that chunk row by row
    | so a single bad row cannot discard the rest of the batch.
    |
    */

    'fallback_row_by_row' => (bool) env('IMPORT_FALLBACK_ROW_BY_ROW', true),

    /*
    |--------------------------------------------------------------------------
    | Progress stream (server-sent events)
    |--------------------------------------------------------------------------
    |
    | `stream_interval_ms` is how often the stream re-reads the progress payload
    | from the cache; `stream_timeout` caps how long one connection is held open
    | so a php-fpm worker is never parked on a dead job.
    |
    */

    'stream_interval_ms' => (int) env('IMPORT_STREAM_INTERVAL_MS', 700),

    'stream_timeout' => (int) env('IMPORT_STREAM_TIMEOUT', 60),

];
