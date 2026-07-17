<?php

namespace App\Services;

use App\Contracts\FileServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class FileService implements FileServiceInterface
{
    public function uploadFile(
        $file,
        ?string $fileName,
        string $folder,
        ?string $disk = null,
        array $options = []
    ) {

        $disk = $disk ?? config('filesystems.default');
        $storage = Storage::disk($disk);
        $visibility = (string) ($options['visibility'] ?? 'public');
        $returnMetadata = (bool) ($options['return_metadata'] ?? false);
        $generateUniqueName = (bool) ($options['generate_unique_name'] ?? true);
        $maxSizeKb = (int) ($options['max_size_kb'] ?? 5120);
        $allowedMimes = $options['allowed_mimes'] ?? null;

        Log::info('Uploading file');
        Log::info($disk);

        try {
            $this->validateUploadFile($file, $allowedMimes, $maxSizeKb);
            $folder = trim($folder, '/');

            if (! $storage->exists($folder)) {
                $storage->makeDirectory($folder);
            }

            $fileName = $this->resolveFileName($file, $fileName, $generateUniqueName, $storage, $folder);
            $filePath = $folder.'/'.$fileName;

            $storage->putFileAs($folder, $file, $fileName);
            $storage->setVisibility($filePath, $visibility);

            $metadata = [
                'disk' => $disk,
                'path' => $filePath,
                'url' => $storage->url($filePath),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'visibility' => $visibility,
                'file_name' => $fileName,
            ];

            return $returnMetadata ? $metadata : $metadata['url'];
        } catch (Throwable $e) {
            Log::error('File upload failed', [
                'disk' => $disk,
                'folder' => $folder,
                'file_name' => $fileName,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    public function deleteFileByUrl(string $url, ?string $disk = null): bool
    {
        $disk = $disk ?? config('filesystems.default', 's3');
        $storage = Storage::disk($disk);

        try {
            $path = $this->resolvePathFromUrl($url, $disk);
            if ($path === '') {
                return false;
            }

            if (! $storage->exists($path)) {
                return false;
            }

            return $storage->delete($path);
        } catch (Throwable $e) {
            Log::error('File delete failed', [
                'disk' => $disk,
                'url' => $url,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    protected function validateUploadFile($file, ?array $allowedMimes, int $maxSizeKb): void
    {
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw new \InvalidArgumentException('Invalid uploaded file.');
        }

        if ($maxSizeKb > 0 && $file->getSize() > $maxSizeKb * 1024) {
            throw new \InvalidArgumentException("File exceeds max size of {$maxSizeKb} KB.");
        }

        if (is_array($allowedMimes) && ! empty($allowedMimes) && ! in_array($file->getMimeType(), $allowedMimes, true)) {
            throw new \InvalidArgumentException('File MIME type is not allowed.');
        }
    }

    protected function resolveFileName(
        UploadedFile $file,
        ?string $fileName,
        bool $generateUniqueName,
        $storage,
        string $folder
    ): string {
        $extension = $file->getClientOriginalExtension() ?: $file->extension();

        if (empty($fileName)) {
            $fileName = Str::uuid()->toString().($extension ? '.'.$extension : '');
        }

        if (! $generateUniqueName) {
            return $fileName;
        }

        $pathInfo = pathinfo($fileName);
        $baseName = Str::slug($pathInfo['filename'] ?? 'file') ?: 'file';
        $ext = $pathInfo['extension'] ?? $extension;
        $candidate = $baseName.($ext ? '.'.$ext : '');

        while ($storage->exists($folder.'/'.$candidate)) {
            $candidate = $baseName.'_'.now()->timestamp.'_'.Str::random(6).($ext ? '.'.$ext : '');
        }

        return $candidate;
    }

    protected function resolvePathFromUrl(string $url, string $disk): string
    {
        $storageUrl = rtrim(Storage::disk($disk)->url('/'), '/');
        $normalizedUrl = rtrim($url, '/');

        if (Str::startsWith($normalizedUrl, $storageUrl)) {
            return ltrim(Str::after($normalizedUrl, $storageUrl), '/');
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return '';
        }

        return ltrim(urldecode($path), '/');
    }
}
