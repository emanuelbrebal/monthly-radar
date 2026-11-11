# Agente de revisão — acompanhados

Registrado por CLAUDE.md ("prompt de agente novo registrado em `docs/agents/`"). Roda em `RefreshContestJob` (T8, `docs/04-plano-execucao.md`), disparado só pelo botão "atualizar acompanhados" — nunca agendado. Prompt de sistema real em `resources/prompts/revisao.md`; este doc é a referência de humano + o schema que `App\Services\Radar\RefreshValidator` valida.

Diferença para o agente do dossiê (`docs/agents/dossie.md`): o dossiê preenche **lacunas**; a revisão procura o que **mudou** num concurso que já está completo, e toda fonte vem com data.

## Quando roda

`RefreshTrackedContestsJob` seleciona: `pinned_slot` preenchido **ou** `enrollment_status` em `interested|enrolled|fee_paid|venue_released|exam_taken`; fora descartados e status `finished`. Teto `radar.refresh.max_per_run`, revisado há mais tempo primeiro (`last_enriched_at`). Sem `ANTHROPIC_API_KEY` não roda (endpoint responde 422).

## Input

Texto simples montado por `RefreshContestJob`: data de hoje, dados atuais do concurso (incluindo `status`), `field_sources` atual (URL, origem e `source_date` de cada campo) e os próximos eventos.

## Output — schema

Só um objeto JSON:

```json
{
  "campos": {
    "<campo>": {
      "valor": "<string|number|array>",
      "fonte": { "url": "<http(s)>", "confianca": "high|medium|low", "data": "AAAA-MM-DD" }
    }
  },
  "eventos_extra": [ { "tipo": "fee_deadline|result|other", "data": "AAAA-MM-DD", "rotulo": "<opcional>" } ],
  "novidades": ["<até 5 frases curtas>"]
}
```

- `campos` obrigatório (pode ser `{}` = nada mudou). Chaves permitidas: `status`, `board`, `registration_start`, `registration_end`, `exam_date`, `fee_cents`, `salary_min_cents`, `salary_max_cents`, `it_vacancies`, `regime`, `edital_url`, `registration_url`, `locations`. Qualquer outra chave invalida a resposta — em especial os campos do dono (`enrollment_status`, `pinned_slot`, `dismissed_reason`, `personal_notes`).
- `fonte.data` obrigatório: data de publicação da fonte, não a de hoje; não pode ser futura.
- `status` no enum `EditalStatus`; `regime` em `clt|statutory|temporary`; datas `AAAA-MM-DD`; dinheiro em centavos (inteiro).
- `eventos_extra` e `novidades` opcionais.

## Validação e retry

Inválido → `RefreshContestJob` reanexa o erro e tenta **mais uma vez**. Segunda falha → `enrichment_status = failed` + `contest_changes` `failed`; nada é mergeado.

## Merge

Cada campo vai para `ContestMerger` com a **própria** URL e `source_date`, `origin: 'llm'`. Entra se a prioridade da fonte for maior que a registrada, ou igual e com `source_date` não mais antiga (campo vazio sempre aceita). Prioridade: oficial (`.gov.br` + `radar.official_domains`) > agregador/seed > llm sem fonte oficial. Campo recusado vira item em `dossier.divergences` (`side_a` = valor atual e fonte, `side_b` = valor novo e fonte). `novidades` e a data da checagem vão para `dossier.last_refresh`; `source_checked_at` passa a ser hoje. Mudanças entram em `contest_changes` com o `search_run_id` (tela de Atividade da T7).

## Custo

Por concurso: até `radar.refresh.max_web_searches` buscas (3). Tokens e buscas somados em `search_runs.meta` (CLAUDE.md regra 8).
