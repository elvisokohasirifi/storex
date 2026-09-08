<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['product_categories', 'brands'] as $table) {
            Schema::create($table, function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('shop_id')->constrained()->cascadeOnDelete();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->timestamps();
                $table->unique(['shop_id', 'name']);
            });
        }
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignUuid('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('brand_id');
        });
        Schema::dropIfExists('brands');
        Schema::dropIfExists('product_categories');
    }
};
