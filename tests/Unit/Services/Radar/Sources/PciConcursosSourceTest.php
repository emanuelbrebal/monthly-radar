<?php

use App\Models\RawListing;
use App\Services\Radar\Sources\PciConcursosSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'radar.sources.pci_concursos.enabled' => true,
        'radar.sources.pci_concursos.detail_delay_ms' => 0,
    ]);
});

function fakePci(): void
{
    Http::fake([
        'pciconcursos.com.br/noticias/camara-de-cristalina-go-abre-concurso' => Http::response(file_get_contents(base_path('tests/Fixtures/pci_noticia_sample.html'))),
        'pciconcursos.com.br/*' => Http::response(file_get_contents(base_path('tests/Fixtures/pci_concursos_sample.html'))),
    ]);
}

it('is enabled by default', function () {
    expect((new PciConcursosSource)->enabled())->toBeTrue();
});

it('parses cards grouped by the preceding state marker', function () {
    fakePci();

    $listings = iterator_to_array((new PciConcursosSource)->fetch());

    expect($listings)->toHaveCount(4);

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

it('separates the card fields instead of gluing them together', function () {
    fakePci();

    $listings = iterator_to_array((new PciConcursosSource)->fetch());

    expect($listings[2]->payload['text'])->toContain('R$ 5.318,63 | Analista de TI, Escrivão de Polícia Civil | Superior');
});

it('reads the role list from the news page when the card hides it', function () {
    fakePci();

    $listings = iterator_to_array((new PciConcursosSource)->fetch());

    expect($listings[3]->payload['roles'])->toBe([
        'Analista de Contabilidade (1 vaga + 5 CR)',
        'Analista de Tecnologia da Informação (1 vaga + 5 CR)',
        'Técnico Administrativo (7 vagas)',
    ]);
    expect($listings[2]->payload)->not->toHaveKey('roles');

    Http::assertSentCount(2);
});

it('reuses roles read in a previous run instead of fetching the news again', function () {
    fakePci();
    RawListing::create([
        'source' => 'pci_concursos',
        'external_url' => 'https://www.pciconcursos.com.br/noticias/camara-de-cristalina-go-abre-concurso',
        'title' => 'Câmara de Cristalina',
        'payload' => ['text' => 'Vários Cargos', 'links' => [], 'roles' => ['Analista de Tecnologia da Informação']],
        'content_hash' => 'old',
        'fetched_at' => now()->subDay(),
    ]);

    $listings = iterator_to_array((new PciConcursosSource)->fetch());

    expect($listings[3]->payload['roles'])->toBe(['Analista de Tecnologia da Informação']);
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/noticias/'));
});

it('keeps the card when the news page fails or the fetch budget is spent', function () {
    config(['radar.sources.pci_concursos.max_detail_fetches' => 0]);
    fakePci();

    $listings = iterator_to_array((new PciConcursosSource)->fetch());

    expect($listings)->toHaveCount(4);
    expect($listings[3]->payload)->not->toHaveKey('roles');
});

it('throws when the request fails, so HarvestSourcesJob can record the error', function () {
    Http::fake([
        'pciconcursos.com.br/*' => Http::response('erro', 500),
    ]);

    expect(fn () => iterator_to_array((new PciConcursosSource)->fetch()))->toThrow(Exception::class);
});
