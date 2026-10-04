<?php

namespace System\Database;

/**
 * Base class for files in database/migrations/. Each file returns an instance:
 *
 *   return new class extends Migration {
 *       public function up(): void   { Schema::create(...); }
 *       public function down(): void { Schema::dropIfExists(...); }
 *   };
 */
abstract class Migration
{
    abstract public function up(): void;

    abstract public function down(): void;
}
