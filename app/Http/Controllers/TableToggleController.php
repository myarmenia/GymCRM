<?php

namespace App\Http\Controllers;

use App\Services\TableService;
use Illuminate\Http\Request;

class TableToggleController extends Controller
{
    public function __construct(protected TableService $service) {}

    public function toggleChange(Request $request, $model, $id)
    {
        $column = $request->input('column', 'active');

        $active = $this->service->toggleChange($model, $id, $column);

        return response()->json([
            'success' => true,
            'active' => $active
        ]);
    }

    public function toggleChangeLocale(Request $request, $locale, $model, $id)
    {
        $column = $request->input('column', 'active');

        $active = $this->service->toggleChange($model, $id, $column);

        return response()->json([
            'success' => true,
            'active' => $active
        ]);
    }
}