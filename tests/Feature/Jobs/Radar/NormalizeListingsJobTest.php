<?php

use App\Jobs\Radar\NormalizeListingsJob;
use App\Models\Contest;
use App\Models\RawListing;
use App\Models\SearchRun;
use App\Services\Radar\ContestMerger;
use App\Services\Radar\ContestNormalizer;

function runNormalize(SearchRun $run): void
{
    (new NormalizeListingsJob($run->id))->handle(app(ContestNormalizer::class), app(ContestMerger::class));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function createRawListing(array $attributes): RawListing
{
    return RawListing::create([
        ...$attributes,
        'content_hash' => hash('sha256', $attributes['external_url'].$attributes['title']),
    ]);
}

it('creates a new contest from a matching raw listing', function () {
    $run = SearchRun::factory()->create(['area' => 'development']);

    createRawListing([
        'source' => 'test',
        'external_url' => 'https://orgao.gov.br/edital-oficial.pdf',
        'title' => 'TRF3 2026 — Analista Judiciário, Desenvolvimento de Software',
        'payload' => [
            'text' => 'Inscrições abertas até 10/10/2026. Prova em 06/12/2026, em São Paulo/SP.',
            'links' => ['https://orgao.gov.br/edital-oficial.pdf'],
        ],
        'fetched_at' => now(),
    ]);

    runNormalize($run);

    $contest = Contest::query()->where('slug', 'trf-2026')->first();

    expect($contest)->not->toBeNull();
    expect($contest->status->value)->toBe('registration_open');
    expect($contest->registration_end->toDateString())->toBe('2026-10-10');
    expect($contest->exam_date->toDateString())->toBe('2026-12-06');
    expect($contest->states)->toBe(['SP']);
    expect($contest->areas)->toContain('development');

    $raw = RawListing::first();
    expect($raw->contest_id)->toBe($contest->id);

    expect($run->refresh()->counts['created'])->toBe(1);
});

it('discards a raw listing that matches no area', function () {
    $run = SearchRun::factory()->create(['area' => 'development']);

    createRawListing([
        'source' => 'test',
        'external_url' => 'https://x.com/a',
        'title' => 'Concurso para Procurador do estado',
        'payload' => ['text' => 'Vaga jurídica, sem relação com TI.', 'links' => []],
        'fetched_at' => now(),
    ]);

    runNormalize($run);

    expect(Contest::query()->count())->toBe(0);
    expect(RawListing::first()->contest_id)->toBeNull();
});

it('skips a raw listing outside the run area even if it matches another area', function () {
    $run = SearchRun::factory()->create(['area' => 'security']);

    createRawListing([
        'source' => 'test',
        'external_url' => 'https://x.com/a',
        'title' => 'XPTO 2026 — Analista de Sistemas, Desenvolvimento',
        'payload' => ['text' => 'Concurso de desenvolvimento de software.', 'links' => []],
        'fetched_at' => now(),
    ]);

    runNormalize($run);

    expect(Contest::query()->count())->toBe(0);
});

it('matches an existing contest by slug and merges into it', function () {
    $run = SearchRun::factory()->create(['area' => 'development']);
    $contest = Contest::factory()->create([
        'slug' => 'trf-2026',
        'organ' => 'Tribunal Regional Federal',
        'organ_acronym' => 'TRF',
        'year' => 2026,
        'board' => null,
        'edital_url' => null,
        'areas' => ['development'],
    ]);

    createRawListing([
        'source' => 'test',
        'external_url' => 'https://x.com/a',
        'title' => 'TRF3 2026 — Analista Judiciário, Desenvolvimento de Software',
        'payload' => ['text' => 'Prova em 06/12/2026.', 'links' => []],
        'fetched_at' => now(),
    ]);

    runNormalize($run);

    expect(Contest::query()->count())->toBe(1);
    expect($contest->refresh()->exam_date->toDateString())->toBe('2026-12-06');
    expect($run->refresh()->counts['updated'])->toBe(1);
});

it('matches an existing contest by organ similarity when the slug differs', function () {
    $run = SearchRun::factory()->create(['area' => 'development']);
    $contest = Contest::factory()->create([
        'slug' => 'dataprev-2026',
        'organ' => 'Empresa de Tecnologia e Informações da Previdência Social',
        'organ_acronym' => 'DATAPREV',
        'year' => 2026,
        'areas' => ['development'],
    ]);

    createRawListing([
        'source' => 'test',
        'external_url' => 'https://x.com/a',
        'title' => 'Empresa de Tecnologia e Informacoes da Previdencia Social — concurso 2026',
        'payload' => ['text' => 'Analista de sistemas, desenvolvimento de software.', 'links' => []],
        'fetched_at' => now(),
    ]);

    runNormalize($run);

    expect(Contest::query()->count())->toBe(1);
});

it('rejects an organ name that only mentions development, counting it', function () {
    $run = SearchRun::factory()->create(['area' => 'all']);

    createRawListing([
        'source' => 'pci_concursos',
        'external_url' => 'https://www.pciconcursos.com.br/noticias/funcamp',
        'title' => 'FUNCAMP - Fundação de Desenvolvimento da Unicamp',
        'payload' => ['text' => 'FUNCAMP abre processo seletivo | 3 vagas | Enfermeiro | Superior', 'links' => []],
        'fetched_at' => now(),
    ]);

    runNormalize($run);

    expect(Contest::query()->count())->toBe(0);
    expect($run->refresh()->counts['rejected_no_it'])->toBe(1);
});

it('creates a contest when the IT role only shows up in the roles read from the news page', function () {
    $run = SearchRun::factory()->create(['area' => 'all']);

    createRawListing([
        'source' => 'pci_concursos',
        'external_url' => 'https://www.pciconcursos.com.br/noticias/camara-de-cristalina',
        'title' => 'Câmara de Cristalina',
        'payload' => [
            'text' => 'Câmara de Cristalina abre concurso | 15 vagas | Vários Cargos | Médio / Superior 30/11/2026 -GO',
            'links' => [],
            'roles' => ['Analista de Contabilidade (1 vaga)', 'Analista de Tecnologia da Informação (1 vaga + 5 CR)'],
        ],
        'fetched_at' => now(),
    ]);

    runNormalize($run);

    expect(Contest::query()->count())->toBe(1);
    expect(Contest::first()->areas)->toContain('development');
});
