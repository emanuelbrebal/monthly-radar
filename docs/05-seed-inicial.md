# Seed inicial — concursos já mapeados

Arquivo: `database/seeders/data/contests-seed.json` (27 concursos, atualizado em 18/09/2026). Seeder: `ContestSeeder`, chamado pelo `DatabaseSeeder` depois do `UserSeeder`.

Os dados vêm das pesquisas feitas no Claude entre 20/07 e 17/09/2026. Eles servem de ponto de partida. **A primeira varredura com área `all` confere tudo:** todo concurso semeado nasce com `last_enriched_at = null`, então entra na fila de enriquecimento.

## O que o seeder faz

Para cada item de `contests`:

1. `updateOrCreate` por `slug`. Rodar o seeder duas vezes não duplica nada.
2. Colunas: copia os campos homônimos, incluindo os campos de curadoria manual descritos em `docs/01-modelo-de-dados.md` (`sphere`, `education_level`, `is_it_role`, `immediate_vacancies`/`reserve_vacancies`/`open_competition_vacancies`, `salary_note`, `board_affinity`/`board_affinity_note`, `workplace_city`/`workplace_state`, `distance_from_home_km`, `exam_city`, `essay_exam_date`, `validity_months`/`validity_extendable`, `source_checked_at`, `confidence`, `personal_notes`). `status_changed_at = now()`, `last_seen_at = now()`, `enrichment_status = pending`.
3. `dossier` = `{summary, divergences, gaps, it_roles: [], syllabus: [], sources: [{url: source_url, name: null, kind, accessed: _meta.collected_between}]}`. `kind` vale `official` se o domínio for `.gov.br` ou de banca da config; senão, `aggregator`. Sem URL → `sources: []`.
4. `field_sources`: para cada campo não nulo, `{url: source_url, fetched_at: null, origin: "seed", confidence}`.
5. Passa pelo `ContestMerger` só para gerar `contest_events` (colunas de data, incluindo `essay_exam_date`, + `extra_events`) e aplicar a regra do `closed_at`. Log de status com `origin = seed`.
6. `states` e `nationwide` vêm do JSON. Petrobras e BACEN ficam com `states = []` (local a definir). O Banco do Brasil é `nationwide`.
7. `pinned_slot`, `enrollment_status` e `dismissed_reason` vêm do JSON. Para os `dismissed`, `dismissed_at = now()`. Slot duplicado → exceção (o arquivo está errado).
8. `UserSeeder` cria `preferences.states` com todos os estados + `NACIONAL`.

**Regra ao atualizar este arquivo com pesquisa nova**: `enrollment_status`, `pinned_slot` e `dismissed_reason` de um concurso que já existe no seed são decisão do dono do radar — não sobrescrever com o resultado de uma pesquisa nova sem confirmar antes. Só os campos factuais (dinheiro, datas, vagas, notas) devem ser atualizados livremente.

## Ajustes nos outros docs

- `docs/01-modelo-de-dados.md`: `field_sources[*].origin` e `contest_status_logs.origin` aceitam também `seed`. Seção "Campos de curadoria manual" documenta as colunas que só a pesquisa manual preenche.
- `docs/02-pipeline.md → ContestMerger`: `seed` com URL oficial conta como **oficial**; senão, tem a mesma prioridade de **agregador**. Assim, a varredura pode corrigir qualquer dado semeado que tenha vindo de blog.

## Conteúdo semeado (rodada de 17/09/2026)

