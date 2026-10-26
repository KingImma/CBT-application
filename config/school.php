<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | School timezone
    |--------------------------------------------------------------------------
    |
    | The single business timezone the school's screens and timetables are
    | expressed in. Storage stays UTC (see config/app.php and
    | App\Shared\Casts\ToUtc); this value is only used where a UTC instant has
    | to be turned back into a local calendar day — e.g. counting how many
    | subjects a class already has on a given day.
    |
    | Multi-tenant deployments that serve schools in different zones should
    | move this onto the school settings rather than the environment.
    |
    */

    'timezone' => env('SCHOOL_TIMEZONE', 'Africa/Lagos'),

];