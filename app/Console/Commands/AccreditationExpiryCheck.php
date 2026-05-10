<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Accreditation;
use App\Mail\AccreditationExpiryReminderEmail;
use App\Mail\AccreditationExpiredEmail;
use Carbon\Carbon;

class AccreditationExpiryCheck extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'accreditation:expiry-check';

    /**
     * The console command description.
     */
    protected $description = 'Auto-expire past-due accreditations and send email reminders at 3 months and 1 month before expiration.';

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
     * Mark all active accreditations with validity_date < today as expired
     * and notify each holder via email.
     */
    private function autoExpire(Carbon $today): void
    {
        $expired = Accreditation::where('status', 'active')
            ->whereDate('validity_date', '<', $today)
            ->with('user')
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No accreditations to expire.');
            return;
        }

        foreach ($expired as $accreditation) {
            $accreditation->update(['status' => 'expired']);

            // Send expiration email
            if ($accreditation->user && $accreditation->user->email) {
                try {
                    Mail::to($accreditation->user->email)
                        ->send(new AccreditationExpiredEmail($accreditation));
                } catch (\Exception $e) {
                    Log::error('Failed to send accreditation expired email for #' . $accreditation->accreditation_number . ': ' . $e->getMessage());
                }
            }
        }

        $this->info("Expired {$expired->count()} accreditation(s).");
    }

    /**
     * Send 3-month and 1-month expiry reminders for active accreditations.
     */
    private function sendReminders(Carbon $today): void
    {
        $threeMonthsFromNow = $today->copy()->addMonths(3);
        $oneMonthFromNow    = $today->copy()->addMonth();

        // ── 3-month reminders ────────────────────────────────────────────
        $threeMonthCandidates = Accreditation::where('status', 'active')
            ->whereNull('reminder_3mo_sent_at')
            ->whereDate('validity_date', '<=', $threeMonthsFromNow)
            ->whereDate('validity_date', '>=', $today)
            ->with('user')
            ->get();

        $sent3 = 0;
        foreach ($threeMonthCandidates as $accreditation) {
            if ($accreditation->user && $accreditation->user->email) {
                try {
                    Mail::to($accreditation->user->email)
                        ->send(new AccreditationExpiryReminderEmail($accreditation, '3 months'));

                    $accreditation->update(['reminder_3mo_sent_at' => now()]);
                    $sent3++;
                } catch (\Exception $e) {
                    Log::error('Failed to send 3-month reminder for #' . $accreditation->accreditation_number . ': ' . $e->getMessage());
                }
            }
        }

        // ── 1-month reminders ────────────────────────────────────────────
        $oneMonthCandidates = Accreditation::where('status', 'active')
            ->whereNull('reminder_1mo_sent_at')
            ->whereDate('validity_date', '<=', $oneMonthFromNow)
            ->whereDate('validity_date', '>=', $today)
            ->with('user')
            ->get();

        $sent1 = 0;
        foreach ($oneMonthCandidates as $accreditation) {
            if ($accreditation->user && $accreditation->user->email) {
                try {
                    Mail::to($accreditation->user->email)
                        ->send(new AccreditationExpiryReminderEmail($accreditation, '1 month'));

                    $accreditation->update(['reminder_1mo_sent_at' => now()]);
                    $sent1++;
                } catch (\Exception $e) {
                    Log::error('Failed to send 1-month reminder for #' . $accreditation->accreditation_number . ': ' . $e->getMessage());
                }
            }
        }

        $this->info("Sent {$sent3} three-month reminder(s) and {$sent1} one-month reminder(s).");
    }
}