| slug | status | inscrição até | prova | minha situação | slot |
|---|---|---|---|---|---|
| dataprev-2026 | inscrições encerradas | 06/08 | 11/10 | taxa paga | 0 |
| transpetro-2026 | inscrições abertas | 21/09 | 06/12 | quero fazer | 1 |
| sema-mt-2026 | edital publicado (abre 17/09) | 14/10 | 13/12 | quero fazer | 2 |
| anpd-2026 | autorizado | — | — | quero fazer | 3 |
| cgu-2026 | autorizado | — | — | quero fazer | 4 |
| petrobras-2026 | edital em produção | — | — | quero fazer | 5 |
| sefaz-al-afte-2026 | inscrições abertas | 21/10 | 20/12 | quero fazer | — |
| transpetro-2026-infra | inscrições abertas | 21/09 | 06/12 | descartado (lotação RJ) | — |
| seplag-rj-2026 | inscrições abertas | 08/10 | 29/11 | descartado (ENADE) | — |
| manausprev-2026 | inscrições abertas | 29/09 | 06/12 | descartado (Transpetro) | — |
| tcdf-anace-gestao-2026 | inscrições abertas | 17/09 | 22/11 | descartado (não é TI + Brasília) | — |
| bacen-2026 | previsto | — | — | quero fazer | — |
| trf3-2026 | edital em produção | — | — | não inscrito | — |
| bnb-2026 | previsto | — | — | não inscrito | — |
| banco-do-brasil-2027 | previsto | — | — | não inscrito | — |
| mpu-analista-ti-2027 | previsto | — | — | quero fazer | — |
| trt19-analista-ti-2027 | previsto | — | — | quero fazer | — |
| trf5-analista-ti-2028 | previsto | — | — | quero fazer | — |
| ifal-tae-ti-2027 | previsto | — | — | quero fazer | — |
| ufal-tae-ti-2027 | previsto | — | — | quero fazer | — |
| tj-ce-analista-ti-2027 | previsto | — | — | quero fazer | — |
| tce-se-servidores-2027 | previsto | — | — | quero fazer | — |
| tj-pe-servidores-2027 | previsto | — | — | quero fazer | — |
| detran-al-analise-sistemas-2026 | inscrições encerradas há meses | 09/06 | 20/09 | não inscrito — **janela perdida** | — |
| ufpe-analista-ti-desenvolvimento-2026 | inscrições encerradas há meses | 08/09 | 24/01/2027 | não inscrito — **janela perdida** | — |
| ifpe-tecnico-ti-2026 | inscrições encerradas há meses | 04/09 | — | não inscrito — **janela perdida** | — |
| ifpi-analista-ti-2026 | inscrições encerradas há meses | 13/09 | 18/10 | não inscrito — **janela perdida** | — |

As quatro últimas ("janela perdida") somem da mesa pela regra normal das 24h — não têm status especial no banco, é só `enrollment_status = not_enrolled` com uma data de encerramento efetivo bem no passado. Ficam no banco pro histórico e pro dedup, mas não aparecem em lugar nenhum da UI até a T5 trazer uma visão dedicada (se um dia fizer sentido).

Ficaram de fora de propósito:
- **BNDES, BRB, BDMG e BASA:** têm lista vigente, sem processo novo.
- **Serpro:** o cadastro de reserva de 2023 ainda está sendo consumido.
- **TCU e TJs estaduais (exceto TJ-CE/TJ-PE, agora mapeados como vigia):** os dados eram vagos demais para virar registro.

## Casos de teste que o seed cobre de graça

- Dataprev: encerrado há mais de 24h, mas com a taxa paga → **continua visível**.
- Transpetro: status corrigido por retificação (prova adiada) → evento do calendário em 06/12, não em 29/11.
- SEMA-MT: `published` com `registration_start` = 17/09 → o resolver passa a mostrar "inscrições abertas" a partir desse dia.
- SEPLAG-RJ, ManausPrev, Transpetro-infra e TCDF: descartados → fora da mesa desde o seed, visíveis só no toggle `descartados (4)`.
- DETRAN-AL, UFPE, IFPE, IFPI: inscrição encerrada há meses, nunca inscrito → já nascem fora de `visible()`, cobrindo a regra das 24h com dado real em vez de factory.
- TCDF: `is_it_role = false` com `dismissed_reason` — cobre o campo de curadoria manual num concurso descartado por não ser de TI, não só por conflito de agenda.
