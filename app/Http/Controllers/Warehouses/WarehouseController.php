<?php

namespace App\Http\Controllers\Warehouses; 

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouses\StoreWarehouseRequest;
use App\Services\Warehouses\WarehouseService;
use Inertia\Inertia;

class WarehouseController extends Controller
{
    public function __construct(
        protected WarehouseService $warehouseService
    ) {}

    public function list()
    {
        $warehouses = $this->warehouseService->all();
        
        return Inertia::render('Warehouses/List', [
            'warehouses' => $warehouses
        ]);
    }

    public function create()
    {
        return Inertia::render('Warehouses/Create');
    }

    public function store(StoreWarehouseRequest $request, $locale) 
    {
        $this->warehouseService->create($request->validated());
        
        return redirect()->route('warehouse.list', ['locale' => $locale])
            ->with('success', 'Warehouse created successfully.');
    }

    public function edit($locale, $id) 
    {    
        $warehouse = $this->warehouseService->find($id);

        return Inertia::render('Warehouses/Edit', [
            'warehouse' => $warehouse
        ]);
    }

    public function update(StoreWarehouseRequest $request, $locale, $id) 
    {
        $this->warehouseService->update($id, $request->validated());
        
        return redirect()->route('warehouse.list', ['locale' => $locale])
            ->with('success', 'Warehouse updated successfully.');
    }
}