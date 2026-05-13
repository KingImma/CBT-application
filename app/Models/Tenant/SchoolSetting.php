<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SchoolSetting extends Model
{
    use HasUuids;

    protected $guarded = ['id'];
}