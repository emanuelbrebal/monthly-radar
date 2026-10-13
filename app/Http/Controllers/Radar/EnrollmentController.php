<?php

namespace App\Http\Controllers\Radar;

use App\Enums\EnrollmentStatus;
use App\Models\Contest;
use App\Support\Radar\ContestPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * `PATCH /contests/{contest}/enrollment` — checklist de "minha situação"
 * (docs/00-spec.md → "Minha situação no concurso"). O enum é sequencial:
 * gravar um passo já representa "marca os anteriores, desmarca os
 * seguintes", sem lógica extra. Descartar/restaurar tem endpoints próprios
 * (`DismissController`) porque carregam motivo e liberam o slot.
 *
 * Validação manual (em vez de Request::validate()) pelo mesmo motivo do
 * `SweepController`: bootstrap/app.php → shouldRenderJsonWhen() só força
 * JSON pra 'api/*', e essa rota não é uma.
 */
class EnrollmentController
{
    public function update(Request $request, Contest $contest): JsonResponse
    {
        $steps = array_map(
            fn (EnrollmentStatus $s) => $s->value,
            array_filter(EnrollmentStatus::cases(), fn (EnrollmentStatus $s) => $s->isStep()),
        );

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', Rule::in($steps)],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        if ($contest->isDismissed()) {
            return response()->json(['message' => 'Esse tá descartado. Traz de volta antes.'], 422);
        }

        $contest->enrollment_status = EnrollmentStatus::from($validator->validated()['status']);
        $contest->save();

        return response()->json(['contest' => ContestPresenter::present($contest->refresh()->load('events'))]);
    }
}
