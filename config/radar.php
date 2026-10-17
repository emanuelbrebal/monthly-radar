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
    | chaveada por ContestSource::key(). Cada entrada guarda 'enabled' e a
    | config específica do adapter.
    |
    | 'provas_de_ti' desativada: verificado na T2 que
    | provasdeti.com.br/concursosdeti/abertos é uma SPA React sem HTML no
    | servidor (sem headless browser não tem o que raspar) — ver
    | App\Services\Radar\Sources\ProvasDeTiSource.
    |
    | 'pci_concursos' (T6): verificado em 18/09/2026 que
    | pciconcursos.com.br/concursos/ lista nacional + todos os estados numa
    | página só (não uma por estado, como a spec original supunha) — ver
    | App\Services\Radar\Sources\PciConcursosSource. robots.txt permite.
    |
    | 'official_pages' (T6): 'pages' vazio por padrão — adicionar manualmente
    | os concursos que você acompanha, ex.:
    |   ['url' => 'https://exemplo.gov.br/concurso-2026', 'organ_label' => 'Exemplo 2026']
    | Ver App\Services\Radar\Sources\OfficialPagesSource.
    |
    */
    'sources' => [
        'provas_de_ti' => [
            'enabled' => false,
            'max_pages' => 5,
        ],
        'pci_concursos' => [
            'enabled' => true,
            'url' => 'https://www.pciconcursos.com.br/concursos/',
        ],
        'official_pages' => [
            'enabled' => true,
            // Só páginas com HTML no servidor: as da Cebraspe e do bcb.gov.br
            // são SPA (sem headless não há texto) — verificado em 13/10/2026.
            // ANPD, BACEN e TJPB entram aqui quando tiverem página da banca.
            'pages' => [
                ['url' => 'https://www.gov.br/cgu/pt-br/acesso-a-informacao/concursos', 'organ_label' => 'CGU 2026 — Auditor Federal de Finanças e Controle'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Contato do User-Agent de scraping
    |--------------------------------------------------------------------------
    |
    | Usado no header User-Agent de toda fonte de scraping
    | (docs/02-pipeline.md → "1. Fontes"), pra quem administra o site raspado
    | poder identificar e contatar quem faz as requisições.
    |
    */
    'contact_email' => env('RADAR_USER_EMAIL', 'contato@example.com'),

    /*
    |--------------------------------------------------------------------------
    | Credenciais do único usuário do app (UserSeeder)
    |--------------------------------------------------------------------------
    |
    | Sem cadastro público — database/seeders/UserSeeder.php lê daqui (não
    | via env() direto, pra não quebrar com config cacheada em produção).
    |
    */
    'seed_user' => [
        'email' => env('RADAR_USER_EMAIL'),
        'password' => env('RADAR_USER_PASSWORD'),
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
    'enrich' => [
        'max_per_run' => 15,
        'gap_max_age_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Revisão dos acompanhados (T8)
    |--------------------------------------------------------------------------
    |
    | Botão "atualizar acompanhados" — ver docs/agents/revisao.md. Só por
    | clique, nunca agendado.
    |
    */
    'refresh' => [
        'max_per_run' => 20,
        'max_web_searches' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Domínios oficiais (T8)
    |--------------------------------------------------------------------------
    |
    | Além de .gov.br, contam como fonte oficial na prioridade de merge
    | (CLAUDE.md regra 7: "edital/banca/DOU/.gov.br"). Comparação por sufixo
    | do host.
    |
    */
    'official_domains' => [
        'gov.br', 'jus.br', 'mp.br', 'leg.br', 'def.br',
        'cebraspe.org.br', 'fgv.br', 'cesgranrio.org.br', 'concursosfcc.com.br',
        'vunesp.com.br', 'ibfc.org.br', 'institutoaocp.org.br', 'funcern.br',
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider LLM (T3)
    |--------------------------------------------------------------------------
    |
    | Config do App\Services\Radar\LlmProvider (modelo, tools de busca web etc.).
    | Sem ANTHROPIC_API_KEY, provider fica nulo e enrichment_status = skipped.
    | 'model' padrão é o Haiku 4.5 atual — barato o bastante pra rodar com
    | busca web em todo concurso com lacuna.
    |
    */
    'llm' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('RADAR_LLM_MODEL', 'claude-haiku-4-5-20251001'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1/messages'),
        'api_version' => '2023-06-01',
        'max_tokens' => 4096,
        'max_web_searches' => 5,
        'timeout' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Janela da tag "novo"
    |--------------------------------------------------------------------------
    |
    | Dias desde a criação do concurso em que ele ainda é "novo" (se o usuário
    | não agiu). Ver ContestPhaseResolver::isNew().
    |
    */
    'new_window_days' => 7,

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
