<?php

namespace App\Support\Radar;

use App\Models\SearchRun;

/**
 * Serializa SearchRun para o masthead (varredura e revisão dos
 * acompanhados usam o mesmo polling). Antes duplicado em SweepController e
 * RadarPageController.
 */
class SearchRunPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(SearchRun $run): array
    {
        return [
            'id' => $run->id,
            'area' => $run->area->value,
            'kind' => $run->meta['kind'] ?? 'sweep',
            'status' => $run->status->value,
            'started_at' => $run->started_at->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'counts' => $run->counts,
            'meta' => $run->meta,
        ];
    }
}
