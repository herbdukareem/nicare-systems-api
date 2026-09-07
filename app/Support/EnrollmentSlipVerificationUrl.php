<?php

namespace App\Support;

use App\Models\Enrollee;
use Illuminate\Support\Facades\URL;

final class EnrollmentSlipVerificationUrl
{
    public static function for(Enrollee $enrollee): string
    {
        $relativeUrl = URL::signedRoute('enrollment-slip.verify', [
            'enrollee' => $enrollee->getRouteKey(),
        ], absolute: false);

        return rtrim(url('/'), '/') . $relativeUrl;
    }
}
