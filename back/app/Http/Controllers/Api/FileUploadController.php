<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileUploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class FileUploadController extends Controller
{
    #[OA\Post(
        path: '/user/files/upload',
        tags: ['Files'],
        summary: 'Upload a file to S3',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Uploaded'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function upload(FileUploadRequest $request): JsonResponse
    {

        $file = $request->file('file');
        $disk = config('filesystems.cloud', 's3');
        $path = Storage::disk($disk)->putFile('uploads', $file);

        return response()->json([
            'message' => __('api.files.uploaded'),
            'data' => [
                'disk' => $disk,
                'path' => $path,
                'url' => Storage::disk($disk)->url($path),
            ],
        ], 201);
    }
}
