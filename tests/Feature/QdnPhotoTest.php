<?php

namespace Tests\Feature;

use App\Models\Qdn;
use App\Models\QdnPhoto;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QdnPhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(QdnPhoto::DISK);
        DashboardTest::makeQdnTables();
        Schema::create('qdn_photos', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('qdn_id'); $t->string('path'); $t->string('original_name')->nullable();
            $t->unsignedInteger('size')->nullable(); $t->unsignedSmallInteger('sort_order')->default(0);
            $t->string('uploaded_by')->nullable(); $t->timestamps();
        });
        Schema::create('location_list', fn (Blueprint $t) => [$t->id(), $t->string('location_name')]);
        DB::table('location_list')->insert([
            ['id' => 1, 'location_name' => 'Final Visual Inspection'],
            ['id' => 2, 'location_name' => 'OQA'],
            ['id' => 3, 'location_name' => 'Wire Bond'],
        ]);
        $this->withoutMiddleware(\App\Http\Middleware\AuthMiddleware::class);
        $this->withSession(['emp_data' => ['token' => 't', 'emp_id' => '1705', 'emp_name' => 'Juan', 'emp_jobtitle' => 'Operator', 'emp_dept' => 'Production']]);
    }

    public function test_detection_areas_that_need_photos(): void
    {
        $this->assertTrue(Qdn::requiresPhotos('Final Visual Inspection'));
        $this->assertTrue(Qdn::requiresPhotos('OQA'));
        $this->assertTrue(Qdn::requiresPhotos('fvi - line 2'));
        $this->assertFalse(Qdn::requiresPhotos('Wire Bond'));
        $this->assertFalse(Qdn::requiresPhotos(null));
    }

    public function test_validation_requires_jpg_only_for_fvi_and_oqa(): void
    {
        Schema::create((new \App\Models\Customer)->getTable(), fn (Blueprint $t) => [$t->id(), $t->string('customer_name')]);

        $rules = function (int $loc, array $files = []) {
            $request = \App\Http\Requests\StoreQdnRequest::create('/qdn', 'POST', ['location_id' => $loc]);
            $request->setContainer(app());

            return validator(['location_id' => $loc, 'photos' => $files], $request->rules());
        };

        // Only check the photo rules here.
        $photoErrors = fn ($v) => collect($v->errors()->keys())->filter(fn ($k) => str_starts_with($k, 'photos'))->values()->all();

        $this->assertSame(['photos'], $photoErrors($rules(1)));                     // FVI, none -> required
        $this->assertSame(['photos'], $photoErrors($rules(2)));                     // OQA, none -> required
        $this->assertSame([], $photoErrors($rules(3)));                             // Wire Bond -> not needed
        $this->assertSame([], $photoErrors($rules(1, [UploadedFile::fake()->image('a.jpg')])));
        $this->assertSame(['photos.0'], $photoErrors($rules(1, [UploadedFile::fake()->image('a.png')])));
    }

    public function test_photos_are_listed_and_served(): void
    {
        $qdnId = DB::table('qdns')->insertGetId(['qdn_no' => 'QDN-1', 'status' => 'pending_pe', 'created_at' => now(), 'updated_at' => now()]);
        Storage::disk(QdnPhoto::DISK)->put("qdn-photos/{$qdnId}/x.jpg", 'JPEGDATA');
        $photo = QdnPhoto::create(['qdn_id' => $qdnId, 'path' => "qdn-photos/{$qdnId}/x.jpg", 'original_name' => 'defect.jpg']);

        $this->getJson("/qdn/{$qdnId}/photos")
            ->assertOk()
            ->assertJsonPath('photos.0.name', 'defect.jpg')
            ->assertJsonPath('photos.0.url', route('qdn.photos.show', $photo));

        $res = $this->get("/qdn/photos/{$photo->id}")->assertOk();
        $this->assertSame('image/jpeg', $res->headers->get('Content-Type'));
    }
}
