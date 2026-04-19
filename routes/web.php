<?php

use Illuminate\Support\Facades\Route;


//LANDING PAGE 
Route::get('/', function () {
    return view('LandingPage.index');
});

use App\Http\Controllers\RegistrationController;
// Registration — show form
Route::get('/register', function () {
    return view('LandingPage.register');
})->name('register');

// Registration — process form & send verification email
Route::post('/register', [RegistrationController::class, 'store'])->name('register.store');

// Email verification link
Route::get('/verify-email/{token}', [RegistrationController::class, 'verify'])->name('register.verify');

use App\Http\Controllers\AuthController;

Route::get('/login', function () {
    return view('LandingPage.login');
})->name('login');

Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Dashboard placeholders
Route::middleware('auth')->group(function () {
    Route::get('/applicant/dashboard', function () {
        return view('applicant.dashboard');
    })->name('applicant.dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/hcd/dashboard', function () {
            return view('admin.hcd.dashboard');
        })->name('hcd.dashboard');

        use App\Http\Controllers\Admin\HCD\ApplicationController as HCDApplicationController;
        Route::get('/hcd/applications/pending', [HCDApplicationController::class, 'pending'])->name('hcd.applications.pending');
        Route::post('/hcd/applications/{application}/update-evaluation', [HCDApplicationController::class, 'updateToEvaluation'])->name('hcd.applications.update_evaluation');
    });
});

use App\Http\Controllers\TrackingController;
// Track Registration
Route::get('/track-application', [TrackingController::class, 'index'])->name('track');
Route::post('/track-application/document/{document}', [TrackingController::class, 'resubmitDocument'])->name('track.resubmit');



// Password Reset Routes
use App\Http\Controllers\PasswordResetController;

Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
