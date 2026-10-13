<?php

namespace App\Services\Radar\Sources;

use App\Models\RawListing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

/**
 * `official_pages` (T6, docs/02-pipeline.md → "1. Fontes"): lista curta de
 * páginas oficiais cadastradas à mão em `config('radar.sources.official_pages.pages')`
 * — vazia por padrão até o dono do projeto adicionar as que acompanha.
 *
 * Sem seletor por página de propósito: cada banca/órgão tem HTML diferente
 * e o objetivo aqui não é extrair campos estruturados (isso é trabalho do
 * `ContestNormalizer` sobre texto puro + do enriquecimento por LLM), só
 * trazer o texto visível da página inteira e os links de PDF de edital — o
 * mesmo texto/links que qualquer outra fonte entrega em `payload`.
 */
class OfficialPagesSource implements ContestSource
{
    public function key(): string
    {
        return 'official_pages';
    }

    public function enabled(): bool
    {
        return (bool) config('radar.sources.official_pages.enabled', true);
    }

    public function fetch(): iterable
    {
        foreach (config('radar.sources.official_pages.pages', []) as $page) {
            $listing = $this->fetchPage($page);

            if ($listing !== null) {
                yield $listing;
            }
        }
    }

    /**
     * @param  array{url?: string, organ_label?: string}  $page
     */
    private function fetchPage(array $page): ?RawListing
    {
        $url = $page['url'] ?? null;

        if ($url === null || $url === '') {
            return null;
        }

        $response = Http::withHeaders([
            'User-Agent' => 'radar-mensal/1.0 (uso pessoal; contato: '.config('radar.contact_email', 'contato@example.com').')',
        ])->timeout(15)->retry(2, 1000)->get($url);

        $response->throw();

        $html = $response->body();
        $crawler = new Crawler($html, $url);

        $links = array_values(array_unique(array_map(
            fn ($link) => $link->getUri(),
            $crawler->filter('a')->links(),
        )));

        $withoutScripts = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $text = Str::squish(strip_tags($withoutScripts));

        $title = $page['organ_label'] ?? $this->extractPageTitle($crawler) ?? $url;

        return new RawListing([
            'title' => $title,
            'external_url' => $url,
            'payload' => [
                'text' => $text,
                'links' => $links,
            ],
        ]);
    }

    private function extractPageTitle(Crawler $crawler): ?string
    {
        $node = $crawler->filter('title');

        return $node->count() > 0 ? trim($node->text()) : null;
    }
}
