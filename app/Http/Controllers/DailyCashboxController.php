<?php

namespace App\Http\Controllers;

use App\Services\Finance\DailyCashboxService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DailyCashboxController extends Controller
{
    public function index(Request $request, DailyCashboxService $service): Response
    {
        return Inertia::render('Finance/DailyCashbox', $service->pageData($request->user()));
    }
}
