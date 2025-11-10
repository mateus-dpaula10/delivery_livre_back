<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name'     => 'Mateus De Paula',
            'email'    => 'mateus.dpaula10@gmail.com',
            'password' => bcrypt('Mateus!20250417'),
            'role'     => 'admin'
        ]);
    }
}
