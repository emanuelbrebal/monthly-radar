<?php

namespace App\Console\Commands;

use App\Services\Radar\Sources\ContestSourceRegistry;
use Illuminate\Console\Command;

/**
 * `radar:source {key} {--dry}` — imprime o que o adapter extraiu, sem
 * gravar (docs/02-pipeline.md → "CLI"). Por ora só `--dry` existe: sem ele,
 * grava via a mesma lógica de dedup do HarvestSourcesJob — [inferido, T2 só
 * pede o modo --dry explicitamente].
 */
class SourceCommand extends Command
{
    protected $signature = 'radar:source {key} {--dry}';

    protected $description = 'Roda um adapter de scraping (App\Services\Radar\Sources) e imprime o que ele extraiu';

    public function handle(ContestSourceRegistry $registry): int
    {
        $key = $this->argument('key');
        $source = $registry->find($key);

        if ($source === null) {
            $this->error("Fonte desconhecida: {$key}");

            return self::FAILURE;
        }

        if (! $source->enabled()) {
            $this->warn("Fonte '{$key}' está desativada em config('radar.sources.{$key}.enabled') — rodando mesmo assim.");
        }

        try {
            $count = 0;

            foreach ($source->fetch() as $listing) {
                $count++;
                $this->line("— {$listing->title}");
                $this->line("  url: {$listing->external_url}");
                $this->line('  payload: '.json_encode($listing->payload, JSON_UNESCAPED_UNICODE));
            }

            $this->info("{$count} listagem(ns) extraída(s) de '{$key}'.");

            if (! $this->option('dry')) {
                $this->warn('Gravação ainda não implementada fora do --dry — rode via HarvestSourcesJob.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Falha em '{$key}': {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
