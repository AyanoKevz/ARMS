<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ntc_documents', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'returned'])
                  ->default('pending')
                  ->after('uploaded_at');

            $table->text('remarks')->nullable()->after('status');

            $table->foreignId('evaluated_by')
                  ->nullable()
                  ->after('remarks')
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('evaluated_at')->nullable()->after('evaluated_by');
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
