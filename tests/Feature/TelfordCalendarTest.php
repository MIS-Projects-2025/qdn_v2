<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TelfordCalendarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DashboardTest::makeQdnTables();
        if (!\Illuminate\Support\Facades\Schema::hasTable('admin')) {
            \Illuminate\Support\Facades\Schema::create('admin', fn ($t) => [$t->id(), $t->string('emp_id')]);
        }
        (require base_path('database/migrations/2026_11_13_000001_create_telford_calendar_table.php'))->up();
        $this->withoutMiddleware(\App\Http\Middleware\AuthMiddleware::class);
        $this->withoutVite();
    }

    private function as(string $dept): static
    {
        return $this->withSession(['emp_data' => ['token' => 't', 'emp_id' => '27-0000009', 'emp_name' => 'Ana QA', 'emp_jobtitle' => 'QA Engineer', 'emp_dept' => $dept]]);
    }

    public function test_qa_can_add_edit_filter_and_delete(): void
    {
        $row = ['cal_date' => '2026-11-01', 'cal_year' => '2027', 'cal_quarter' => 1, 'cal_month' => 11, 'cal_workweek' => 1];
        $this->as('Quality Assurance')->post('/qdn/maintenance/calendar', $row)->assertSessionHasNoErrors();
        $saved = DB::table('telford_calendar')->first();
        $this->assertSame('27-0000009', $saved->created_by_emp_num);
        $this->assertSame('Ana QA', $saved->created_by_emp_name);
        $this->assertNotNull($saved->date_created);
        $this->assertNull($saved->date_updated);

        // Same date again is refused.
        $this->as('Quality Assurance')->post('/qdn/maintenance/calendar', $row)->assertSessionHasErrors('cal_date');
        // Bad values refused.
        $this->as('Quality Assurance')->post('/qdn/maintenance/calendar', ['cal_date' => '2026-11-02', 'cal_year' => '2027', 'cal_quarter' => 5, 'cal_month' => 13, 'cal_workweek' => 10000])
            ->assertSessionHasErrors(['cal_quarter', 'cal_month', 'cal_workweek']);

        $this->as('Quality Management System')->put("/qdn/maintenance/calendar/{$saved->id}", ['cal_workweek' => 2] + $row)->assertSessionHasNoErrors();
        $saved = DB::table('telford_calendar')->first();
        $this->assertSame(2, (int) $saved->cal_workweek);
        $this->assertSame('Ana QA', $saved->updated_by_emp_name);
        $this->assertNotNull($saved->date_updated);

        DB::table('telford_calendar')->insert(['cal_date' => '2026-11-08', 'cal_year' => '2027', 'cal_quarter' => 1, 'cal_month' => 11, 'cal_workweek' => 3]);
        $this->as('Quality Assurance')->get('/qdn/maintenance/calendar?workweek=3')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Qdn/Maintenance/Calendar')
                ->has('rows.data', 1)->where('rows.data.0.cal_date', '2026-11-08')
                ->where('years', ['2027'])
                ->where('lists', fn ($l) => collect($l)->last()['key'] === 'calendar'));

        $this->as('Quality Assurance')->delete("/qdn/maintenance/calendar/{$saved->id}")->assertSessionHas('success');
        $this->assertSame(1, DB::table('telford_calendar')->count());
    }

    public function test_other_departments_are_turned_away(): void
    {
        $this->as('Production')->get('/qdn/maintenance/calendar')->assertRedirect(route('qdn.dashboard'));
        $this->as('Production')->post('/qdn/maintenance/calendar', ['cal_date' => '2026-11-01'])->assertRedirect(route('qdn.dashboard'));
        $this->assertSame(0, DB::table('telford_calendar')->count());
    }

    public function test_export_csv_and_excel_respect_filters(): void
    {
        DB::table('telford_calendar')->insert([
            ['cal_date' => '2026-11-01', 'cal_year' => '2027', 'cal_quarter' => 1, 'cal_month' => 11, 'cal_workweek' => 1, 'created_by_emp_name' => '=cmd'],
            ['cal_date' => '2025-11-02', 'cal_year' => '2026', 'cal_quarter' => 1, 'cal_month' => 11, 'cal_workweek' => 1, 'created_by_emp_name' => null],
        ]);

        $csv = $this->as('Quality Assurance')->get('/qdn/maintenance/calendar/export?format=csv&year=2027');
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $body = $csv->streamedContent();
        $this->assertStringContainsString('cal_year,cal_quarter', $body);
        $this->assertStringContainsString('2026-11-01', $body);
        $this->assertStringNotContainsString('2025-11-02', $body);
        $this->assertStringContainsString("'=cmd", $body);

        $xlsx = $this->as('Quality Assurance')->get('/qdn/maintenance/calendar/export?format=xlsx');
        $xlsx->assertOk();
        $file = $xlsx->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($file) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('2025-11-02', $sheet);
        $this->assertStringContainsString('2026-11-01', $sheet);
        $zip->close();

        $this->as('Production')->get('/qdn/maintenance/calendar/export?format=csv')->assertRedirect(route('qdn.dashboard'));
    }

    private function upload(string $name, string $content)
    {
        $path = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($path, $content);

        return new \Illuminate\Http\UploadedFile($path, $name, null, null, true);
    }

    public function test_csv_upload_adds_updates_and_skips_unchanged(): void
    {
        DB::table('telford_calendar')->insert([
            ['cal_date' => '2026-11-01', 'cal_year' => '2027', 'cal_quarter' => 1, 'cal_month' => 11, 'cal_workweek' => 9],
            ['cal_date' => '2026-11-02', 'cal_year' => '2027', 'cal_quarter' => 1, 'cal_month' => 11, 'cal_workweek' => 1],
        ]);
        $csv = "\xEF\xBB\xBFcal_date,cal_year,cal_quarter,cal_month,cal_workweek,extra\n"
            . "2026-11-01,2027,1,11,1,x\n"     // update (ww 9 -> 1)
            . "11/02/2026,2027,1,11,1,\n"      // unchanged, US date format
            . "\n"                             // blank row ignored
            . "46329,2027,1,11,1,\n";          // Excel serial for 2026-11-03 -> add

        $this->as('Quality Assurance')
            ->post('/qdn/maintenance/calendar/import', ['file' => $this->upload('cal.csv', $csv)])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Upload done: 1 added, 1 updated, 1 unchanged.');

        $this->assertSame(3, DB::table('telford_calendar')->count());
        $updated = DB::table('telford_calendar')->where('cal_date', 'like', '2026-11-01%')->first();
        $this->assertSame(1, (int) $updated->cal_workweek);
        $this->assertSame('Ana QA', $updated->updated_by_emp_name);
        $added = DB::table('telford_calendar')->where('cal_date', 'like', '2026-11-03%')->first();
        $this->assertSame('Ana QA', $added->created_by_emp_name);
        $this->assertNull(DB::table('telford_calendar')->where('cal_date', 'like', '2026-11-02%')->value('updated_by_emp_name'));
    }

    public function test_upload_with_bad_rows_saves_nothing(): void
    {
        $csv = "Date,Year,Quarter,Month,Work Week\n"
            . "2026-11-01,2027,1,11,1\n"
            . "not a date,2027,5,11,1\n"
            . "2026-11-01,2027,1,11,abc\n";
        $this->as('Quality Assurance')
            ->post('/qdn/maintenance/calendar/import', ['file' => $this->upload('cal.csv', $csv)])
            ->assertSessionHasErrors(['file', 'import_rows']);
        $this->assertSame(0, DB::table('telford_calendar')->count());
        $rows = session('errors')->get('import_rows')[0];
        $this->assertStringContainsString('Row 3', $rows);
        $this->assertStringContainsString('Row 4', $rows);
        $this->assertStringContainsString('also on row 2', $rows);

        $this->as('Quality Assurance')
            ->post('/qdn/maintenance/calendar/import', ['file' => $this->upload('cal.csv', "cal_date,cal_year\n2026-11-01,2027\n")])
            ->assertSessionHasErrors('file');
        $this->as('Quality Assurance')
            ->post('/qdn/maintenance/calendar/import', ['file' => $this->upload('cal.xls', 'x')])
            ->assertSessionHasErrors('file');
        $this->as('Production')
            ->post('/qdn/maintenance/calendar/import', ['file' => $this->upload('cal.csv', "cal_date\n")])
            ->assertRedirect(route('qdn.dashboard'));
    }

    public function test_xlsx_upload_and_template_round_trip(): void
    {
        $response = $this->as('Quality Assurance')->get('/qdn/maintenance/calendar/template');
        $response->assertOk();
        $template = file_get_contents($response->baseResponse->getFile()->getPathname());

        $this->as('Quality Assurance')
            ->post('/qdn/maintenance/calendar/import', ['file' => $this->upload('template.xlsx', $template)])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Upload done: 1 added, 0 updated, 0 unchanged.');
        $this->assertSame('2027', DB::table('telford_calendar')->value('cal_year'));

        // An export can be uploaded back unchanged.
        $export = $this->as('Quality Assurance')->get('/qdn/maintenance/calendar/export?format=xlsx');
        $this->as('Quality Assurance')
            ->post('/qdn/maintenance/calendar/import', ['file' => $this->upload('export.xlsx', file_get_contents($export->baseResponse->getFile()->getPathname()))])
            ->assertSessionHas('success', 'Upload done: 0 added, 0 updated, 1 unchanged.');
    }

    public function test_workweek_like_644_and_ww644(): void
    {
        $csv = "cal_date,cal_year,cal_quarter,cal_month,cal_workweek\n"
            . "2026-11-01,2027,1,11,644\n"
            . "2026-11-02,2027,1,11,WW645\n"
            . "2026-11-03,2027,1,11,ww 646\n";
        $this->as('Quality Assurance')
            ->post('/qdn/maintenance/calendar/import', ['file' => $this->upload('cal.csv', $csv)])
            ->assertSessionHasNoErrors();
        $this->assertSame([644, 645, 646], DB::table('telford_calendar')->orderBy('cal_date')->pluck('cal_workweek')->map(fn ($v) => (int) $v)->all());

        $this->as('Quality Assurance')->post('/qdn/maintenance/calendar', [
            'cal_date' => '2026-11-04', 'cal_year' => '2027', 'cal_quarter' => 1, 'cal_month' => 11, 'cal_workweek' => 'WW647',
        ])->assertSessionHasNoErrors();
        $this->assertSame(647, (int) DB::table('telford_calendar')->where('cal_date', 'like', '2026-11-04%')->value('cal_workweek'));

        $this->as('Quality Assurance')->get('/qdn/maintenance/calendar?workweek=WW645')
            ->assertInertia(fn (Assert $p) => $p->has('rows.data', 1)->where('rows.data.0.cal_workweek', 645));
    }
}
