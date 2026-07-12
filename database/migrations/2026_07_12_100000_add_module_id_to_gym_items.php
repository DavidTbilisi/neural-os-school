<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tag gym items with the course module they exercise. This scopes the gym's
 * telemetry per module — the first step toward evidence-based module coverage
 * (a per-module METER verdict instead of the honor-system lesson checkbox).
 *
 * Nullable + nullOnDelete: course seeders rebuild modules in place (IDs churn),
 * so a module delete must untag items, not destroy them. GymSeeder re-tags by
 * module title on re-seed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gym_items', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable()->after('gym_id')
                ->constrained('modules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gym_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_id');
        });
    }
};
