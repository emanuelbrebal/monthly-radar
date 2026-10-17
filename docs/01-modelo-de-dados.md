# Modelo de dados

Postgres. Datas de edital em `date` (local `America/Maceio`), marcos de sistema em `timestamptz`. Dinheiro em centavos (`integer`).

## Enums (`app/Enums/`, backed string)

- `TiArea`: `development`, `data`, `infrastructure`, `security`, `support`, `it_governance`, `all`
- `EditalStatus`: ver `docs/00-spec.md` (tem método `isClosedOrLater(): bool` e `label(): string`)
- `EnrollmentStatus`: `not_enrolled`, `interested`, `enrolled`, `fee_paid`, `venue_released`, `exam_taken`, `dismissed`. Métodos: `countsAsEnrolled(): bool` → true de `enrolled` a `exam_taken`; `order(): ?int` → `null` para `dismissed`; `isStep(): bool` → false para `dismissed`.
- `Regime`: `clt`, `statutory`, `temporary`, `unknown`
- `EventType`: `registration_start`, `registration_end`, `fee_deadline`, `exam`, `result`, `other`
- `EnrichmentStatus`: `pending`, `skipped`, `done`, `failed`
- `Confidence`: `high`, `medium`, `low`, `unconfirmed` (curadoria manual sem fonte localizada)
- `RunStatus`: `queued`, `scraping`, `enriching`, `done`, `failed`
- `BrazilianState` (27 siglas, com `region()`) e `Region` — ver `docs/06-filtro-estados.md`
- `Sphere`: `federal`, `estadual`, `distrital`, `municipal` — esfera do órgão, curadoria manual
- `BoardAffinity`: `alta`, `media`, `media_baixa`, `baixa` — quanto o estudo em andamento aproveita para a banca, julgamento pessoal

## Tabelas

### `contests`

| coluna | tipo | nota |
|---|---|---|
| id | bigint pk | |
| slug | string unique | `Str::slug("{organ_acronym}-{year}")`, ex. `dataprev-2026` |
| organ | string | nome do órgão |
| organ_acronym | string null | |
| year | smallint | |
| title | string | ex. "Dataprev 2026 — Analista de TI" |
| board | string null | banca |
| areas | jsonb | array de `TiArea` em que o concurso casou |
| status | string (EditalStatus) | último status **raspado/enriquecido** |
| status_changed_at | timestamptz | |
| closed_at | timestamptz null | primeiro momento em que o status efetivo virou encerrado ou posterior |
| registration_start | date null | |
| registration_end | date null | |
| exam_date | date null | |
| fee_cents | int null | |
| salary_min_cents / salary_max_cents | int null | só cargos de TI |
| it_vacancies | int null | null = desconhecido; 0 = só cadastro reserva |
| regime | string (Regime) | |
| locations | jsonb | array de strings ("Natal/RN"), só exibição |
| states | jsonb default `[]` | siglas de UF normalizadas — ver `docs/06-filtro-estados.md` |
| nationwide | boolean default false | abrangência nacional |
| edital_url | text null | preferir PDF oficial |
| registration_url | text null | |
| dossier | jsonb null | saída validada do agente (schema em `docs/agents/dossie.md`) |
| field_sources | jsonb | `{campo: {url, fetched_at, origin: scrape\|llm\|seed, confidence}}` |
| enrichment_status | string | |
| enrollment_status | string | default `not_enrolled` |
| dismissed_at | timestamptz null | preenchido ao descartar, limpo ao trazer de volta |
| dismissed_reason | string(120) null | |
| pinned_slot | smallint null | 0–11; **unique index parcial** `where pinned_slot is not null` |
| last_seen_at | timestamptz | última varredura que encontrou o concurso |
| last_enriched_at | timestamptz null | |
| timestamps | | |

Índices: `status`, `registration_end`, `exam_date`, GIN em `areas` e em `states`.

#### Campos de curadoria manual

Adicionados em 18/09/2026 para receber uma pesquisa feita fora do app (fora do fluxo de scraping/LLM). **Nenhum é preenchido pela varredura** — nascem `null` em todo concurso raspado e só existem no que o dono do radar pesquisa e cola no seed. Não têm regra de negócio própria (`ContestStatusResolver`, `PinService` etc. os ignoram).

