<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de alterações (T7, docs/01-modelo-de-dados.md → `contest_changes`):
 * uma linha por campo alterado, por concurso criado e por falha de enriquecimento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contest_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('search_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind'); // created|updated|failed
            $table->string('field')->nullable();
            $table->jsonb('old_value')->nullable();
            $table->jsonb('new_value')->nullable();
            $table->string('origin'); // scrape|llm|seed
            $table->text('note')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['contest_id', 'created_at']);
            $table->index('search_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contest_changes');
    }
};
