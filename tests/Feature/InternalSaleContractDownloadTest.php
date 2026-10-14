<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalSaleContractDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_pdf_endpoint_requires_a_fresh_valid_hmac(): void
    {
        config()->set('sale_contract.shared_secret', 'test-secret');
        $uuid = '320cf506-ec4b-4aeb-90a1-ae3130a11c53';
        $url = "/internal/sale-contracts/{$uuid}/pdf";

        $this->get($url)->assertForbidden();
        $this->withHeaders([
            'X-Contract-Timestamp' => (string) (time() - 300),
            'X-Contract-Signature' => 'invalid',
        ])->get($url)->assertForbidden();

        $timestamp = (string) time();
        $this->withHeaders([
            'X-Contract-Timestamp' => $timestamp,
            'X-Contract-Signature' => hash_hmac('sha256', "{$uuid}|{$timestamp}", 'test-secret'),
        ])->get($url)->assertNotFound();
    }
}
