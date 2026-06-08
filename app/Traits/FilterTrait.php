<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait FilterTrait
{
    public function scopeFilter(Builder $query, array $filters = []): Builder
    {
        foreach ($filters as $field => $value) {
            if ($this->isEmptyFilterValue($value)) {
                continue;
            }

            if (str_contains($field, '.')) {
                $this->applyRelationFilter($query, $field, $value);
                continue;
            }

            $this->applyDirectFilter($query, $field, $value);
        }

        return $query;
    }

    protected function applyDirectFilter(Builder $query, string $field, mixed $value): void
    {
        $config = $this->getFilterConfig($field);

        if (!$config) {
            return;
        }

        if (isset($config['callback']) && method_exists($this, $config['callback'])) {
            $this->{$config['callback']}($query, $value, $field, $config);
            return;
        }

        $column = $config['column'] ?? $field;
        $method = $config['method'] ?? 'where';
        $operator = $config['operator'] ?? null;

        if ($operator === 'like') {
            $value = '%' . $value . '%';
        }

        if ($method === 'whereIn') {
            $query->whereIn($column, (array) $value);
            return;
        }

        if ($operator) {
            $query->{$method}($column, $operator, $value);
            return;
        }

        $query->{$method}($column, $value);
    }

    protected function applyRelationFilter(Builder $query, string $field, mixed $value): void
    {
        [$relation, $relationField] = explode('.', $field, 2);

        if (!method_exists($this, $relation)) {
            return;
        }

        $query->whereHas($relation, function (Builder $relatedQuery) use ($relationField, $value) {
            $relatedModel = $relatedQuery->getModel();

            if (!method_exists($relatedModel, 'scopeFilter')) {
                return;
            }

            $relatedQuery->filter([
                $relationField => $value,
            ]);
        });
    }

    protected function getFilterConfig(string $field): ?array
    {
        $config = $this->filterConfig ?? [];

        return $config[$field] ?? null;
    }

    protected function isEmptyFilterValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