| coluna | tipo | nota |
|---|---|---|
| sphere | string (`Sphere`) null | esfera do órgão |
| education_level | string null | `superior` \| `medio_tecnico` — sem enum próprio, não tem lógica associada ainda |
| is_it_role | boolean null | `null` = não confirmado se o cargo é de TI; `false` = confirmadamente não é (concurso mantido só para comparação) |
| immediate_vacancies / reserve_vacancies / open_competition_vacancies | smallint null | granularidade que `it_vacancies` não tem |
| salary_note | text null | observação sobre `salary_min_cents`/`salary_max_cents` (ex. "inclui gratificação") |
| board_affinity | string (`BoardAffinity`) null | quanto o estudo em andamento aproveita pra essa banca |
| board_affinity_note | text null | |
| tier | string (`ContestTier`) null | nível do concurso na escada: `elite` \| `alto` \| `intermediario` \| `entrada`. Régua: salário e tipo de órgão indicam o nível de quem disputa; número de vagas diz se é disputa ou loteria. Só alimenta a marca de cor e a vista "por nível" da maleta — sem regra de negócio |
| tier_note | text null | justificativa curta do nível (ex. "R$ 20 mil, Cebraspe, 10 vagas por especialidade") |
| workplace_city / workplace_state | string / string(2) null | lotação específica — distinto de `locations`/`states`, que são pro filtro e exibição |
| distance_from_home_km | smallint null | distância até a cidade de referência do dono do radar (Maceió) — critério de qualidade de vida, não usar pra custo de mudança |
| exam_city | string null | cidade onde a prova é feita, quando diverge da lotação |
| essay_exam_date | date null | prova discursiva, quando existe além da objetiva (`exam_date`) |
| validity_months / validity_extendable | smallint null / boolean null | validade do concurso |
| source_checked_at | date null | quando essa pesquisa manual foi conferida — distinto de `last_seen_at` (varredura automática) |
| confidence | string (`Confidence`) null | confiança geral da linha, quando não há `field_sources` granular (curadoria manual costuma vir sem isso) |
| personal_notes | text null | resumo/observações pessoais — distinto de `dossier`, que é saída do agente de enriquecimento |

`dismissed_reason` passou de `string(120)` para `text` — motivos reais de descarte da curadoria manual passam de 120 caracteres.

### `users` (coluna nova)

`preferences jsonb default '{}'` → `{states: string[]}` (siglas + `"NACIONAL"`).

### `contest_events`

`id, contest_id fk cascade, type (EventType), date (date), label (string null), source (scrape|llm), timestamps`. Unique `(contest_id, type, date)`.
Recriados a cada merge a partir das colunas de data + `dossier.datas` extras. É o que o calendário lê.

### `contest_status_logs`

`id, contest_id fk, from (string null), to (string), origin (scrape|llm|resolver|seed), note (string null), created_at`.
Uma linha por mudança de status efetivo. Alimenta o "histórico" do modal.

### `contest_changes`

`id, contest_id fk, search_run_id fk null (nullOnDelete), kind (created|updated|failed), field (string null), old_value (jsonb null), new_value (jsonb null), origin (scrape|llm|seed), note (text null), created_at`.
Histórico de alterações (T7). Escrito por `ContestMerger::apply`: uma linha `created` por concurso novo e uma `updated` por campo que mudou de fato (ignora `field_sources`, `dossier`, carimbos e `enrichment_status`). Linhas `failed` vêm do enriquecimento (`note` = último erro). Base do selo "novo/mudou/falhou" e da tela de atividade.

### `raw_listings`

`id, source (string), external_url (text), title (string), payload (jsonb), content_hash (char(64) unique), contest_id (fk null), fetched_at`.
Cache e trilha do scraping. Mesmo hash = não reprocessa.

### `search_runs`

`id, area (TiArea), status (RunStatus), started_at, finished_at, counts (jsonb: {scraped, matched, created, updated, enriched, failed}), meta (jsonb: {input_tokens, output_tokens, web_searches, errors: []}), timestamps`.
Só uma run `queued|scraping|enriching` por vez → o botão fica desabilitado.

## Regras no model `Contest`

```php
// status efetivo — delega a ContestStatusResolver (puro, testado)
public function effectiveStatus(?CarbonImmutable $now = null): EditalStatus

// momento de encerramento efetivo:
// closed_at ?? (registration_end ? registration_end->endOfDay() (America/Maceio) : null)
public function effectiveClosedAt(): ?CarbonImmutable

// some da listagem?
// effectiveStatus()->isClosedOrLater()
//   && ! enrollment_status->countsAsEnrolled()
//   && effectiveClosedAt() <= now()->subDay()
public function isExpired(?CarbonImmutable $now = null): bool

// descartado? enrollment_status === dismissed
public function isDismissed(): bool

// escopo SQL equivalente a ! isExpired() && ! isDismissed() — precisa bater com o método PHP (teste de paridade)
public function scopeVisible(Builder $q): Builder

// só os descartados (para o toggle da caixa de itens); ignora a regra das 24h
public function scopeDismissed(Builder $q): Builder
```

Observações:
- `scopeVisible` usa `coalesce(closed_at, (registration_end + interval '1 day') at time zone 'America/Maceio')` para o encerramento efetivo. Status "fechado ou posterior" no SQL = `status in (...) OR registration_end < current_date local`.
- Desafixar expirados: `UnpinExpiredContests` action roda no início de cada request da tela principal (`RadarPageController`) e no fim de cada varredura (`FinalizeRunJob`). Sem scheduler. Até 17/10/2026 só rodava na varredura; sem varrer, expirado segurava o slot ("slot fantasma").
- Setar `closed_at`: o `ContestMerger` preenche na primeira vez que o status efetivo passa a fechado; nunca sobrescreve depois, a não ser que reabra (aí volta para null).

## Testes obrigatórios desta camada

- `ContestStatusResolver`: tabela de casos (status raspado × datas × hoje).
- `isExpired`: fechado há 23h59 (visível), 24h01 (some), inscrito (nunca some), reaberto (volta).
- Paridade `isExpired()`/`isDismissed()` × `scopeVisible()` sobre o mesmo dataset.
- Descartar: some do visible na hora, libera o slot, limpa ao trazer de volta; `PinService` recusa concurso descartado.
- `pinned_slot` único; fixar com maleta cheia falha com exceção de domínio.
