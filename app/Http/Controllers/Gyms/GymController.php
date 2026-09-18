<?php

namespace App\Http\Controllers\Gyms;

use App\Http\Controllers\Controller;
use App\Services\Gyms\GymService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Http\Requests\Gyms\StoreGymRequest;
use App\Http\Requests\Gyms\UpdateGymRequest;
class GymController extends Controller
{
    public function __construct(protected GymService $gymService) {}

    public function list()
    {
        $this->authorizeOwner();

        $gyms = $this->gymService->getAll();
        return Inertia::render('Gyms/List', ['gyms' => $gyms]);
    }

    public function create()
    {
        $this->authorizeOwner();

        $availableLanguages = $this->gymService->availableLanguages();

        return Inertia::render('Gyms/Create', [
            'availableLanguages' => $availableLanguages,
            'selectedLanguageCodes' => $availableLanguages->pluck('code')->values()->all(),
        ]);
    }

    public function store(StoreGymRequest $request)
    {
        $this->authorizeOwner();

        $validated = $request->validated();
        $this->gymService->create($validated);

        return redirect()
            ->route('gym.list', ['locale' => app()->getLocale()])
            ->with('success', 'Gym created successfully!');
    }

    public function edit($locale, $id)
    {
        $this->authorizeOwner();

        $gym = $this->gymService->find($id);

        return inertia('Gyms/Edit', [
            'gym' => $gym,
            'availableLanguages' => $this->gymService->availableLanguages(),
            'selectedLanguageCodes' => $gym->languages
                ->filter(fn ($language) => (bool) $language->pivot->active)
                ->pluck('code')
                ->values()
                ->all(),
        ]);
    }

    public function update(UpdateGymRequest $request, $locale, $id)
    {
        $this->authorizeOwner();

        $validated = $request->validated();
        $this->gymService->update($id, $validated);

        return redirect()
            ->route('gym.list', ['locale' => app()->getLocale()])
            ->with('success', 'Gym updated successfully!');
    }


    private function authorizeOwner(): void
    {
        abort_unless(Auth::user()?->hasRole('owner'), 403);
    }
}
