<?php

namespace App\Repositories\Memberships;

use App\Helpers\MyHelper;
use App\Interfaces\Memberships\MembershipPlanInterface;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

class MembershipPlanRepository extends BaseRepository implements MembershipPlanInterface
{

    public function __construct(MembershipPlan $model)
    {
        parent::__construct($model);
    }


    public function getCreateData()
    {
        $gymId = MyHelper::find_auth_user_client();

        return MembershipCategory::query()
            ->where('gym_id', $gymId)
            ->with('translations')
            ->get()
            ->map(fn($category) => [
                'id' => $category->id,
                'name' => $category->translations
                    ->where('locale', app()->getLocale())
                    ->first()?->name,
            ]);

        //'trainers' => User::query()
        //    ->where('gym_id', $gymId)
        //    ->whereHas('roles', fn($q) => $q->where('id', 7))
        //    ->with('scheduleNames:id,name')
        //    ->get(['id', 'gym_id', 'name', 'surname']),

    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $plan = $this->model::create([
                'membership_category_id' => $data['membership_category_id'],
                'price' => $data['price'],
                'duration_type' => $data['duration_type'],
                'duration_value' => $data['duration_value'] ?? null,
                'visits_limit' => $data['visits_limit'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'guest_limit' => $data['guest_limit'] ?? 0,
                'freeze_limit' => $data['freeze_limit'] ?? 0,
                'active' => $data['active'] ?? true,
            ]);

            foreach ($data['translations'] ?? [] as $locale => $translation) {
                $plan->translations()->create([
                    'locale' => $locale,
                    'name' => $translation['name'],
                    'description' => $translation['description'] ?? null,
                ]);
            }

            $trainerIds = collect($data['trainers'] ?? [])
                ->pluck('trainer_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $scheduleIds = collect($data['trainers'] ?? [])
                ->pluck('schedule_ids')
                ->flatten()
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $plan->trainers()->sync($trainerIds);
            $plan->schedules()->sync($scheduleIds);

            return $plan;
        });
    }

    public function edit(int $id)
    {
        $plan = $this->model::query()
            ->with([
                'translations',
                'trainers:id,name,surname',
                'schedules:id,name',
            ])
            ->findOrFail($id);

        $createData = $this->getCreateData();

        return array_merge($createData, [
            'membershipPlan' => $plan,
        ]);
    }

    //public function updateMembership(int $id, array $data)
    //{
    //    return DB::transaction(function () use ($id, $data) {
    //        $plan = $this->model::findOrFail($id);

    //        $plan->update([
    //            'membership_category_id' => $data['membership_category_id'],
    //            'price' => $data['price'],
    //            'duration_type' => $data['duration_type'],
    //            'duration_value' => $data['duration_value'] ?? null,
    //            'visits_limit' => $data['visits_limit'] ?? null,
    //            'start_date' => $data['start_date'] ?? null,
    //            'end_date' => $data['end_date'] ?? null,
    //            'guest_limit' => $data['guest_limit'] ?? 0,
    //            'freeze_limit' => $data['freeze_limit'] ?? 0,
    //            'active' => $data['active'] ?? true,
    //        ]);

    //        foreach ($data['translations'] ?? [] as $locale => $translation) {
    //            $plan->translations()->updateOrCreate(
    //                ['locale' => $locale],
    //                [
    //                    'name' => $translation['name'],
    //                    'description' => $translation['description'] ?? null,
    //                ]
    //            );
    //        }

    //        $trainerIds = collect($data['trainers'] ?? [])
    //            ->pluck('trainer_id')
    //            ->filter()
    //            ->unique()
    //            ->values()
    //            ->toArray();

    //        $scheduleIds = collect($data['trainers'] ?? [])
    //            ->pluck('schedule_ids')
    //            ->flatten()
    //            ->filter()
    //            ->unique()
    //            ->values()
    //            ->toArray();

    //        $plan->trainers()->sync($trainerIds);
    //        $plan->schedules()->sync($scheduleIds);

    //        return $plan;
    //    });
    //}
    public function findForEdit(int $id, string $locale): array
    {
        $membershipPlan = $this->model::query()
            ->with([
                'translations',
                'schedules',
                'trainers',
            ])
            ->findOrFail($id);

        $translation = $membershipPlan->translations
            ->where('locale', $locale)
            ->first();

        $schedule = $membershipPlan->schedules->first();
       // dd($membershipPlan->trainers);
        return [
            'id' => $membershipPlan->id,
            'membership_category_id' => $membershipPlan->membership_category_id,
            'price' => $membershipPlan->price,
            'duration_type' => $membershipPlan->duration_type,
            'duration_value' => $membershipPlan->duration_value,
            'visits_limit' => $membershipPlan->visits_limit,
            'start_date' => $membershipPlan->start_date,
            'end_date' => $membershipPlan->end_date,
            'guest_limit' => $membershipPlan->guest_limit,
            'freeze_limit' => $membershipPlan->freeze_limit,
            'active' => (bool) $membershipPlan->active,

            'schedule_name_id' => $schedule?->schedule_id,

            'translation' => [
                'name' => $translation?->name ?? '',
                'description' => $translation?->description ?? '',
            ],

            'trainers' => $membershipPlan->trainers
                ->map(function ($trainer) {
                    return [
                        'trainer_id' => (int) $trainer->id,
                        'name' => $trainer->name,
                        'surname' => $trainer->surname,
                        'price_type' => $trainer->pivot->price_type,
                        'price_value' => (float) $trainer->pivot->price_value,
                        'total_price' => (float) $trainer->pivot->total_price,
                    ];
                })
                ->values()
                ->toArray(),
        ];
    }

    public function update(int $id, array $data): MembershipPlan
    {
        $membershipPlan = $this->model::query()->findOrFail($id);

        $membershipPlan->update($data);

        return $membershipPlan->fresh();
    }
}
