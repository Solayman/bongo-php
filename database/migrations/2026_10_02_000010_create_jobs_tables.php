<?php

use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Queued jobs waiting for `php bongo queue:work`
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue', 100)->default('default');
            $table->longText('payload');
            $table->tinyInteger('attempts')->unsigned()->default(0);
            $table->integer('available_at')->unsigned();
            $table->integer('reserved_at')->unsigned()->nullable();
            $table->integer('created_at')->unsigned();
            $table->index(['queue', 'available_at']);
        });

        // Jobs that failed every attempt
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue', 100);
            $table->longText('payload');
            $table->text('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
    }
};
