<?php

namespace Tests\Feature;

use App\Services\Exports\ReportExcelExportService;
use Tests\TestCase;

class ReportExcelExportServiceTest extends TestCase
{
    public function test_it_renders_optional_summary_before_table_data(): void
    {
        $response = app(ReportExcelExportService::class)->download(
            rows: [
                ['customer' => 'Արամ', 'paid_amount' => 15000],
            ],
            columns: [
                ['key' => 'customer', 'title' => 'Հաճախորդ'],
                ['key' => 'paid_amount', 'title' => 'Վճարված'],
            ],
            filename: 'membership-sales-report.xls',
            title: 'Աբոնեմենտների հաշվետվություն',
            summary: [
                'title' => 'Այս էջի ամփոփում',
                'rows' => [
                    ['label' => 'Վճարված գումար', 'value' => 15000],
                ],
            ],
        );

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('Այս էջի ամփոփում', $content);
        $this->assertStringContainsString('Վճարված գումար', $content);
        $this->assertStringContainsString('Հաճախորդ', $content);
        $this->assertLessThan(
            strpos($content, 'Հաճախորդ'),
            strpos($content, 'Այս էջի ամփոփում')
        );
    }
}
