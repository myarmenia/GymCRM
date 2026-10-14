<?php

namespace App\Http\Controllers\SaleContracts;

use App\Http\Controllers\Controller;
use App\Models\SaleContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InternalSaleContractController extends Controller
{
    public function download(Request $request, string $uuid)
    {
        $secret = (string) config('sale_contract.shared_secret');
        abort_if($secret === '', 503);
        $timestamp = $request->header('X-Contract-Timestamp');
        $signature = (string) $request->header('X-Contract-Signature');
        abort_unless(ctype_digit((string) $timestamp) && abs(time() - (int) $timestamp) <= 120, 403);
        $expected = hash_hmac('sha256', "{$uuid}|{$timestamp}", $secret);
        abort_unless(hash_equals($expected, $signature), 403);

        $contract = SaleContract::query()->where('uuid', $uuid)->firstOrFail();
        abort_unless($contract->status === 'ready' && $contract->pdf_path, 404);
        abort_unless(Storage::disk($contract->pdf_disk)->exists($contract->pdf_path), 404);

        return Storage::disk($contract->pdf_disk)->download(
            $contract->pdf_path,
            "contract-{$contract->number}-v{$contract->revision}.pdf",
        );
    }
}
