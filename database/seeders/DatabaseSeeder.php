<?php
namespace Database\Seeders;

use System\Database\DB;
use System\Security\Hash;

/**
 * Seed data: run with `php bongo db:seed`.
 * Creates one user so you can sign in. Change the password after the first login.
 */
class DatabaseSeeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            'name'     => 'Admin',
            'email'    => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
    }
}
