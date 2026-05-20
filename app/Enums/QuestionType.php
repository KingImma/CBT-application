<?php

namespace App\Enums;

enum QuestionType: string
{
    case McqSingle = 'mcq_single';
    case McqMulti = 'mcq_multi';
    case TrueFalse = 'true_false';
    case Fill_Blank = 'fill_blank';
    case Short_Answer = 'short_answer';
    case Essay = 'essay';
    case Matching = 'matching';
    case Ordering = 'ordering';
}
