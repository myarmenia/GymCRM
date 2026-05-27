<?php

namespace App\DTO;

use App\Http\Requests\DocumentRequest;
use Illuminate\Http\UploadedFile;

class DocumentDTO
{
    public function __construct(
        public string $type,
        public UploadedFile|string|null $file, // файл или уже путь
        public ?string $status = null,
    ) {}


    public static function fromRequest(DocumentRequest $request): array
    {
        $files = $request->file('documents') ?? [];

        return collect(is_array($files) ? $files : [$files])
            ->map(fn($file) => new self(
                $request->type,
                $file
            ))
            ->all();
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'file_path' => is_string($this->file) ? $this->file : null,
            'status' => $this->status,
        ];
    }
}
