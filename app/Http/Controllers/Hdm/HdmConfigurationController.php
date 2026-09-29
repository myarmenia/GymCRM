<?php

namespace App\Http\Controllers\Hdm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hdm\StoreHdmCashierRequest;
use App\Http\Requests\Hdm\StoreHdmConfigRequest;
use App\Http\Requests\Hdm\UpdateHdmCashierRequest;
use App\Http\Requests\Hdm\UpdateHdmConfigRequest;
use App\Models\Gym;
use App\Models\HdmCashier;
use App\Models\HdmConfig;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HdmConfigurationController extends Controller
{
    public function index(): Response
    {
        $configs = HdmConfig::query()
            ->with('gym:id,name')
            ->withCount('cashiers')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('HdmConfigurations/Index', [
            'configs' => $configs,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('HdmConfigurations/Create', [
            'gyms' => Gym::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreHdmConfigRequest $request): RedirectResponse
    {
        $config = HdmConfig::query()->create($request->validated());

        return redirect()
            ->route('hdm-configurations.edit', [
                'locale' => app()->getLocale(),
                'hdmConfig' => $config->id,
            ])
            ->with('success', __('backend_messages.hdm_config_created'));
    }

    public function edit(string $locale, HdmConfig $hdmConfig): Response
    {
        $hdmConfig->load([
            'gym:id,name',
            'cashiers' => fn ($query) => $query
                ->with('user:id,name,surname,email')
                ->latest('id'),
        ]);

        return Inertia::render('HdmConfigurations/Edit', [
            'config' => $hdmConfig,
            'users' => User::query()
                ->where('gym_id', $hdmConfig->gym_id)
                ->orderBy('name')
                ->orderBy('surname')
                ->get(['id', 'name', 'surname', 'email', 'active']),
        ]);
    }

    public function update(
        UpdateHdmConfigRequest $request,
        string $locale,
        HdmConfig $hdmConfig,
    ): RedirectResponse {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $hdmConfig->update($data);

        return back()->with('success', __('backend_messages.hdm_config_updated'));
    }

    public function destroy(string $locale, HdmConfig $hdmConfig): RedirectResponse
    {
        if ($hdmConfig->operations()->exists()) {
            return back()->with('error', __('backend_messages.hdm_config_has_operations'));
        }

        DB::transaction(function () use ($hdmConfig): void {
            $hdmConfig->cashiers()->each(fn (HdmCashier $cashier) => $cashier->delete());
            $hdmConfig->delete();
        });

        return redirect()
            ->route('hdm-configurations.index', ['locale' => $locale])
            ->with('success', __('backend_messages.hdm_config_deleted'));
    }

    public function toggleStatus(string $locale, HdmConfig $hdmConfig): RedirectResponse
    {
        $hdmConfig->update(['status' => ! $hdmConfig->status]);

        return back()->with('success', __('backend_messages.hdm_config_status_updated'));
    }

    public function storeCashier(
        StoreHdmCashierRequest $request,
        string $locale,
        HdmConfig $hdmConfig,
    ): RedirectResponse {
        $hdmConfig->cashiers()->create([
            ...$request->validated(),
            'gym_id' => $hdmConfig->gym_id,
        ]);

        return back()->with('success', __('backend_messages.hdm_cashier_created'));
    }

    public function updateCashier(
        UpdateHdmCashierRequest $request,
        string $locale,
        HdmConfig $hdmConfig,
        HdmCashier $cashier,
    ): RedirectResponse {
        $this->ensureCashierBelongsToConfig($hdmConfig, $cashier);
        $data = $request->validated();

        if (empty($data['pin'])) {
            unset($data['pin']);
        }

        $cashier->update($data);

        return back()->with('success', __('backend_messages.hdm_cashier_updated'));
    }

    public function destroyCashier(
        string $locale,
        HdmConfig $hdmConfig,
        HdmCashier $cashier,
    ): RedirectResponse {
        $this->ensureCashierBelongsToConfig($hdmConfig, $cashier);
        $cashier->delete();

        return back()->with('success', __('backend_messages.hdm_cashier_deleted'));
    }

    private function ensureCashierBelongsToConfig(HdmConfig $config, HdmCashier $cashier): void
    {
        abort_unless((int) $cashier->hdm_config_id === (int) $config->id, 404);
    }
}
