<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contest_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->date('date');
            $table->string('label')->nullable();
            // scrape|llm|seed — 'seed' é aceito além do par documentado em
            // docs/01-modelo-de-dados.md porque o ContestSeeder gera eventos
            // via ContestMerger a partir de dados semeados. [inferido]
            $table->string('source');
            $table->timestamps();

            $table->unique(['contest_id', 'type', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contest_events');
    }
};
