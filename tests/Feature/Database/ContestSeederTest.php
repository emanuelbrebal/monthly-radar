<?php

use App\Enums\EditalStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Contest;
use Database\Seeders\ContestSeeder;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function seedRow(array $overrides = []): array
{
    return array_merge([
        'slug' => 'orgao-teste-2026',
        'organ' => 'Órgão de Teste',
        'organ_acronym' => 'OT',
        'year' => 2026,
        'title' => 'Órgão de Teste 2026 — Analista de TI',
        'board' => 'FGV',
        'areas' => ['development'],
        'status' => 'authorized',
        'exam_date' => null,
        'regime' => 'statutory',
        'locations' => ['João Pessoa/PB'],
        'states' => ['PB'],
        'nationwide' => false,
        'salary_note' => 'nota de salário',
        'confidence' => 'medium',
        'enrollment_status' => 'interested',
        'pinned_slot' => 3,
        'source_url' => 'https://blog.exemplo.com.br/concurso',
        'personal_notes' => 'nota da pesquisa',
        'summary' => ['resumo da pesquisa'],
        'divergences' => [],
        'gaps' => ['banca não definida'],
        'extra_events' => [],
    ], $overrides);
}

function runSeed(array ...$rows): void
{
    (new ContestSeeder)->seedFromArray([
        '_meta' => ['collected_between' => '2026-10-01/2026-10-13'],
        'contests' => $rows,
    ]);
}

it('creates a new contest with everything from the json, curation included', function () {
    runSeed(seedRow());

    $contest = Contest::query()->where('slug', 'orgao-teste-2026')->sole();

    expect($contest->board)->toBe('FGV')
        ->and($contest->enrollment_status)->toBe(EnrollmentStatus::Interested)
        ->and($contest->pinned_slot)->toBe(3)
        ->and($contest->personal_notes)->toBe('nota da pesquisa')
        ->and($contest->dossier['summary'])->toBe(['resumo da pesquisa'])
        ->and($contest->field_sources['board']['origin'])->toBe('seed');
});

it('updates facts of an existing contest without touching what the owner set on screen', function () {
    runSeed(seedRow());

    $contest = Contest::query()->where('slug', 'orgao-teste-2026')->sole();
    $contest->update([
        'enrollment_status' => EnrollmentStatus::FeePaid->value,
        'pinned_slot' => 0,
        'personal_notes' => 'anotação feita pela tela',
    ]);

    runSeed(seedRow([
        'board' => 'Cebraspe',
        'status' => 'registration_open',
        'exam_date' => '2026-12-13',
        'salary_note' => 'nota nova',
        'enrollment_status' => 'interested',
        'pinned_slot' => 4,
        'personal_notes' => 'nota nova da pesquisa',
        'summary' => ['edital publicado'],
    ]));

    $contest->refresh();

    expect($contest->board)->toBe('Cebraspe')
        ->and($contest->status)->toBe(EditalStatus::RegistrationOpen)
        ->and($contest->exam_date->toDateString())->toBe('2026-12-13')
        ->and($contest->salary_note)->toBe('nota nova')
        ->and($contest->dossier['summary'])->toBe(['edital publicado'])
        ->and($contest->enrollment_status)->toBe(EnrollmentStatus::FeePaid)
        ->and($contest->pinned_slot)->toBe(0)
        ->and($contest->personal_notes)->toBe('anotação feita pela tela');

    expect($contest->events()->where('type', 'exam')->count())->toBe(1);
    expect($contest->statusLogs()->where('to', 'registration_open')->count())->toBe(1);
});

it('keeps a dismissed contest dismissed when the seed runs again', function () {
    runSeed(seedRow());

    $contest = Contest::query()->where('slug', 'orgao-teste-2026')->sole();
    $contest->update([
        'enrollment_status' => EnrollmentStatus::Dismissed->value,
        'dismissed_at' => now(),
        'dismissed_reason' => 'fora do foco',
        'pinned_slot' => null,
    ]);

    runSeed(seedRow());

    $contest->refresh();

    expect($contest->enrollment_status)->toBe(EnrollmentStatus::Dismissed)
        ->and($contest->dismissed_at)->not->toBeNull()
        ->and($contest->dismissed_reason)->toBe('fora do foco')
        ->and($contest->pinned_slot)->toBeNull();
});

it('fills personal notes only when they are empty', function () {
    runSeed(seedRow(['personal_notes' => null]));

    runSeed(seedRow(['personal_notes' => 'primeira nota']));

    expect(Contest::query()->where('slug', 'orgao-teste-2026')->sole()->personal_notes)->toBe('primeira nota');
});

it('does not let the seed overwrite a value that came from an official source', function () {
    runSeed(seedRow());

    $contest = Contest::query()->where('slug', 'orgao-teste-2026')->sole();
    $contest->board = 'Cebraspe';
    $contest->field_sources = array_merge($contest->field_sources, [
        'board' => ['url' => 'https://www.gov.br/orgao/edital', 'fetched_at' => null, 'origin' => 'scrape', 'confidence' => 'high'],
    ]);
    $contest->save();

    runSeed(seedRow(['board' => 'FGV']));

    expect($contest->refresh()->board)->toBe('Cebraspe');
});

it('keeps what enrichment added to the dossier and does not duplicate sources', function () {
    runSeed(seedRow());

    $contest = Contest::query()->where('slug', 'orgao-teste-2026')->sole();
    $contest->update(['dossier' => array_merge($contest->dossier, ['it_roles' => ['Analista de Sistemas']])]);

    runSeed(seedRow());
    runSeed(seedRow());

    $contest->refresh();

    expect($contest->dossier['it_roles'])->toBe(['Analista de Sistemas'])
        ->and(count($contest->dossier['sources']))->toBe(1)
        ->and(Contest::query()->where('slug', 'orgao-teste-2026')->count())->toBe(1);
});
