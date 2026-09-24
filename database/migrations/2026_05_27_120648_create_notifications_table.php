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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');

            // Spelled out rather than morphs(), so the composite below can be
            // the table's only index on these columns instead of a second one
            // duplicating its prefix. Notifications are written on every status
            // change, so an extra index is a cost on the write path.
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');

            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The bell in the portal header runs on every authenticated page
            // load: unread rows for one user, newest first. This covers the
            // lookup, the unread filter and the ordering in one index, and its
            // leading pair still serves plain morph lookups.
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at', 'created_at'],
                'idx_notifications_inbox'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
