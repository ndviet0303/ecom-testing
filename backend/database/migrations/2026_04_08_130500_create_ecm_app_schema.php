<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $schema = env('DB_SCHEMA', 'ecm_app');

        DB::statement(sprintf('CREATE SCHEMA IF NOT EXISTS "%s"', str_replace('"', '""', $schema)));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally keep schema to avoid dropping shared objects accidentally.
    }
};
