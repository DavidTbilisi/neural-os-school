<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The wiki's own scoreboard (dashboard-scores.json): Complexity / Acquirement /
 * Absorbed-% rolled up per lens (domain / palace / track), plus one 'total' row.
 * Imported read-only by wiki:import; regenerated each run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_lenses', function (Blueprint $table) {
            $table->id();
            $table->string('lens_type')->index(); // total | domain | palace | track
            $table->string('lens_key');
            $table->string('lens_label')->nullable();
            $table->unsignedInteger('pages')->default(0);
            $table->float('complexity')->default(0);
            $table->float('acquirement')->default(0);
            $table->float('absorbed')->default(0); // % for lens rows; absorbed_pct for total
            $table->json('extra')->nullable();     // total row: edges/glossary/recall_packs/skills/generated
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_lenses');
    }
};
