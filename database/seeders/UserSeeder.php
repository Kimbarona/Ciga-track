<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Super Admin', 'email' => 'superadmin@example.com', 'role' => Role::SuperAdmin],
            ['name' => 'Admin', 'email' => 'admin@example.com', 'role' => Role::Admin],
            ['name' => 'Staff', 'email' => 'staff@example.com', 'role' => Role::Staff],
        ];

        foreach ($users as $user) {
            User::query()->firstOrCreate(
                ['email' => $user['email']],
                [...$user, 'password' => 'password', 'is_active' => true, 'email_verified_at' => now()],
            );
        }
    }
}
