<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FviOqaListTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DashboardTest::makeQdnTables();
        if (!Schema::hasTable('qdn_photos')) {
            Schema::create('qdn_photos', function (Blueprint $t) {
                $t->id(); $t->unsignedBigInteger('qdn_id'); $t->string('path'); $t->string('original_name')->nullable();
                $t->unsignedInteger('size')->nullable(); $t->unsignedSmallInteger('sort_order')->default(0);
                $t->string('uploaded_by')->nullable(); $t->timestamps();
            });
        }
        $this->withoutMiddleware(\App\Http\Middleware\AuthMiddleware::class);
        $this->withSession(['emp_data' => ['token' => 't', 'emp_id' => '1705', 'emp_name' => 'Juan', 'emp_jobtitle' => 'Operator', 'emp_dept' => 'Production']]);
    }

    public function test_lists_only_fvi_and_oqa_with_tabs(): void
    {
        $fvi = $this->makeQdn(['qdn_no' => 'Q-1', 'detection_area' => 'Final Visual Inspection']);
        $this->makeQdn(['qdn_no' => 'Q-2', 'detection_area' => 'OQA']);
        $this->makeQdn(['qdn_no' => 'Q-3', 'detection_area' => 'Wire Bond']);
        DB::table('qdn_photos')->insert(['qdn_id' => $fvi, 'path' => 'x.jpg', 'sort_order' => 1]);

        $this->get('/qdn/fvi-oqa')->assertOk()->assertInertia(fn ($p) => $p
            ->component('Qdn/FviOqa/Index')
            ->where('counts', ['all' => 2, 'fvi' => 1, 'oqa' => 1])
            ->has('qdns.data', 2));

        $this->get('/qdn/fvi-oqa?area=fvi')->assertInertia(fn ($p) => $p
            ->has('qdns.data', 1)
            ->where('qdns.data.0.qdn_no', 'Q-1')
            ->where('qdns.data.0.area', 'fvi')
            ->where('qdns.data.0.photos_count', 1));

        $this->get('/qdn/fvi-oqa?area=oqa')->assertInertia(fn ($p) => $p
            ->has('qdns.data', 1)
            ->where('qdns.data.0.qdn_no', 'Q-2')
            ->where('qdns.data.0.thumbnail', null));
    }

    private function makeQdn(array $attrs): int
    {
        return DB::table('qdns')->insertGetId($attrs + ['status' => 'pending_pe', 'created_at' => now(), 'updated_at' => now()]);
    }
}
