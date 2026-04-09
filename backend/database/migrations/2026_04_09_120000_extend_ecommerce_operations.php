<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('recipient_name');
            $table->string('phone', 32);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('ward')->nullable();
            $table->string('district');
            $table->string('province');
            $table->string('postal_code', 16)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->unsignedBigInteger('rate_per_kg_cents');
            $table->unsignedBigInteger('free_shipping_from_subtotal_cents')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_address_id')->nullable()->after('user_id')->constrained('addresses')->nullOnDelete();
            $table->json('shipping_address_snapshot')->nullable()->after('shipping_address_id');
            $table->unsignedInteger('weight_grams')->default(0)->after('shipping_address_snapshot');
            $table->foreignId('shipping_zone_id')->nullable()->after('weight_grams')->constrained('shipping_zones')->nullOnDelete();
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('processed_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id', 191);
            $table->string('checksum', 64)->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('processed_webhook_events');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('wishlists');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['shipping_address_id']);
            $table->dropForeign(['shipping_zone_id']);
            $table->dropColumn(['shipping_address_id', 'shipping_address_snapshot', 'weight_grams', 'shipping_zone_id']);
        });

        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('addresses');

        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
