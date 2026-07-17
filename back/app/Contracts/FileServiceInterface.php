<?php

namespace App\Contracts;

interface FileServiceInterface
{
    /**
     * Upload a file to storage and return the URL or metadata.
     *
     * @param  mixed  $file  The uploaded file instance.
     * @param  string|null  $fileName  Desired file name (null = auto-generate).
     * @param  string  $folder  Target folder path.
     * @param  string|null  $disk  Storage disk (null = default).
     * @param  array  $options  Additional options:
     *                          - visibility (string, default 'public')
     *                          - return_metadata (bool, default false)
     *                          - generate_unique_name (bool, default true)
     *                          - max_size_kb (int, default 5120)
     *                          - allowed_mimes (array|null)
     * @return string|array URL string or metadata array (when return_metadata=true).
     */
    public function uploadFile(
        $file,
        ?string $fileName,
        string $folder,
        ?string $disk = null,
        array $options = []
    );

    /**
     * Delete a file identified by its public URL.
     *
     * @param  string  $url  The full public URL of the file.
     * @param  string|null  $disk  Storage disk (null = default).
     */
    public function deleteFileByUrl(string $url, ?string $disk = null): bool;
}
