<?php

namespace App\Enums;

enum ExamType: string
{
    case Exam = 'exam';
    case Test = 'test';
    case Quiz = 'quiz';
    case Mock = 'mock';
    case Ca = 'ca';
}
