<?php

namespace App\Jobs\Radar;

use App\Enums\ChangeKind;
use App\Enums\EditalStatus;
use App\Enums\EnrichmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\RunStatus;
use App\Models\Contest;
use App\Models\ContestChange;
use App\Models\SearchRun;
use App\Services\Radar\LlmProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/**
 * Botão "atualizar acompanhados" (T8, docs/agents/revisao.md): roda
 * RefreshContestJob pra cada concurso acompanhado, síncrono e sequencial —
 * mesmo motivo de EnrichContestsJob (um caminho de código só, sem batch
 * aninhado na chain). Sem LLM não faz nada: o controller já recusa antes.
 */
class RefreshTrackedContestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Situações que contam como "acompanhado" além de estar na maleta. */
    public const array TRACKED_ENROLLMENT = ['interested', 'enrolled', 'fee_paid', 'venue_released', 'exam_taken'];

    public function __construct(public int $searchRunId) {}

    public function handle(LlmProvider $provider): void
    {
        $run = SearchRun::query()->findOrFail($this->searchRunId);
        $run->status = RunStatus::Enriching;
        $run->save();

        if (! $provider->available()) {
            return;
        }

        $refreshed = 0;
        $updated = 0;
        $failed = 0;
        $inputTokens = 0;
        $outputTokens = 0;
        $webSearches = 0;

        foreach (self::candidates()->limit((int) config('radar.refresh.max_per_run', 20))->get() as $contest) {
            try {
                $result = app()->call([new RefreshContestJob($contest->id, $run->id), 'handle'], ['provider' => $provider]);
            } catch (Throwable $e) {
                $contest->update(['enrichment_status' => EnrichmentStatus::Failed->value]);
                ContestChange::query()->create([
                    'contest_id' => $contest->id,
                    'search_run_id' => $run->id,
                    'kind' => ChangeKind::Failed,
                    'origin' => 'llm',
                    'note' => Str::limit($e->getMessage(), 500),
                    'created_at' => now(),
                ]);
                $failed++;

                continue;
            }

            $result->success ? $refreshed++ : $failed++;
            $inputTokens += $result->inputTokens;
            $outputTokens += $result->outputTokens;
            $webSearches += $result->webSearches;

            if ($result->success && $contest->changes()->where('search_run_id', $run->id)->where('kind', ChangeKind::Updated->value)->exists()) {
                $updated++;
            }
        }

        $run->refresh();
        $run->counts = [...$run->counts, 'enriched' => $refreshed, 'updated' => $updated, 'failed' => $failed];
        $run->meta = [
            ...$run->meta,
            'input_tokens' => ($run->meta['input_tokens'] ?? 0) + $inputTokens,
            'output_tokens' => ($run->meta['output_tokens'] ?? 0) + $outputTokens,
            'web_searches' => ($run->meta['web_searches'] ?? 0) + $webSearches,
        ];
        $run->save();
    }

    /**
     * Na maleta ou com situação ≥ "quero fazer"; fora descartados e
     * encerrados. Revisado há mais tempo (ou nunca) primeiro.
     *
     * @return Builder<Contest>
     */
    public static function candidates(): Builder
    {
        return Contest::query()
            ->where('enrollment_status', '!=', EnrollmentStatus::Dismissed->value)
            ->where('status', '!=', EditalStatus::Finished->value)
            ->where(function (Builder $q) {
                $q->whereNotNull('pinned_slot')
                    ->orWhereIn('enrollment_status', self::TRACKED_ENROLLMENT);
            })
            ->orderByRaw('last_enriched_at IS NOT NULL, last_enriched_at ASC')
            ->orderBy('id');
    }
}
