<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\InstructorCredential;
use App\Mail\InstructorCredentialExpiryReminderEmail;
use App\Mail\InstructorCredentialExpiredEmail;
use Carbon\Carbon;

class InstructorCredentialExpiryCheck extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'instructor-credential:expiry-check';

    /**
     * The console command description.
     */
    protected $description = 'Auto-expire past-due instructor credentials and send email reminders at 3 months, 2 months, and 1 month before expiration.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();

        $this->autoExpire($today);
        $this->sendReminders($today);

        return self::SUCCESS;
    }

    /**
     * Mark all approved instructor credentials with validity_date < today as expired
     * and notify the applicant via email.
     */
    private function autoExpire(Carbon $today): void
    {
        $expired = InstructorCredential::where('status', 'approved')
            ->whereNotNull('validity_date')
            ->whereDate('validity_date', '<', $today)
            ->with(['instructor.user', 'instructor.application.user'])
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No instructor credentials to expire.');
            return;
        }

        foreach ($expired as $credential) {
            $credential->update(['status' => 'expired']);

            $recipientEmail = $this->getRecipientEmail($credential);
            if ($recipientEmail) {
                try {
                    Mail::to($recipientEmail)
                        ->send(new InstructorCredentialExpiredEmail($credential));
                } catch (\Exception $e) {
                    Log::error('Failed to send instructor credential expired email for ID #' . $credential->id . ': ' . $e->getMessage());
                }
            }
        }

        $this->info("Expired {$expired->count()} instructor credential(s).");
    }

    /**
     * Send 3-month, 2-month, and 1-month expiry reminders for active approved instructor credentials.
     */
    private function sendReminders(Carbon $today): void
    {
        $threeMonthsFromNow = $today->copy()->addMonths(3);
        $twoMonthsFromNow   = $today->copy()->addMonths(2);
        $oneMonthFromNow    = $today->copy()->addMonth();

        // ── 3-month reminders ────────────────────────────────────────────
        $threeMonthCandidates = InstructorCredential::where('status', 'approved')
            ->whereNotNull('validity_date')
            ->whereNull('reminder_3mo_sent_at')
            ->whereDate('validity_date', '<=', $threeMonthsFromNow)
            ->whereDate('validity_date', '>=', $today)
            ->with(['instructor.user', 'instructor.application.user'])
            ->get();

        $sent3 = 0;
        foreach ($threeMonthCandidates as $credential) {
            $recipientEmail = $this->getRecipientEmail($credential);
            if ($recipientEmail) {
                try {
                    Mail::to($recipientEmail)
                        ->send(new InstructorCredentialExpiryReminderEmail($credential, '3 months'));

                    $credential->update(['reminder_3mo_sent_at' => now()]);
                    $sent3++;
                } catch (\Exception $e) {
                    Log::error('Failed to send 3-month reminder for instructor credential ID #' . $credential->id . ': ' . $e->getMessage());
                }
            }
        }

        // ── 2-month reminders ────────────────────────────────────────────
        $twoMonthCandidates = InstructorCredential::where('status', 'approved')
            ->whereNotNull('validity_date')
            ->whereNull('reminder_2mo_sent_at')
            ->whereDate('validity_date', '<=', $twoMonthsFromNow)
            ->whereDate('validity_date', '>', $oneMonthFromNow)
            ->with(['instructor.user', 'instructor.application.user'])
            ->get();

        $sent2 = 0;
        foreach ($twoMonthCandidates as $credential) {
            $recipientEmail = $this->getRecipientEmail($credential);
            if ($recipientEmail) {
                try {
                    Mail::to($recipientEmail)
                        ->send(new InstructorCredentialExpiryReminderEmail($credential, '2 months'));

                    $credential->update(['reminder_2mo_sent_at' => now()]);
                    $sent2++;
                } catch (\Exception $e) {
                    Log::error('Failed to send 2-month reminder for instructor credential ID #' . $credential->id . ': ' . $e->getMessage());
                }
            }
        }

        // ── 1-month reminders ────────────────────────────────────────────
        $oneMonthCandidates = InstructorCredential::where('status', 'approved')
            ->whereNotNull('validity_date')
            ->whereNull('reminder_1mo_sent_at')
            ->whereDate('validity_date', '<=', $oneMonthFromNow)
            ->whereDate('validity_date', '>=', $today)
            ->with(['instructor.user', 'instructor.application.user'])
            ->get();

        $sent1 = 0;
        foreach ($oneMonthCandidates as $credential) {
            $recipientEmail = $this->getRecipientEmail($credential);
            if ($recipientEmail) {
                try {
                    Mail::to($recipientEmail)
                        ->send(new InstructorCredentialExpiryReminderEmail($credential, '1 month'));

                    $credential->update(['reminder_1mo_sent_at' => now()]);
                    $sent1++;
                } catch (\Exception $e) {
                    Log::error('Failed to send 1-month reminder for instructor credential ID #' . $credential->id . ': ' . $e->getMessage());
                }
            }
        }

        $this->info("Sent {$sent3} three-month reminder(s), {$sent2} two-month reminder(s), and {$sent1} one-month reminder(s) for instructor credentials.");
    }

    private function getRecipientEmail(InstructorCredential $credential): ?string
    {
        if ($credential->instructor && $credential->instructor->user && $credential->instructor->user->email) {
            return $credential->instructor->user->email;
        }

        if ($credential->instructor && $credential->instructor->application && $credential->instructor->application->user && $credential->instructor->application->user->email) {
            return $credential->instructor->application->user->email;
        }

        return null;
    }
}
