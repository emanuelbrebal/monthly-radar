<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nível do concurso (curadoria manual, ver docs/01-modelo-de-dados.md →
 * "Campos de curadoria manual"). Nasce null em todo concurso raspado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contests', function (Blueprint $table) {
            $table->string('tier')->nullable()->after('board_affinity_note');
            $table->text('tier_note')->nullable()->after('tier');
        });
    }

    public function down(): void
    {
        Schema::table('contests', function (Blueprint $table) {
            $table->dropColumn(['tier', 'tier_note']);
        });
    }
};
