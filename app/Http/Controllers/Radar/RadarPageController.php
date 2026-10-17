<?php

namespace App\Http\Controllers\Radar;

use App\Actions\Radar\UnpinExpiredContests;
use App\Enums\Region;
use App\Enums\TiArea;
use App\Models\Contest;
use App\Models\SearchRun;
use App\Support\Radar\ContestPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `GET /` — tela principal (docs/02-pipeline.md → "Endpoints"). A varredura
 * em si é `SweepController`. A única escrita é desafixar expirados antes de
 * listar (docs/01-modelo-de-dados.md → "Desafixar expirados").
 */
class RadarPageController
{
    public function index(Request $request, UnpinExpiredContests $unpinExpiredContests): Response
    {
        $unpinExpiredContests->handle();

        $user = $request->user();
        $selection = $user->preferences->states;

        $contests = Contest::query()
            ->visible()
            ->matchingStates($selection)
            ->with(['events', 'results'])
            ->orderByRaw('registration_end IS NULL, registration_end asc')
            ->orderByRaw('exam_date IS NULL, exam_date asc')
            ->get();

        $month = $request->string('month')->toString() ?: Carbon::now()->format('Y-m');

        return Inertia::render('radar/index', [
            'visibleContests' => $contests->map(fn (Contest $c) => ContestPresenter::present($c))->all(),
            'month' => $month,
            'lastRun' => $this->lastRun(),
            'areas' => array_map(fn (TiArea $a) => ['value' => $a->value, 'label' => $a->label()], TiArea::cases()),
            'preferences' => ['states' => $selection],
            'regions' => $this->regions(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lastRun(): ?array
    {
        $run = SearchRun::query()->latest('started_at')->first();

        if ($run === null) {
            return null;
        }

        return [
            'id' => $run->id,
            'area' => $run->area->value,
            'status' => $run->status->value,
            'started_at' => $run->started_at->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'counts' => $run->counts,
            'meta' => $run->meta,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function regions(): array
    {
        return array_map(fn (Region $region) => [
            'value' => $region->value,
            'label' => $region->label(),
            'states' => array_map(
                fn ($state) => ['value' => $state->value, 'label' => $state->label()],
                $region->states(),
            ),
        ], Region::cases());
    }
}
