<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminInvitationController;
use App\Http\Controllers\Admin\HCD\ApplicationController as HCDApplicationController;
use App\Http\Controllers\Applicant\InstructorController as ApplicantInstructorController;
use App\Http\Controllers\Applicant\RenewalController;
use App\Http\Controllers\Applicant\NtcController;
use App\Http\Controllers\Admin\HCD\NtcController as AdminNtcController;

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
Route::post('/register', [RegistrationController::class, 'store'])->name('register.store')->middleware('throttle:5,1');

// Email verification link
Route::get('/verify-email/{token}', [RegistrationController::class, 'verify'])->name('register.verify');

Route::get('/login', function () {
    if (Auth::check()) {
        return AuthController::redirectAuthenticatedUser(Auth::user());
    }
    return view('landing.login');
})->name('login')->middleware('prevent-back-history');

Route::post('/login', [AuthController::class, 'login'])->name('login.post')->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Dashboard placeholders
Route::middleware(['auth', 'prevent-back-history'])->group(function () {
    // ── Profile routes (available to any authenticated user) ──────────
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/change-password', [ProfileController::class, 'changePassword'])->name('profile.change_password');
    Route::get('/profile/{user}', [ProfileController::class, 'show'])->name('profile.show');

    Route::prefix('applicant')->name('applicant.')->group(function () {
        Route::get('/dashboard', function () {
            return view('applicant.dashboard');
        })->name('dashboard');

        // FATPro Instructor Management
        Route::get('/instructors', [ApplicantInstructorController::class, 'index'])->name('instructors.index');
        Route::get('/instructors/{instructor}', [ApplicantInstructorController::class, 'show'])->name('instructors.show');
        Route::post('/instructors/{instructor}/update-name', [ApplicantInstructorController::class, 'updateName'])->name('instructors.update_name');
        Route::post('/instructors/{instructor}/batch-update', [ApplicantInstructorController::class, 'batchUpdate'])->name('instructors.batch_update')->middleware('throttle:5,1');
        // Note: instructor update requests are now admin-initiated only

        // Renewal / Reinstatement
        Route::get('/renewal', [RenewalController::class, 'index'])->name('renewal.index');
        Route::post('/renewal', [RenewalController::class, 'store'])->name('renewal.store')->middleware('throttle:5,1');
        Route::get('/renewal/reupload', [RenewalController::class, 'reupload'])->name('renewal.reupload');
        Route::post('/renewal/reupload', [RenewalController::class, 'submitReupload'])->name('renewal.reupload.store')->middleware('throttle:5,1');
        Route::post('/renewal/submit-payment', [RenewalController::class, 'submitPaymentPortal'])->name('renewal.submit_payment')->middleware('throttle:5,1');

        // Notice to Conduct (NTC)
        Route::get('/ntc', [NtcController::class, 'index'])->name('ntc.index');
        Route::post('/ntc', [NtcController::class, 'store'])->name('ntc.store')->middleware('throttle:10,1');
        Route::get('/ntc/documents/{document}/view', [NtcController::class, 'serveDocument'])->name('ntc.document.view');
        Route::post('/ntc/documents/{document}/reupload', [NtcController::class, 'reuploadDocument'])->name('ntc.document.reupload')->middleware('throttle:10,1');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        
        // Notification Routes
        Route::get('/notifications/{id}/read', function ($id) {
            $notification = auth()->user()->notifications()->findOrFail($id);
            $notification->markAsRead();
            return redirect($notification->data['link'] ?? '/admin/hcd/dashboard');
        })->name('notifications.read');

        Route::post('/notifications/mark-all-read', function () {
            auth()->user()->unreadNotifications->markAsRead();
            return back();
        })->name('notifications.mark_all_read');

        Route::prefix('hcd')->name('hcd.')->group(function () {
            Route::get('/dashboard', [HCDApplicationController::class, 'dashboard'])->name('dashboard');
            Route::get('/applications/pending', [HCDApplicationController::class, 'pending'])->name('applications.pending');
            Route::get('/applications/under-review', [HCDApplicationController::class, 'underReview'])->name('applications.under_review');
            Route::get('/applications/archived', [HCDApplicationController::class, 'archived'])->name('applications.archived');
            Route::post('/applications/{application}/update-evaluation', [HCDApplicationController::class, 'updateToEvaluation'])->name('applications.update_evaluation');
            Route::post('/applications/{application}/schedule-interview', [HCDApplicationController::class, 'scheduleInterview'])->name('applications.schedule_interview');
            Route::post('/applications/{application}/start-interview', [HCDApplicationController::class, 'startInterview'])->name('applications.start_interview');
            Route::post('/applications/{application}/stop-interview', [HCDApplicationController::class, 'stopInterview'])->name('applications.stop_interview');
            Route::get('/interviews/check-slot', [HCDApplicationController::class, 'checkInterviewSlot'])->name('interviews.check_slot');
            Route::post('/applications/{application}/finalize-evaluation', [HCDApplicationController::class, 'finalizeEvaluation'])->name('applications.finalize_evaluation');
            Route::post('/applications/{application}/evaluate-item', [HCDApplicationController::class, 'evaluateItem'])->name('applications.evaluate_item');
            Route::post('/documents/{document}/evaluate', [HCDApplicationController::class, 'evaluateDocument'])->name('documents.evaluate');
            Route::get('/interviews/pending', [HCDApplicationController::class, 'pendingInterview'])->name('interviews.pending');
            Route::get('/interviews/scheduled', [HCDApplicationController::class, 'scheduledInterviews'])->name('interviews.scheduled');
            Route::post('/applications/{application}/interview-result', [HCDApplicationController::class, 'recordInterviewResult'])->name('applications.interview_result');
            Route::post('/instructors/{instructor}/request-update', [HCDApplicationController::class, 'requestInstructorUpdate'])->name('instructors.request_update');

            // Payment and Recommendation
            Route::get('/applications/awaiting-payment', [HCDApplicationController::class, 'awaitingPaymentList'])->name('applications.awaiting_payment');
            Route::get('/applications/releasing', [HCDApplicationController::class, 'releasingList'])->name('applications.releasing');

            Route::get('/applications/{application}', [HCDApplicationController::class, 'show'])->name('applications.show');
            Route::match(['get', 'post'], '/applications/{application}/generate-recommendation', [HCDApplicationController::class, 'generateRecommendationPDF'])->name('applications.generate_recommendation');
            Route::post('/applications/{application}/request-payment', [HCDApplicationController::class, 'requestPayment'])->name('applications.request_payment');
            Route::post('/applications/{application}/evaluate-payment', [HCDApplicationController::class, 'evaluatePayment'])->name('applications.evaluate_payment');
            Route::post('/applications/{application}/archive-payment', [HCDApplicationController::class, 'archiveFromPayment'])->name('applications.archive_payment');

            // Directories (using main ApplicationController)
            Route::get('/directory/admins', [HCDApplicationController::class, 'adminsList'])->name('directory.admins');
            Route::post('/directory/admins/invite', [HCDApplicationController::class, 'inviteAdmin'])->name('directory.admins.invite');
            Route::get('/directory/fatpros', [HCDApplicationController::class, 'activeFatprosList'])->name('directory.fatpros');
            Route::get('/directory/fatpros/inactive', [HCDApplicationController::class, 'inactiveFatprosList'])->name('directory.fatpros.inactive');

            // Accreditation Certificate PDF
            Route::get('/accreditations/{accreditation}/certificate', [HCDApplicationController::class, 'downloadCertificate'])->name('accreditations.certificate');
            Route::post('/accreditations/{accreditation}/revoke', [HCDApplicationController::class, 'revokeAccreditation'])->name('accreditations.revoke');
            Route::post('/accreditations/{accreditation}/upload-scanned', [HCDApplicationController::class, 'uploadScannedCertificate'])->name('accreditations.upload_scanned');
            Route::get('/accreditations/{accreditation}/view-scanned', [HCDApplicationController::class, 'viewScannedCertificate'])->name('accreditations.view_scanned');

            // Renewal / Reinstatement
            Route::get('/renewal/pending', [HCDApplicationController::class, 'renewalPending'])->name('renewal.pending');
            Route::get('/renewal/under-review', [HCDApplicationController::class, 'renewalUnderReview'])->name('renewal.under_review');

            // Reports
            Route::get('/reports/ntc', [AdminNtcController::class, 'index'])->name('reports.ntc.index');
            Route::get('/reports/ntc/{ntcReport}', [AdminNtcController::class, 'show'])->name('reports.ntc.show');
            Route::post('/reports/ntc/documents/{document}/evaluate', [AdminNtcController::class, 'evaluateDocument'])->name('reports.ntc.documents.evaluate');
            Route::post('/reports/ntc/{ntcReport}/finalize-evaluation', [AdminNtcController::class, 'finalizeEvaluation'])->name('reports.ntc.finalize_evaluation');
        });
    });
});

