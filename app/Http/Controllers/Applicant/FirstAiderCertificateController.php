<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Mail\FirstAiderCertificateEmail;
use App\Models\Accreditation;
use App\Models\FirstAiderCertificateSubmission;
use App\Models\User;
use App\Support\ApplicantStoragePath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * A FATPro renewing their first-aider certificate during an accreditation.
 *
 * Any time the accreditation is active — before or after the certificate has
 * expired — they upload the renewed certificate with its new validity date.
 * It goes to the application's assigned evaluator; until it is approved the
 * certificate already on file stays in force.
 */
class FirstAiderCertificateController extends Controller
{
    private const MAX_FILE_KB = 15360; // 15 MB, as for instructor credentials

    public function store(Request $request)
    {
        $user = Auth::user();

        $accreditation = Accreditation::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (!$accreditation || !$accreditation->hasFirstAiderCertificate()) {
            return back()->with('error', 'Only an active FATPro accreditation has a first-aider certificate to renew.');
        }

        if ($accreditation->pendingFirstAiderCertificate()->exists()) {
            return back()->with('error', 'Your renewed first-aider certificate is still being reviewed. You can upload another once the evaluator has decided on it.');
        }

        $validated = $request->validate([
            'certificate'   => ['required', 'file', 'mimes:pdf', 'max:' . self::MAX_FILE_KB],
            'validity_date' => ['required', 'date', 'after:today'],
        ], [
            'certificate.required'   => 'Please upload your renewed first-aider certificate.',
            'certificate.mimes'      => 'The first-aider certificate must be a PDF file.',
            'certificate.max'        => 'The first-aider certificate must not exceed 15 MB.',
            'validity_date.required' => 'Please enter the validity date of the renewed certificate.',
            'validity_date.after'    => 'The validity date must be in the future — this is the date the renewed certificate expires.',
        ]);

        $accreditation->loadMissing(['accreditationType', 'application.assignedEvaluator']);

        $file = $request->file('certificate');
        $path = $file->storeAs(
            ApplicantStoragePath::documents($accreditation->accreditationType->name ?? null, $user->id),
            'first_aider_certificate_' . time() . '.pdf',
            'local'
        );

        $submission = FirstAiderCertificateSubmission::create([
            'accreditation_id'  => $accreditation->id,
            'user_id'           => $user->id,
            'file_path'         => $path,
            'original_filename' => $file->getClientOriginalName(),
            'validity_date'     => $validated['validity_date'],
            'status'            => 'pending',
        ]);

        $this->notifyEvaluator($accreditation, $submission);

        return redirect()->route('applicant.dashboard')
            ->with('success', 'Your renewed first-aider certificate has been submitted for review.');
    }

    /**
     * The application's assigned evaluator reviews it. With nobody assigned,
     * every Evaluator is told so it does not sit unseen.
     */
    private function notifyEvaluator(Accreditation $accreditation, FirstAiderCertificateSubmission $submission): void
    {
        try {
            $assigned   = $accreditation->application?->assignedEvaluator;
            $recipients = $assigned
                ? collect([$assigned])
                : User::whereHas('adminProfile.adminRole', fn ($q) => $q->where('name', 'Evaluator'))->get();

            if ($recipients->isEmpty()) {
                Log::warning("First-aider certificate renewal #{$submission->id} has no evaluator to notify.");
                return;
            }

            Mail::to($recipients->pluck('email')->all())
                ->send(new FirstAiderCertificateEmail($accreditation, FirstAiderCertificateEmail::KIND_SUBMITTED, $submission));

            $name = $accreditation->user->name ?? 'A FATPro';

            foreach ($recipients as $recipient) {
                $recipient->notifications()->create([
                    'id'   => Str::uuid(),
                    'type' => 'App\Notifications\FirstAiderCertificateSubmittedNotification',
                    'data' => [
                        'accreditation_id' => $accreditation->id,
                        'message'          => "{$name} uploaded a renewed first-aider certificate for review.",
                        'link'             => '/admin/hcd/applications/' . $accreditation->application_id,
                    ],
                    'read_at' => null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('First-aider certificate renewal notification failed: ' . $e->getMessage());
        }
    }
}
