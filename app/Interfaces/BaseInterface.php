<?php

namespace App\Interfaces;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;


interface BaseInterface
{
    public function query();

    public function getAll(array $with = []): Collection;

    public function paginate(int $perPage = 15, array $with = []): LengthAwarePaginator;

    public function find(int $id, array $with = []): ?Model;

    public function findOrFail(int $id, array $with = []): Model;

    public function findBy(string $column, mixed $value, array $with = []): ?Model;

    public function create(array $data): Model;

    public function update(int $id, array $data): Model;

    public function delete(int $id): bool;

    public function active(array $with = []): Collection;

    public function where(array $conditions, array $with = []): Collection;

    public function whereNull(string $column, array $conditions = [], array $with = []): Collection;

    public function wherePaginate(array $conditions, array $with = [], int $perPage = 10): LengthAwarePaginator;

    public function getAllCleanings(array $conditions, array $with = [], int $perPage = 30): LengthAwarePaginator;

    public function whereIn(string $column, array $values, array $with = []): Collection;

    public function createTranslation(int $id, array $data): Model;

    public function updateTranslation(array $conditions, array $data): ?Model;

    public function createAmenities(array $payload);

    public function upsert(array $values, array $uniqueBy, array $update);

    public function getAllTasksByUser(array $conditions, array $with = [], int $perPage = 30): LengthAwarePaginator;

    public function updateTaskStatus(int $id, string $key, mixed $value);

    public function updateRoomStatus(int $id, string $key, mixed $value);

    public function updateCleaningStatus(int $id, string $key, mixed $value);

    public function createCategory(array $data): Model;

    public function findCategoryBy(string $column, mixed $value, array $relations = []): ?Model;

    public function wherePaginateCategory(array $conditions, array $with = [], int $perPage = 10): LengthAwarePaginator;

    public function getParentCategories(string $locale): SupportCollection;

    public function getProductsByFilter(array $filters = [], array $with = [], int $perPage = 10): LengthAwarePaginator;

    public function getProductForConsumption(array $ids): Collection;
}
