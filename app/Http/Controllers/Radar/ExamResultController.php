<?php

namespace App\Http\Controllers\Radar;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamOutcome;
use App\Enums\ResultSource;
use App\Models\Contest;
use App\Models\ExamResult;
use App\Support\Radar\ContestPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;

/**
 * `PUT|DELETE /contests/{contest}/result` — meu resultado na prova objetiva
 * (docs/01-modelo-de-dados.md → `exam_results`, docs/03 → "Resultado").
 * Só com "prova feita". Validação manual pelo mesmo motivo do
 * `EnrollmentController` (JSON fora de `api/*`).
 */
class ExamResultController
{
    public function update(Request $request, Contest $contest): JsonResponse
    {
        if ($contest->enrollment_status !== EnrollmentStatus::ExamTaken) {
            return response()->json(['message' => 'Marca "prova feita" antes de lançar a nota.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'score' => ['required', 'numeric', 'min:0', 'max:99999'],
            'max_score' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'cutoff_score' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'outcome' => ['required', Rule::enum(ExamOutcome::class)],
            'source' => ['required', Rule::enum(ResultSource::class)],
            'position' => ['nullable', 'integer', 'min:1'],
            'candidates' => ['nullable', 'integer', 'min:1'],
            'taken_on' => ['nullable', 'date'],
            'breakdown' => ['nullable', 'array', 'max:30'],
            'breakdown.*.subject' => ['required', 'string', 'max:80'],
            'breakdown.*.score' => ['required', 'numeric', 'min:0'],
            'breakdown.*.max' => ['required', 'numeric', 'gt:0'],
        ], [], [
            'score' => 'nota',
            'max_score' => 'nota máxima',
            'cutoff_score' => 'nota de corte',
            'position' => 'classificação',
            'candidates' => 'candidatos',
        ]);

        $validator->after(function (ValidatorInstance $v) use ($request) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $max = (float) $request->input('max_score');

            if ((float) $request->input('score') > $max) {
                $v->errors()->add('score', 'A nota não pode passar da nota máxima.');
            }

            if ($request->filled('cutoff_score') && (float) $request->input('cutoff_score') > $max) {
                $v->errors()->add('cutoff_score', 'O corte não pode passar da nota máxima.');
            }

            if ($request->filled(['position', 'candidates']) && (int) $request->input('position') > (int) $request->input('candidates')) {
                $v->errors()->add('position', 'A classificação não pode passar do nº de candidatos.');
            }

            foreach ($request->input('breakdown', []) ?? [] as $i => $row) {
                if ((float) $row['score'] > (float) $row['max']) {
                    $v->errors()->add("breakdown.{$i}.score", "Em {$row['subject']}, a nota passou do máximo.");
                }
            }
        });

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $breakdown = array_values(array_map(fn (array $row) => [
            'subject' => trim($row['subject']),
            'score' => (float) $row['score'],
            'max' => (float) $row['max'],
        ], $data['breakdown'] ?? []));

        ExamResult::query()->updateOrCreate(
            ['contest_id' => $contest->id, 'stage' => ExamResult::STAGE_OBJECTIVE],
            [
                'score' => $data['score'],
                'max_score' => $data['max_score'],
                'cutoff_score' => $data['cutoff_score'] ?? null,
                'outcome' => $data['outcome'],
                'source' => $data['source'],
                'position' => $data['position'] ?? null,
                'candidates' => $data['candidates'] ?? null,
                'breakdown' => $breakdown === [] ? null : $breakdown,
                'taken_on' => $data['taken_on'] ?? $contest->exam_date?->toDateString(),
            ],
        );

        return $this->respond($contest);
    }

    public function destroy(Contest $contest): JsonResponse
    {
        $contest->results()->where('stage', ExamResult::STAGE_OBJECTIVE)->delete();

        return $this->respond($contest);
    }

    private function respond(Contest $contest): JsonResponse
    {
        return response()->json([
            'contest' => ContestPresenter::present($contest->refresh()->load(['events', 'results'])),
        ]);
    }
}
