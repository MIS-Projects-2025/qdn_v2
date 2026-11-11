<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Specs List and Violation List for the Maintenance pages. Neither exists
 * in qdn_db, so they are app-owned tables like defect_source. Both start
 * empty; QA / QMS fill them in on Maintenance. Skips a table that is
 * already there (e.g. created by hand).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('spec_list')) {
            Schema::create('spec_list', function (Blueprint $table) {
                $table->id();
                $table->string('spec_name');
            });
        }

        if (!Schema::hasTable('violation_list')) {
            Schema::create('violation_list', function (Blueprint $table) {
                $table->id();
                $table->string('violation_name');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('violation_list');
        Schema::dropIfExists('spec_list');
    }
};
