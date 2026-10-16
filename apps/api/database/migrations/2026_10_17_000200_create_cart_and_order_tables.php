<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carts, coupons and orders (Milestone 3 step 2, DATABASE_DESIGN §2.3). Orders and their items are
 * financial records: never deleted (RESTRICT everywhere), and prices are snapshotted on the item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->char('guest_token_hash', 64)->nullable()->unique();
            $table->char('currency', 3);
            $table->string('coupon_code', 40)->nullable();
            $table->timestamps();
            $table->index('updated_at');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
            $table->unique(['cart_id', 'product_id']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique(); // stored upper-case; matched case-insensitively
            $table->string('description', 200)->nullable();
            $table->string('type', 10); // percent, fixed
            $table->unsignedBigInteger('value'); // percent: 1–100; fixed: minor units of `currency`
            $table->char('currency', 3)->nullable(); // fixed coupons only
            $table->unsignedBigInteger('min_subtotal')->nullable(); // minor units, in the order currency
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_user')->nullable();
            $table->json('applies_to')->nullable(); // product ids; null = every product
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('number', 30)->unique(); // human reference, e.g. IO-2026-000123
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->foreignId('coupon_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('coupon_code', 40)->nullable();
            $table->string('billing_name', 120);
            $table->string('billing_email', 254);
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 30)->nullable(); // customer, expired, admin
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->json('product_snapshot'); // title, type, slug, version at the time of purchase
            $table->unsignedBigInteger('unit_amount');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('discount_amount');
            $table->timestamp('released_at')->nullable(); // order cancelled or expired: no longer counts
            $table->timestamps();
            $table->unique(['coupon_id', 'order_id']);
        });

        // Order numbers are sequential per year, assigned under a row lock.
        Schema::create('order_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_number_sequences');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
