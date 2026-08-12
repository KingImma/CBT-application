<?php

declare(strict_types=1);

namespace App\Domains\Exams\Contracts;

use App\Domains\Exams\Data\MaterializeExamRequest;
use App\Models\Tenant\Exam;

interface MaterializesExamFromExternalSource
{
    public function execute(MaterializeExamRequest $request): Exam;
}