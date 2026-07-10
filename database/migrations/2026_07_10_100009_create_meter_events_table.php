<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * METER (Slice 7) — the unified, append-only measurement log.
 *
 * Every operation in the app that produces signal emits one row here: a gym rep
 * (hit/miss + latency), a gym-session summary, a lesson completion, … Future
 * layers (SRS, PULSE) write to the same schema, so cross-layer patterns become
 * queryable. Mirrors the wiki's METER event schema (layer · operation ·
 * metric_type · metric_value · mode · context). Rows are never mutated except by
 * the idempotent `meter:backfill` upsert (keyed on source_key).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('occurred_at');

            $table->string('layer');            // performance | retrieval | ...
            $table->string('operation');        // gym-rep | gym-session | lesson-complete | ...
            $table->string('metric_type');      // hit-miss | session-summary | completion
            $table->string('mode')->nullable(); // e.g. the gym slug (oracle::distributional analog)

            // Promoted hot fields for the common performance aggregations.
            $table->boolean('correct')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->float('value')->nullable();          // generic scalar (accuracy, 1 for a completion)

            $table->json('context')->nullable();         // {gym_id, gym_item_id, lesson_id, course_id, stage, …}
            $table->string('source_key')->nullable()->unique(); // "gym_attempt:123" — dedup for backfill

            $table->timestamps();

            $table->index(['user_id', 'layer', 'occurred_at']);
            $table->index(['user_id', 'mode', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_events');
    }
};
