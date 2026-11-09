<?php

namespace Tests\Feature;

use App\Models\Masterlist\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * QDN employee lookups resolve through Masterlist\Employee and the
 * Lookups models (department, position, position level, station, prod line, team).
 */
class QdnEmployeeLookupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.masterlist' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('masterlist');
        $db = DB::connection('masterlist');
        // SQLite has no CONCAT_WS; register one so the search query runs.
        $db->getPdo()->sqliteCreateFunction('CONCAT_WS', fn ($sep, ...$parts) => implode($sep, array_filter($parts, fn ($p) => $p !== null && $p !== '')));

        $schema = Schema::connection('masterlist');
        $schema->create('employee_details', function (Blueprint $t) {
            $t->integer('employid')->primary();
            $t->string('firstname')->nullable();
            $t->string('middlename')->nullable();
            $t->string('lastname')->nullable();
            $t->integer('accstatus')->default(1);
        });
        foreach ([
            ['department', 'dep_name', 'employee_department', 'department_id'],
            ['position', 'emp_position_name', 'employee_position', 'empposition_id'],
            ['position_level', 'position_level_name', 'employee_position_level', 'position_level_id'],
            ['station', 'station_name', 'employee_station', 'station_id'],
            ['prod_lines', 'pl_name', 'employee_production_line', 'prodline_id'],
            ['team', 'team_name', 'employee_team', 'team_id'],
        ] as [$lookup, $col, $pivot, $fk]) {
            $schema->create($lookup, fn (Blueprint $t) => [$t->id(), $t->string($col)]);
            $schema->create($pivot, fn (Blueprint $t) => [$t->id(), $t->integer('employid'), $t->integer($fk)]);
        }
        $stationTable = (new \App\Models\Lookups\Station)->getTable();
        if ($stationTable !== 'station') {
            $schema->rename('station', $stationTable);
        }

        $db->table('employee_details')->insert([
            ['employid' => 1001, 'firstname' => 'Ana', 'middlename' => 'B', 'lastname' => 'Reyes', 'accstatus' => 1],
            ['employid' => 1002, 'firstname' => 'Ben', 'middlename' => null, 'lastname' => 'Santos', 'accstatus' => 1],
            ['employid' => 1003, 'firstname' => 'Gone', 'middlename' => null, 'lastname' => 'Person', 'accstatus' => 0],
        ]);
        $db->table('department')->insert([['id' => 1, 'dep_name' => 'Process Engineering'], ['id' => 2, 'dep_name' => 'Production']]);
        $db->table('position')->insert([['id' => 1, 'emp_position_name' => 'Production Supervisor'], ['id' => 2, 'emp_position_name' => 'FVI Inspector 1']]);
        $db->table('position_level')->insert([['id' => 1, 'position_level_name' => 'Supervisor'], ['id' => 2, 'position_level_name' => 'Rank and File']]);
        $db->table($stationTable)->insert([['id' => 1, 'station_name' => 'Wire Bond']]);
        $db->table('prod_lines')->insert([['id' => 1, 'pl_name' => 'Line 3']]);
        $db->table('team')->insert([['id' => 1, 'team_name' => 'Team A']]);
        $db->table('employee_department')->insert([['employid' => 1001, 'department_id' => 2], ['employid' => 1002, 'department_id' => 1]]);
        $db->table('employee_position')->insert([['employid' => 1001, 'empposition_id' => 1], ['employid' => 1002, 'empposition_id' => 2]]);
        $db->table('employee_position_level')->insert([['employid' => 1001, 'position_level_id' => 1], ['employid' => 1002, 'position_level_id' => 2]]);
        $db->table('employee_station')->insert([['employid' => 1001, 'station_id' => 1]]);
        $db->table('employee_production_line')->insert([['employid' => 1001, 'prodline_id' => 1]]);
        $db->table('employee_team')->insert([['employid' => 1001, 'team_id' => 1]]);

        $this->withoutMiddleware(\App\Http\Middleware\AuthMiddleware::class);
    }

    public function test_find_exposes_qdn_roster_fields(): void
    {
        $e = Employee::find(1001);

        $this->assertSame([
            'EMPLOYID' => 1001,
            'EMPLOYNAME' => 'Ana B Reyes',
            'DEPARTMENT' => 'Production',
            'JOB_TITLE' => 'Production Supervisor',
            'POSITION_LEVEL' => 'Supervisor',
            'STATION' => 'Wire Bond',
            'PRODLINE' => 'Line 3',
            'TEAM' => 'Team A',
        ], $e->toQdnRoster());
    }

    public function test_search_by_name_skips_inactive(): void
    {
        $this->getJson('/employees/search?q=Ana Reyes')
            ->assertOk()
            ->assertJsonCount(1, 'employees')
            ->assertJsonPath('employees.0.EMPLOYID', 1001);

        $this->getJson('/employees/search?q=Gone')->assertJsonCount(0, 'employees');
    }

    public function test_search_filters_by_department_and_title(): void
    {
        $this->getJson('/employees/search?department=Process%20Engineering')
            ->assertJsonCount(1, 'employees')
            ->assertJsonPath('employees.0.EMPLOYNAME', 'Ben Santos');

        $this->getJson('/employees/search?like[]=%25Supervisor%25')
            ->assertJsonCount(1, 'employees')
            ->assertJsonPath('employees.0.DEPARTMENT', 'Production');

        $this->getJson('/employees/search?titles[]=FVI%20Inspector%201')
            ->assertJsonCount(1, 'employees')
            ->assertJsonPath('employees.0.EMPLOYID', 1002);
    }
}
