<?php

namespace App\Http\Controllers\Warehouses; 

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouses\StoreWarehouseRequest;
use App\Services\Warehouses\WarehouseService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WarehouseController extends Controller
{
    public function __construct(
        protected WarehouseService $warehouseService
    ) {}

    private function authorizeWarehouseManagement(): void
    {
        abort_unless(
            Auth::user()?->hasAnyRole(['owner', 'admin', 'super_admin', 'sales_manager', 'manager']),
            403,
            'You are not allowed to manage warehouses.'
        );
    }

    public function list()
    {
        $warehouses = $this->warehouseService->all();
        
        return Inertia::render('Warehouses/List', [
            'warehouses' => $warehouses
        ]);
    }

    public function create()
    {
        $this->authorizeWarehouseManagement();

        return Inertia::render('Warehouses/Create');
    }

    public function store(StoreWarehouseRequest $request, $locale) 
    {
        $this->authorizeWarehouseManagement();

        $this->warehouseService->create($request->validated());
        
        return redirect()->route('warehouse.list', ['locale' => $locale])
            ->with('success', 'Warehouse created successfully.');
    }

    public function edit($locale, $id) 
    {    
        $this->authorizeWarehouseManagement();

        $warehouse = $this->warehouseService->find($id);

        return Inertia::render('Warehouses/Edit', [
            'warehouse' => $warehouse
        ]);
    }

    public function update(StoreWarehouseRequest $request, $locale, $id) 
    {
        $this->authorizeWarehouseManagement();

        $this->warehouseService->update($id, $request->validated());
        
        return redirect()->route('warehouse.list', ['locale' => $locale])
            ->with('success', 'Warehouse updated successfully.');
    }
}
