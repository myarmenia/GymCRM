<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class FileUploadService
{
    public function upload(UploadedFile $file, string $folderPath, string $disk = 'public'): string
    {
        $filename = md5(microtime()) . '.' . $file->getClientOriginalExtension();

        return Storage::disk($disk)->putFileAs(
            $folderPath,
            $file,
            $filename
        );
    }

    public function getFile(string $path, string $disk = 'public')
    {
        return response()->file(Storage::disk($disk)->path($path));
    }
}
