<?php

// Esqueleto criado na T0, preenchido nas T2/T3 — ver docs/02-pipeline.md,
// docs/06-filtro-estados.md e docs/04-plano-execucao.md. Não adicionar chaves
// fora das listadas aqui sem atualizar esses docs primeiro. 'states' foi
// adicionada na T2 por docs/06-filtro-estados.md (posterior ao plano original).

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
    | 'provas_de_ti' desativada: verificado na T2 que
    | provasdeti.com.br/concursosdeti/abertos é uma SPA React sem HTML no
    | servidor (sem headless browser não tem o que raspar) — ver
    | App\Services\Radar\Sources\ProvasDeTiSource. 'pci_concursos' e
    | 'official_pages' entram na T6.
    |
    */
    'sources' => [
        'provas_de_ti' => [
            'enabled' => false,
            'max_pages' => 5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Palavras-chave por área (T2)
    |--------------------------------------------------------------------------
    |
    | Mapa TiArea::value => array de termos usados pelo ContestNormalizer para
    | filtrar listagens raspadas. 'all' é a união de todas as áreas.
    |
    */
    'area_keywords' => [
        'development' => ['desenvolv', 'programador', 'software', 'sistemas', 'analista de ti', 'analista de sistemas', 'desenvolvedor'],
        'data' => ['dados', 'data science', 'cientista de dados', 'engenheiro de dados', 'business intelligence', 'banco de dados', 'dba'],
        'infrastructure' => ['infraestrutura', 'redes', 'suporte de rede', 'devops', 'cloud', 'sysadmin', 'administrador de sistemas'],
        'security' => ['segurança da informação', 'cibersegurança', 'segurança de ti', 'pentest'],
        'support' => ['suporte técnico', 'help desk', 'atendimento ao usuário', 'técnico em informática'],
        'it_governance' => ['governança de ti', 'gestão de ti', 'arquitetura de ti', 'gestão de projetos de ti'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bancas conhecidas (T2)
    |--------------------------------------------------------------------------
    |
    | Lista/mapa de bancas organizadoras usada pelo ContestMerger para decidir
    | se uma URL de origem 'seed' ou 'aggregator' conta como oficial.
    |
    */
    'boards' => [
        'FGV', 'Cesgranrio', 'Cebraspe', 'FCC', 'FUNDATEC', 'IBFC', 'AOCP', 'Instituto AOCP',
        'Vunesp', 'IADES', 'Consulplan', 'Quadrix', 'IBADE', 'Instituto Access',
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Filtro de estados (T2)
    |--------------------------------------------------------------------------
    |
    | 'aliases': nome de estado ou capital por extenso → sigla, usado pelo
    | ContestNormalizer para preencher 'states' (docs/06-filtro-estados.md).
    | Chaves em minúsculo, sem acento — o normalizer compara já normalizado.
    |
    */
    'states' => [
        'aliases' => [
            'acre' => 'AC', 'rio branco' => 'AC',
            'alagoas' => 'AL', 'maceio' => 'AL',
            'amapa' => 'AP', 'macapa' => 'AP',
            'amazonas' => 'AM', 'manaus' => 'AM',
            'bahia' => 'BA', 'salvador' => 'BA',
            'ceara' => 'CE', 'fortaleza' => 'CE',
            'distrito federal' => 'DF', 'brasilia' => 'DF',
            'espirito santo' => 'ES', 'vitoria' => 'ES',
            'goias' => 'GO', 'goiania' => 'GO',
            'maranhao' => 'MA', 'sao luis' => 'MA',
            'mato grosso do sul' => 'MS', 'campo grande' => 'MS',
            'mato grosso' => 'MT', 'cuiaba' => 'MT',
            'minas gerais' => 'MG', 'belo horizonte' => 'MG',
            'para' => 'PA', 'belem' => 'PA',
            'paraiba' => 'PB', 'joao pessoa' => 'PB',
            'parana' => 'PR', 'curitiba' => 'PR',
            'pernambuco' => 'PE', 'recife' => 'PE',
            'piaui' => 'PI', 'teresina' => 'PI',
            'rio de janeiro' => 'RJ',
            'rio grande do norte' => 'RN', 'natal' => 'RN',
            'rio grande do sul' => 'RS', 'porto alegre' => 'RS',
            'rondonia' => 'RO', 'porto velho' => 'RO',
            'roraima' => 'RR', 'boa vista' => 'RR',
            'santa catarina' => 'SC', 'florianopolis' => 'SC',
            'sao paulo' => 'SP',
            'sergipe' => 'SE', 'aracaju' => 'SE',
            'tocantins' => 'TO', 'palmas' => 'TO',
        ],
    ],

];
