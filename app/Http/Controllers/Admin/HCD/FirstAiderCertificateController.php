<?php

namespace App\Http\Controllers\Admin\HCD;

use App\Http\Controllers\Controller;
use App\Mail\FirstAiderCertificateEmail;
use App\Models\FirstAiderCertificateSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The evaluator's side of a first-aider certificate renewal, from the
 * application page.
 *
 * Decided by the evaluator assigned to the application the accreditation came
 * from — the one who evaluated it in the first place. When nobody is assigned,
 * any Evaluator may decide, so a renewal can never be stranded.
 */
class FirstAiderCertificateController extends Controller
{
    /** Open the uploaded PDF. Anyone who may decide on it may also read it; Team Lead may read. */
    public function view(FirstAiderCertificateSubmission $submission)
    {
        $role = strtolower(Auth::user()?->adminProfile?->adminRole?->name ?? '');

        if (!in_array($role, ['evaluator', 'team lead'], true)) {
            abort(403, 'Unauthorized action.');
        }

        if (!$submission->file_path || !Storage::disk('local')->exists($submission->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('local')->response(
            $submission->file_path,
            $submission->original_filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function approve(FirstAiderCertificateSubmission $submission)
    {
        $this->authorizeReviewer($submission);

        if (!$submission->isPending()) {
            return back()->with('error', 'This first-aider certificate renewal has already been decided.');
        }

        $accreditation = $submission->accreditation;

        try {
            DB::transaction(function () use ($submission, $accreditation) {
                $submission->update([
                    'status'      => 'approved',
                    'remarks'     => null,
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);

                // The renewal becomes the certificate on file, with its new date.
                $accreditation->applyFirstAiderCertificate($submission);
            });
        } catch (\Throwable $e) {
            Log::error('First-aider certificate approval failed: ' . $e->getMessage());
            return back()->with('error', 'The renewal could not be approved. Please try again.');
        }

        $this->notifyFatpro($submission->refresh(), FirstAiderCertificateEmail::KIND_APPROVED);

        return back()->with('success', 'The renewed first-aider certificate was approved and is now on file.');
    }

    public function reject(Request $request, FirstAiderCertificateSubmission $submission)
    {
        $this->authorizeReviewer($submission);

        if (!$submission->isPending()) {
            return back()->with('error', 'This first-aider certificate renewal has already been decided.');
        }

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $submission->update([
            'status'      => 'rejected',
            'remarks'     => $validated['remarks'] ?? null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        // The certificate on file is untouched; the rejected upload is removed
        // so the FATPro's folder holds only what is current.
        if ($submission->file_path && Storage::disk('local')->exists($submission->file_path)) {
            Storage::disk('local')->delete($submission->file_path);
        }
        $submission->update(['file_path' => null]);

        $this->notifyFatpro($submission, FirstAiderCertificateEmail::KIND_REJECTED);

        return back()->with('success', 'The renewal was rejected. The FATPro has been notified to upload a corrected certificate.');
    }

    private function authorizeReviewer(FirstAiderCertificateSubmission $submission): void
    {
        $user = Auth::user();
        $role = strtolower($user?->adminProfile?->adminRole?->name ?? '');

        if ($role !== 'evaluator') {
            abort(403, 'Unauthorized action. Only the evaluator of this application can decide on the renewal.');
        }

        $assignedId = $submission->accreditation?->application?->assigned_evaluator_id;

        if ($assignedId && $assignedId !== $user->id) {
            abort(403, 'Unauthorized action. Only the evaluator assigned to this application can decide on the renewal.');
        }
    }

    private function notifyFatpro(FirstAiderCertificateSubmission $submission, string $kind): void
    {
        try {
            $accreditation = $submission->accreditation;
            $user          = $accreditation?->user;

            if (!$user?->email) {
                return;
            }

            Mail::to($user->email)->send(new FirstAiderCertificateEmail($accreditation, $kind, $submission));

            $user->notifications()->create([
                'id'   => Str::uuid(),
                'type' => 'App\Notifications\FirstAiderCertificateNotification',
                'data' => [
                    'accreditation_id' => $accreditation->id,
                    'message'          => $kind === FirstAiderCertificateEmail::KIND_APPROVED
                        ? 'Your renewed first-aider certificate was approved.'
                        : 'Your renewed first-aider certificate was rejected. Please upload a corrected one.',
                    'link'             => '/applicant/dashboard',
                ],
                'read_at' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('First-aider certificate decision notice failed: ' . $e->getMessage());
        }
    }
}
