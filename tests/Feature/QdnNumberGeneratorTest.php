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
        Schema::create((new \App\Models\AnalogCalendar)->getTable(), function (Blueprint $t) {
            $t->id(); $t->date('cal_date'); $t->string('cal_year'); $t->unsignedInteger('cal_workweek')->default(1);
        });
    }

    public function test_format_is_two_digit_year_and_seven_digit_sequence(): void
    {
        DB::table((new \App\Models\AnalogCalendar)->getTable())->insert(['cal_date' => now()->toDateString(), 'cal_year' => '2027']);

        $this->assertSame('27-0000001', QdnNumberGenerator::next());

        DB::table('qdns')->insert(['qdn_no' => '27-0000041', 'status' => 'issued']);
        DB::table('qdns')->insert(['qdn_no' => '2027-0000999', 'status' => 'issued']); // old format is ignored
        $this->assertSame('27-0000042', QdnNumberGenerator::next());
    }

    public function test_falls_back_to_calendar_year(): void
    {
        $this->assertSame(now()->format('y') . '-0000001', QdnNumberGenerator::next());
    }
}
