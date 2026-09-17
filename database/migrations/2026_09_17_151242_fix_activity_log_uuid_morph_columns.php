<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = config('activitylog.database_connection');
        $tableName = config('activitylog.table_name');

        Schema::connection($connection)->table($tableName, function (Blueprint $table) use ($connection): void {
            if (Schema::connection($connection)->hasIndex($table->getTable(), 'subject')) {
                $table->dropIndex('subject');
            }

            if (Schema::connection($connection)->hasIndex($table->getTable(), 'causer')) {
                $table->dropIndex('causer');
            }
        });

        Schema::connection($connection)->table($tableName, function (Blueprint $table): void {
            $table->uuid('subject_id')->nullable()->change();
            $table->uuid('causer_id')->nullable()->change();
        });

        Schema::connection($connection)->table($tableName, function (Blueprint $table): void {
            $table->index(['subject_type', 'subject_id'], 'subject');
            $table->index(['causer_type', 'causer_id'], 'causer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('activitylog.database_connection');
        $tableName = config('activitylog.table_name');

        Schema::connection($connection)->table($tableName, function (Blueprint $table) use ($connection): void {
            if (Schema::connection($connection)->hasIndex($table->getTable(), 'subject')) {
                $table->dropIndex('subject');
            }

            if (Schema::connection($connection)->hasIndex($table->getTable(), 'causer')) {
                $table->dropIndex('causer');
            }
        });

        Schema::connection($connection)->table($tableName, function (Blueprint $table): void {
            $table->unsignedBigInteger('subject_id')->nullable()->change();
            $table->unsignedBigInteger('causer_id')->nullable()->change();
        });

        Schema::connection($connection)->table($tableName, function (Blueprint $table): void {
            $table->index(['subject_type', 'subject_id'], 'subject');
            $table->index(['causer_type', 'causer_id'], 'causer');
        });
    }
};
