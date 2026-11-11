<?php

namespace App\Http\Controllers\Radar;

use App\Models\Contest;
use App\Support\Radar\ContestPresenter;
use Illuminate\Http\JsonResponse;

/**
 * `GET /contests/dismissed` — lista pro toggle "descartados" da caixa de
 * itens (docs/03-ui-inventario.md → "Caixa de itens"). Ignora a regra das
 * 24h: descartado some da mesa na hora, não depende de prazo.
 */
class DismissedContestsController
{
    public function index(): JsonResponse
    {
        $contests = Contest::query()
            ->dismissed()
            ->with(['events', 'results'])
            ->orderByDesc('dismissed_at')
            ->get();

        return response()->json([
            'contests' => $contests->map(fn (Contest $c) => ContestPresenter::present($c))->all(),
        ]);
    }
}
