<?php

use App\Http\Controllers\EnrollmentSlipVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/verify/enrollment-slip/{enrollee}', EnrollmentSlipVerificationController::class)
    ->middleware(['signed:relative', 'throttle:60,1'])
    ->name('enrollment-slip.verify');

Route::get('/{any}', function () {
       return view('welcome');
})->where('any', '.*');
