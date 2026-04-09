<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('customer')->after('password');
        });

        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });

        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('reason')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('staff_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        DB::table('users')->where('role', 'admin')->update(['is_admin' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
