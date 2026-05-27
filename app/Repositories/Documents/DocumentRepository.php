<?php

namespace App\Repositories\Documents;

use App\Interfaces\Documents\DocumentInterface;
use App\Models\Document;
use App\Repositories\BaseRepository;

class DocumentRepository extends BaseRepository implements DocumentInterface
{

    public function __construct(Document $model)
    {
        parent::__construct($model);
    }

    public function getByOwner(int|string $ownerId, string $ownerType): array
    {
        return $this->model
            ->where('owner_id', $ownerId)
            ->where('owner_type', $ownerType)
            ->get()
            ->all();
    }


}
