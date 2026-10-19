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
    protected $description = 'Auto-expire past-due accreditations and send email reminders at 3 months, 2 months, and 1 month before expiration.';

    /**
     * Reminder milestones, furthest first: period label => sent-at column.
     */
    private const MILESTONES = [
        '3 months' => 'reminder_3mo_sent_at',
        '2 months' => 'reminder_2mo_sent_at',
        '1 month'  => 'reminder_1mo_sent_at',
    ];

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
            ->where('validity_date', '<', $today->toDateString())
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
     * Send the 3-month, 2-month and 1-month expiry reminders.
     *
     * Only the closest milestone that has been reached is ever sent. An
     * accreditation first seen with 20 days left gets the 1-month reminder
     * alone, and the 3- and 2-month ones are marked spent without sending —
     * "expiring in 3 months" next to "expiring in 1 month" on the same day
     * reads as a system fault, and the earlier notices are moot by then.
     */
    private function sendReminders(Carbon $today): void
    {
        $candidates = Accreditation::where('status', 'active')
            ->where('validity_date', '>=', $today->toDateString())
            ->where('validity_date', '<=', $today->copy()->addMonths(3)->toDateString())
            ->where(function ($q) {
                foreach (self::MILESTONES as $column) {
                    $q->orWhereNull($column);
                }
            })
            ->with('user')
            ->get();

        $sent = array_fill_keys(array_keys(self::MILESTONES), 0);

        foreach ($candidates as $accreditation) {
            $period = $this->currentMilestone($accreditation, $today);
            $column = self::MILESTONES[$period];

            if ($accreditation->{$column}) {
                continue;
            }

            if (!$accreditation->user || !$accreditation->user->email) {
                continue;
            }

            try {
                Mail::to($accreditation->user->email)
                    ->send(new AccreditationExpiryReminderEmail($accreditation, $period));
            } catch (\Exception $e) {
                // Left unmarked so tomorrow's run tries again.
                Log::error("Failed to send {$period} reminder for #" . $accreditation->accreditation_number . ': ' . $e->getMessage());
                continue;
            }

            // Mark this milestone and every earlier one still pending.
            $updates = [];
            foreach (self::MILESTONES as $milestone => $milestoneColumn) {
                if (!$accreditation->{$milestoneColumn}) {
                    $updates[$milestoneColumn] = now();
                }
                if ($milestone === $period) {
                    break;
                }
            }
            $accreditation->update($updates);

            $sent[$period]++;
        }

        $this->info("Sent {$sent['3 months']} three-month reminder(s), {$sent['2 months']} two-month reminder(s), and {$sent['1 month']} one-month reminder(s).");
    }

    /**
     * The closest milestone the accreditation has reached.
     */
    private function currentMilestone(Accreditation $accreditation, Carbon $today): string
    {
        $validity = Carbon::parse($accreditation->validity_date)->startOfDay();

        if ($validity->lessThanOrEqualTo($today->copy()->addMonth())) {
            return '1 month';
        }

        if ($validity->lessThanOrEqualTo($today->copy()->addMonths(2))) {
            return '2 months';
        }

        return '3 months';
    }
}
