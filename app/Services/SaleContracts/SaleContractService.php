<?php

namespace App\Services\SaleContracts;

use App\Jobs\GenerateSaleContractJob;
use App\Models\Gym;
use App\Models\MembershipSale;
use App\Models\SaleContract;
use App\Models\SaleContractSetting;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SaleContractService
{
    public function settingsForGym(Gym $gym): SaleContractSetting
    {
        $defaults = json_decode(file_get_contents(resource_path('contracts/default.json')), true, 512, JSON_THROW_ON_ERROR);

        return SaleContractSetting::query()->firstOrCreate(['gym_id' => $gym->id], [
            'contract_title' => 'Մարմնամարզական ծառայությունների մատուցման պայմանագիր',
            'legal_name' => $gym->name,
            'service_address' => $gym->address,
            'phone' => $gym->phone,
            'clauses' => $defaults['clauses'],
            'rules' => $defaults['rules'],
            'rules_title' => 'Ակումբի ներքին կանոնակարգ',
            'appendices' => [],
        ]);
    }

    public function issueForSale(MembershipSale $sale, bool $forceRevision = false): ?SaleContract
    {
        return DB::transaction(function () use ($sale, $forceRevision): ?SaleContract {
            $settings = SaleContractSetting::query()
                ->where('gym_id', $sale->gym_id)
                ->lockForUpdate()
                ->first();

            // Existing live gyms keep selling until their legal details and first number are reviewed.
            if (! $settings?->enabled) {
                return null;
            }

            $latest = SaleContract::query()
                ->where('membership_sale_id', $sale->id)
                ->orderByDesc('revision')
                ->first();
            $snapshot = $this->snapshot($sale, $settings);
            if ($latest) {
                $snapshot['contract_number'] = $latest->number;
            }

            if ($latest && ! $forceRevision && $latest->snapshot === $snapshot) {
                if ($latest->status !== 'ready') {
                    GenerateSaleContractJob::dispatch($latest->id)->afterCommit();
                }

                return $latest;
            }

            $number = $latest?->number ?? $settings->next_number;
            $snapshot['contract_number'] = $number;
            if (! $latest) {
                $settings->next_number = $number + 1;
                $settings->save();
            }

            $contract = SaleContract::query()->create([
                'uuid' => (string) Str::uuid(),
                'gym_id' => $sale->gym_id,
                'membership_sale_id' => $sale->id,
                'number' => $number,
                'revision' => ($latest?->revision ?? 0) + 1,
                'paired_black_revision' => $latest?->paired_black_revision,
                'status' => 'pending',
                'price' => $snapshot['price'],
                'snapshot' => $snapshot,
                ...$this->snapshotColumns($snapshot),
            ]);

            GenerateSaleContractJob::dispatch($contract->id)->afterCommit();

            return $contract;
        });
    }

    public function snapshot(MembershipSale $sale, SaleContractSetting $settings): array
    {
        // Audit snapshots preload a deliberately narrow person select; reload full data for the legal document.
        $sale->load(['person', 'gym', 'membershipPlan.translations', 'personMemberships']);
        $priceColumn = config('sale_contract.price_column');
        $price = $sale->getAttribute($priceColumn);
        if ($price === null) {
            throw new RuntimeException("Missing {$priceColumn} for membership sale {$sale->id}.");
        }

        $person = $sale->person;
        $plan = $sale->membershipPlan;
        $membership = $sale->personMemberships->first();
        $planName = $plan?->translations?->firstWhere('locale', 'hy')?->name
            ?? $plan?->translations?->first()?->name
            ?? $plan?->name
            ?? '';

        return [
            'contract_number' => null, // Filled from the immutable contract row while rendering.
            'contract_title' => $settings->contract_title ?: 'Մարմնամարզական ծառայությունների մատուցման պայմանագիր',
            'issued_at' => ($sale->sold_at ?? $sale->created_at)?->format('Y-m-d'),
            'gym' => [
                'name' => $settings->legal_name,
                'director_name' => $settings->director_name,
                'tax_number' => $settings->tax_number,
                'registration_number' => $settings->registration_number,
                'legal_address' => $settings->legal_address,
                'service_address' => $settings->service_address,
                'city' => $settings->city,
                'phone' => $settings->phone,
                'bank_details' => $settings->bank_details,
            ],
            'person' => [
                'name' => trim(($person?->name ?? '').' '.($person?->surname ?? '')),
                'birth_date' => $this->dateString($person?->birth_date),
                'phone' => $person?->phone,
                'address' => $person?->address,
                'passport_series' => $person?->passport_series,
                'passport_issued_by' => $person?->passport_issued_by,
                'passport_issued_at' => $this->dateString($person?->passport_issued_at),
            ],
            'membership' => [
                'name' => $planName,
                'start_date' => $this->dateString($membership?->start_date),
                'end_date' => $this->dateString($membership?->end_date),
                'visits_limit' => $plan?->visits_limit,
            ],
            'price' => number_format((float) $price, 2, '.', ''),
            'template_version' => $settings->template_version,
            'clauses' => $settings->clauses ?? [],
            'rules' => $settings->rules ?? [],
            'rules_title' => $settings->rules_title ?: 'Ակումբի ներքին կանոնակարգ',
            'appendices' => $settings->appendices ?? [],
        ];
    }

    public function render(SaleContract $contract): SaleContract
    {
        if ($contract->status === 'ready') {
            return $contract;
        }

        try {
            if ($contract->snapshot === null) {
                $settings = SaleContractSetting::query()->where('gym_id', $contract->gym_id)->firstOrFail();
                $snapshot = $this->snapshot($contract->sale, $settings);
                $snapshot['price'] = number_format((float) $contract->price, 2, '.', '');
                $snapshot['contract_number'] = $contract->number;
                $contract->snapshot = $snapshot;
                $contract->fill($this->snapshotColumns($contract->snapshot));
                $contract->save();
            }

            $options = new Options;
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);
            $options->set('isHtml5ParserEnabled', true);
            $pdf = new Dompdf($options);
            $pdf->loadHtml(view('contracts.sale', [
                'contract' => $contract,
                'snapshot' => $contract->snapshot,
            ])->render());
            $pdf->setPaper('A4');
            $pdf->render();
            $bytes = $pdf->output();

            $disk = (string) config('sale_contract.disk', 'local');
            $gymUuid = $contract->gym?->uuid ?? (string) $contract->gym_id;
            $saleUuid = $contract->sale?->uuid ?? (string) $contract->membership_sale_id;
            $path = "sale-contracts/{$gymUuid}/{$saleUuid}/{$contract->number}-v{$contract->revision}.pdf";
            if (! Storage::disk($disk)->put($path, $bytes)) {
                throw new RuntimeException('Unable to store the contract PDF.');
            }

            $contract->forceFill([
                'status' => 'ready',
                'pdf_disk' => $disk,
                'pdf_path' => $path,
                'pdf_sha256' => hash('sha256', $bytes),
                'error' => null,
                'generated_at' => now(),
            ])->save();

            return $contract;
        } catch (Throwable $exception) {
            $contract->forceFill(['status' => 'failed', 'error' => $exception->getMessage()])->save();
            throw $exception;
        }
    }

    private function dateString(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toDateString() : null;
    }

    private function snapshotColumns(array $snapshot): array
    {
        return [
            'contract_title' => $snapshot['contract_title'],
            'issued_on' => $snapshot['issued_at'],
            'gym_legal_name' => $snapshot['gym']['name'],
            'gym_director_name' => $snapshot['gym']['director_name'],
            'gym_tax_number' => $snapshot['gym']['tax_number'],
            'gym_registration_number' => $snapshot['gym']['registration_number'],
            'gym_legal_address' => $snapshot['gym']['legal_address'],
            'gym_service_address' => $snapshot['gym']['service_address'],
            'gym_city' => $snapshot['gym']['city'],
            'gym_phone' => $snapshot['gym']['phone'],
            'gym_bank_details' => $snapshot['gym']['bank_details'],
            'client_name' => $snapshot['person']['name'],
            'client_phone' => $snapshot['person']['phone'],
            'client_address' => $snapshot['person']['address'],
            'client_birth_date' => $snapshot['person']['birth_date'],
            'client_passport_series' => $snapshot['person']['passport_series'],
            'client_passport_issued_by' => $snapshot['person']['passport_issued_by'],
            'client_passport_issued_at' => $snapshot['person']['passport_issued_at'],
            'membership_name' => $snapshot['membership']['name'],
            'membership_start_date' => $snapshot['membership']['start_date'],
            'membership_end_date' => $snapshot['membership']['end_date'],
            'membership_visits_limit' => $snapshot['membership']['visits_limit'],
            'template_version' => $snapshot['template_version'],
        ];
    }
}
