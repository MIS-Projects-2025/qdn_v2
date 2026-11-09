<?php

namespace App\Http\Controllers;

use App\Models\Qdn;
use App\Models\QdnPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Photos attached to a QDN (Final Visual Inspection / OQA). Any signed-in
 * user can view them, same as QDN Records.
 */
class QdnPhotoController extends Controller
{
    public function index(Qdn $qdn): JsonResponse
    {
        return response()->json([
            'photos' => $qdn->photos->map(fn (QdnPhoto $p) => [
                'id' => $p->id,
                'url' => $p->url(),
                'name' => $p->original_name,
            ])->values(),
        ]);
    }

    public function show(QdnPhoto $photo): StreamedResponse
    {
        $disk = Storage::disk(QdnPhoto::DISK);
        abort_unless($disk->exists($photo->path), 404);

        return $disk->response($photo->path, $photo->original_name ?: basename($photo->path), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
