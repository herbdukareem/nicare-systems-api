<?php

namespace App\Support;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

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

        try {
            $qrCode = QrCode::create($value)->setSize(240)->setMargin(8);

            if (function_exists('imagecreatetruecolor')) {
                self::$pngWriter ??= new PngWriter();

                return self::$pngWriter->write($qrCode)->getDataUri();
            }

            self::$svgWriter ??= new SvgWriter();

            return self::$svgWriter->write(
                $qrCode,
                options: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true]
            )->getDataUri();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
