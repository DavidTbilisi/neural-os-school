<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tag gym items with the specific lesson they check, in addition to the
 * module they exercise (module_id). This scopes which items render as an
 * embedded "check your understanding" block on a given lesson page, while
 * module_id keeps feeding the existing Module::gymAttempts()/coverage gate
 * unchanged — every lesson-check item sets both fields.
 *
 * Nullable + nullOnDelete: course seeders rebuild lessons in place (IDs
 * churn), so a lesson delete must untag items, not destroy them — same
 * reasoning as the module_id column above.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_items', function (Blueprint $table) {
            $table->foreignId('lesson_id')->nullable()->after('module_id')
                ->constrained('lessons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gym_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lesson_id');
        });
    }
};
