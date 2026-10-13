<?php

namespace App\Services\Radar\Sources;

use App\Enums\BrazilianState;
use App\Models\RawListing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

/**
 * `pci_concursos` (T6, docs/02-pipeline.md → "1. Fontes"). Verificado em
 * 18/09/2026: ao contrário do que a spec original supunha, não existe uma
 * página por estado — `https://www.pciconcursos.com.br/concursos/` já lista
 * TUDO (nacional + 26 estados + DF) numa página só, agrupado por marcadores
 * `<div id="{UF}" class="ua">` em ordem de documento, cada um seguido pelos
 * cartões de concurso (`div.na`/`div.ea`/`div.da`, mesma estrutura) até o
 * próximo marcador. `robots.txt` permite `/concursos/` (só bloqueia
 * `/admin/`, `/pdf/`, `*.php` etc.).
 *
 * O cartão da listagem não traz o link do edital, só a notícia do PCI sobre
 * a abertura — extrair o edital de verdade fica pro enriquecimento por LLM
 * (docs/02-pipeline.md → "3. Enriquecimento"), que já existe pra isso.
 */
class PciConcursosSource implements ContestSource
{
    public function key(): string
    {
        return 'pci_concursos';
    }

    public function enabled(): bool
    {
        return (bool) config('radar.sources.pci_concursos.enabled', true);
    }

    public function fetch(): iterable
    {
        $url = config('radar.sources.pci_concursos.url', 'https://www.pciconcursos.com.br/concursos/');

        $response = Http::withHeaders([
            'User-Agent' => 'radar-mensal/1.0 (uso pessoal; contato: '.config('radar.contact_email', 'contato@example.com').')',
        ])->timeout(15)->retry(2, 1000)->get($url);

        $response->throw();

        $crawler = new Crawler($response->body());
        $currentState = null;

        foreach ($crawler->filter('#concursos > .ua, #concursos > .na, #concursos > .ea, #concursos > .da') as $node) {
            $element = new Crawler($node);

            if (Str::contains($element->attr('class') ?? '', 'ua')) {
                $currentState = $this->normalizeStateMarker($element->attr('id') ?? '');

                continue;
            }

            $listing = $this->parseCard($element, $currentState);

            if ($listing !== null) {
                yield $listing;
            }
        }
    }

    /**
     * `id="NACIONAL"` → nationwide; `id="XX"` de UF válida → esse estado;
     * qualquer outro marcador (ex. `id="NORDESTE"`, região com concurso
     * multi-estado) → desconhecido, fica pro enriquecimento resolver.
     *
     * @return string|null 'NACIONAL', a sigla, ou null se desconhecido
     */
    private function normalizeStateMarker(string $id): ?string
    {
        if ($id === 'NACIONAL') {
            return 'NACIONAL';
        }

        $isValidState = in_array($id, array_column(BrazilianState::cases(), 'value'), true);

        return $isValidState ? $id : null;
    }

    private function parseCard(Crawler $card, ?string $state): ?RawListing
    {
        $link = $card->filter('.ca > a')->first();

        if ($link->count() === 0) {
            return null;
        }

        $title = trim($link->text());
        $externalUrl = $link->attr('href') ?? '';
        $headline = $link->attr('title') ?? '';

        if ($title === '' || $externalUrl === '') {
            return null;
        }

        $detail = $card->filter('.cd')->count() > 0 ? trim($card->filter('.cd')->text(normalizeWhitespace: true)) : '';
        $deadline = $card->filter('.ce')->count() > 0 ? trim($card->filter('.ce')->text(normalizeWhitespace: true)) : '';

        $textParts = [$headline, $detail, $deadline];

        // extractStates() (ContestNormalizer) só reconhece UF colada a "/" ou
        // "-" (ex. "Natal/RN"); a coluna .cc do card já dá a sigla certa, sem
        // depender de heurística de texto — mais confiável que adivinhar.
        if ($state === 'NACIONAL') {
            $textParts[] = 'nacional';
        } elseif ($state !== null) {
            $textParts[] = "-{$state}";
        }

        return new RawListing([
            'title' => $title,
            'external_url' => $externalUrl,
            'payload' => [
                'text' => implode(' ', array_filter($textParts, fn ($p) => $p !== '')),
                'links' => [],
                'state' => $state,
            ],
        ]);
    }
}
