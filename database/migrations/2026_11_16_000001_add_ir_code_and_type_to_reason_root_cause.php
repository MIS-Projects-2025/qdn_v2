<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IR Code and Root Cause Type on the Root Cause List (reason_root_cause),
 * edited on Maintenance > Root Cause List. Skips a column that already
 * exists (e.g. if qdn_db's reason_root_cause already had it).
 *
 * NOTE: `php artisan qdn:copy-lookup-tables --fresh` drops and recreates
 * reason_root_cause from qdn_db -- run `php artisan migrate` again after
 * it if qdn_db's table doesn't have these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reason_root_cause')) {
            return;
        }

        Schema::table('reason_root_cause', function (Blueprint $table) {
            if (!Schema::hasColumn('reason_root_cause', 'ir_code')) {
                $table->string('ir_code', 50)->nullable();
            }
            if (!Schema::hasColumn('reason_root_cause', 'root_cause_type')) {
                $table->string('root_cause_type', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Leave the columns: they may hold data entered on the Maintenance page.
    }
};
