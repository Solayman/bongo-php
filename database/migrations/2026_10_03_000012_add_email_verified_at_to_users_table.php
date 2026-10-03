<?php

use System\Database\DB;
use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('email_verified_at')->nullable();
        });
        // Accounts that already exist count as verified, so nobody is locked out
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => date('Y-m-d H:i:s')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
