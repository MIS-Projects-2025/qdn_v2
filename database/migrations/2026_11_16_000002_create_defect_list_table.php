<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * defect_list, exactly as your existing table (Maintenance > Defect List).
 * Does nothing if the table already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('defect_list')) {
            return;
        }

        Schema::create('defect_list', function (Blueprint $table) {
            $table->increments('id');
            $table->string('defect_name', 45)->nullable();
            $table->longText('defect_description')->nullable();
            $table->string('created_by_emp_num', 45)->nullable();
            $table->string('created_by_emp_name', 45)->nullable();
            $table->dateTime('date_created')->nullable();
            $table->string('updated_by_emp_num', 45)->nullable();
            $table->string('update_by_emp_name', 45)->nullable();
            $table->dateTime('date_update')->nullable();
        });
    }

    public function down(): void
    {
        // Keep the table: it holds data maintained on the Maintenance page.
    }
};
