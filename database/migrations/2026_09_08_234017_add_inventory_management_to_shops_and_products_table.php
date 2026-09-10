<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->boolean('enable_inventory_management')->default(false)->after('currency');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('reorder_level')->default(10)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('reorder_level');
        });

        Schema::table('shops', function (Blueprint $table): void {
            $table->dropColumn('enable_inventory_management');
        });
    }
};
