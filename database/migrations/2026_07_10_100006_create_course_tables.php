<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The learning layer built ON TOP of the imported wiki (Slice 5 — Courses).
 *
 * A course is an authored, ordered path through existing wiki pages:
 *   course → modules → lessons(→ page). Courses are DB-authored (not markdown) —
 *   they are the "interactive layer" the wiki import deliberately leaves to Laravel.
 *   `courses:scaffold` seeds a draft course from a `para: project` roadmap page;
 *   admins curate it in Filament and publish it.
 *
 * Prerequisites (course → course) model the mastery-tree DAG. In this slice they
 * are a SOFT gate: surfaced to the learner as "recommended first", never enforced
 * (real mastery-confirmation waits for METER, Slice 7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->longText('description')->nullable();

            // The roadmap page this course was scaffolded from (traceability; nullable
            // so hand-authored courses need no source).
            $table->foreignId('source_page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained('domains')->nullOnDelete();

            // Publish gate — mirrors the pages' visibility spirit: scaffolds land draft,
            // admin publishes deliberately.
            $table->string('status')->default('draft')->index(); // draft / published
            $table->unsignedSmallInteger('sort')->default(0);

            $table->timestamps();
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->unsignedSmallInteger('sort')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            // The wiki page this lesson points at. Nullable so a lesson can be a
            // placeholder before its page exists / is chosen.
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('title');
            $table->boolean('optional')->default(false); // excluded from progress %
            $table->unsignedSmallInteger('sort')->default(0)->index();
            $table->timestamps();
        });

        // Course → course prerequisite edges (the mastery-tree DAG, soft-gated).
        Schema::create('course_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('required_course_id')->constrained('courses')->cascadeOnDelete();
            $table->unique(['course_id', 'required_course_id']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('lesson_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_completions');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('course_prerequisites');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('courses');
    }
};
