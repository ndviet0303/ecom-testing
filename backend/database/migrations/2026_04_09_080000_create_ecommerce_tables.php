<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->index();
            $table->string('brand')->nullable();
            $table->unsignedBigInteger('base_price_cents');
            $table->unsignedBigInteger('sale_price_cents')->nullable();
            $table->json('specs')->nullable();
            $table->timestamps();
        });

        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('on_hand')->default(0);
            $table->unsignedInteger('reserved')->default(0);
            $table->timestamps();
            $table->unique('product_id');
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('guest_token')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_cents');
            $table->timestamps();
            $table->unique(['cart_id', 'product_id']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->unsignedBigInteger('min_subtotal_cents')->default(0);
            $table->unsignedBigInteger('discount_cents');
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->index();
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('shipping_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->string('coupon_code')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 64);
            $table->string('name');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('products');
    }
};
