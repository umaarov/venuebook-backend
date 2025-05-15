<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DistrictSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Admin',
            'surname' => 'User',
            'username' => 'admin',
            'email' => 'test@admin.com',
            'password' => bcrypt('12345678'),
            'role' => 'admin',
        ]);

        User::factory()->create([
            'name' => 'Owner',
            'surname' => 'User',
            'username' => 'owner',
            'email' => 'test@owner.com',
            'password' => bcrypt('12345678'),
            'role' => 'owner',
        ]);

        User::factory()->create([
            'name' => 'User',
            'surname' => 'User',
            'username' => 'user',
            'email' => 'test@user.com',
            'password' => bcrypt('12345678'),
            'role' => 'user',
        ]);
    }
}
