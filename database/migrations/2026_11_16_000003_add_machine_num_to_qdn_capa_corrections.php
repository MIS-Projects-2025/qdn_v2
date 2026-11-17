<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Your existing qdn_capa_corrections table has machine_id but no
     * machine_num. QdnWorkflowController saves the selected machine's
     * machine_num on each CAPA Correction row (shown in the Machine No
     * column of the QDN detail card), so the insert failed with
     * "Unknown column 'machine_num'". Adds it only if it's missing.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('qdn_capa_corrections', 'machine_num')) {
            Schema::table('qdn_capa_corrections', function (Blueprint $table) {
                $table->string('machine_num')->nullable()->after('machine_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('qdn_capa_corrections', 'machine_num')) {
            Schema::table('qdn_capa_corrections', function (Blueprint $table) {
                $table->dropColumn('machine_num');
            });
        }
    }
};
