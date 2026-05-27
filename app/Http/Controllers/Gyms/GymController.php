<?php

namespace App\Http\Controllers\Gyms;

use App\Http\Controllers\Controller;
use App\Services\Gyms\GymService;
use Inertia\Inertia;
use App\Http\Requests\Gyms\StoreGymRequest;
use App\Http\Requests\Gyms\UpdateGymRequest;
class GymController extends Controller
{
    public function __construct(protected GymService $gymService) {}

    public function list()
    {
        $gyms = $this->gymService->getAll();
        return Inertia::render('Gyms/List', ['gyms' => $gyms]);
    }

    public function create()
    {
        return Inertia::render('Gyms/Create');
    }

    public function store(StoreGymRequest $request)
    {

        $validated = $request->validated();
        $this->gymService->create($validated);

        return redirect()
            ->route('gym.list', ['locale' => app()->getLocale()])
            ->with('success', 'Gym created successfully!');
    }

    public function edit($locale, $id)
    {
        $gym = $this->gymService->find($id);

        return inertia('Gyms/Edit', [
            'gym' => $gym
        ]);
    }

    public function update(UpdateGymRequest $request, $locale, $id)
    {
        $validated = $request->validated();
        $this->gymService->update($id, $validated);

        return redirect()
            ->route('gym.list', ['locale' => app()->getLocale()])
            ->with('success', 'Gym updated successfully!');
    }


}
