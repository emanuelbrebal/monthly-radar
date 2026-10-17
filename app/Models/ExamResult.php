<?php

namespace App\Models;

use App\Enums\ExamOutcome;
use App\Enums\ResultSource;
use Database\Factories\ExamResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Meu resultado numa etapa da prova (docs/01-modelo-de-dados.md →
 * `exam_results`). Dado meu: merger, seed e varredura nunca escrevem aqui.
 *
 * @property int $id
 * @property int $contest_id
 * @property string $stage
 * @property string $score
 * @property string $max_score
 * @property string|null $cutoff_score
 * @property ExamOutcome $outcome
 * @property ResultSource $source
 * @property int|null $position
 * @property int|null $candidates
 * @property array<int, array{subject: string, score: float|int, max: float|int}>|null $breakdown
 * @property Carbon|null $taken_on
 */
class ExamResult extends Model
{
    /** @use HasFactory<ExamResultFactory> */
    use HasFactory;

    public const STAGE_OBJECTIVE = 'objective';

    protected $fillable = [
        'contest_id',
        'stage',
        'score',
        'max_score',
        'cutoff_score',
        'outcome',
        'source',
        'position',
        'candidates',
        'breakdown',
        'taken_on',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'cutoff_score' => 'decimal:2',
            'outcome' => ExamOutcome::class,
            'source' => ResultSource::class,
            'position' => 'integer',
            'candidates' => 'integer',
            'breakdown' => 'array',
            'taken_on' => 'date',
        ];
    }

    /**
     * Nota em % do máximo (0–100, 1 casa) — a única régua comparável entre
     * bancas com escalas diferentes.
     */
    public function percent(): float
    {
        return round((float) $this->score / (float) $this->max_score * 100, 1);
    }

    /** Pontos acima (+) ou abaixo (−) do corte; null sem corte. */
    public function margin(): ?float
    {
        return $this->cutoff_score === null ? null : round((float) $this->score - (float) $this->cutoff_score, 2);
    }

    /** Corte em % do máximo; null sem corte. */
    public function cutoffPercent(): ?float
    {
        return $this->cutoff_score === null ? null : round((float) $this->cutoff_score / (float) $this->max_score * 100, 1);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(): array
    {
        return [
            'id' => $this->id,
            'stage' => $this->stage,
            'score' => (float) $this->score,
            'max_score' => (float) $this->max_score,
            'cutoff_score' => $this->cutoff_score === null ? null : (float) $this->cutoff_score,
            'percent' => $this->percent(),
            'cutoff_percent' => $this->cutoffPercent(),
            'margin' => $this->margin(),
            'outcome' => $this->outcome->value,
            'outcome_label' => $this->outcome->label(),
            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'position' => $this->position,
            'candidates' => $this->candidates,
            'breakdown' => $this->breakdown ?? [],
            'taken_on' => $this->taken_on?->toDateString(),
        ];
    }

    /**
     * @return BelongsTo<Contest, $this>
     */
    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }
}
