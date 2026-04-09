<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_url', 500)->nullable()->after('brand');
            $table->unsignedInteger('warranty_months')->default(0)->after('specs');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('serial_number', 128)->nullable()->index()->after('quantity');
            $table->timestamp('warranty_expires_at')->nullable()->after('serial_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['serial_number', 'warranty_expires_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'warranty_months']);
        });
    }
};
