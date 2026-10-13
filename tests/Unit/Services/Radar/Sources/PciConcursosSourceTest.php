<?php

use App\Services\Radar\Sources\PciConcursosSource;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['radar.sources.pci_concursos.enabled' => true]);
});

it('is enabled by default', function () {
    expect((new PciConcursosSource)->enabled())->toBeTrue();
});

it('parses cards grouped by the preceding state marker', function () {
    Http::fake([
        'pciconcursos.com.br/*' => Http::response(file_get_contents(base_path('tests/Fixtures/pci_concursos_sample.html'))),
    ]);

    $listings = iterator_to_array((new PciConcursosSource)->fetch());

    expect($listings)->toHaveCount(3);

    expect($listings[0]->title)->toBe('Transpetro - Petrobras Transporte S.A.');
    expect($listings[0]->payload['state'])->toBe('NACIONAL');
    expect($listings[0]->payload['text'])->toContain('nacional');

    expect($listings[1]->title)->toBe('ADEAL - Agência de Defesa e Inspeção Agropecuária de Alagoas');
    expect($listings[1]->payload['state'])->toBe('AL');
    expect($listings[1]->payload['text'])->toContain('-AL');
    expect($listings[1]->payload['text'])->toContain('Fiscal Estadual Agropecuário');

    expect($listings[2]->title)->toBe('SEPLAG - AL');
    expect($listings[2]->external_url)->toBe('https://www.pciconcursos.com.br/noticias/seplag-al-edital');
    expect($listings[2]->payload['links'])->toBe([]);
});

it('throws when the request fails, so HarvestSourcesJob can record the error', function () {
    Http::fake([
        'pciconcursos.com.br/*' => Http::response('erro', 500),
    ]);

    expect(fn () => iterator_to_array((new PciConcursosSource)->fetch()))->toThrow(Exception::class);
});
