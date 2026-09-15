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

        if (!class_exists(QrCode::class)) {
            Log::error('PDF QR generation is unavailable because the endroid/qr-code package is missing.');

            return null;
        }

        try {
            $qrCode = QrCode::create($value)->setSize(240)->setMargin(8);
        } catch (\Throwable $e) {
            Log::error('PDF QR code object creation failed.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (function_exists('imagecreatetruecolor') && class_exists(PngWriter::class)) {
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

        if (!class_exists(SvgWriter::class)) {
            Log::error('PDF QR SVG generation is unavailable because the SVG writer class is missing.');

            return null;
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
