<?php

namespace App\Enums;

enum QuestionType: string
{
    case Mcq_Single   = 'mcq_single';
    case Mcq_Multi    = 'mcq_multi';
    case True_False   = 'true_false';
    case Fill_Blank   = 'fill_blank';
    case Short_Answer = 'short_answer';
    case Essay        = 'essay';
    case Matching     = 'matching';
    case Ordering     = 'ordering';
}
