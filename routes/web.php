<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\Admin\HCD\ApplicationController as HCDApplicationController;

// LANDING PAGE 
Route::get('/', function () {
    if (Auth::check()) {
        return AuthController::redirectAuthenticatedUser(Auth::user());
    }
    return view('landing.index');
})->middleware('prevent-back-history');

// Registration — show form
Route::get('/register', function () {
    if (Auth::check()) {
        return AuthController::redirectAuthenticatedUser(Auth::user());
    }
    return view('landing.register');
})->name('register')->middleware('prevent-back-history');

// Registration — process form & send verification email
Route::post('/register', [RegistrationController::class, 'store'])->name('register.store');

// Email verification link
Route::get('/verify-email/{token}', [RegistrationController::class, 'verify'])->name('register.verify');

Route::get('/login', function () {
    if (Auth::check()) {
        return AuthController::redirectAuthenticatedUser(Auth::user());
    }
    return view('landing.login');
})->name('login')->middleware('prevent-back-history');

Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Dashboard placeholders
Route::middleware(['auth', 'prevent-back-history'])->group(function () {
    Route::prefix('applicant')->name('applicant.')->group(function () {
        Route::get('/dashboard', function () {
            return view('applicant.dashboard');
        })->name('dashboard');

        // Example of multi-portal support by accreditation type
        Route::get('/practitioners/dashboard', function () {
            return view('applicant.practitioners.dashboard');
        })->name('practitioners.dashboard');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::prefix('hcd')->name('hcd.')->group(function () {
            Route::get('/dashboard', function () {
                return view('admin.hcd.dashboard');
            })->name('dashboard');
            Route::get('/applications/pending', [HCDApplicationController::class, 'pending'])->name('applications.pending');
            Route::post('/applications/{application}/update-evaluation', [HCDApplicationController::class, 'updateToEvaluation'])->name('applications.update_evaluation');
        });

        // Example of multi-portal support by division
        Route::prefix('scd')->name('scd.')->group(function () {
            Route::get('/dashboard', function () {
                return view('admin.scd.dashboard');
            })->name('dashboard');
        });
    });
});

// Track Registration
Route::get('/track-application', [TrackingController::class, 'index'])->name('track');
Route::post('/track-application/document/{document}', [TrackingController::class, 'resubmitDocument'])->name('track.resubmit');

// Password Reset Routes
Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
