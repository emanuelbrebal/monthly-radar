<?php

use App\Services\Radar\Sources\OfficialPagesSource;
use Illuminate\Support\Facades\Http;

it('is enabled by default but yields nothing with an empty page list', function () {
    config(['radar.sources.official_pages.pages' => []]);

    $source = new OfficialPagesSource;

    expect($source->enabled())->toBeTrue();
    expect(iterator_to_array($source->fetch()))->toBe([]);
});

it('scrapes each configured page, resolving relative edital links to absolute', function () {
    config(['radar.sources.official_pages.pages' => [
        ['url' => 'https://sefaz.rn.gov.br/concurso-2026', 'organ_label' => 'SEFAZ-RN 2026'],
    ]]);

    Http::fake([
        'sefaz.rn.gov.br/*' => Http::response(file_get_contents(base_path('tests/Fixtures/official_page_sample.html'))),
    ]);

    $listings = iterator_to_array((new OfficialPagesSource)->fetch());

    expect($listings)->toHaveCount(1);

    $listing = $listings[0];
    expect($listing->title)->toBe('SEFAZ-RN 2026');
    expect($listing->external_url)->toBe('https://sefaz.rn.gov.br/concurso-2026');
    expect($listing->payload['text'])->toContain('Auditor Fiscal de Tributos Estaduais');
    expect($listing->payload['links'])->toContain('https://sefaz.rn.gov.br/documentos/edital-sefaz-rn-2026.pdf');
    expect($listing->payload['links'])->toContain('https://outro-dominio.gov.br/inscricao');
});

it('falls back to the page <title> when organ_label is missing', function () {
    config(['radar.sources.official_pages.pages' => [
        ['url' => 'https://sefaz.rn.gov.br/concurso-2026'],
    ]]);

    Http::fake([
        'sefaz.rn.gov.br/*' => Http::response(file_get_contents(base_path('tests/Fixtures/official_page_sample.html'))),
    ]);

    $listing = iterator_to_array((new OfficialPagesSource)->fetch())[0];

    expect($listing->title)->toBe('SEFAZ-RN 2026 — Concurso Público');
});
