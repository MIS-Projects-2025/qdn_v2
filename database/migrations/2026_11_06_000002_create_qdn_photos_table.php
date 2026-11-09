<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JPG photos attached at Create QDN. Required when the Detection Area is
 * Final Visual Inspection or OQA (see Qdn::requiresPhotos()). Files live on
 * the private "local" disk under qdn-photos/{qdn_id}/ and are served
 * through QdnPhotoController behind AuthMiddleware.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qdn_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qdn_id')->constrained('qdns')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('uploaded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qdn_photos');
    }
};
