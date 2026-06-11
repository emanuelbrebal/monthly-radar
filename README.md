# Radar Hub

A personal SaaS/AI idea discovery engine. Harvests signals from Show HN, GitHub Trending, and Product Hunt, scores them through a 4-agent LLM pipeline, and surfaces the top weekly picks in a Kanban board + email digest.

## What it does

1. **Harvest** — three daily scrapers collect raw signals from public sources
2. **Scout** — LLM agent structures and scores each idea (pain, solution, evidence, BR gap)
3. **Synthesis** — cross-batch pattern finder; generates 0–5 synthetic ideas when patterns warrant
4. **Curator** — deterministic ranking + editorial veto layer
5. **Deep-Dive** — weekly deep analysis (tropicalization + MVP architecture) for 1–2 top picks
6. **Digest** — Friday email with picks and Kanban link

The hub runs fully without LLM access — if the API is down or the key is removed, the Kanban, firmeza-check, and all stored data keep working.

## Architecture

```
Laravel Scheduler (VPS cron)
  ├─ daily tick → PipelineOrchestrator
  │     ├─ pool ≤ 5 && age > 30d  → BatchPipeline
  │     │     HarvestJob → DedupService → ScoutJob → SynthesisJob → CuratorJob
  │     └─ Friday && !ran          → WeeklyPipeline
  │           CuratorJob (weekly-pick) → DeepDiveJob → WeeklyDigestNotification
  │
  └─ Inertia + React: Kanban, idea detail, firmeza-check form, run history
```

## Stack

| Layer | Tech |
|---|---|
| Backend | Laravel 13 (PHP 8.4) |
| Frontend | Inertia.js + React 19 + TypeScript |
| Database | PostgreSQL |
| LLM | Claude Haiku 4.5 (batch) · Sonnet 4.6 (deep-dive) via Anthropic Batch API |
| Tests | Pest |
| Deploy | VPS (single monolith) |

## Getting started

```bash
# 1. Clone and install
git clone https://github.com/emanuelbrebal/monthly-radar
cd monthly-radar
composer install
npm install

# 2. Configure environment
cp .env.example .env
php artisan key:generate
# Edit .env: set DB_* and ANTHROPIC_API_KEY

# 3. Database
createdb radar_hub
php artisan migrate

# 4. Run
php artisan serve
npm run dev
```

## Artisan commands

```bash
php artisan radar:harvest {show-hn|github-trending|product-hunt}
php artisan radar:tick          # run orchestrator manually
php artisan radar:weekly --force
php artisan radar:deep-dive {slug} --force  # bypass cache
```

## Cost model

Target: < US$10/month. Scout and Synthesis use Claude Haiku 4.5 via Batch API. Deep-Dive uses Sonnet 4.6 for 1–2 ideas/week only. All runs log `input_tokens`/`output_tokens` in `pipeline_runs.meta`.

## Agent docs

See [`docs/agents/`](docs/agents/) for each agent's system prompt, JSON schema, and retry policy.

## License

MIT
