<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The blind-spot floor. A recognition gym's aggregate accuracy averages away
 * a category the learner missed *every* item of — 85% across 12 pattern
 * families reads as "reflexive" while one family sits at 0/2. The floor makes
 * an all-wrong category cap the session's ladder rung, so the top band is
 * never awarded over a hole (App\Support\KnowledgeLadder::BLIND_SPOT_CEILING).
 *
 * Ported from the wiki's gyms/algorithm-pattern-gym.html, which gained the same
 * gate after a real 85%/5.2s run promoted past a zeroed Monotonic Stack.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            // On by default: for any classification gym, `correct` values are
            // category labels, so an all-wrong category is always meaningful.
            $table->boolean('blind_spot_floor')->default(true)->after('promote_accuracy');
        });

        Schema::table('gym_sessions', function (Blueprint $table) {
            // [{category, items}] — the categories this run zeroed, so the
            // summary can name them instead of just withholding a rung.
            $table->json('blind_spots')->nullable()->after('stage_code');
        });
    }

    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->dropColumn('blind_spot_floor');
        });

        Schema::table('gym_sessions', function (Blueprint $table) {
            $table->dropColumn('blind_spots');
        });
    }
};
