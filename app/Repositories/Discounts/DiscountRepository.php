<?php

namespace App\Repositories\Discounts;

use App\Interfaces\Discounts\DiscountInterface;
use App\Models\Discount;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

class DiscountRepository extends BaseRepository implements DiscountInterface
{
    public function __construct(Discount $model)
    {
        parent::__construct($model);
    }

    public function paginateForUser($user, int $perPage = 15, array $filters = [])
    {
        return $this->query()
            ->with(['translations', 'membershipPlans.translations'])
            ->when(! $user->hasRole('owner'), fn ($query) => $query->where('gym_id', $user->gym_id))
            ->filter($this->normalizeFilters($filters))
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    protected function normalizeFilters(array $filters): array
    {
        unset($filters['page'], $filters['per_page']);

        $dateField = $filters['date_field'] ?? 'start_date';

        if (! in_array($dateField, ['start_date', 'end_date'], true)) {
            $dateField = 'start_date';
        }

        if (! empty($filters['date_from'])) {
            $filters["{$dateField}_from"] = $filters['date_from'];
        }

        if (! empty($filters['date_to'])) {
            $filters["{$dateField}_to"] = $filters['date_to'];
        }

        unset($filters['date_field'], $filters['date_from'], $filters['date_to']);

        return array_intersect_key($filters, array_flip([
            'type',
            'start_date_from',
            'start_date_to',
            'end_date_from',
            'end_date_to',
            'membership_plan_id',
        ]));
    }

    public function createWithRelations(array $discountData, array $translations, array $membershipPlanIds): Discount
    {
        return DB::connection($this->model->getConnectionName())->transaction(function () use (
            $discountData,
            $translations,
            $membershipPlanIds,
        ): Discount {
            /** @var Discount $discount */
            $discount = $this->create($discountData);

            foreach ($translations as $locale => $data) {
                $discount->translations()->create([
                    'locale' => $locale,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                ]);
            }

            $discount->membershipPlans()->sync($membershipPlanIds);

            return $discount->load(['translations', 'membershipPlans']);
        });
    }

    public function updateWithRelations(int $id, array $discountData, array $translations, array $membershipPlanIds): Discount
    {
        return DB::connection($this->model->getConnectionName())->transaction(function () use (
            $id,
            $discountData,
            $translations,
            $membershipPlanIds,
        ): Discount {
            /** @var Discount $discount */
            $discount = $this->query()->lockForUpdate()->findOrFail($id);
            $startingVersion = (int) $discount->version;
            $discount->fill($discountData);
            $aggregateChanged = $discount->isDirty(array_keys($discountData));

            foreach ($translations as $locale => $data) {
                $translation = $discount->translations()->firstOrNew(['locale' => $locale]);
                $translation->fill([
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                ]);

                if ($translation->isDirty()) {
                    $translation->save();
                    $aggregateChanged = true;
                }
            }

            $membershipPlanChanges = $discount->membershipPlans()->sync($membershipPlanIds);
            $aggregateChanged = $aggregateChanged
                || $membershipPlanChanges['attached'] !== []
                || $membershipPlanChanges['detached'] !== []
                || $membershipPlanChanges['updated'] !== [];

            if ($aggregateChanged) {
                $discount->version = $startingVersion + 1;
                $discount->save();
            }

            return $discount->fresh(['translations', 'membershipPlans']);
        });
    }

    public function getWithRelations($id)
    {
        return $this->findOrFail($id, ['translations', 'membershipPlans']);
    }
}
