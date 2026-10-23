<?php

namespace App\Console\Commands;

use App\Actions\Radar\DismissNonItScrapedContests;
use Illuminate\Console\Command;

/**
 * `radar:prune-noise {--dry}` — descarta concursos raspados que não passam
 * no filtro de TI atual (ver DismissNonItScrapedContests). Com --dry só lista.
 */
class PruneNoiseCommand extends Command
{
    protected $signature = 'radar:prune-noise {--dry}';

    protected $description = 'Descarta concursos só raspados que não citam cargo de TI (reversível pela tela de descartados)';

    public function handle(DismissNonItScrapedContests $action): int
    {
        $dryRun = (bool) $this->option('dry');
        $contests = $action->handle($dryRun);

        foreach ($contests as $contest) {
            $this->line("— #{$contest->id} {$contest->title}");
        }

        $verb = $dryRun ? 'seriam descartado(s)' : 'descartado(s)';
        $this->info(count($contests)." concurso(s) {$verb}.");

        return self::SUCCESS;
    }
}
