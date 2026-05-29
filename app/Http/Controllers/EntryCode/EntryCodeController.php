<?php

namespace App\Http\Controllers\EntryCode;

use App\Http\Controllers\Controller;
use App\Services\EntryCodes\EntryCodeService;
use App\Http\Requests\EntryCode\StoreEntryCodeRequest;
use App\Http\Requests\EntryCode\UpdateEntryCodeRequest;
use App\Models\Gym;
use Inertia\Inertia;

class EntryCodeController extends Controller
{
    public function __construct(protected EntryCodeService $entryCodeService) {}

    public function list()
    {
        $entryCodes = $this->entryCodeService->getAllPaginated(10);
        return Inertia::render('EntryCode/List', ['entryCodes' => $entryCodes]);
    }
    

  


    public function create()
    {
        $gyms = $this->entryCodeService->getGymsForCurrentUser();
        $defaultGymId = null;
        $user = auth()->user();

        if (!$user->hasRole('owner')) {
            // Եթե user-ն ունի gym_id
            $defaultGymId = $user->gym_id ?? null;
            
            // Եթե user-ը gym_id-ն վերցվում է staff-ից
            if (!$defaultGymId && $user->staff) {
                $defaultGymId = $user->staff->gym_id ?? null;
            }
            
            // Կամ եթե user-ը client_admin է
            if (!$defaultGymId && $user->client) {
                $defaultGymId = $user->client->gym_id ?? null;
            }
        }

        return Inertia::render('EntryCode/Create', [
            'gyms' => $gyms,
            'defaultGymId' => $defaultGymId,
        ]);
    }

    public function store(StoreEntryCodeRequest $request)
    {
        $this->entryCodeService->create($request->validated());

        return redirect()
            ->route('entry-code.list', ['locale' => app()->getLocale()])
            ->with('success', 'Entry code created!');
    }


    public function edit($locale, $id)
{
    $entryCode = $this->entryCodeService->find($id);
    $gyms = $this->entryCodeService->getGymsForCurrentUser();
    return Inertia::render('EntryCode/Edit', [
        'entryCode' => $entryCode,
        'gyms' => $gyms,
    ]);
}

    public function update(UpdateEntryCodeRequest $request, $locale, $id)
    {
        $this->entryCodeService->update($id, $request->validated());
        return redirect()
            ->route('entry-code.list', ['locale' => app()->getLocale()])
            ->with('success', 'Entry code updated!');
    }

    public function destroy($locale, $id)
    {
        $this->entryCodeService->delete($id);
        return redirect()
            ->route('entry-code.list', ['locale' => app()->getLocale()])
            ->with('success', 'Entry code deleted!');
    }
}