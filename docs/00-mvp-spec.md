# Radar Hub — Spec do MVP (v1)

> Consolidação do veredito da pressão técnica de 10/06/2026.
> Substitui o briefing anterior como documento de referência para desenvolvimento.
> Repo local: `C:\Users\manub\OneDrive\Área de Trabalho\pasta dev\ideias SAAS\radar mensal`. GitHub: `https://github.com/emanuelbrebal/monthly-radar`. README público: **em inglês** (peça de portfólio).

## Decisões fechadas (não rediscutir sem novo painel)

1. **Stack:** Laravel 12 + Inertia + React/TypeScript + PostgreSQL, num único monolito, deploy em VPS.
2. **De 10+1 agentes para 4 agentes LLM:** `scout` (parametrizado global/regional), `synthesis` (ex cross-analyzer + innovator), `curator` (dois modos), `deep-dive` (ex tropicalizer + mvp-architect).
3. **Viram código determinístico (zero LLM):** orchestrator (Scheduler + state em banco), librarian (service de dedup sha256 + registro), reporter (Blade/markdown templates).
4. **Modelo de custo:** API em vez de Claude Pro/Claude Code para a operação. Default: Claude Haiku 4.5 via **Batch API** (scout, synthesis); Sonnet 4.6 só no deep-dive semanal. Adapter de provider com alternativa barata (Gemini Flash ou DeepSeek).
5. **Cortado do MVP (backlog v2):** council-of-five, track regional, tropicalização completa (5 lentes), firmeza-bonus adaptativo, 12 das 15 fontes de harvest, PowerShell/Task Scheduler (eliminados permanentemente).
6. **WebSearch dos scouts:** removido do MVP. Validação de gap BR no MVP é responsabilidade do deep-dive (1-2 ideias/semana, custo controlado) — não do scout em lote.

## Arquitetura

```
Laravel Scheduler (cron do VPS)
  ├─ daily tick → PipelineOrchestrator (service, regras determinísticas)
  │     ├─ pool ≤ 5 && idade > 30d  → dispatch BatchPipeline
  │     └─ sexta && !rodou          → dispatch WeeklyPipeline
  │
  ├─ BatchPipeline (jobs encadeados via Bus::chain)
  │     HarvestJob (3 fontes, PHP puro)
  │       → DedupService (sha256, zero LLM)
  │       → ScoutJob (Haiku via Batch API, chunks de 5)
  │       → SynthesisJob (Haiku via Batch API)
  │       → CuratorJob modo batch-rank (Haiku)
  │       → persiste ideas no Postgres
  │
  └─ WeeklyPipeline
        CuratorJob modo weekly-pick (lê firmeza_logs)
          → DeepDiveJob (Sonnet, 1-2 ideias, cache permanente por slug)
          → WeeklyDigestNotification (Mail nativo)

Inertia + React: Kanban (ideação → aprovada → dev → pausada → descartada),
detalhe da ideia, formulário do firmeza-check, histórico de runs.
```

**Princípio mestre preservado:** o hub opera sem LLM — se a API cair ou for cancelada, Kanban, firmeza-check e dados continuam funcionando. LLM é plugável via `LlmProvider`.

## Adapter de provider

```php
interface LlmProvider {
    public function complete(string $system, string $user, LlmOptions $opts): LlmResult;
    public function batch(array $requests, LlmOptions $opts): BatchHandle; // async
}
// Implementações: AnthropicProvider (default), GeminiProvider (alternativa)
// Config por agente em config/radar.php: ['scout' => ['provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'mode' => 'batch'], ...]
```

Regra: todo output de agente é **JSON validado contra schema** (cada agente define o seu — ver `agents/*.md`). Parsing com retry 1x em falha de schema; falha dupla → ideia marcada `parse_failed`, pipeline segue.

## Modelo de dados (migrations do MVP)

