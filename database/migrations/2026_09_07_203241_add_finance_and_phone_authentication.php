<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique();
            $table->string('pin')->nullable();
            $table->string('google_id')->nullable()->unique();
        });
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained();
            $table->foreignUuid('created_by')->constrained('users');
            $table->string('type');
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->date('occurred_on');
            $table->string('receipt')->nullable();
            $table->text('notes')->nullable();
            $table->index(['shop_id', 'occurred_on']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['phone', 'pin', 'google_id']));
    }
};
