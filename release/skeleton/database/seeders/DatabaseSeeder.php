<?php

namespace Database\Seeders;

use System\Database\DB;

class DatabaseSeeder
{
    /** php bongo db:seed (and every php bongo test run) */
    public function run(): void
    {
        // DB::table('users')->insert(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => \System\Security\Hash::make('password'), 'role' => 'admin']);
    }
}
