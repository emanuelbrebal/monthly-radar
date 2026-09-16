# CLAUDE.md — monthly-radar

Contexto para o Claude Code ao trabalhar neste repositório.

## O que é este projeto

Radar Mensal · Concursos: app pessoal (só o Emanuel, sem multiusuário, sem SaaS) que varre a web por concursos públicos de TI no Brasil, organiza cada um como dossiê, mostra tudo num calendário e numa "maleta" (estilo inventário de Resident Evil) de 12 slots para os que importam. Monolito Laravel 13 + Inertia 3 + React 19/TS + Tailwind 4 + PostgreSQL. Documento de referência: `docs/00-spec.md` — **leia antes de qualquer tarefa**, junto com `docs/01-modelo-de-dados.md` (dados) e `docs/02-pipeline.md` (varredura/enriquecimento). Plano de execução por sessão ("tardes" T0–T6) em `docs/04-plano-execucao.md`.

> Pivot de 15/09/2026: este repo era "Radar Hub" (curador de ideias SaaS via pipeline de agentes LLM). Esse produto foi descontinuado; não recrie nada dele.

**Repo:** `C:\Users\manub\projects\monthly-radar` → GitHub: `https://github.com/emanuelbrebal/monthly-radar`

## Regras invioláveis

1. **Escopo é lei.** Implemente só o que está na tarda (T0–T6) sendo executada em `docs/04-plano-execucao.md`. Se uma tarefa tocar item do "Backlog v2" (varredura agendada, notificações, .ics, itens de tamanhos diferentes na maleta, anotações livres, headless browser, leitura de PDF, mobile), pare e pergunte.
2. **A varredura é a única operação.** Sem cron de busca, sem e-mail, sem multiusuário, sem cadastro público. Login vem de seeder (`UserSeeder`), Fortify com `Features::registration()` desligado.
3. **App funciona sem LLM.** Sem `ANTHROPIC_API_KEY`, a varredura ainda faz scraping e popula a tela — o enriquecimento fica `enrichment_status=skipped`. Nenhuma tela pode quebrar por causa disso.
4. **LLM só via `LlmProvider`.** Nunca chamar SDK/HTTP de provider diretamente em Job ou Controller. Config em `config/radar.php → llm`.
5. **Zero LLM para o mecânico.** Dedup (`ContestNormalizer`), status efetivo (`ContestStatusResolver`), regra das 24h, pin (`PinService`), merge de campos (`ContestMerger`): tudo PHP puro, com testes Pest.
6. **Output do agente de enriquecimento é JSON validado** contra o schema de `docs/agents/dossie.md` (quando esse doc existir — T3). Retry 1x; segunda falha → `enrichment_status=failed`.
7. **Prioridade de fonte em qualquer merge de campo:** oficial (edital/banca/DOU/.gov.br) > agregador > seed sem URL oficial > llm sem fonte oficial. Toda escrita atualiza `field_sources[campo]`.
8. **Custo:** logar `input_tokens`/`output_tokens`/nº de buscas de toda chamada LLM em `search_runs.meta`.
9. **Idioma:** código, commits e nomes de branch em **inglês**; conteúdo semeado/raspado/gerado (dossiês, resumos) em **português**.

## Convenções

- Services em `app/Services/Radar/` (`ContestStatusResolver`, `PinService`, `ContestMerger`, `ContestNormalizer`, `LlmProvider` e implementações em `Llm/`).
- Actions em `app/Actions/Radar/` (ex. `UnpinExpiredContests`) quando a operação é um passo único e idempotente, sem estado próprio.
- Jobs (a partir da T3) em `app/Jobs/Radar/`, encadeados com `Bus::chain`. Idempotentes — `content_hash` único em `raw_listings` evita reprocesso.
- Enums em `app/Enums/` (backed string): `TiArea`, `EditalStatus`, `EnrollmentStatus`, `Regime`, `EventType`, `EnrichmentStatus`, `Confidence`, `RunStatus`, e (a partir da T2) `BrazilianState`/`Region`.
- Front: páginas Inertia em `resources/js/pages/`, componentes em `resources/js/components/`. Tailwind 4. Tokens visuais só do handoff em `design/radar mensal/` — sem cara de shadcn padrão, `radius: 0`.
- Testes: Pest, em `tests/Unit` e `tests/Feature`. Mínimo obrigatório da camada de domínio: `ContestStatusResolver` (tabela de casos), `isExpired`/regra das 24h, paridade `isExpired()+isDismissed()` vs `scopeVisible()`, descarte, `pinned_slot` único.
- Migrations seguem exatamente o modelo de dados de `docs/01-modelo-de-dados.md` — não inventar coluna. Colunas de filtro por estado (`states`, `nationwide`, `users.preferences`) só entram na T2 (`docs/06-filtro-estados.md`).

## Comandos úteis

```bash
php artisan test                              # Pest
php artisan migrate:fresh --seed              # sobe schema + 12 concursos de database/seeders/data/contests-seed.json
php artisan radar:sweep {area} {--no-llm}      # varredura síncrona (a partir da T3)
php artisan radar:source {key} {--dry}         # testa um adapter sem gravar (a partir da T2)
```

## O que NÃO fazer

- Não implementar scraping headless, agendamento, notificações ou export `.ics` — Backlog v2.
- Não trocar a stack (sem Next, sem tRPC, sem Filament).
- Não reabrir cadastro público (`Features::registration()`).
- Não adicionar fonte de scraping além das 3 do MVP (`provas_de_ti`, `pci_concursos`, `official_pages`).
- Não implementar o filtro de estados (`BrazilianState`/`Region`/`states`/`nationwide`) fora da T2.
- Não escrever prompt de agente novo sem registrar em `docs/agents/`.
