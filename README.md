# Radar Mensal · Concursos

A personal survival-kit for tracking Brazilian public-sector IT hiring exams (*concursos públicos de TI*). Pick an IT area, hit **sweep**, and the app scrapes a handful of sources, turns each contest into a dossier, and shows everything on a calendar and in a 12-slot "attaché case" (Resident Evil-style inventory) for the ones that matter.

Single-user by design — there's no sign-up, no SaaS, no other accounts. It's built for one person to track their own exam applications.

> This repo previously hosted "Radar Hub", an unrelated SaaS-idea-curation pipeline. That product was discontinued on 2026-09-15 and nothing of it remains in the current codebase or docs.

## What it does

1. Pick an IT area (development, data, infrastructure, security, support, IT governance, or all) and the states you care about.
2. Hit **sweep**. The app scrapes its sources, filters by area keywords, deduplicates, and creates/updates contests.
3. For contests with gaps in their data, an LLM with web search fills in the missing fields and writes a short dossier — this step is entirely optional (see [Cost model](#cost-model)).
4. The screen tracks progress via polling and updates the calendar, the item list, and the attaché case.

There's no scheduled scraping and no email. Refreshing a contest's data just means sweeping its area again.

The app works fully without an `ANTHROPIC_API_KEY` — sweeping still scrapes and populates the screen, it just skips the LLM enrichment step (`enrichment_status = skipped`). No screen breaks because of a missing key.

## Architecture

```
[choose area + states, hit sweep]
              │
     POST /sweeps  →  SearchRun(queued)  →  Bus::chain
              │
 1. HarvestSourcesJob     scrape each enabled source → raw_listings
 2. NormalizeListingsJob  filter by area → normalize → dedup → upsert contests
 3. EnrichContestsJob     pick contests with gaps → Bus::batch(EnrichContestJob…)
 4. FinalizeRunJob        recompute status/events, unpin expired, close the run
              │
     GET /sweeps/{run}  (polling, 3s)  →  front-end updates
```

A source adapter failing doesn't fail the whole run — the error is recorded in `search_runs.meta.errors` and the sweep continues with the other sources.

```
┌──────────────── Laravel 13 monolith ────────────────┐
│                                                       │
│  Inertia + React 19 (resources/js)                   │
│    ├─ radar/index — table (calendar + item box)      │
│    │                and case (pinned slots + dossier) │
│                                                       │
│  Controllers (app/Http/Controllers/Radar)            │
│  Services (app/Services/Radar)                       │
│    ├─ Sources/*          scraping adapters           │
│    ├─ ContestNormalizer  pure text → structured data │
│    ├─ ContestMerger      field-by-field merge + rules │
│    ├─ ContestStatusResolver, PinService, LlmProvider  │
│                                                       │
│  Jobs (app/Jobs/Radar) — queued via QUEUE_CONNECTION  │
│                                                       │
│  PostgreSQL                                           │
└───────────────────────────────────────────────────────┘
```

See [`docs/`](docs/) for the full spec, data model, pipeline details, and the seed format. `docs/04-plano-execucao.md` tracks what's been built per work session.

## Stack

| Layer | Tech |
|---|---|
| Backend | Laravel 13 (PHP 8.3+) |
| Frontend | Inertia.js + React 19 + TypeScript + Tailwind 4 |
| Database | PostgreSQL |
| Scraping | Laravel HTTP client + `symfony/dom-crawler` + `symfony/css-selector` |
| LLM (optional) | Anthropic Messages API with server-side `web_search`, default model `claude-haiku-4-5-20251001` |
| Tests | Pest |
| Deploy | Single VPS, queue worker under Supervisor |

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
# Edit .env: DB_*, RADAR_USER_EMAIL/RADAR_USER_PASSWORD (the app's only login),
# and optionally ANTHROPIC_API_KEY for LLM enrichment.

# 3. Database (creates the schema and seeds the one user + a sample of contests)
createdb monthly_radar
php artisan migrate:fresh --seed

# 4. Run
php artisan serve
npm run dev
```

Sign in with `RADAR_USER_EMAIL`/`RADAR_USER_PASSWORD`. Public registration is disabled (`Features::registration()` is off in Fortify) — the only user comes from the seeder.

To actually process a sweep you also need a queue worker running (jobs are dispatched via `QUEUE_CONNECTION`, `database` by default):

```bash
php artisan queue:work
```

### Local on Windows

Notes for running on Windows 11 (PowerShell) with a local PostgreSQL:

- **Postgres password:** PostgreSQL rejects empty passwords on `127.0.0.1`. Set `DB_PASSWORD` in `.env` and create the database once with `psql -U postgres -c "CREATE DATABASE monthly_radar"` (`psql` lives in `C:\Program Files\PostgreSQL\<version>\bin`, usually not on `PATH`).
- **SSL for scraping:** PHP on Windows ships without a CA bundle, so `radar:source`/`radar:sweep` fail with `cURL error 60: SSL certificate problem`. Download `https://curl.se/ca/cacert.pem` to a fixed folder outside the repo and set both `curl.cainfo` and `openssl.cafile` in your `php.ini` to its path (forward slashes work), then open a new terminal. Never disable certificate verification in code. Check with `php -r "echo ini_get('curl.cainfo');"` and `php artisan radar:source pci_concursos --dry`.
- **One command to start everything:** `composer dev` runs the web server (`localhost:8000`), the queue worker and Vite. The queue worker is required — without it a sweep started from the UI stays `queued` forever. Laravel Pail is not part of it (it needs `pcntl`, unavailable on Windows); follow logs with `composer dev:logs` in another terminal.
- **Stale `public/hot`:** if the page loads blank without `composer dev` running, delete `public/hot` (Vite leaves it behind when it is killed) or run `npm run build`.
- **Updating the seed:** drop the new `database/seeders/data/contests-seed.json` and run `php artisan db:seed --class=ContestSeeder`. It is idempotent by `slug` and never overwrites `enrollment_status`, `pinned_slot`, dismissals or `personal_notes` of existing contests. Do not use `migrate:fresh` once the app has data.
- **No API key:** leave `ANTHROPIC_API_KEY` empty; sweeps still scrape and enrichment stays `skipped`.

## Artisan commands

```bash
php artisan radar:sweep {area} {--no-llm}      # runs the sweep pipeline synchronously
php artisan radar:source {key} {--dry}         # runs one scraping adapter and prints what it extracted, without saving
```

## Sources

| key | target | status |
|---|---|---|
| `pci_concursos` | `pciconcursos.com.br/concursos/` — a single page listing nationwide + every state | enabled by default |
| `official_pages` | a short hand-curated list of official contest pages (`config('radar.sources.official_pages.pages')`) | enabled, empty list by default — add your own |
| `provas_de_ti` | `provasdeti.com.br/concursosdeti/abertos` | **disabled** — it's a client-rendered SPA with no server HTML, and this project deliberately avoids a headless browser |

Every scraping request sends a descriptive `User-Agent` (`config('radar.contact_email')`, sourced from `RADAR_USER_EMAIL`) and respects each site's `robots.txt`.

## Cost model

Without `ANTHROPIC_API_KEY`, enrichment is entirely skipped — the app costs nothing beyond hosting.

With the key set, `EnrichContestJob` calls the Anthropic Messages API with the server-side `web_search` tool for each contest that has a data gap (see `docs/02-pipeline.md` → "3. Enriquecimento" for the exact gap criteria and the per-run cap, `config('radar.enrich.max_per_run')`). Web search is billed per search in addition to token usage — check [Anthropic's current pricing](https://www.anthropic.com/pricing) before enabling this in production, since it changes independently of the base model price.

Every LLM call logs `input_tokens`, `output_tokens`, and the number of searches performed into `search_runs.meta` — the sweep result printed by `radar:sweep` and the `GET /sweeps/{run}` payload both surface this.

## Deployment

Single-VPS deployment — no containers, no autoscaling, one Postgres instance. Sweeps run asynchronously via `Bus::chain`, so a queue worker under Supervisor is required — without it, a sweep gets stuck in `queued` forever. No cron job is needed for scraping; sweeps are always triggered manually from the UI.

See [`docs/07-deploy.md`](docs/07-deploy.md) (in Portuguese) for the full walkthrough — cheapest-VPS comparison with real 2026-09-18 prices, and copy-pasteable commands for provisioning, PHP/Postgres/Nginx setup, HTTPS, and the Supervisor config.

## License

MIT
