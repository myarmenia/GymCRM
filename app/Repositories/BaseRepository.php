<?php

namespace App\Repositories;

use App\Interfaces\BaseInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseRepository
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Base query
     */
    public function query()
    {
        return $this->model->newQuery();
    }


    /**
     * Get all rows
     */
    public function getAll(array $with = []): Collection
    {
        return $this->query()
            ->with($with)
            ->get();
    }

    /**
     * Get paginated rows
     */
    public function paginate(int $perPage = 10, array $conditions = [], array $with = [], ?string $orderBy = 'id', string $direction = 'desc'): LengthAwarePaginator
    {
        $query = $this->query()->with($with);

        if (!empty($conditions)) {
            $query->where($conditions);
        }

        if ($orderBy) {
            $query->orderBy($orderBy, $direction);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get by ID
     */
    public function find(int $id, array $with = []): ?Model
    {
        return $this->query()
            ->with($with)
            ->find($id);
    }

    /**
     * Find or fail
     */
    public function findOrFail(int $id, array $with = []): Model
    {
        return $this->query()
            ->with($with)
            ->findOrFail($id);
    }

    /**
     * Find by column
     */
    public function findBy(string $column, mixed $value, array $with = []): ?Model
    {
        return $this->query()
            ->with($with)
            ->where($column, $value)
            ->first();
    }

    /**
     * Create
     */
    public function create(array $data): Model
    {
        return $this->query()->create($data);
    }

    /**
     * Update
     */
    public function update(int $id, array $data): Model
    {
        $model = $this->findOrFail($id);
        $model->update($data);

        return $model;
    }


    /**
     * Delete
     */
    public function delete(int $id): bool
    {
        return (bool) $this->findOrFail($id)->delete();
    }

    /**
     * Active rows (if you use status field)
     */
    public function active(array $with = []): Collection
    {
        return $this->query()
            ->with($with)
            ->where('active', 1)
            ->get();
    }

    /**
     * Filter by conditions
     */
    public function where(array $conditions, array $with = [],): Collection
    {
        return $this->query()
            ->with($with)
            ->where($conditions)
            ->get();
    }

    public function whereIn(string $column, array $values, array $with = []): Collection
    {
        return $this->query()
            ->with($with)
            ->whereIn($column, $values)
            ->get();
    }

    public function gymQuery(): mixed
    {
        $query = $this->query();

        if (method_exists($this->model, 'scopeCurrentGym')) {
            $query->currentGym();
        }

        return $query;
    }


}