- `ideas` — id, batch_id, slug, title, url, source, track (`global` por ora), dor, solucao, evidencia, moscow (`must|like`), score (decimal), score_breakdown (json), tags (json, máx 3, vocabulário fechado), status (`ideacao|aprovada|dev|pausada|descartada`), deep_dive (json nullable), content_hash (unique)
- `pipeline_runs` — id, type (`batch|weekly`), state (`running|done|failed`), meta (json), started_at, finished_at
- `firmeza_logs` — id, idea_id, resposta (`firme|morta|talvez`), week
- `harvest_items` — staging pré-dedup: source, title, url, raw (json), content_hash

Like-to-have: mesma tabela `ideas` com `moscow = like` (sem tabela separada). Good-to-have: descartada na origem, não persiste.

## Fontes de harvest do MVP (3)

| Fonte | Método | Observação |
|---|---|---|
| Show HN | API oficial do HN (Firebase/Algolia) | estruturada, estável |
| GitHub Trending | HTTP + parser | scraping leve, sem auth |
| Product Hunt | API GraphQL oficial | requer token gratuito |

Cada fonte = um Artisan command `radar:harvest {source}` que grava em `harvest_items`. Adicionar fonte nova = adicionar command. LinkedIn geo-filtrado: **fora** (frágil + ToS).

## Regras de negócio preservadas do briefing

- **Score:** `triangulation*0.4 + br_gap*0.3 + executability*0.2 + tropicalizable*0.1` (no MVP, `br_gap` é estimado pelo scout sem WebSearch e revalidado no deep-dive).
- **MoSCoW:** Must Have → pool principal; Like-to-Have → persiste sem notificação; Good-to-Have → não registra.
- **Tags (vocabulário fechado, máx 3):** tropicalizacao-forte, alto-roi, dev-rapido, boa-adesao, geral, must-have-validado, defensavel, ia-core, nordeste-fit, padrao-recorrente. Banidas: game-changing, revolutionary, unicorn, disruptivo, AI-powered genérico.
- **Firmeza-check:** toda sexta antes do weekly-pick; `talvez` 3 semanas seguidas → descarte automático (código, não LLM). Bonus adaptativo por tag: v2.
- **Anti-slop:** proibido inventar concorrente BR sem evidência; email com subject sem emoji/buzzword.
- **Deep-dive cache:** permanente por slug; re-roda só com flag `--force`.

## Plano por tarde de quinta (~4h cada)

1. **T1:** `laravel new radar-hub` + Breeze/Inertia/React + migrations + seeds fake + Kanban estático lendo do banco.
2. **T2:** 3 Artisan commands de harvest + DedupService + testes do dedup.
3. **T3:** `LlmProvider` + `AnthropicProvider` + ScoutJob processando 5 ideias reais de ponta a ponta (marco de validação da arquitetura).
4. **T4:** CuratorJob (2 modos) + firmeza-check no front + WeeklyDigestNotification.
5. **T5:** SynthesisJob + Batch API + PipelineOrchestrator + Scheduler no VPS.
6. **T6:** DeepDiveJob + polish + README em inglês com diagrama de arquitetura + deploy.

## Critérios de aceite do MVP

- [ ] Pipeline batch roda de ponta a ponta sem intervenção e popula o Kanban.
- [ ] Email de sexta chega na inbox (não spam) com 1-2 ideias e link.
- [ ] Hub funciona com a API key removida (degradação graciosa).
- [ ] Trocar `provider` no config de `anthropic` para `gemini` não exige mudança de código fora do adapter.
- [ ] Custo mensal de API < US$10 (logar tokens por run em `pipeline_runs.meta`).
- [ ] README em inglês responde em 30s: o que é, arquitetura, stack, como rodar.

## Backlog v2 (não tocar antes do MVP fechado)

Track regional NE · tropicalizer 5 lentes completo · council (3 personas, opt-in) · firmeza-bonus adaptativo · +12 fontes de harvest · modo newsletter pública / "tinder" de swipe.
