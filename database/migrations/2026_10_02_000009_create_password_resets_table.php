<?php

use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One pending reset per email; the token is stored hashed (sha256), never in plain text
        Schema::create('password_resets', function (Blueprint $table) {
            $table->string('email', 150)->unique();
            $table->char('token_hash', 64);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_resets');
    }
};
