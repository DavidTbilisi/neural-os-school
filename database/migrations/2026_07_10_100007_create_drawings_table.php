<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-learner Excalidraw whiteboards — one saved scene per (user, course).
 * The scene is the raw Excalidraw serialized JSON (elements + appState + files),
 * stored verbatim so it round-trips straight back into the editor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drawings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->longText('scene')->nullable(); // Excalidraw serializeAsJSON output
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drawings');
    }
};
