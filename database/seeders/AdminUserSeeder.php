<?php

namespace Database\Seeders;

use App\Models\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SuperAdmin::updateOrCreate(
            ["email" => "superadmin@example.com"],
            [
                "name" => "Super Admin",
                "email" => "superadmin@example.com",
                "password" => Hash::make("password"),
            ],
        );
    }
}
