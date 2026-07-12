<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-module target rung: the Knowledge Ladder level (0–9) a learner's
 * coverage evidence must reach for the module to count as covered. Defaults
 * to 4 (Classifiable, "I know when it applies") — exactly the pass-accuracy
 * bar the gate used before, so existing modules behave unchanged. Reflex
 * modules can demand 7 (Reflexive); rungs above 7 exceed what a timed
 * recognition drill can certify and read as needing a deeper instrument.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->unsignedTinyInteger('target_rung')->default(4)->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('target_rung');
        });
    }
};
