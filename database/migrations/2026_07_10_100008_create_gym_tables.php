<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gyms (Slice 6) — spec-driven recognition drills ported from the wiki's
 * web-gym engine. A gym is a timed classification session; each play-through
 * logs per-learner telemetry (accuracy + latency per attempt), which is the
 * seed of METER (Slice 7). Optionally linked to a course as its "Practice".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gyms', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('mode')->default('recognition'); // recognition/execution/retrieval/stress
            $table->text('target_reflex')->nullable();
            $table->text('description')->nullable();

            // "Practice" link back to a course (nullable — gyms can stand alone).
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained('domains')->nullOnDelete();

            // Session + scoring config (from the spec).
            $table->unsignedSmallInteger('timer_seconds')->default(8);
            $table->unsignedSmallInteger('round_count')->default(20);
            $table->unsignedInteger('latency_target_ms')->default(6000);
            $table->float('pass_accuracy')->default(0.80);
            $table->float('promote_accuracy')->default(0.85);
            $table->json('stages')->nullable(); // [{code,label,min_accuracy,max_latency_ms}]

            $table->string('status')->default('draft')->index(); // draft/published
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('source')->nullable();
            $table->timestamps();
        });

        Schema::create('gym_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->text('prompt');
            $table->json('choices');            // ordered list of answer options
            $table->string('correct');          // the correct option (must be in choices)
            $table->text('explanation')->nullable();
            $table->text('detail')->nullable(); // near-miss / next-move note
            $table->string('difficulty')->nullable();
            $table->json('tags')->nullable();
            $table->unsignedInteger('latency_target_ms')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('gym_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('total')->default(0);    // rounds answered
            $table->unsignedSmallInteger('correct')->default(0);
            $table->float('accuracy')->nullable();
            $table->unsignedInteger('median_latency_ms')->nullable();
            $table->string('stage_code')->nullable();             // computed stage read
            $table->timestamps();
        });

        Schema::create('gym_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_session_id')->constrained('gym_sessions')->cascadeOnDelete();
            $table->foreignId('gym_item_id')->nullable()->constrained('gym_items')->nullOnDelete();
            $table->string('selected')->nullable(); // null = timed out / no answer
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_attempts');
        Schema::dropIfExists('gym_sessions');
        Schema::dropIfExists('gym_items');
        Schema::dropIfExists('gyms');
    }
};
