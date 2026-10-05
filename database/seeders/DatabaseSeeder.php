<?php

namespace Database\Seeders;

use System\Database\DB;
use System\Security\Hash;

/**
 * Default database seeder for Bongo PHP: run with `php bongo db:seed`.
 */
class DatabaseSeeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            'name'              => 'Admin',
            'email'             => 'admin@example.com',
            'password'          => Hash::make('password'),
            'role'              => 'admin',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        DB::table('users')->insert([
            'name'              => 'Standard User',
            'email'             => 'user@example.com',
            'password'          => Hash::make('password'),
            'role'              => 'user',
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
