<?php

use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->references('categories')->onDelete('set null');
            $table->string('name', 150);
            $table->string('sku', 50)->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('stock')->unsigned()->default(0);
            $table->enum('status', ['active', 'draft'])->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
