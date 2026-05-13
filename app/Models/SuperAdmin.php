<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class SuperAdmin extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids;
    
    protected $table = 'super_admins';
    
    public $incrementing = false;
    
    protected $guarded = ['id'];
    
    protected $hidden = ['password'];
    
    protected function casts(): array
    {
        return [
            'password'       => 'hashed',
            'is_active'      => 'boolean',
            'last_login_at'  => 'datetime'
        ];
    }
}
