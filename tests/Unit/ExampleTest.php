<?php

namespace Tests\Unit;

use App\Support\PdfQrCode;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }

    public function test_pdf_qr_code_is_generated_inline_without_a_network_request(): void
    {
        $dataUri = PdfQrCode::dataUri('NGSCHA133155');

        $this->assertNotNull($dataUri);
        $this->assertMatchesRegularExpression('#^data:image/(?:png|svg\+xml);base64,#', $dataUri);

        [$metadata, $payload] = explode(',', $dataUri, 2);
        $image = base64_decode($payload, true);
        $this->assertIsString($image);

        if ($metadata === 'data:image/png;base64') {
            $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $image);
        } else {
            $this->assertStringContainsString('<svg', $image);
            $this->assertStringContainsString('<path', $image);
        }
    }

    public function test_bulk_slip_template_does_not_reference_the_remote_qr_service(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/resources/views/pdf/bulk-enrollment-slip.blade.php');

        $this->assertIsString($template);
        $this->assertStringNotContainsString('api.qrserver.com', $template);
    }
}
