<?php

namespace App\Interfaces;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

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

    public function whereIn(string $column, array $values, array $with = []): Collection;

    public function gymQuery(): mixed;

}
