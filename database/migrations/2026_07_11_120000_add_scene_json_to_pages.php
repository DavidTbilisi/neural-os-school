<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Curated "frozen scene" content for the page representations.
 *
 * This is the ONE piece of page content authored in the Filament admin rather
 * than synced from markdown (scenes are hand-curated, not derivable). Because it
 * lives only in the DB, WikiImport must preserve it across re-imports (see the
 * upsert exclusion in App\Console\Commands\WikiImport), exactly like `visibility`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->json('scene_json')->nullable()->after('body_html');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('scene_json');
        });
    }
};
