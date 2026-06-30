<?php

namespace App\Repositories\Purchase;

use App\Interfaces\Purchase\PurchaseInterface;
use App\Models\Purchase;
use App\Repositories\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;




class PurchaseRepository extends BaseRepository implements PurchaseInterface
{

    public function __construct(Purchase $model)
    {
        parent::__construct($model);
    }

    public function paginateHistory(
        int $gymId,
        string $locale,
        ?string $search = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $paymentMethod = null,
        ?int $personId = null,
        ?int $warehouseId = null,
        int $perPage = 10
    ) {
        return Purchase::query()
            ->with([
                'person:id,name,surname',
                'warehouse:id,name',
                'items.product.translations' => function ($query) use ($locale) {
                    $query->where('locale', $locale);
                },
            ])
            ->where('gym_id', $gymId)
            ->when($search, function ($query) use ($search, $locale) {
                $query->where(function ($q) use ($search, $locale) {
                    $q->where('id', $search)
                        ->orWhereHas('person', function ($personQuery) use ($search) {
                            $personQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('surname', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items.product', function ($productQuery) use ($search, $locale) {
                            $productQuery
                                ->where('sku', 'like', "%{$search}%")
                                ->orWhereHas('translations', function ($translationQuery) use ($search, $locale) {
                                    $translationQuery
                                        ->where('locale', $locale)
                                        ->where('name', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when($startDate, function ($query) use ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            })
            ->when($endDate, function ($query) use ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            })
            ->when($paymentMethod, function ($query) use ($paymentMethod) {
                $query->where('payment_method', $paymentMethod);
            })
            ->when($personId, function ($query) use ($personId) {
                $query->where('people_id', $personId);
            })
            ->when($warehouseId, function ($query) use ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
