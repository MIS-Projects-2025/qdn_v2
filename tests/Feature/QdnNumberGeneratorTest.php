<?php

namespace Tests\Feature;

use App\Services\QdnNumberGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QdnNumberGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DashboardTest::makeQdnTables();
        if (! Schema::hasTable('telford_calendar')) {
            Schema::create('telford_calendar', function (Blueprint $t) {
                $t->increments('id');
                $t->string('cal_year', 45)->nullable();
                $t->smallInteger('cal_quarter')->nullable();
                $t->smallInteger('cal_month')->nullable();
                $t->integer('cal_workweek')->nullable();
                $t->date('cal_date')->nullable()->unique();
            });
        }
    }

    public function test_year_comes_from_telford_calendar(): void
    {
        DB::table('telford_calendar')->insert(['cal_date' => now()->toDateString(), 'cal_year' => '2031', 'cal_workweek' => 1]);

        $this->assertSame('31-0000001', QdnNumberGenerator::next());

        DB::table('qdns')->insert(['qdn_no' => '31-0000041', 'status' => 'issued']);
        DB::table('qdns')->insert(['qdn_no' => '2031-0000999', 'status' => 'issued']); // old format is ignored
        DB::table('qdns')->insert(['qdn_no' => '30-0000500', 'status' => 'issued']);   // previous year is ignored
        $this->assertSame('31-0000042', QdnNumberGenerator::next());
    }

    public function test_analog_calendar_is_not_used(): void
    {
        $analog = (new \App\Models\AnalogCalendar)->getTable();
        if (! Schema::hasTable($analog)) {
            Schema::create($analog, function (Blueprint $t) {
                $t->id(); $t->date('cal_date'); $t->string('cal_year'); $t->unsignedInteger('cal_workweek')->default(1);
            });
        }
        DB::table($analog)->insert(['cal_date' => now()->toDateString(), 'cal_year' => '2040']);
        DB::table('telford_calendar')->insert(['cal_date' => now()->toDateString(), 'cal_year' => '2031', 'cal_workweek' => 1]);

        $this->assertSame('31-0000001', QdnNumberGenerator::next());
    }

    public function test_falls_back_to_regular_year_when_today_missing(): void
    {
        $this->assertSame(now()->format('y') . '-0000001', QdnNumberGenerator::next());
    }
}
