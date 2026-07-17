<?php

namespace App\Http\Controllers;

use App\Services\FileService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    protected function uploadFile($file, ?string $fileName, string $folder, ?string $disk = null, array $options = [])
    {
        return app(FileService::class)->uploadFile($file, $fileName, $folder, $disk, $options);
    }

    protected function deleteFileByUrl(string $url, ?string $disk = null): bool
    {
        return app(FileService::class)->deleteFileByUrl($url, $disk);
    }
}
