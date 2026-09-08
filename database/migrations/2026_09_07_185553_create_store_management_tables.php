<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->uuid('id')->change();
            $t->boolean('is_platform_admin')->default(false);
        });
        Schema::table('sessions', fn (Blueprint $t) => $t->uuid('user_id')->nullable()->change());
        Schema::create('shops', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('owner_id')->constrained('users');
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->string('logo')->nullable();
            $t->string('banner')->nullable();
            $t->string('location');
            $t->string('contacts');
            $t->string('email');
            $t->string('currency', 3)->default('GHS');
            $t->string('status')->default('pending')->index();
            $t->text('paystack_secret_key')->nullable();
            $t->text('paystack_public_key')->nullable();
            $t->timestamps();
        });
        Schema::create('shop_members', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('shop_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $t->string('role');
            $t->unique(['shop_id', 'user_id']);
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('shop_id')->constrained();
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('image')->nullable();
            $t->decimal('cost_price', 12, 2)->nullable();
            $t->decimal('selling_price', 12, 2);
            $t->unsignedInteger('quantity')->nullable();
            $t->string('barcode')->nullable();
            $t->string('sku')->nullable();
            $t->string('status')->default('pending');
            $t->unique(['shop_id', 'barcode']);
            $t->index(['shop_id', 'status']);
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('shop_id')->constrained();
            $t->foreignUuid('seller_id')->nullable()->constrained('users');
            $t->string('reference')->unique();
            $t->string('customer_name');
            $t->string('customer_email');
            $t->string('customer_phone')->nullable();
            $t->text('delivery_address')->nullable();
            $t->string('currency', 3);
            $t->unsignedBigInteger('total');
            $t->string('channel');
            $t->string('status')->default('pending');
            $t->text('payment_secret')->nullable();
            $t->text('payment_url')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->index(['shop_id', 'status']);
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('product_id')->constrained();
            $t->string('name');
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('unit_price');
            $t->unsignedBigInteger('unit_cost')->nullable();
            $t->boolean('tracks_stock');
            $t->timestamps();
        });
        Schema::create('moderation_logs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('actor_id')->constrained('users');
            $t->string('subject_type');
            $t->uuid('subject_id');
            $t->string('status');
            $t->text('reason')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['moderation_logs', 'order_items', 'orders', 'products', 'shop_members', 'shops'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_platform_admin'));
    }
};
