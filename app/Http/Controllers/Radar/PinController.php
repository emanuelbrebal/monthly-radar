<?php

namespace App\Http\Controllers\Radar;

use App\Exceptions\Radar\ContestDismissedException;
use App\Exceptions\Radar\PinSlotsFullException;
use App\Models\Contest;
use App\Services\Radar\PinService;
use App\Support\Radar\ContestPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * `POST/DELETE /contests/{contest}/pin` (docs/02-pipeline.md → "Endpoints").
 * Adiantado da T5 para a T4 para a `ItemBox` ter um botão de pin funcional
 * antes da maleta visual existir — ver docs/04-plano-execucao.md.
 */
class PinController
{
    public function __construct(private readonly PinService $pins) {}

    public function store(Request $request, Contest $contest): JsonResponse
    {
        $slot = $request->input('slot');

        try {
            $contest = $this->pins->pin($contest, $slot !== null ? (int) $slot : null);
        } catch (ContestDismissedException|PinSlotsFullException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['contest' => ContestPresenter::present($contest->load('events'))]);
    }

    public function destroy(Contest $contest): JsonResponse
    {
        $contest = $this->pins->unpin($contest);

        return response()->json(['contest' => ContestPresenter::present($contest->load('events'))]);
    }

    /**
     * `PATCH /pins` — arrastar item entre slots (docs/03-ui-inventario.md →
     * "Maleta → Interação"). `order[N]` = id do concurso no slot N, ou null.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order' => ['required', 'array', 'max:'.Contest::PIN_SLOTS],
            'order.*' => ['nullable', 'integer', 'exists:contests,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $this->pins->reorder($validator->validated()['order']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $pinned = Contest::query()->whereNotNull('pinned_slot')->with('events')->get();

        return response()->json(['contests' => $pinned->map(fn (Contest $c) => ContestPresenter::present($c))->all()]);
    }
}
