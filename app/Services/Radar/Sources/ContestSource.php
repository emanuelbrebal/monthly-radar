<?php

namespace App\Services\Radar\Sources;

use App\Models\RawListing;

/**
 * Uma fonte de scraping (docs/02-pipeline.md → "1. Fontes"). Implementações
 * ficam nesse namespace, chaveadas por key() e configuradas em
 * config('radar.sources.{key}').
 */
interface ContestSource
{
    /**
     * Chave usada em config('radar.sources') e em raw_listings.source.
     */
    public function key(): string;

    /**
     * config('radar.sources.{key}.enabled'), default false.
     */
    public function enabled(): bool;

    /**
     * Raspa a fonte. Cada item é um RawListing ainda não salvo (título, url,
     * trecho de texto em payload, datas cruas, links) — quem chama decide
     * dedup por content_hash e persiste.
     *
     * @return iterable<int, RawListing>
     *
     * @throws SourceRequiresBrowserException página vazia porque depende de JS.
     */
    public function fetch(): iterable;
}
