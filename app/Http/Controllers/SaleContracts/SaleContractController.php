<?php

namespace App\Http\Controllers\SaleContracts;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\MembershipSale;
use App\Models\SaleContract;
use App\Models\SaleContractSetting;
use App\Services\SaleContracts\SaleContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SaleContractController extends Controller
{
    public function __construct(private readonly SaleContractService $contracts) {}

    public function show(string $locale, int $sale)
    {
        $membershipSale = $this->sale($sale);

        return Inertia::render('SaleContracts/Show', [
            'sale' => $membershipSale->load(['person', 'gym']),
            'contracts' => SaleContract::query()
                ->where('membership_sale_id', $sale)
                ->orderByDesc('revision')
                ->get(['id', 'number', 'revision', 'status', 'price', 'generated_at', 'error']),
        ]);
    }

    public function download(string $locale, int $sale, int $contract)
    {
        $membershipSale = $this->sale($sale);
        $document = SaleContract::query()
            ->where('membership_sale_id', $membershipSale->id)
            ->findOrFail($contract);
        abort_unless($document->status === 'ready' && $document->pdf_path, 404);
        abort_unless(Storage::disk($document->pdf_disk)->exists($document->pdf_path), 404);

        return Storage::disk($document->pdf_disk)->download(
            $document->pdf_path,
            "contract-{$document->number}-v{$document->revision}.pdf",
        );
    }

    public function reissue(string $locale, int $sale)
    {
        $membershipSale = $this->sale($sale);
        abort_unless(auth()->user()->hasAnyRole(['owner', 'admin', 'super_admin', 'sales_manager']), 403);
        abort_if(SaleContract::query()->where('membership_sale_id', $sale)->where('status', 'pending')->exists(), 409);
        $contract = $this->contracts->issueForSale($membershipSale, forceRevision: true);
        abort_if($contract === null, 422, 'Enable this gym\'s contract settings first.');

        return back()->with('success', __('backend_messages.sale_contract_revision_queued'));
    }

    public function retry(string $locale, int $sale, int $contract)
    {
        $membershipSale = $this->sale($sale);
        abort_unless(auth()->user()->hasAnyRole(['owner', 'admin', 'super_admin', 'sales_manager']), 403);
        $document = SaleContract::query()
            ->where('membership_sale_id', $membershipSale->id)
            ->findOrFail($contract);
        abort_unless($document->status === 'failed', 409);
        $this->contracts->render($document);

        return back()->with('success', __('backend_messages.sale_contract_created'));
    }

    public function settings(string $locale, int $gym)
    {
        $gymModel = $this->editableGym($gym);
        $settings = $this->contracts->settingsForGym($gymModel);

        return Inertia::render('SaleContracts/Settings', [
            'gym' => $gymModel->only(['id', 'name', 'uuid']),
            'settings' => $settings,
        ]);
    }

    public function updateSettings(Request $request, string $locale, int $gym)
    {
        $gymModel = $this->editableGym($gym);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'next_number' => ['required', 'integer', 'min:1'],
            'contract_title' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'director_name' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'legal_address' => ['nullable', 'string', 'max:500'],
            'service_address' => ['required', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'bank_details' => ['nullable', 'string'],
            'clauses' => ['required', 'array'],
            'clauses.*.level' => ['required', 'integer', 'in:0,1'],
            'clauses.*.text' => ['required', 'string'],
            'rules' => ['required', 'array'],
            'rules.*.level' => ['required', 'integer', 'in:0,1'],
            'rules.*.text' => ['required', 'string'],
            'rules_title' => ['required', 'string', 'max:255'],
            'appendices' => ['present', 'array'],
            'appendices.*.number' => ['required', 'integer', 'min:2', 'distinct'],
            'appendices.*.title' => ['required', 'string', 'max:255'],
            'appendices.*.items' => ['present', 'array'],
            'appendices.*.items.*.level' => ['required', 'integer', 'in:0,1'],
            'appendices.*.items.*.text' => ['required', 'string'],
        ]);

        DB::transaction(function () use ($gymModel, $data): void {
            $settings = $this->contracts->settingsForGym($gymModel);
            $settings = SaleContractSetting::query()->lockForUpdate()->findOrFail($settings->id);
            $lastNumber = (int) SaleContract::query()->where('gym_id', $gymModel->id)->max('number');
            if ((int) $data['next_number'] <= $lastNumber) {
                throw ValidationException::withMessages([
                    'next_number' => "Հաջորդ համարը պետք է մեծ լինի արդեն տրված {$lastNumber} համարից։",
                ]);
            }

            $templateFields = ['contract_title', 'legal_name', 'director_name', 'tax_number', 'registration_number',
                'legal_address', 'service_address', 'city', 'phone', 'bank_details', 'clauses', 'rules', 'rules_title', 'appendices'];
            $settings->fill($data);
            if ($settings->isDirty($templateFields)) {
                $settings->template_version++;
            }
            $settings->save();
        });

        return back()->with('success', 'Պայմանագրի կարգավորումները պահպանվեցին։');
    }

    private function sale(int $id): MembershipSale
    {
        $sale = MembershipSale::query()->findOrFail($id);
        $user = auth()->user();
        abort_unless($user->hasRole('owner') || (int) $user->gym_id === (int) $sale->gym_id, 403);

        return $sale;
    }

    private function editableGym(int $id): Gym
    {
        $gym = Gym::query()->findOrFail($id);
        $user = auth()->user();
        abort_unless($user->hasAnyRole(['owner', 'admin', 'super_admin']), 403);
        abort_unless($user->hasRole('owner') || (int) $user->gym_id === (int) $gym->id, 403);

        return $gym;
    }
}
