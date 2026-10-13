<?php

namespace App\Http\Controllers\Radar;

use App\Models\Contest;
use App\Support\Radar\ContestPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * `GET /calendar?month=YYYY-MM` de docs/02-pipeline.md → "Endpoints". Navegar
 * de mês na `MonthCalendar` busca aqui em vez de recarregar a tela inteira.
 * Só eventos de concursos visíveis (docs/03-ui-inventario.md → "Calendário").
 */
class CalendarController
{
    public function index(Request $request): JsonResponse
    {
        // Validação manual — ver comentário em SweepController sobre
        // shouldRenderJsonWhen() fora de 'api/*'.
        $validator = Validator::make($request->all(), [
            'month' => ['required', 'date_format:Y-m'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $month = $validator->validated()['month'];
        $start = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $user = $request->user();

        $contests = Contest::query()
            ->visible()
            ->matchingStates($user->preferences->states)
            ->whereHas('events', fn ($q) => $q->whereBetween('date', [$start->toDateString(), $end->toDateString()]))
            ->with(['events' => fn ($q) => $q->whereBetween('date', [$start->toDateString(), $end->toDateString()])])
            ->get();

        return response()->json([
            'month' => $month,
            'contests' => $contests->map(fn (Contest $c) => ContestPresenter::present($c))->all(),
        ]);
    }
}
