<?php

namespace App\Support;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Log;

final class PdfQrCode
{
    private static ?SvgWriter $svgWriter = null;

    private static ?PngWriter $pngWriter = null;

    public static function dataUri(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $qrCode = QrCode::create($value)->setSize(240)->setMargin(8);

        if (function_exists('imagecreatetruecolor')) {
            try {
                self::$pngWriter ??= new PngWriter();

                return self::$pngWriter->write($qrCode)->getDataUri();
            } catch (\Throwable $e) {
                Log::warning('PDF QR PNG generation failed; falling back to SVG.', [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        try {
            self::$svgWriter ??= new SvgWriter();

            return self::$svgWriter->write(
                $qrCode,
                options: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true]
            )->getDataUri();
        } catch (\Throwable $e) {
            Log::error('PDF QR generation failed.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
