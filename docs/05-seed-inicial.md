# Seed inicial — concursos já mapeados

Arquivo: `database/seeders/data/contests-seed.json` (12 concursos). Seeder: `ContestSeeder`, chamado pelo `DatabaseSeeder` depois do `UserSeeder`.

Os dados vêm das pesquisas feitas no Claude entre 20/07 e 15/09/2026. Eles servem de ponto de partida. **A primeira varredura com área `all` confere tudo:** todo concurso semeado nasce com `last_enriched_at = null`, então entra na fila de enriquecimento.

## O que o seeder faz

Para cada item de `contests`:

1. `updateOrCreate` por `slug`. Rodar o seeder duas vezes não duplica nada.
2. Colunas: copia os campos homônimos. `status_changed_at = now()`, `last_seen_at = now()`, `enrichment_status = pending`.
3. `dossier` = `{summary, divergences, gaps, it_roles: [], syllabus: [], sources: [{url: source_url, name: null, kind, accessed: _meta.collected_between}]}`. `kind` vale `official` se o domínio for `.gov.br` ou de banca da config; senão, `aggregator`. Sem URL → `sources: []`.
4. `field_sources`: para cada campo não nulo, `{url: source_url, fetched_at: null, origin: "seed", confidence}`.
5. Passa pelo `ContestMerger` só para gerar `contest_events` (colunas de data + `extra_events`) e aplicar a regra do `closed_at`. Log de status com `origin = seed`.
6. `states` e `nationwide` vêm do JSON. Petrobras, BACEN e BNB ficam com `states = []` (local a definir). O Banco do Brasil é `nationwide`.
7. `pinned_slot`, `enrollment_status` e `dismissed_reason` vêm do JSON. Para os `dismissed`, `dismissed_at = now()`. Slot duplicado → exceção (o arquivo está errado).
8. `UserSeeder` cria `preferences.states` com todos os estados + `NACIONAL`.

## Ajustes nos outros docs

- `docs/01-modelo-de-dados.md`: `field_sources[*].origin` e `contest_status_logs.origin` aceitam também `seed`.
- `docs/02-pipeline.md → ContestMerger`: `seed` com URL oficial conta como **oficial**; senão, tem a mesma prioridade de **agregador**. Assim, a varredura pode corrigir qualquer dado semeado que tenha vindo de blog.

## Conteúdo semeado

| slug | status | inscrição até | prova | minha situação | slot |
|---|---|---|---|---|---|
| dataprev-2026 | inscrições encerradas | 06/08 | 11/10 | taxa paga | 0 |
| transpetro-2026 | inscrições abertas | 21/09 | 06/12 | quero fazer | 1 |
| sema-mt-2026 | edital publicado (abre 17/09) | 14/10 | 13/12 | quero fazer | 2 |
| anpd-2026 | autorizado | — | — | quero fazer | 3 |
| cgu-2026 | autorizado | — | — | quero fazer | 4 |
| petrobras-2026 | edital em produção | — | — | quero fazer | 5 |
| seplag-rj-2026 | inscrições abertas | 08/10 | 29/11 | descartado (ENADE) | — |
| manausprev-2026 | inscrições abertas | 29/09 | 06/12 | descartado (Transpetro) | — |
| bacen-2026 | previsto | — | — | quero fazer | — |
| trf3-2026 | edital em produção | — | — | não inscrito | — |
| bnb-2026 | previsto | — | — | não inscrito | — |
| banco-do-brasil-2027 | previsto | — | — | não inscrito | — |

Ficaram de fora de propósito:
- **TCDF:** o cargo não é de TI.
- **BNDES, BRB, BDMG e BASA:** têm lista vigente, sem processo novo.
- **Serpro:** o cadastro de reserva de 2023 ainda está sendo consumido.
- **TCU e TJs estaduais:** os dados eram vagos demais para virar registro.

## Casos de teste que o seed cobre de graça

- Dataprev: encerrado há mais de 24h, mas com a taxa paga → **continua visível**.
- Transpetro: status corrigido por retificação (prova adiada) → evento do calendário em 06/12, não em 29/11.
- SEMA-MT: `published` com `registration_start` = 17/09 → o resolver passa a mostrar "inscrições abertas" a partir desse dia.
- SEPLAG-RJ e ManausPrev: descartados → fora da mesa desde o seed, visíveis só no toggle `descartados (2)`.
