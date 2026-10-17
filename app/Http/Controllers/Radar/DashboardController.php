<?php

namespace App\Http\Controllers\Radar;

use App\Models\Contest;
use App\Services\Radar\ResultsSummary;
use App\Support\Radar\ContestPresenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `GET /dashboard` — painel com meu progresso nas provas
 * (docs/03-ui-inventario.md → "Painel"). Só leitura. O login cai em `/`
 * (config/fortify.php → home), não aqui.
 */
class DashboardController
{
    public function index(ResultsSummary $summary): Response
    {
        $contests = Contest::query()->with(['events', 'results'])->get();

        return Inertia::render('radar/dashboard', [
            'summary' => $summary->build($contests),
            // Só os que têm nota: o clique numa prova abre o dossiê.
            'contests' => $contests
                ->filter(fn (Contest $c) => $c->objectiveResult() !== null)
                ->map(fn (Contest $c) => ContestPresenter::present($c))
                ->values()
                ->all(),
        ]);
    }
}
