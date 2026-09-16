# Pipeline da varredura

```
[botão varrer + área]
      │
POST /sweeps  → SearchRun(queued) → Bus::chain
      │
 1. HarvestSourcesJob     scraping de cada fonte ativa → raw_listings
 2. NormalizeListingsJob  filtro por área → normaliza → dedup → upsert contests (origin=scrape)
 3. EnrichContestsJob     seleciona concursos com lacuna → Bus::batch(EnrichContestJob…)
 4. FinalizeRunJob        recalcula status/eventos, desafixa expirados, fecha a run
      │
GET /sweeps/{run} (polling 3s) → front atualiza
```

Falha num adapter não derruba a run: grava em `meta.errors` e segue. Falha total do scraping → run `failed` com mensagem.

## 1. Fontes (MVP: 3 adapters)

Interface `App\Services\Radar\Sources\ContestSource`:

```php
public function key(): string;                 // 'provas_de_ti'
public function enabled(): bool;               // config/radar.php
/** @return iterable<RawListing> */ 
public function fetch(): iterable;             // título, url, trecho de texto, datas cruas, links
```

| key | alvo | por quê |
|---|---|---|
| `provas_de_ti` | páginas de concursos de TI abertos e previstos do Provas de TI | agregador já focado em TI |
| `pci_concursos` | páginas por estado + página nacional do PCI Concursos, só as marcadas no filtro | cobertura ampla; filtra por palavra-chave |
| `official_pages` | lista curta de páginas oficiais em `config/radar.php` (ex.: página do concurso na banca/órgão) cadastradas à mão | fonte primária dos concursos que já acompanho |

`[inferido]` As URLs e seletores exatos ficam em `config/radar.php` e **devem ser verificados na T2** (estrutura HTML muda). Antes de ativar cada fonte: checar `robots.txt` e termos de uso; se proibir, desativar e anotar no README.

Regras de scraping:
- `Http::withHeaders(['User-Agent' => 'radar-mensal/1.0 (uso pessoal; contato: <email>)'])->timeout(15)->retry(2, 1000)`.
- No máximo 1 request a cada 2s por host (`RateLimiter` por host). Máximo de páginas por fonte em config (padrão 5).
- Parse com `symfony/dom-crawler` + `symfony/css-selector`.
- `content_hash = sha256(source|url|texto normalizado)`. Hash já existente e `fetched_at` < 6h → pula.
- Nada de JS/headless. Página vazia porque depende de JS → erro "fonte exige navegador" em `meta.errors`.

## 2. Normalização e dedup (PHP puro)

`ContestNormalizer`:
- **Filtro por área:** `config('radar.area_keywords')` mapeia cada `TiArea` para termos (ex. development: `desenvolv`, `programador`, `software`, `sistemas`, `analista de ti`, `tecnologia da informação`). `all` = união. Sem match → descarta.
- **Órgão/sigla/ano:** heurística por regex no título (`/(\b[A-Z]{2,}(?:-[A-Z]{2})?\b)/`, ano `/20\d{2}/`); sem ano → ano da data mais próxima ou ano corrente.
- **Datas:** extrair `dd/mm/aaaa` e "de X a Y de mês"; classificar pelo contexto (≤ 40 chars antes: "inscriç", "prova", "resultado", "taxa").
- **Status raspado:** palavras-chave ("inscrições abertas", "edital publicado", "banca definida", "autorizado", "previsto", "suspenso"). Sem sinal → `expected`.
- **Links:** `edital_url` = href com `edital` e `.pdf`, preferindo domínio de banca/gov.
- **Dedup:** match por `slug`; sem match, similaridade ≥ 0.85 (`similar_text` normalizado) no `organ` do mesmo ano → mesmo concurso, com log de aviso.

`ContestMerger` (usado aqui e no passo 3):
- Campo vazio → preenche.
- Origem `seed` (ver `docs/05-seed-inicial.md`): com URL oficial conta como oficial; sem ela, prioridade de agregador.
- Campo preenchido → só troca se a origem nova tiver prioridade ≥ a atual: `oficial (edital/banca/DOU/.gov.br) > agregador > llm sem fonte oficial`.
- Toda escrita atualiza `field_sources[campo]`.
- Mudança de status efetivo → linha em `contest_status_logs`; aplica regra do `closed_at`.
- Regenera `contest_events`.

