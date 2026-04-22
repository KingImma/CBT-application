<?php

declare(strict_types=1);

namespace App\Enums;

enum SchoolType: string
{
    case PRIMARY = 'Primary School';
    case SECONDARY = 'Secondary School';
    case MIXED = 'Mixed';
}