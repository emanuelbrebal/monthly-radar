<?php

namespace App\Http\Controllers\Radar;

use App\Enums\RunStatus;
use App\Enums\TiArea;
use App\Jobs\Radar\EnrichContestsJob;
use App\Jobs\Radar\FinalizeRunJob;
use App\Jobs\Radar\HarvestSourcesJob;
use App\Jobs\Radar\NormalizeListingsJob;
use App\Models\SearchRun;
use App\Support\Radar\SearchRunPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * `POST /sweeps` e `GET /sweeps/{run}` de docs/02-pipeline.md → "Endpoints".
 * Dispara a mesma cadeia de jobs que `radar:sweep`, mas via fila
 * (Bus::chain) em vez de síncrono — front acompanha por polling. Valida
 * manualmente (em vez de Request::validate()) porque bootstrap/app.php →
 * shouldRenderJsonWhen() só renderiza JSON pra rotas 'api/*' — este
 * endpoint não é uma; se deixasse o ValidationException subir, o handler
 * tentaria devolver um redirect (fluxo Inertia), não o JSON 422 que o
 * front deste endpoint espera.
 */
class SweepController
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'area' => ['required', Rule::enum(TiArea::class)],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $activeRun = SearchRun::query()
            ->whereIn('status', [RunStatus::Queued->value, RunStatus::Scraping->value, RunStatus::Enriching->value])
            ->first();

        if ($activeRun !== null) {
            return response()->json([
                'message' => 'Já existe uma varredura em andamento.',
                'run' => SearchRunPresenter::present($activeRun),
            ], 409);
        }

        $run = SearchRun::query()->create([
            'area' => $data['area'],
            'status' => RunStatus::Scraping->value,
            'started_at' => Carbon::now(),
            'counts' => ['scraped' => 0, 'matched' => 0, 'created' => 0, 'updated' => 0, 'enriched' => 0, 'failed' => 0],
            'meta' => ['input_tokens' => 0, 'output_tokens' => 0, 'web_searches' => 0, 'errors' => []],
        ]);

        Bus::chain([
            new HarvestSourcesJob($run->id),
            new NormalizeListingsJob($run->id),
            new EnrichContestsJob($run->id),
            new FinalizeRunJob($run->id),
        ])->catch(function () use ($run) {
            $run->refresh();
            $run->status = RunStatus::Failed;
            $run->finished_at = Carbon::now();
            $run->save();
        })->dispatch();

        return response()->json(['run' => SearchRunPresenter::present($run)], 201);
    }

    public function show(SearchRun $run): JsonResponse
    {
        return response()->json(['run' => SearchRunPresenter::present($run)]);
    }
}
