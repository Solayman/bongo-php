<?php

use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ids (jti) of JWTs that were logged out or refreshed, kept until they expire
        Schema::create('jwt_revocations', function (Blueprint $table) {
            $table->id();
            $table->char('jti', 32)->unique();
            $table->timestamp('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jwt_revocations');
    }
};
