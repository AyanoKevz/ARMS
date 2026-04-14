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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accreditation_type_id')->constrained()->cascadeOnDelete();
            $table->enum('application_type', [
                'new',
                'renewal',
                'reinstatement'
            ]);
            $table->foreignId('handled_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tracking_number')->unique();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();
            $table->string('file_path'); // ONLY file path (PDF)
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'for_revision'
            ])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('application_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('application_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('status_id')->constrained('application_statuses')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->json('required_updates')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('application_statuses');
        Schema::dropIfExists('application_status_logs');
    }
};
