<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('accreditations', function (Blueprint $table) {
            $table->timestamp('reminder_3mo_sent_at')->nullable()->after('status');
            $table->timestamp('reminder_1mo_sent_at')->nullable()->after('reminder_3mo_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accreditations', function (Blueprint $table) {
            $table->dropColumn(['reminder_3mo_sent_at', 'reminder_1mo_sent_at']);
        });
    }
};
