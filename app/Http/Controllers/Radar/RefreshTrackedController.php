<?php

namespace App\Http\Controllers\Radar;

use App\Enums\RunStatus;
use App\Enums\TiArea;
use App\Jobs\Radar\FinalizeRunJob;
use App\Jobs\Radar\RefreshTrackedContestsJob;
use App\Models\SearchRun;
use App\Services\Radar\LlmProvider;
use App\Support\Radar\SearchRunPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

/**
 * `POST /contests/refresh-tracked` — botão "atualizar acompanhados" (T8,
 * docs/agents/revisao.md). Cria uma search_run com meta.kind = refresh e
 * reaproveita o polling de `GET /sweeps/{run}`. Uma run ativa por vez,
 * dividida com a varredura.
 */
class RefreshTrackedController
{
    public function store(LlmProvider $provider): JsonResponse
    {
        if (! $provider->available()) {
            return response()->json([
                'message' => 'Sem ANTHROPIC_API_KEY: a revisão automática precisa de LLM. Use a revisão pelo seed.',
            ], 422);
        }

        $activeRun = SearchRun::query()
            ->whereIn('status', [RunStatus::Queued->value, RunStatus::Scraping->value, RunStatus::Enriching->value])
            ->first();

        if ($activeRun !== null) {
            return response()->json([
                'message' => 'Já existe uma varredura em andamento.',
                'run' => SearchRunPresenter::present($activeRun),
            ], 409);
        }

        if (! RefreshTrackedContestsJob::candidates()->exists()) {
            return response()->json(['message' => 'Nenhum concurso acompanhado pra revisar.'], 422);
        }

        $run = SearchRun::query()->create([
            'area' => TiArea::All->value,
            'status' => RunStatus::Queued->value,
            'started_at' => Carbon::now(),
            'counts' => ['scraped' => 0, 'matched' => 0, 'created' => 0, 'updated' => 0, 'enriched' => 0, 'failed' => 0],
            'meta' => ['kind' => 'refresh', 'input_tokens' => 0, 'output_tokens' => 0, 'web_searches' => 0, 'errors' => []],
        ]);

        Bus::chain([
            new RefreshTrackedContestsJob($run->id),
            new FinalizeRunJob($run->id),
        ])->catch(function () use ($run) {
            $run->refresh();
            $run->status = RunStatus::Failed;
            $run->finished_at = Carbon::now();
            $run->save();
        })->dispatch();

        return response()->json(['run' => SearchRunPresenter::present($run)], 201);
    }
}
