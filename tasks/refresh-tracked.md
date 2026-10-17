# Plano — T8: revisão dos acompanhados + prazos na maleta (17/10/2026)

Branch: `feat/refresh-tracked`, empilhada em `feat/exam-results`. Tipo: feat. Desenho aprovado pelo dono do projeto em 17/10/2026 (conversa da rodada de revisão manual do seed).

## Escopo

- R1. Botão "atualizar acompanhados (N)" no `SweepControl`, ao lado de "varrer". Usa a mesma run/polling da varredura (uma run ativa por vez).
- R2. `POST /contests/refresh-tracked` → `RefreshTrackedController` cria `search_runs` com `meta.kind = refresh` e encadeia `RefreshTrackedContestsJob` + `FinalizeRunJob`.
- R3. Quem entra: `pinned_slot` preenchido **ou** situação em `interested|enrolled|fee_paid|venue_released|exam_taken`; fora `dismissed` e status `finished`. Teto `radar.refresh.max_per_run` (20), ordem: revisado há mais tempo primeiro.
- R4. Agente de revisão (`docs/agents/revisao.md`, prompt em `resources/prompts/revisao.md`): devolve só o que mudou, cada campo com `fonte.url` + `fonte.data`. Validado por `RefreshValidator`; 1 retry; 2ª falha → `failed` naquele concurso.
- R5. Merge campo a campo (`ContestMerger`), cada campo com a própria URL. Entra se a fonte for de prioridade maior, ou igual **e** não mais antiga que a registrada (`field_sources[campo].source_date`). Perdeu → divergência em `dossier.divergences`. Nunca toca `enrollment_status`, `pinned_slot`, descarte, `personal_notes`.
- R6. "Oficial" passa a reconhecer, além de `.gov.br`, os domínios de `radar.official_domains` (tribunais `.jus.br`, MP `.mp.br`, `.leg.br`, `in.gov.br`, sites das bancas). Regra 7 já dizia "edital/banca/DOU/.gov.br"; o código só olhava `.gov.br`.
- R7. Custo: tokens e nº de buscas somados em `search_runs.meta` (regra 8).
- R8. Sem `ANTHROPIC_API_KEY`: botão desabilitado com explicação; endpoint 422.
- R9. Cartão da maleta mostra o próximo prazo: rótulo curto + `dd/mm` + contagem (`hoje`, `amanhã`, `Nd`); ≤ 7 dias em vermelho; sem prazo futuro → `sem prazo`.

## Não-escopo

Agendamento/cron, notificação, headless, PDF (Backlog v2). Reescrever o resumo curado do seed (a revisão só anexa novidades em `dossier.last_refresh`).

## Critérios de aceite

1. Clique com chave → run `refresh` aparece no masthead, termina `done`, `meta` com tokens/buscas.
2. Campo com fonte oficial nova sobrescreve seed/agregador; fonte llm-agregador não sobrescreve seed → vira divergência.
3. Fonte de mesma prioridade mais antiga que a registrada não sobrescreve.
4. Descartados, `finished` e não acompanhados não são revisados.
5. Sem chave: 422 e botão desabilitado; nenhuma tela quebra.
6. Cartão da maleta mostra o prazo; ≤ 7 dias destacado.
7. Gates: Pest, pint, phpstan, eslint, tsc, `test:js`.

## Desvio do desenho

O desenho falava em `Bus::chain` de um job por concurso. Segui o padrão de `EnrichContestsJob` (um job que chama o job por concurso de forma síncrona e sequencial): um caminho de código só, sem batch aninhado na chain.

## Execução (17/10/2026)

- Backend: `RefreshTrackedController` (`POST /contests/refresh-tracked`), `RefreshTrackedContestsJob` + `RefreshContestJob`, `RefreshValidator`, prompt `resources/prompts/revisao.md`, `SearchRunPresenter` (tirou a duplicação de `SweepController`/`RadarPageController`). `ContestMerger`: `accepts()`, `source_date` em `field_sources`, `isOfficialUrl()` com `radar.official_domains`; `source_checked_at` passou a ser carimbo (não gera `contest_changes`).
- Front: botão no `SweepControl`; `upcomingDeadline` em `lib/radar.ts`; prazo no `CaseSlot`.
- Achado no browser (não nos testes): CGU com "taxa paga" mostrava "insc. até 04/11" — prazo que já não importa. Corrigido: inscrito pula prazos de inscrição, taxa paga pula o da taxa. Rótulo longo ("locais de prova") cortava a data — agora rótulo e data ficam em linhas separadas.
- Gates: Pest 252 ✓ (2 skipped), `test:js` 13 ✓, pint ✓, phpstan 0 erros, eslint ✓ nos arquivos tocados, tsc com os mesmos 3 erros pré-existentes em `resources/js/actions` (gerado).
- Pendente: nunca rodou contra a API real (sem `ANTHROPIC_API_KEY` local) — mesma pendência do `AnthropicProvider` da T3. Primeiro clique real: conferir `search_runs.meta` e o custo.
