<?php

use System\Database\Migration;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->references('orders')->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->references('products')->onDelete('set null');
            $table->string('product_name', 150);
            $table->integer('quantity')->unsigned();
            $table->decimal('unit_price', 10, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
