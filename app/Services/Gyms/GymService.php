<?php

namespace App\Services\Gyms;

use App\Interfaces\Gyms\GymInterface;
use App\Models\Gym;
use App\Models\Lang;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GymService
{
    // Ավելացնում ենք FileUploadService-ը constructor-ի մեջ
    public function __construct(
        protected GymInterface $gymRepository,
        protected FileUploadService $fileUploadService
    ) {}

    public function getAll()
    {
        return $this->gymRepository->getAll();
    }

    public function getAllPaginated()
    {
        return $this->gymRepository->paginate(10);
    }

    public function availableLanguages()
    {
        return Lang::query()
            ->orderBy('id')
            ->get(['id', 'code', 'name']);
    }

    public function find(int $id): Gym
    {
        /** @var Gym $gym */
        $gym = $this->gymRepository->findOrFail($id, ['languages']);

        return $gym;
    }

    public function create(array $data): Gym
    {
        $languageCodes = array_values(array_unique(
            $data['language_codes'] ?? $this->availableLanguages()->pluck('code')->all(),
        ));
        unset($data['language_codes']);

        $folder = 'gyms/logos';

        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
            $data['logo'] = $this->fileUploadService->upload($data['logo'], $folder);
        }

        return DB::transaction(function () use ($data, $languageCodes): Gym {
            /** @var Gym $gym */
            $gym = $this->gymRepository->create($data);
            $this->syncLanguageSettings($gym, $languageCodes);

            return $gym->fresh('languages');
        });
    }

    public function update(int $id, array $data): Gym
    {
        $gym = $this->find($id);
        $languageCodes = array_key_exists('language_codes', $data)
            ? array_values(array_unique($data['language_codes']))
            : null;
        unset($data['language_codes']);

        $folder = 'gyms/logos';

        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {

            if ($gym->logo && Storage::disk('public')->exists($gym->logo)) {
                Storage::disk('public')->delete($gym->logo);
            }

            $data['logo'] = $this->fileUploadService->upload($data['logo'], $folder);
        } else {
            $data['logo'] = $data['logo'] ?? $gym->logo;
        }

        return DB::transaction(function () use ($id, $data, $languageCodes): Gym {
            /** @var Gym $gym */
            $gym = $this->gymRepository->update($id, $data);

            if ($languageCodes !== null) {
                $this->syncLanguageSettings($gym, $languageCodes);
            }

            return $gym->fresh('languages');
        });
    }


    private function syncLanguageSettings(Gym $gym, array $activeCodes): void
    {
        $activeCodes = array_flip($activeCodes);
        $currentLanguages = $gym->languages()->get()->keyBy('id');

        foreach ($this->availableLanguages() as $language) {
            $active = isset($activeCodes[$language->code]);
            $current = $currentLanguages->get($language->id);

            if ($current === null) {
                $gym->languages()->attach($language->id, ['active' => $active]);

                continue;
            }

            if ((bool) $current->pivot->active !== $active) {
                $gym->languages()->updateExistingPivot($language->id, [
                    'active' => $active,
                ]);
            }
        }
    }
}
