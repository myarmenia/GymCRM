<?php

namespace App\Http\Controllers\Partners;

use App\Http\Controllers\Controller;
use App\Http\Requests\Partners\StorePartnerRequest;
use App\Http\Requests\Partners\UpdatePartnerRequest;
use App\Services\Partners\PartnerService;
use Inertia\Inertia;

class PartnerController extends Controller
{
    public function __construct(
        protected PartnerService $partnerService
    ) {}

    public function list()
    {
        $partners = $this->partnerService->all();
        
        return Inertia::render('Partners/List', [
            'partners' => $partners
        ]);
    }

    public function create()
    {
        return Inertia::render('Partners/Create');
    }

    public function store(StorePartnerRequest $request)
    {
        $partner = $this->partnerService->create($request->validated());

        return redirect()->route('partner.edit', [
            'locale' => app()->getLocale(),
            'id' => $partner->id
        ])->with('success', 'Partner created successfully.');
    }

    public function edit($locale, $id) 
    {    
        $partner = $this->partnerService->find($id);

        return Inertia::render('Partners/Edit', [
            'partner' => $partner
        ]);
    }

    public function update(UpdatePartnerRequest $request, $locale, $id) 
    {
        $this->partnerService->update($id, $request->validated());
        
        return redirect()->route('partner.list', ['locale' => $locale])
            ->with('success', 'Partner updated successfully.');
    }
}