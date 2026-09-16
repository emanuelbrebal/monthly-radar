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
