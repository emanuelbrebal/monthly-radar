<?php

namespace App\Console\Commands;

use App\Enums\RunStatus;
use App\Enums\TiArea;
use App\Jobs\Radar\EnrichContestsJob;
use App\Jobs\Radar\FinalizeRunJob;
use App\Jobs\Radar\HarvestSourcesJob;
use App\Jobs\Radar\NormalizeListingsJob;
use App\Models\SearchRun;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * `radar:sweep {area} {--no-llm}` — roda a mesma cadeia do pipeline de
 * forma síncrona (docs/02-pipeline.md → "CLI"), chamando handle() de cada
 * job direto via app()->call() em vez de despachar pra fila. `--no-llm`
 * pula o passo de enriquecimento inteiro, mesmo com ANTHROPIC_API_KEY
 * configurada — pra testar só o scraping.
 */
class SweepCommand extends Command
{
    protected $signature = 'radar:sweep {area} {--no-llm}';

    protected $description = 'Roda a varredura completa (scraping + enriquecimento) de forma síncrona pra uma área';

    public function handle(): int
    {
        $area = TiArea::tryFrom($this->argument('area'));

        if ($area === null) {
            $this->error("Área desconhecida: {$this->argument('area')}. Valores válidos: ".implode(', ', array_column(TiArea::cases(), 'value')));

            return self::FAILURE;
        }

        $run = SearchRun::query()->create([
            'area' => $area->value,
            'status' => RunStatus::Scraping->value,
            'started_at' => Carbon::now(),
            'counts' => ['scraped' => 0, 'matched' => 0, 'created' => 0, 'updated' => 0, 'enriched' => 0, 'failed' => 0],
            'meta' => ['input_tokens' => 0, 'output_tokens' => 0, 'web_searches' => 0, 'errors' => []],
        ]);

        $this->info("Run #{$run->id} ({$area->value}) — varrendo fontes...");
        app()->call([new HarvestSourcesJob($run->id), 'handle']);

        $this->info('Normalizando e deduplicando...');
        app()->call([new NormalizeListingsJob($run->id), 'handle']);

        if ($this->option('no-llm')) {
            $this->warn('--no-llm: pulando enriquecimento.');
        } else {
            $this->info('Enriquecendo concursos com lacuna...');
            app()->call([new EnrichContestsJob($run->id), 'handle']);
        }

        app()->call([new FinalizeRunJob($run->id), 'handle']);

        $run->refresh();
        $this->info("Run #{$run->id} finalizada com status '{$run->status->value}'.");
        $this->table(['métrica', 'valor'], collect($run->counts)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->table(['custo', 'valor'], collect($run->meta)->except('errors')->map(fn ($v, $k) => [$k, $v])->values()->all());

        if ($run->meta['errors'] !== []) {
            $this->warn('Erros de fonte: '.json_encode($run->meta['errors'], JSON_UNESCAPED_UNICODE));
        }

        return $run->status === RunStatus::Failed ? self::FAILURE : self::SUCCESS;
    }
}
