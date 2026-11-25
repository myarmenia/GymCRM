<?php

namespace App\Services\Documents;

use App\Interfaces\Documents\DocumentInterface;
use App\Models\Document;
use App\Services\FileUploadService;
use App\Services\ModelService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class DocumentService
{
    public function __construct(
        protected DocumentInterface $documentRepository,
        protected ModelService $modelService,
        protected FileUploadService $fileService,
        protected DocumentGymAccess $gymAccess
    ) {}

    public function uploadDocuments(array $dtos, int|string $ownerId, $ownerType)
    {

        $owner = $this->modelService->find($ownerType, $ownerId);
        $gymId = $this->gymAccess->gymFor($owner, request()->filled('gym_id') ? (int) request('gym_id') : null);

        $folder = 'documents/'.strtolower(class_basename($owner)).'/'.$owner->uuid;

        return collect($dtos)->map(function ($dto) use ($owner, $folder, $gymId) {

            $filePath = $dto->file instanceof UploadedFile
                ? $this->fileService->upload($dto->file, $folder)
                : $dto->file;

            return $owner->documents()->create([
                'gym_id' => $gymId,
                'type' => $dto->type,
                'file_path' => $filePath,
                'status' => $dto->status ?? 'active',
            ]);
        })->all();

    }

    public function getOwnerDocuments(int|string $ownerId, string $ownerType): Collection
    {
        $owner = $this->modelService->find($ownerType, $ownerId);
        $gymId = $this->gymAccess->gymFor($owner, request()->filled('gym_id') ? (int) request('gym_id') : null);

        return $owner->documents()->where('gym_id', $gymId)->get();
    }

    public function deleteDocument(Document $document)
    {
        $this->gymAccess->authorizeDocument($document);
        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        return $document->delete();

    }
}
