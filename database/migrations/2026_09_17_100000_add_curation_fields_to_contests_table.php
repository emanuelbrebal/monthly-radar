<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de curadoria manual (pesquisa feita fora do app, ver
 * docs/01-modelo-de-dados.md → "Campos de curadoria manual"). Nenhum é
 * exigido pelo scraping/enriquecimento — todos nascem null nos concursos
 * raspados e só existem para o que o dono do radar preenche à mão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contests', function (Blueprint $table) {
            $table->string('sphere')->nullable()->after('regime');
            $table->string('education_level')->nullable()->after('sphere');
            $table->boolean('is_it_role')->nullable()->after('education_level');
            $table->unsignedSmallInteger('immediate_vacancies')->nullable()->after('it_vacancies');
            $table->unsignedSmallInteger('reserve_vacancies')->nullable()->after('immediate_vacancies');
            $table->unsignedSmallInteger('open_competition_vacancies')->nullable()->after('reserve_vacancies');
            $table->text('salary_note')->nullable()->after('salary_max_cents');
            $table->string('board_affinity')->nullable()->after('board');
            $table->text('board_affinity_note')->nullable()->after('board_affinity');
            $table->text('personal_notes')->nullable()->after('dossier');
            $table->string('workplace_city')->nullable()->after('locations');
            $table->string('workplace_state', 2)->nullable()->after('workplace_city');
            $table->unsignedSmallInteger('distance_from_home_km')->nullable()->after('workplace_state');
            $table->string('exam_city')->nullable()->after('exam_date');
            $table->date('essay_exam_date')->nullable()->after('exam_city');
            $table->unsignedSmallInteger('validity_months')->nullable()->after('essay_exam_date');
            $table->boolean('validity_extendable')->nullable()->after('validity_months');
            $table->date('source_checked_at')->nullable()->after('registration_url');
            $table->string('confidence')->nullable()->after('source_checked_at');

            // motivo_descarte real passou de 120 caracteres em vários itens
            // da curadoria manual — texto livre em vez de string curta.
            $table->text('dismissed_reason')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('contests', function (Blueprint $table) {
            $table->dropColumn([
                'sphere',
                'education_level',
                'is_it_role',
                'immediate_vacancies',
                'reserve_vacancies',
                'open_competition_vacancies',
                'salary_note',
                'board_affinity',
                'board_affinity_note',
                'personal_notes',
                'workplace_city',
                'workplace_state',
                'distance_from_home_km',
                'exam_city',
                'essay_exam_date',
                'validity_months',
                'validity_extendable',
                'source_checked_at',
                'confidence',
            ]);

            $table->string('dismissed_reason', 120)->nullable()->change();
        });
    }
};
