<?php

namespace App\Jobs\Radar;

use App\Enums\ChangeKind;
use App\Enums\EnrichmentStatus;
use App\Models\Contest;
use App\Models\ContestChange;
use App\Services\Radar\ContestMerger;
use App\Services\Radar\DossierValidationException;
use App\Services\Radar\LlmProvider;
use App\Services\Radar\RefreshValidator;
use BackedEnum;
use Carbon\CarbonInterface;
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
 * Revisão de um concurso acompanhado (T8, docs/agents/revisao.md). Mesmo
 * esqueleto de EnrichContestJob — 1 retry se a resposta for inválida — mas
 * o merge é campo a campo, cada um com a própria fonte e data: o que a
 * prioridade de fonte recusa vira divergência no dossiê em vez de sumir.
 */
class RefreshContestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $contestId, public ?int $searchRunId = null) {}

    public function handle(LlmProvider $provider, ContestMerger $merger, RefreshValidator $validator): EnrichResult
    {
        $contest = Contest::query()->with('events')->findOrFail($this->contestId);
        $system = File::get(resource_path('prompts/revisao.md'));
        $input = $this->buildInput($contest);
        $maxWebSearches = (int) config('radar.refresh.max_web_searches', 3);

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
                $review = $validator->validate($response->text);
            } catch (DossierValidationException $e) {
                $retryNote = $e->getMessage();

                continue;
            }

            $this->applyReview($contest, $merger, $review);

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
        $current = [];

        foreach (RefreshValidator::ALLOWED_FIELDS as $field) {
            $value = $this->plain($contest->{$field});

            if ($value !== null && $value !== []) {
                $current[$field] = $value;
            }
        }

        $known = json_encode([
            'organ' => $contest->organ,
            'organ_acronym' => $contest->organ_acronym,
            'year' => $contest->year,
            'title' => $contest->title,
            ...$current,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $sources = json_encode($contest->field_sources, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $events = $contest->events
            ->sortBy('date')
            ->map(fn ($event) => "- {$event->date->toDateString()} · {$event->type->value}".($event->label ? " · {$event->label}" : ''))
            ->implode("\n") ?: '(nenhum)';

        $today = Carbon::now()->toDateString();

        return <<<TEXT
        Hoje é {$today}.

        Dados atuais do concurso no radar:
        {$known}

        Fonte de cada campo (field_sources — url, origem, confiança, source_date):
        {$sources}

        Eventos no calendário:
        {$events}

        Procure o que mudou desde essas fontes e responda só com o JSON.
        TEXT;
    }

    /**
     * @param  array{campos: array<string, array{valor: mixed, fonte: array{url: string, confianca: string, data: string}}>, eventos_extra?: array<int, array<string, mixed>>, novidades?: array<int, string>}  $review
     */
    private function applyReview(Contest $contest, ContestMerger $merger, array $review): void
    {
        $today = Carbon::now()->toDateString();
        $dossier = $contest->dossier ?? [];
        $divergences = $dossier['divergences'] ?? [];
        $groups = [];

        foreach ($review['campos'] as $field => $entry) {
            $source = $entry['fonte'];

            if ($merger->accepts($contest, $field, 'llm', $source['url'], $source['data'])) {
                $key = "{$source['url']}|{$source['confianca']}|{$source['data']}";
                $groups[$key] ??= ['source' => $source, 'fields' => []];
                $groups[$key]['fields'][$field] = $entry['valor'];

                continue;
            }

            if ($this->plain($contest->{$field}) == $entry['valor']) {
                continue;
            }

            $divergences = $this->addDivergence($divergences, $contest, $field, $entry, $today);
        }

        $dossier['divergences'] = $divergences;
        $dossier['last_refresh'] = [
            'checked_at' => $today,
            'search_run_id' => $this->searchRunId,
            'novidades' => $review['novidades'] ?? [],
        ];

        $contest->dossier = $dossier;
        $contest->source_checked_at = Carbon::now()->startOfDay();
        $contest->enrichment_status = EnrichmentStatus::Done;
        $contest->last_enriched_at = Carbon::now();

        $extraEvents = array_map(fn (array $e) => [
            'type' => $e['tipo'],
            'date' => $e['data'],
            'label' => $e['rotulo'] ?? null,
        ], $review['eventos_extra'] ?? []);

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
                sourceUrl: $group['source']['url'],
                confidence: $group['source']['confianca'],
                extraEvents: $key === $lastKey ? $extraEvents : [],
                searchRunId: $this->searchRunId,
                sourceDate: $group['source']['data'],
            );
        }
    }

    /**
     * Uma divergência por (campo, valor novo) — revisões repetidas não empilham a mesma.
     *
     * @param  array<int, array<string, mixed>>  $divergences
     * @param  array{valor: mixed, fonte: array{url: string, confianca: string, data: string}}  $entry
     * @return array<int, array<string, mixed>>
     */
    private function addDivergence(array $divergences, Contest $contest, string $field, array $entry, string $today): array
    {
        $current = $contest->field_sources[$field] ?? [];
        $sideB = sprintf('%s (%s, %s)', $this->display($entry['valor']), $entry['fonte']['url'], $entry['fonte']['data']);

        foreach ($divergences as $divergence) {
            if (($divergence['field'] ?? null) === $field && ($divergence['side_b'] ?? null) === $sideB) {
                return $divergences;
            }
        }

        $divergences[] = [
            'field' => $field,
            'side_a' => sprintf('%s (%s)', $this->display($this->plain($contest->{$field})), $current['url'] ?? 'sem fonte registrada'),
            'side_b' => $sideB,
            'likely_reason' => "revisão de {$today}: fonte nova não supera a prioridade da atual — conferir",
            'confidence' => $entry['fonte']['confianca'],
        ];

        return $divergences;
    }

    private function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof CarbonInterface => $value->toDateString(),
            default => $value,
        };
    }

    private function display(mixed $value): string
    {
        return is_array($value) ? implode(', ', $value) : (string) ($value ?? '—');
    }
}
