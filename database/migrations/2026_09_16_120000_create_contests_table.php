<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contests', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('organ');
            $table->string('organ_acronym')->nullable();
            $table->smallInteger('year');
            $table->string('title');
            $table->string('board')->nullable();
            $table->jsonb('areas');
            $table->string('status');
            $table->timestampTz('status_changed_at');
            $table->timestampTz('closed_at')->nullable();
            $table->date('registration_start')->nullable();
            $table->date('registration_end')->nullable();
            $table->date('exam_date')->nullable();
            $table->integer('fee_cents')->nullable();
            $table->integer('salary_min_cents')->nullable();
            $table->integer('salary_max_cents')->nullable();
            $table->integer('it_vacancies')->nullable();
            $table->string('regime');
            $table->jsonb('locations');
            $table->text('edital_url')->nullable();
            $table->text('registration_url')->nullable();
            $table->jsonb('dossier')->nullable();
            $table->jsonb('field_sources');
            $table->string('enrichment_status')->default('pending');
            $table->string('enrollment_status')->default('not_enrolled');
            $table->timestampTz('dismissed_at')->nullable();
            $table->string('dismissed_reason', 120)->nullable();
            $table->unsignedSmallInteger('pinned_slot')->nullable();
            $table->timestampTz('last_seen_at');
            $table->timestampTz('last_enriched_at')->nullable();
            $table->timestamps();

            $table->unique('pinned_slot');
            $table->index('status');
            $table->index('registration_end');
            $table->index('exam_date');
        });

        // GIN em `areas` — só faz sentido/existe em Postgres.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX contests_areas_gin_index ON contests USING GIN (areas)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contests');
    }
};