## 3. Enriquecimento (LLM + busca web)

Roda só para concursos da run que tenham **lacuna**:

- falta qualquer um: `board`, `registration_start`, `registration_end`, `exam_date`, `salary_min_cents`, `it_vacancies`, `edital_url`, `regime`; **ou**
- status mudou nesta run; **ou**
- `last_enriched_at` nulo ou > 7 dias.

Concursos expirados (regra das 24h) e descartados nunca são enriquecidos. Concursos fora do filtro de estados também não, exceto os fixados e os inscritos. Limite por run: `config('radar.enrich.max_per_run')` = 15, os com data mais próxima primeiro.

`EnrichContestJob`:
1. Monta o input: campos conhecidos + `field_sources` + trechos dos `raw_listings` ligados.
2. `LlmProvider::complete(system: prompt dossie.md, input, tools: [web_search max_uses=5])`.
3. Parse JSON → valida contra o schema (`DossierValidator`, regras Laravel sobre o array). Inválido → 1 retry com a mensagem de erro anexada. Falhou de novo → `enrichment_status=failed`.
4. Válido → `ContestMerger` com origem de cada campo vinda de `dossier.campos[*].fonte`. Salva `dossier` inteiro.
5. Loga tokens e nº de buscas em `search_runs.meta`.

Provider:
- Interface `LlmProvider` + `AnthropicProvider` (HTTP direto no `/v1/messages`, tool `web_search` do servidor da Anthropic).
- `config/radar.php → llm.model` padrão `claude-haiku-4-5-20251001` `[inferido — confirmar custo/qualidade na T3]`. Busca web é cobrada à parte por busca: conferir o preço atual na doc da Anthropic e registrar no README.
- Sem `ANTHROPIC_API_KEY` → provider nulo → `enrichment_status=skipped`.

## 4. Status efetivo (`ContestStatusResolver`)

Entrada: status armazenado, datas, hoje (local). Regras em ordem:

1. `suspended` ou `finished` → mantém.
2. `exam_date` < hoje → no mínimo `exam_done`.
3. `registration_end` < hoje → no mínimo `registration_closed`.
4. `registration_start` ≤ hoje ≤ `registration_end` e status < `registration_open` → `registration_open`.
5. `registration_start` > hoje e status ≥ `registration_open` → `published` (edital retificado/adiado).
6. Senão → status armazenado.

"No mínimo" = maior entre o armazenado e o sugerido na ordem da tabela da spec (exceto `suspended`).

## Endpoints

| método | rota | faz |
|---|---|---|
| GET | `/` | tela principal (props: visibleContests, pinned, events do mês, lastRun, areas) |
| POST | `/sweeps` | `{area}` → cria run (409 se já houver uma ativa) |
| GET | `/sweeps/{run}` | status/counts para polling (JSON) |
| GET | `/contests/{contest}` | dossiê completo (partial reload do modal) |
| PATCH | `/contests/{contest}/enrollment` | `{status, reason?}`. `dismissed` preenche `dismissed_at`/`reason` e desafixa; sair de `dismissed` limpa os dois |
| GET | `/contests/dismissed` | lista os descartados (partial reload da caixa de itens) |
| POST | `/contests/{contest}/pin` | fixa no primeiro slot livre, ou `{slot}` |
| DELETE | `/contests/{contest}/pin` | desafixa |
| PATCH | `/preferences/states` | `{states}` — ver `docs/06-filtro-estados.md` |
| PATCH | `/pins` | `{order: [contestId|null ×12]}` reordena |
| GET | `/calendar?month=YYYY-MM` | eventos do mês (partial reload) |

Todas as leituras de listagem usam `Contest::visible()`.

## CLI

`radar:sweep {area} {--no-llm}` roda a mesma cadeia de forma síncrona. `radar:source {key} {--dry}` imprime o que o adapter extraiu, sem gravar.
