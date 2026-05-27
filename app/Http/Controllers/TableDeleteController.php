<?php

namespace App\Http\Controllers;

use App\Services\TableService;
use Illuminate\Http\Request;

class TableDeleteController extends Controller
{
    public function __construct(protected TableService $service) {}


    public function destroy($model, $id)
    {
        $this->service->delete($model, $id);

        return response()->json([
            'success' => true
        ]);
    }

     public function destroyLocale($locale, $model, $id)
    {
        $this->service->delete($model, $id);

        return response()->json([
            'success' => true
        ]);
    }
}
