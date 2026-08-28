<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-learner top-bar preferences: {"order": [...keys], "hidden": [...keys]}.
     * Null means "never touched it" — the defaults in App\Support\Navigation.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('nav_preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nav_preferences');
        });
    }
};
