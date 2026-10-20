<?php

namespace App\Console\Commands;

use App\Mail\FirstAiderCertificateEmail;
use App\Models\Accreditation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Daily first-aider certificate check for every active accreditation.
 *
 * The certificate's validity date is the PREM_DATE document of the application
 * behind the accreditation. The FATPro is reminded 3, 2 and 1 month before it,
 * and told once it has passed — by e-mail and in the portal. Each notice goes
 * once per validity date: approving a renewed certificate resets them.
 *
 * Nothing is sent while a renewal is already waiting on the evaluator, since
 * the FATPro has done what the notice would ask. Notify-only: an expired
 * certificate restricts nothing.
 */
class FirstAiderCertificateExpiryCheck extends Command
{
    protected $signature = 'first-aider-certificate:expiry-check';

    protected $description = 'Remind FATPros 3/2/1 months before their first-aider certificate expires, and notify them once it has.';

    public function handle(): int
    {
        $today = Carbon::today();
        $sent  = 0;

        // Every active accreditation with its certificate date, in one query
        // rather than one per accreditation.
        $rows = DB::table('accreditations')
            ->join('application_documents', 'application_documents.application_id', '=', 'accreditations.application_id')
            ->join('document_fields', 'document_fields.id', '=', 'application_documents.document_field_id')
            ->join('user_documents', 'user_documents.id', '=', 'application_documents.user_document_id')
            ->where('accreditations.status', 'active')
            ->where('document_fields.code', Accreditation::FA_CERT_DATE_FIELD)
            ->whereNotNull('user_documents.value')
            ->pluck('user_documents.value', 'accreditations.id');

        if ($rows->isEmpty()) {
            $this->info('No first-aider certificates to check.');
            return self::SUCCESS;
        }

        Accreditation::whereIn('id', $rows->keys())
            ->with(['user', 'pendingFirstAiderCertificate'])
            ->chunkById(100, function ($accreditations) use ($rows, $today, &$sent) {
                foreach ($accreditations as $accreditation) {
                    if ($accreditation->pendingFirstAiderCertificate) {
                        continue;
                    }

                    try {
                        $validity = Carbon::parse($rows[$accreditation->id])->startOfDay();
                    } catch (\Throwable $e) {
                        continue;
                    }

                    if ($this->notifyFor($accreditation, $validity, $today)) {
                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} first-aider certificate " . Str::plural('notice', $sent) . '.');

        return self::SUCCESS;
    }

    /**
     * Send the one notice this accreditation is due today, if any. The nearest
     * reminder wins, and the earlier ones are marked done with it, so a date
     * first seen a month out never also gets the 3- and 2-month notices.
     */
    private function notifyFor(Accreditation $accreditation, Carbon $validity, Carbon $today): bool
    {
        if ($validity->lessThan($today)) {
            if ($accreditation->fa_cert_expired_notified_at) {
                return false;
            }

            return $this->send($accreditation, FirstAiderCertificateEmail::KIND_EXPIRED, null, [
                'fa_cert_expired_notified_at'  => now(),
                'fa_cert_reminder_1mo_sent_at' => $accreditation->fa_cert_reminder_1mo_sent_at ?? now(),
                'fa_cert_reminder_2mo_sent_at' => $accreditation->fa_cert_reminder_2mo_sent_at ?? now(),
                'fa_cert_reminder_3mo_sent_at' => $accreditation->fa_cert_reminder_3mo_sent_at ?? now(),
            ]);
        }

        $stages = [
            ['1 month',  $today->copy()->addMonth(),     'fa_cert_reminder_1mo_sent_at', ['fa_cert_reminder_2mo_sent_at', 'fa_cert_reminder_3mo_sent_at']],
            ['2 months', $today->copy()->addMonths(2),   'fa_cert_reminder_2mo_sent_at', ['fa_cert_reminder_3mo_sent_at']],
            ['3 months', $today->copy()->addMonths(3),   'fa_cert_reminder_3mo_sent_at', []],
        ];

        foreach ($stages as [$timeframe, $threshold, $column, $earlier]) {
            if ($validity->greaterThan($threshold)) {
                continue;
            }

            if ($accreditation->{$column}) {
                return false;
            }

            $marks = [$column => now()];
            foreach ($earlier as $earlierColumn) {
                $marks[$earlierColumn] = $accreditation->{$earlierColumn} ?? now();
            }

            return $this->send($accreditation, FirstAiderCertificateEmail::KIND_REMINDER, $timeframe, $marks);
        }

        return false;
    }

    /**
     * E-mail and notify the FATPro, then record the notice as sent. A mail
     * failure is logged and not recorded, so tomorrow's run tries again.
     */
    private function send(Accreditation $accreditation, string $kind, ?string $timeframe, array $marks): bool
    {
        $user = $accreditation->user;

        if (!$user?->email) {
            return false;
        }

        try {
            Mail::to($user->email)->send(new FirstAiderCertificateEmail($accreditation, $kind, null, $timeframe));

            $user->notifications()->create([
                'id'   => Str::uuid(),
                'type' => 'App\Notifications\FirstAiderCertificateNotification',
                'data' => [
                    'accreditation_id' => $accreditation->id,
                    'message'          => $kind === FirstAiderCertificateEmail::KIND_EXPIRED
                        ? 'Your first-aider certificate has expired. Please upload your renewed certificate.'
                        : "Your first-aider certificate expires in {$timeframe}. Please upload your renewed certificate.",
                    'link'             => '/applicant/dashboard',
                ],
                'read_at' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error("First-aider certificate notice for accreditation #{$accreditation->id} failed: " . $e->getMessage());
            return false;
        }

        $accreditation->update($marks);

        return true;
    }
}
