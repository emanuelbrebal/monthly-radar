<?php

namespace App\Http\Controllers\Radar;

use App\Enums\EnrollmentStatus;
use App\Models\Contest;
use App\Support\Radar\ContestPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * `POST /contests/{contest}/dismiss` e `POST /contests/{contest}/restore`
 * (docs/00-spec.md → "Descartado"). Sai da mesa e da maleta na hora —
 * `Contest::booted()` já libera `pinned_slot` ao salvar com
 * `enrollment_status=dismissed`.
 *
 * Validação manual pelo mesmo motivo do `SweepController` (ver
 * `EnrollmentController`).
 */
class DismissController
{
    public function store(Request $request, Contest $contest): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => ['nullable', 'string', 'max:120'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $contest->enrollment_status = EnrollmentStatus::Dismissed;
        $contest->dismissed_at = Carbon::now();
        $contest->dismissed_reason = $validator->validated()['reason'] ?? null;
        $contest->save();

        return response()->json(['contest' => ContestPresenter::present($contest->refresh()->load('events'))]);
    }

    public function restore(Contest $contest): JsonResponse
    {
        $contest->enrollment_status = EnrollmentStatus::NotEnrolled;
        $contest->dismissed_at = null;
        $contest->dismissed_reason = null;
        $contest->save();

        return response()->json(['contest' => ContestPresenter::present($contest->refresh()->load('events'))]);
    }
}
