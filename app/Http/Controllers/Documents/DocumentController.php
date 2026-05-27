<?php

namespace App\Http\Controllers\Documents;

use App\DTO\DocumentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentRequest;
use App\Models\Document;
use App\Services\Documents\DocumentService;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function __construct(protected DocumentService $service) {}

    public function ownerDocuments($ownerType, $ownerId)
    {
        return response()->json(
            $this->service->getOwnerDocuments($ownerId, $ownerType)
        );
    }

    public function store(DocumentRequest $request, $ownerType, $ownerId)
    {
        $ownerType = strtolower($ownerType);
        $documents = $this->service->uploadDocuments(DocumentDTO::fromRequest($request), $ownerId, $ownerType);

        return response()->json($documents);
    }

    public function destroy(Document $document)
    {
        $this->service->deleteDocument($document);

        return response()->json(['message' => 'Deleted']);
    }
}

