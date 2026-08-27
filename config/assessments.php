<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Schedule bulk-publish gate
    |--------------------------------------------------------------------------
    |
    | A schedule's results can be published once every materialised exam
    | under it is completed. When the flag below is enabled, exams that were
    | already individually published (status `published`) also satisfy that
    | gate — useful if the single-exam publish endpoint is ever folded into
    | the schedule flow.
    |
    */

    'schedule_publish_counts_published_exams_as_completed' => (bool) env(
        'ASSESSMENT_SCHEDULE_PUBLISH_COUNTS_PUBLISHED_EXAMS_AS_COMPLETED',
        false
    ),

];
