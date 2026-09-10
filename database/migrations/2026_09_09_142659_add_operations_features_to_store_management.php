<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 50);
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->unique(['shop_id', 'phone']);
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['shop_id', 'name']);
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('status')->default('draft')->index();
            $table->unsignedBigInteger('total_cost')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('till_shifts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('open')->index();
            $table->unsignedBigInteger('opening_cash')->default(0);
            $table->unsignedBigInteger('expected_cash')->default(0);
            $table->unsignedBigInteger('actual_cash')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'status']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->integer('quantity')->nullable();
            $table->unsignedInteger('reorder_level')->default(10);
            $table->timestamps();
            $table->unique(['product_id', 'name']);
        });

        Schema::create('stock_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('inventory_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('quantity_received');
            $table->unsignedInteger('quantity_remaining');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'expiry_date']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 120)->index();
            $table->string('subject_type')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignUuid('customer_id')->nullable()->after('seller_id')->constrained()->nullOnDelete();
            $table->foreignUuid('till_shift_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->string('receipt_number')->nullable()->unique()->after('reference');
            $table->string('payment_method')->default('paystack')->after('channel');
            $table->timestamp('cancelled_at')->nullable()->after('paid_at');
            $table->timestamp('refunded_at')->nullable()->after('cancelled_at');
            $table->text('status_reason')->nullable()->after('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('till_shift_id');
            $table->dropUnique(['receipt_number']);
            $table->dropColumn(['receipt_number', 'payment_method', 'cancelled_at', 'refunded_at', 'status_reason']);
        });

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('stock_batches');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('till_shifts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
    }
};
