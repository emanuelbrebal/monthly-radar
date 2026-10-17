<?php

namespace App\Jobs\Radar;

use App\Enums\ChangeKind;
use App\Enums\EnrichmentStatus;
use App\Enums\Regime;
use App\Models\Contest;
use App\Models\ContestChange;
use App\Models\RawListing;
use App\Services\Radar\ContestMerger;
use App\Services\Radar\DossierValidationException;
use App\Services\Radar\DossierValidator;
use App\Services\Radar\LlmProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Um concurso por job (docs/02-pipeline.md → "3. Enriquecimento"). Monta o
 * input, chama LlmProvider::complete() (system prompt de
 * resources/prompts/dossie.md), valida contra o schema (DossierValidator),
 * 1 retry se inválido, e mergeia campo a campo via ContestMerger com
 * origin=llm. Segunda falha de validação (ou erro de transporte em ambas as
 * tentativas) → enrichment_status = failed, nada é mergeado.
 */
class EnrichContestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Campos checados pra decidir lacuna — ver docs/02-pipeline.md e docs/agents/dossie.md. */
    private const array GAP_FIELDS = [
        'board', 'registration_start', 'registration_end', 'exam_date',
        'salary_min_cents', 'it_vacancies', 'edital_url', 'regime',
    ];

    public function __construct(public int $contestId, public ?int $searchRunId = null) {}

    public function handle(LlmProvider $provider, ContestMerger $merger, DossierValidator $validator): EnrichResult
    {
        $contest = Contest::query()->findOrFail($this->contestId);
        $system = File::get(resource_path('prompts/dossie.md'));
        $input = $this->buildInput($contest);
        $maxWebSearches = (int) config('radar.llm.max_web_searches', 5);

        $totalInput = 0;
        $totalOutput = 0;
        $totalSearches = 0;
        $retryNote = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = $provider->complete($system, $retryNote === null ? $input : "{$input}\n\nSua resposta anterior teve erro de validação:\n{$retryNote}\n\nCorrija e responda só com o JSON.", $maxWebSearches);
            } catch (Throwable $e) {
                $retryNote = $e->getMessage();

                continue;
            }

            $totalInput += $response->inputTokens;
            $totalOutput += $response->outputTokens;
            $totalSearches += $response->webSearches;

            try {
                $dossier = $validator->validate($response->text);
            } catch (DossierValidationException $e) {
                $retryNote = $e->getMessage();

                continue;
            }

            $this->applyDossier($contest, $merger, $dossier);

            return new EnrichResult(true, $totalInput, $totalOutput, $totalSearches);
        }

        $contest->enrichment_status = EnrichmentStatus::Failed;
        $contest->save();

        ContestChange::query()->create([
            'contest_id' => $contest->id,
            'search_run_id' => $this->searchRunId,
            'kind' => ChangeKind::Failed,
            'origin' => 'llm',
            'note' => Str::limit((string) $retryNote, 500),
            'created_at' => now(),
        ]);

        return new EnrichResult(false, $totalInput, $totalOutput, $totalSearches);
    }

    private function buildInput(Contest $contest): string
    {
        $known = collect([
            'organ' => $contest->organ,
            'organ_acronym' => $contest->organ_acronym,
            'year' => $contest->year,
            'title' => $contest->title,
            'board' => $contest->board,
            'status' => $contest->status->value,
            'registration_start' => $contest->registration_start?->toDateString(),
            'registration_end' => $contest->registration_end?->toDateString(),
            'exam_date' => $contest->exam_date?->toDateString(),
            'fee_cents' => $contest->fee_cents,
            'salary_min_cents' => $contest->salary_min_cents,
            'salary_max_cents' => $contest->salary_max_cents,
            'it_vacancies' => $contest->it_vacancies,
            'regime' => $contest->regime->value,
            'edital_url' => $contest->edital_url,
            'registration_url' => $contest->registration_url,
            'locations' => $contest->locations,
        ])->filter(fn ($v) => $v !== null && $v !== [])->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $sources = json_encode($contest->field_sources, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $snippets = $contest->rawListings->map(
            fn (RawListing $raw) => "- [{$raw->source}] {$raw->title} ({$raw->external_url})\n  ".Str::limit((string) ($raw->payload['text'] ?? ''), 800),
        )->implode("\n\n");

        if ($snippets === '') {
            $snippets = '(nenhum trecho raspado disponível — busque do zero)';
        }

        return <<<TEXT
        Dados já conhecidos do concurso:
        {$known}

        Fontes já registradas (field_sources):
        {$sources}

        Campos em lacuna a preencher: {$this->gapFieldsLabel($contest)}

        Trechos raspados relacionados a este concurso:
        {$snippets}
        TEXT;
    }

    private function gapFieldsLabel(Contest $contest): string
    {
        $values = [
            'board' => $contest->board,
            'registration_start' => $contest->registration_start,
            'registration_end' => $contest->registration_end,
            'exam_date' => $contest->exam_date,
            'salary_min_cents' => $contest->salary_min_cents,
            'it_vacancies' => $contest->it_vacancies,
            'edital_url' => $contest->edital_url,
            'regime' => $contest->regime === Regime::Unknown ? null : $contest->regime,
        ];

        $missing = array_keys(array_filter(
            array_intersect_key($values, array_flip(self::GAP_FIELDS)),
            fn ($v) => $v === null,
        ));

        return $missing !== []
            ? implode(', ', $missing)
            : '(nenhuma lacuna nos campos principais — atualize mesmo assim se achar algo novo)';
    }

    /**
     * @param  array<string, mixed>  $dossier
     */
    private function applyDossier(Contest $contest, ContestMerger $merger, array $dossier): void
    {
        $contest->dossier = $dossier;
        $contest->enrichment_status = EnrichmentStatus::Done;
        $contest->last_enriched_at = Carbon::now();

        $groups = [];

        foreach ($dossier['campos'] as $field => $entry) {
            $key = $entry['fonte']['url'].'|'.$entry['fonte']['confianca'];
            $groups[$key]['url'] ??= $entry['fonte']['url'];
            $groups[$key]['confidence'] ??= $entry['fonte']['confianca'];
            $groups[$key]['fields'][$field] = $entry['valor'];
        }

        $extraEvents = array_map(fn (array $e) => [
            'type' => $e['tipo'],
            'date' => $e['data'],
            'label' => $e['rotulo'] ?? null,
        ], $dossier['eventos_extra'] ?? []);

        if ($groups === []) {
            $merger->merge($contest, [], origin: 'llm', sourceUrl: null, confidence: 'low', extraEvents: $extraEvents, searchRunId: $this->searchRunId);

            return;
        }

        $lastKey = array_key_last($groups);

        foreach ($groups as $key => $group) {
            $merger->merge(
                $contest,
                $group['fields'],
                origin: 'llm',
                sourceUrl: $group['url'],
                confidence: $group['confidence'],
                extraEvents: $key === $lastKey ? $extraEvents : [],
                searchRunId: $this->searchRunId,
            );
        }
    }
}