// Track Registration
Route::get('/track-application', [TrackingController::class, 'index'])->name('track');
Route::post('/track-application/resubmit-all', [TrackingController::class, 'resubmitAll'])->name('track.resubmit.all')->middleware('throttle:5,1');
Route::post('/track-application/submit-payment', [TrackingController::class, 'submitPaymentPublic'])->name('track.submit_payment')->middleware('throttle:5,1');

// Document and Instructor file viewers (auth required, but NO prevent-back-history to avoid PDF header errors)
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/hcd/documents/{document}/view', [HCDApplicationController::class, 'serveDocument'])->name('admin.hcd.documents.view');
    Route::get('/admin/hcd/instructors/credentials/{credential}/view', [HCDApplicationController::class, 'serveInstructorCredential'])->name('admin.hcd.instructors.credentials.view');
    Route::get('/admin/hcd/instructors/service-agreement/{instructor}/view', [HCDApplicationController::class, 'serveInstructorServiceAgreement'])->name('admin.hcd.instructors.service_agreement.view');
    Route::get('/admin/hcd/payments/{payment}/view/{fileType}', [HCDApplicationController::class, 'servePaymentFile'])->name('admin.hcd.payments.view');
    Route::get('/admin/hcd/reports/ntc/documents/{document}/view', [AdminNtcController::class, 'serveDocument'])->name('admin.hcd.reports.ntc.document.view');

    // Applicant-side file viewers (no prevent-back-history to allow PDF streaming)
    Route::get('/applicant/instructors/credentials/{credential}/view', [ApplicantInstructorController::class, 'serveCredential'])->name('applicant.instructors.credentials.view');
    Route::get('/applicant/instructors/{instructor}/service-agreement/view', [ApplicantInstructorController::class, 'serveServiceAgreement'])->name('applicant.instructors.service_agreement.view');
    Route::get('/applicant/documents/{document}/view', [RenewalController::class, 'serveDocument'])->name('applicant.documents.view');
    Route::get('/applicant/user-documents/{userDocument}/view', [RenewalController::class, 'serveUserDocument'])->name('applicant.user_documents.view');
});

// Password Reset Routes
Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email')->middleware('throttle:3,1');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update')->middleware('throttle:5,1');

// Admin Invitation Routes
Route::get('/admin/setup-password/{token}', [AdminInvitationController::class, 'setupPassword'])->name('admin.setup_password');
Route::post('/admin/setup-password/{token}', [AdminInvitationController::class, 'storePassword'])->name('admin.setup_password.store');
