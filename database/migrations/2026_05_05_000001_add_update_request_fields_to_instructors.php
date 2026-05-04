<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * - Adds update_request_fields JSON column.
     * - Resets any stale 'requested'/'allowed' statuses to 'none' (old flow cleanup).
     */
    public function up(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->json('update_request_fields')
                  ->nullable()
                  ->after('update_request_reason')
                  ->comment('JSON array of fields to update: service_agreement, EMS, TM1, NTTC, BOSH');
        });

        // Reset any stale statuses from the old applicant-initiated flow
        DB::table('instructors')
            ->whereIn('update_request_status', ['requested', 'allowed'])
            ->update(['update_request_status' => 'none', 'update_request_reason' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropColumn('update_request_fields');
        });
    }
};
