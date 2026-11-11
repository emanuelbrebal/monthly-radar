<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meu resultado na prova (T7, docs/01-modelo-de-dados.md → `exam_results`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')->constrained()->cascadeOnDelete();
            $table->string('stage')->default('objective');
            $table->decimal('score', 7, 2);
            $table->decimal('max_score', 7, 2);
            $table->decimal('cutoff_score', 7, 2)->nullable();
            $table->string('outcome')->default('pending'); // pending|passed|reserve|failed
            $table->string('source'); // self_graded|preliminary|official
            $table->unsignedInteger('position')->nullable();
            $table->unsignedInteger('candidates')->nullable();
            $table->jsonb('breakdown')->nullable();
            $table->date('taken_on')->nullable();
            $table->timestamps();

            $table->unique(['contest_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};
