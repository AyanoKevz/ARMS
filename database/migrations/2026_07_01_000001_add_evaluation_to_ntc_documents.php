<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ntc_documents', function (Blueprint $table) {
            $table->string('status')
                  ->default('pending');

            $table->text('remarks')->nullable();

            $table->foreignId('evaluated_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('evaluated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ntc_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evaluated_by');
            $table->dropColumn(['status', 'remarks', 'evaluated_at']);
        });
    }
};
