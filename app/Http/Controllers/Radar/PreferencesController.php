<?php

namespace App\Http\Controllers\Radar;

use App\Enums\BrazilianState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * `PATCH /preferences/states` (docs/06-filtro-estados.md). Valida manualmente
 * como `SweepController` (ver comentário lá) — mesma limitação de
 * `shouldRenderJsonWhen()` fora de `api/*`. Redireciona de volta para `/`; o
 * front pede um partial reload das props que mudam (preferences,
 * visibleContests) em vez de recarregar a página inteira.
 */
class PreferencesController
{
    public function updateStates(Request $request): RedirectResponse|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'states' => ['present', 'array'],
            'states.*' => [Rule::in([...array_map(fn ($s) => $s->value, BrazilianState::cases()), 'NACIONAL'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        $user = $request->user();
        $user->preferences = ['states' => array_values(array_unique($validated['states']))];
        $user->save();

        return back();
    }
}
