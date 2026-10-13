<?php

namespace App\Jobs\Radar;

use App\Actions\Radar\UnpinExpiredContests;
use App\Enums\RunStatus;
use App\Models\SearchRun;
use App\Services\Radar\Sources\ContestSourceRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Passo 4 do pipeline (docs/02-pipeline.md): desafixa expirados e fecha a
 * run. "Recalcula status/eventos" já aconteceu campo a campo em cada
 * ContestMerger::merge() dos passos 2 e 3 — aqui só falta encerrar. Falha
 * total do scraping (nenhuma fonte habilitada trouxe nada e todas erraram)
 * → run failed.
 */
class FinalizeRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $searchRunId) {}

    public function handle(UnpinExpiredContests $unpinExpiredContests, ContestSourceRegistry $registry): void
    {
        $unpinExpiredContests->handle();

        $run = SearchRun::query()->findOrFail($this->searchRunId);
        $run->status = $this->scrapingFailedEntirely($run, $registry) ? RunStatus::Failed : RunStatus::Done;
        $run->finished_at = Carbon::now();
        $run->save();
    }

    private function scrapingFailedEntirely(SearchRun $run, ContestSourceRegistry $registry): bool
    {
        $enabledCount = count($registry->enabled());
        $errorCount = count($run->meta['errors'] ?? []);

        return $enabledCount > 0
            && ($run->counts['scraped'] ?? 0) === 0
            && $errorCount >= $enabledCount;
    }
}
