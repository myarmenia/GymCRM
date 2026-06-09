<?php

namespace App\Services\Discounts;

use App\DTO\Discounts\DiscountDTO;
use App\Interfaces\Discounts\DiscountInterface;
use Illuminate\Support\Facades\Auth;

class DiscountService
{
    public function __construct(protected DiscountInterface $discountRepository)
    {
    }

    public function getAllPaginated(int $perPage = 15, array $filters = [])
    {
        return $this->discountRepository->paginateForUser(Auth::user(), $perPage, $filters);
    }

    public function getById(int $id)
    {
        return $this->discountRepository->getWithRelations($id);
    }

    public function store(DiscountDTO $dto)
    {
        return $this->discountRepository->createWithRelations(
            $this->dataFromDto($dto),
            $dto->translations,
            $dto->membership_plan_ids,
        );
    }

    public function update(int $id, DiscountDTO $dto)
    {
        return $this->discountRepository->updateWithRelations(
            $id,
            $this->dataFromDto($dto),
            $dto->translations,
            $dto->membership_plan_ids,
        );
    }

    public function delete(int $id): bool
    {
        return $this->discountRepository->delete($id);
    }

    protected function dataFromDto(DiscountDTO $dto): array
    {
        return [
            'type' => $dto->type,
            'value' => $dto->value,
            'start_date' => $dto->start_date,
            'end_date' => $dto->end_date,
            'status' => $dto->status,
        ];
    }
}
