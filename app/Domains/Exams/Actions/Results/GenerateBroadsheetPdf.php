<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Results;

use App\Domains\Exams\Data\Output\Results\BroadsheetData;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Str;

final class GenerateBroadsheetPdf
{
    public function execute(BroadsheetData $broadsheet): DomPdf
    {
        return Pdf::loadView('pdf.broadsheet', [
            'broadsheet' => $broadsheet,
            'schoolName' => tenant('name') ?? 'EduCBT',
        ])->setPaper('a4', 'landscape');
    }

    public function filename(BroadsheetData $broadsheet): string
    {
        return Str::slug('broadsheet-' . $broadsheet->meta->class_level_id . '-' . $broadsheet->meta->term_id) . '.pdf';
    }
}
