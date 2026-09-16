<?php

// Esqueleto criado na T0. Conteúdo real (fontes, seletores, palavras-chave por área,
// bancas conhecidas, limites de enriquecimento e config do provider LLM) é da T2/T3
// — ver docs/02-pipeline.md e docs/04-plano-execucao.md. Não adicionar chaves fora
// das listadas aqui sem atualizar docs/02-pipeline.md primeiro.

return [

    /*
    |--------------------------------------------------------------------------
    | Fontes de scraping (T2)
    |--------------------------------------------------------------------------
    |
    | Uma entrada por adapter (App\Services\Radar\Sources\ContestSource),
    | chaveada por ContestSource::key(). Ex.: 'provas_de_ti', 'pci_concursos',
    | 'official_pages'. Cada entrada guarda 'enabled', URLs/seletores e limites
    | de páginas por fonte (padrão 5, ver docs/02-pipeline.md).
    |
    */
    'sources' => [],

    /*
    |--------------------------------------------------------------------------
    | Palavras-chave por área (T2)
    |--------------------------------------------------------------------------
    |
    | Mapa TiArea::value => array de termos usados pelo ContestNormalizer para
    | filtrar listagens raspadas. 'all' é a união de todas as áreas.
    |
    */
    'area_keywords' => [],

    /*
    |--------------------------------------------------------------------------
    | Bancas conhecidas (T2)
    |--------------------------------------------------------------------------
    |
    | Lista/mapa de bancas organizadoras usada pelo ContestMerger para decidir
    | se uma URL de origem 'seed' ou 'aggregator' conta como oficial.
    |
    */
    'boards' => [],

    /*
    |--------------------------------------------------------------------------
    | Enriquecimento (T3)
    |--------------------------------------------------------------------------
    |
    | Limites do EnrichContestsJob, ex. 'max_per_run' => 15.
    |
    */
    'enrich' => [],

    /*
    |--------------------------------------------------------------------------
    | Provider LLM (T3)
    |--------------------------------------------------------------------------
    |
    | Config do App\Services\Radar\LlmProvider (modelo, tools de busca web etc.).
    | Sem ANTHROPIC_API_KEY, provider fica nulo e enrichment_status = skipped.
    |
    */
    'llm' => [],

    /*
    |--------------------------------------------------------------------------
    | Timezone do domínio
    |--------------------------------------------------------------------------
    |
    | Datas de edital são locais (America/Maceio) — ver docs/01-modelo-de-dados.md.
    |
    */
    'timezone' => 'America/Maceio',

];
