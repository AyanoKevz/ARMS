<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Work in progress on a Post Training Report ────────────────────────
        // A report asks for seven things at once: a directory encoded row by
        // row, four scanned PDFs, a roster and a video link. That is more than
        // one sitting's work, and until now closing the dialog threw all of it
        // away.
        //
        // A draft is deliberately NOT a post_training_reports row with a
        // 'draft' status: that table is what the evaluator queues off, its
        // ntc_report_id is unique, and half its columns are required. An
        // unfinished submission has no business appearing there at all.
        Schema::create('post_training_drafts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ntc_report_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();
            // One draft per training. Submitting deletes it.

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            // The FATPro it belongs to, checked on every read and write so a
            // draft can never be loaded by anyone else.

            $table->json('payload');
            // Everything typed or chosen so far: the participant rows, the
            // instructor ids, the video link, the remarks, which step they had
            // reached, and a token per staged document. Files cannot live in
            // JSON, so each one is uploaded to ptr_staging on pick and only
            // its token is held here.

            $table->timestamp('saved_at')->useCurrent();
            // Shown back to the FATPro as "Saved 3 minutes ago".

            $table->timestamps();

            $table->index('user_id', 'ptr_drafts_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_training_drafts');
    }
};
