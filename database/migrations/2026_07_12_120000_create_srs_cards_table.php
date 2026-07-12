<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SRS retention layer (the deferred Slice-8 seed): one card per
 * (learner, gym item), scheduled on a Leitner ladder. Every gym exposure —
 * drill or review — reschedules the card via App\Support\Srs; "due" cards
 * feed review sessions and the per-module retention read. Coverage answers
 * "did they learn it"; this table answers "do they still know it".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('srs_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('gym_item_id')->constrained('gym_items')->cascadeOnDelete();
            $table->unsignedTinyInteger('box')->default(1); // Leitner box → App\Support\Srs::INTERVALS
            $table->timestamp('due_at')->index();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->unsignedSmallInteger('streak')->default(0); // consecutive correct
            $table->unsignedSmallInteger('lapses')->default(0); // drops back to box 1
            $table->timestamps();

            $table->unique(['user_id', 'gym_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('srs_cards');
    }
};
