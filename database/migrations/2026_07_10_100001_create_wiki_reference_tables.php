<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The three fixed axes of the wiki's address system. These are reference tables
 * seeded from config('wiki.*) — their rows are permanent (the domain NUMBER in
 * particular locks a mnemonic peg and must never be renumbered).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary(); // the permanent 1..10 number
            $table->string('name');
        });

        Schema::create('palaces', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();   // core-memory, tactical-memory, ...
            $table->unsignedTinyInteger('sort'); // changeability order (0 = most permanent)
        });

        Schema::create('levels', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary(); // the 1..10 maturity number
            $table->string('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('levels');
        Schema::dropIfExists('palaces');
        Schema::dropIfExists('domains');
    }
};
