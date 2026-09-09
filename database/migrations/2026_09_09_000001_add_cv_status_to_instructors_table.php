<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give the instructor CV its own evaluation status.
     *
     * The CV previously had no status of its own — the instructor row's `status`
     * column covers the service agreement — so an evaluator who received the wrong
     * CV had no way to reject just that. The only lever was rejecting the service
     * agreement, which asked the applicant to re-upload the wrong file.
     *
     * `cv_status` mirrors the values used everywhere else (pending / approved /
     * rejected / returned). An instructor with no `cv_path` is treated as "not
     * applicable" by the approval gates rather than as pending.
     */
    public function up(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->string('cv_status')->default('pending')->after('cv_path');
            $table->text('cv_remarks')->nullable()->after('cv_status');
        });
    }

    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropColumn(['cv_status', 'cv_remarks']);
        });
    }
};
