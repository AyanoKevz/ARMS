<?php

namespace App\Console\Commands;

use App\Models\PendingRegistration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PruneExpiredPendingRegistrations extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'pending-registrations:prune';

    /**
     * The console command description.
     */
    protected $description = 'Delete expired pending registrations and any orphaned pending/{token} upload directories.';

    /**
     * A pending registration is short-lived scratch space: it holds a bcrypt
     * password hash plus the applicant's full form, document and instructor data
     * for the hour between submitting the form and clicking the verification
     * link. Nothing removed it once that hour passed, so rows accumulated
     * indefinitely — along with their uploaded PDFs under storage/app/pending.
     */
    public function handle(): int
    {
        $expired = PendingRegistration::where('expires_at', '<', now())->get();

        foreach ($expired as $pending) {
            Storage::disk('local')->deleteDirectory("pending/{$pending->token}");
            $pending->delete();
        }

        $rowCount = $expired->count();

        // Directories can outlive their row when a prune is interrupted midway,
        // or when a verification failed after the row was already gone.
        $liveTokens = PendingRegistration::pluck('token')->all();
        $orphanCount = 0;

        foreach (Storage::disk('local')->directories('pending') as $directory) {
            $token = basename($directory);

            if (! in_array($token, $liveTokens, true)) {
                Storage::disk('local')->deleteDirectory($directory);
                $orphanCount++;
            }
        }

        $summary = "Pruned {$rowCount} expired pending registration(s) and {$orphanCount} orphaned upload directory(ies).";

        $this->info($summary);

        if ($rowCount > 0 || $orphanCount > 0) {
            Log::info($summary);
        }

        return self::SUCCESS;
    }
}
