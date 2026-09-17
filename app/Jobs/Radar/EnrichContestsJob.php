<?php

namespace App\Jobs\Radar;

use App\Enums\EnrichmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Regime;
use App\Enums\RunStatus;
use App\Models\Contest;
use App\Models\SearchRun;
use App\Models\User;
use App\Services\Radar\LlmProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Passo 3 do pipeline (docs/02-pipeline.md): seleciona concursos com lacuna
 * (visíveis, dentro do filtro de estados salvo — exceto fixados/inscritos)
 * e roda EnrichContestJob pra cada um, síncrono e sequencial (sem
 * Bus::batch — mantém radar:sweep e o SweepController usando o mesmo
 * caminho de código, [inferido] mais simples que orquestrar um batch
 * aninhado dentro de um Bus::chain). Sem ANTHROPIC_API_KEY, marca todos os
 * selecionados como skipped sem nenhuma chamada (CLAUDE.md regra 3).
 */
class EnrichContestsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $searchRunId) {}

    public function handle(LlmProvider $provider): void
    {
        $run = SearchRun::query()->findOrFail($this->searchRunId);
        $run->status = RunStatus::Enriching;
        $run->save();

        $contests = $this->selectCandidates($run);

        if ($contests->isEmpty()) {
            return;
        }

        if (! $provider->available()) {
            Contest::query()
                ->whereIn('id', $contests->pluck('id'))
                ->update(['enrichment_status' => EnrichmentStatus::Skipped->value]);

            return;
        }

        $enriched = 0;
        $failed = 0;
        $inputTokens = 0;
        $outputTokens = 0;
        $webSearches = 0;

        foreach ($contests as $contest) {
            try {
                $result = app()->call([new EnrichContestJob($contest->id), 'handle'], ['provider' => $provider]);
            } catch (Throwable) {
                $contest->update(['enrichment_status' => EnrichmentStatus::Failed->value]);
                $failed++;

                continue;
            }

            $result->success ? $enriched++ : $failed++;
            $inputTokens += $result->inputTokens;
            $outputTokens += $result->outputTokens;
            $webSearches += $result->webSearches;
        }

        $run->refresh();
        $run->counts = [
            ...$run->counts,
            'enriched' => ($run->counts['enriched'] ?? 0) + $enriched,
            'failed' => ($run->counts['failed'] ?? 0) + $failed,
        ];
        $run->meta = [
            ...$run->meta,
            'input_tokens' => ($run->meta['input_tokens'] ?? 0) + $inputTokens,
            'output_tokens' => ($run->meta['output_tokens'] ?? 0) + $outputTokens,
            'web_searches' => ($run->meta['web_searches'] ?? 0) + $webSearches,
        ];
        $run->save();
    }

    /**
     * @return Collection<int, Contest>
     */
    private function selectCandidates(SearchRun $run): Collection
    {
        $maxPerRun = (int) config('radar.enrich.max_per_run', 15);
        $maxAgeDays = (int) config('radar.enrich.gap_max_age_days', 7);
        $states = User::query()->first()?->preferences->states ?? [];

        return Contest::query()
            ->visible()
            ->when($states !== [], function (Builder $query) use ($states) {
                // matchingStates([]) não adiciona cláusula nenhuma (StateFilter:
                // seleção vazia = casa com tudo) — só entra no OR com
                // pinned/inscrito quando há de fato uma seleção de estados.
                $query->where(function (Builder $q) use ($states) {
                    $q->matchingStates($states)
                        ->orWhereNotNull('pinned_slot')
                        ->orWhereIn('enrollment_status', EnrollmentStatus::enrolledValues());
                });
            })
            ->where(function (Builder $q) use ($run, $maxAgeDays) {
                $q->whereNull('board')
                    ->orWhereNull('registration_start')
                    ->orWhereNull('registration_end')
                    ->orWhereNull('exam_date')
                    ->orWhereNull('salary_min_cents')
                    ->orWhereNull('it_vacancies')
                    ->orWhereNull('edital_url')
                    ->orWhere('regime', Regime::Unknown->value)
                    ->orWhereNull('last_enriched_at')
                    ->orWhere('last_enriched_at', '<=', Carbon::now()->subDays($maxAgeDays))
                    ->orWhereHas('statusLogs', fn (Builder $sq) => $sq->where('created_at', '>=', $run->started_at));
            })
            ->orderByRaw('COALESCE(exam_date, registration_end, ?) ASC', ['9999-12-31'])
            ->limit($maxPerRun)
            ->get();
    }
}
