<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * qdn_new_db.telford_calendar -- the TELFORD work-week calendar, one row
 * per day, maintained on Maintenance > TELFORD Calendar (QA / QMS).
 * Same columns as analog_calendar. cal_date is unique (one row per day).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('telford_calendar')) {
            return;
        }

        Schema::create('telford_calendar', function (Blueprint $table) {
            $table->increments('id');
            $table->string('cal_year', 45)->nullable();
            $table->smallInteger('cal_quarter')->nullable();
            $table->smallInteger('cal_month')->nullable();
            $table->integer('cal_workweek')->nullable();
            $table->date('cal_date')->nullable()->unique();
            $table->string('created_by_emp_num', 45)->nullable();
            $table->string('created_by_emp_name', 45)->nullable();
            $table->dateTime('date_created')->nullable();
            $table->string('updated_by_emp_num', 45)->nullable();
            $table->string('updated_by_emp_name', 45)->nullable();
            $table->dateTime('date_updated')->nullable();

            $table->index(['cal_year', 'cal_workweek']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telford_calendar');
    }
};
