<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number', 48)->nullable()->unique()->after('id');
            $table->string('tracking_number', 128)->nullable()->after('coupon_code');
            $table->string('tracking_carrier', 64)->nullable()->after('tracking_number');
            $table->text('customer_note')->nullable()->after('tracking_carrier');
            $table->text('internal_note')->nullable()->after('customer_note');
            $table->timestamp('cancelled_at')->nullable()->after('internal_note');
            $table->string('cancel_reason', 500)->nullable()->after('cancelled_at');
        });

        foreach (DB::table('orders')->orderBy('id')->cursor() as $row) {
            DB::table('orders')->where('id', $row->id)->update([
                'order_number' => sprintf(
                    'ORD-%s-%08d',
                    date('Ymd', strtotime((string) $row->created_at)),
                    $row->id
                ),
            ]);
        }

        Schema::table('inventories', function (Blueprint $table) {
            $table->unsignedInteger('low_stock_threshold')->default(0)->after('reserved');
        });

        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedInteger('max_uses')->nullable()->after('expires_at');
            $table->unsignedInteger('max_uses_per_user')->nullable()->after('max_uses');
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['coupon_id', 'order_id']);
        });

        Schema::create('order_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('actor_type', 24)->default('system');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamp('subscribed_at')->useCurrent();
            $table->timestamp('unsubscribed_at')->nullable();
        });

        Schema::create('product_compare_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('product_recent_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at')->useCurrent();
            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_recent_views');
        Schema::dropIfExists('product_compare_items');
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('order_status_events');
        Schema::dropIfExists('coupon_redemptions');

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['max_uses', 'max_uses_per_user']);
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('low_stock_threshold');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_number',
                'tracking_number',
                'tracking_carrier',
                'customer_note',
                'internal_note',
                'cancelled_at',
                'cancel_reason',
            ]);
        });
    }
};
