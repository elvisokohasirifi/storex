<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->string('momo_number', 50)->nullable()->after('paystack_public_key');
            $table->string('momo_account_name')->nullable()->after('momo_number');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropColumn(['momo_number', 'momo_account_name']);
        });
    }
};
