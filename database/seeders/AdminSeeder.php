<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Default admin — change credentials after first login
        User::firstOrCreate(
            ['email' => 'admin@fleettrack.local'],
            [
                'name'      => 'System Admin',
                'password'  => Hash::make('Admin@1234'),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );

        $this->command->info('Admin seeded: admin@fleettrack.local / Admin@1234');
        $this->command->warn('⚠  Change the default password after first login!');
    }
}
