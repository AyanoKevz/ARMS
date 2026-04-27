<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
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
    // ── Profile routes (available to any authenticated user) ──────────
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/{user}', [ProfileController::class, 'show'])->name('profile.show');

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
            Route::get('/dashboard', [HCDApplicationController::class, 'dashboard'])->name('dashboard');
            Route::get('/applications/pending', [HCDApplicationController::class, 'pending'])->name('applications.pending');
            Route::get('/applications/under-review', [HCDApplicationController::class, 'underReview'])->name('applications.under_review');
            Route::post('/applications/{application}/update-evaluation', [HCDApplicationController::class, 'updateToEvaluation'])->name('applications.update_evaluation');
            Route::post('/applications/{application}/schedule-interview', [HCDApplicationController::class, 'scheduleInterview'])->name('applications.schedule_interview');
            Route::post('/applications/{application}/finalize-evaluation', [HCDApplicationController::class, 'finalizeEvaluation'])->name('applications.finalize_evaluation');
            Route::post('/documents/{document}/evaluate', [HCDApplicationController::class, 'evaluateDocument'])->name('documents.evaluate');
            Route::get('/applications/{application}', [HCDApplicationController::class, 'show'])->name('applications.show');
            Route::get('/interviews/pending', [HCDApplicationController::class, 'pendingInterview'])->name('interviews.pending');
            Route::get('/interviews/scheduled', [HCDApplicationController::class, 'scheduledInterviews'])->name('interviews.scheduled');
            Route::post('/applications/{application}/interview-result', [HCDApplicationController::class, 'recordInterviewResult'])->name('applications.interview_result');

            // Directories (using main ApplicationController)
            Route::get('/directory/admins', [HCDApplicationController::class, 'adminsList'])->name('directory.admins');
            Route::post('/directory/admins/invite', [HCDApplicationController::class, 'inviteAdmin'])->name('directory.admins.invite');
            Route::get('/directory/fatpros', [HCDApplicationController::class, 'activeFatprosList'])->name('directory.fatpros');
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
Route::post('/track-application/resubmit-all', [TrackingController::class, 'resubmitAll'])->name('track.resubmit.all');

// Document file viewer (auth required, but NO prevent-back-history so PDFs open correctly)
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/hcd/documents/{document}/view', [HCDApplicationController::class, 'serveDocument'])->name('admin.hcd.documents.view');
});

// Password Reset Routes
Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

// Admin Invitation Routes
Route::get('/admin/setup-password/{token}', [App\Http\Controllers\AdminInvitationController::class, 'setupPassword'])->name('admin.setup_password');
Route::post('/admin/setup-password/{token}', [App\Http\Controllers\AdminInvitationController::class, 'storePassword'])->name('admin.setup_password.store');
