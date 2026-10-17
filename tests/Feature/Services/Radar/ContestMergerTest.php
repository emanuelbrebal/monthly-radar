<?php

use App\Models\Contest;
use App\Services\Radar\ContestMerger;

beforeEach(function () {
    $this->merger = app(ContestMerger::class);
});

it('fills an empty field regardless of origin', function () {
    $contest = Contest::factory()->create(['board' => null]);

    $this->merger->merge($contest, ['board' => 'Cesgranrio'], origin: 'llm', sourceUrl: null, confidence: 'low');

    expect($contest->board)->toBe('Cesgranrio');
    expect($contest->field_sources['board']['origin'])->toBe('llm');
});

it('lets an official source overwrite an aggregator value', function () {
    $contest = Contest::factory()->create(['board' => null]);

    $this->merger->merge($contest, ['board' => 'Blog Concursos'], origin: 'scrape', sourceUrl: 'https://provasdeti.com.br/x', confidence: 'medium');
    $this->merger->merge($contest, ['board' => 'Cesgranrio'], origin: 'scrape', sourceUrl: 'https://orgao.gov.br/edital', confidence: 'high');

    expect($contest->board)->toBe('Cesgranrio');
});

it('refuses to let an aggregator overwrite an official value', function () {
    $contest = Contest::factory()->create(['board' => null]);

    $this->merger->merge($contest, ['board' => 'Cesgranrio'], origin: 'scrape', sourceUrl: 'https://orgao.gov.br/edital', confidence: 'high');
    $this->merger->merge($contest, ['board' => 'Blog Concursos'], origin: 'scrape', sourceUrl: 'https://provasdeti.com.br/x', confidence: 'medium');

    expect($contest->board)->toBe('Cesgranrio');
});

it('refuses to let llm without an official source overwrite scraped data', function () {
    $contest = Contest::factory()->create(['board' => null]);

    $this->merger->merge($contest, ['board' => 'Cesgranrio'], origin: 'scrape', sourceUrl: 'https://provasdeti.com.br/x', confidence: 'medium');
    $this->merger->merge($contest, ['board' => 'Outra banca'], origin: 'llm', sourceUrl: null, confidence: 'low');

    expect($contest->board)->toBe('Cesgranrio');
});

it('unions states from non-official sources instead of replacing them', function () {
    $contest = Contest::factory()->inStates(['RN'])->create();

    $this->merger->merge($contest, ['states' => ['CE']], origin: 'scrape', sourceUrl: 'https://provasdeti.com.br/x', confidence: 'medium');

    expect($contest->states)->toEqualCanonicalizing(['RN', 'CE']);
});

it('replaces states instead of unioning them when the source is official', function () {
    $contest = Contest::factory()->inStates(['RN', 'CE'])->create();

    $this->merger->merge($contest, ['states' => ['SP']], origin: 'scrape', sourceUrl: 'https://orgao.gov.br/edital', confidence: 'high');

    expect($contest->states)->toBe(['SP']);
});

it('treats tribunals and known boards as official, not only .gov.br', function () {
    expect(ContestMerger::isOfficialUrl('https://www.tjce.jus.br/noticias/x'))->toBeTrue()
        ->and(ContestMerger::isOfficialUrl('https://www.cebraspe.org.br/concursos/cgu_26'))->toBeTrue()
        ->and(ContestMerger::isOfficialUrl('https://conhecimento.fgv.br/concursos/dataprev26'))->toBeTrue()
        ->and(ContestMerger::isOfficialUrl('https://blog.grancursosonline.com.br/x'))->toBeFalse()
        ->and(ContestMerger::isOfficialUrl('https://fakejus.br.example.com/x'))->toBeFalse()
        ->and(ContestMerger::isOfficialUrl(null))->toBeFalse();

    $contest = Contest::factory()->create(['board' => null]);
    $this->merger->merge($contest, ['board' => 'Blog'], origin: 'scrape', sourceUrl: 'https://blog.example.com/x', confidence: 'medium');
    $this->merger->merge($contest, ['board' => 'FCC'], origin: 'llm', sourceUrl: 'https://www.tjce.jus.br/edital', confidence: 'high');

    expect($contest->board)->toBe('FCC');
});

it('does not let an older source of the same priority overwrite a newer one', function () {
    $contest = Contest::factory()->create(['board' => null]);

    $this->merger->merge($contest, ['board' => 'Vunesp'], origin: 'llm', sourceUrl: 'https://orgao.gov.br/a', confidence: 'high', sourceDate: '2026-10-10');
    $this->merger->merge($contest, ['board' => 'FCC'], origin: 'llm', sourceUrl: 'https://orgao.gov.br/b', confidence: 'high', sourceDate: '2026-09-01');

    expect($contest->board)->toBe('Vunesp')
        ->and($contest->field_sources['board']['source_date'])->toBe('2026-10-10');

    $this->merger->merge($contest, ['board' => 'Cebraspe'], origin: 'llm', sourceUrl: 'https://orgao.gov.br/c', confidence: 'high', sourceDate: '2026-10-15');

    expect($contest->board)->toBe('Cebraspe');
});
