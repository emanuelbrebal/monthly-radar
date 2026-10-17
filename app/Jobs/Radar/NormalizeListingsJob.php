<?php

namespace App\Jobs\Radar;

use App\Enums\EditalStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Regime;
use App\Models\Contest;
use App\Models\RawListing;
use App\Models\SearchRun;
use App\Services\Radar\ContestMerger;
use App\Services\Radar\ContestNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Passo 2 do pipeline (docs/02-pipeline.md): filtra raw_listings ainda sem
 * contest_id pela área da run, normaliza, faz dedup (slug, senão
 * similaridade de órgão no mesmo ano) e faz upsert via ContestMerger
 * (origin=scrape). raw_listings sem match de área nenhuma ficam sem
 * contest_id — [inferido] reprocessadas em runs futuras, sem coluna pra
 * marcar "descartado" (não inventar coluna — CLAUDE.md).
 */
class NormalizeListingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $searchRunId) {}

    public function handle(ContestNormalizer $normalizer, ContestMerger $merger): void
    {
        $run = SearchRun::query()->findOrFail($this->searchRunId);
        $counts = $run->counts;
        $matched = 0;
        $created = 0;
        $updated = 0;

        RawListing::query()->whereNull('contest_id')->each(function (RawListing $raw) use (
            $normalizer, $merger, $run, &$matched, &$created, &$updated,
        ) {
            $haystack = $raw->title.' '.($raw->payload['text'] ?? '');
            $areas = $normalizer->matchedAreas($haystack);

            if ($areas === []) {
                return;
            }

            if ($run->area->value !== 'all' && ! in_array($run->area->value, $areas, true)) {
                return;
            }

            $matched++;

            $wasNew = false;
            $contest = $this->matchOrNewContest($normalizer, $raw, $haystack, $areas, $wasNew);

            $this->applyExtractedFields($normalizer, $merger, $run, $contest, $raw, $haystack, $wasNew ? [] : $contest->areas, $areas);

            $raw->contest_id = $contest->id;
            $raw->save();

            $wasNew ? $created++ : $updated++;
        });

        $run->counts = [
            ...$counts,
            'matched' => ($counts['matched'] ?? 0) + $matched,
            'created' => ($counts['created'] ?? 0) + $created,
            'updated' => ($counts['updated'] ?? 0) + $updated,
        ];
        $run->save();
    }

    /**
     * @param  array<int, string>  $areas
     */
    private function matchOrNewContest(ContestNormalizer $normalizer, RawListing $raw, string $haystack, array $areas, bool &$wasNew): Contest
    {
        ['acronym' => $acronym, 'year' => $year] = $normalizer->extractOrganAcronymYear($raw->title);

        if ($year === null) {
            $dates = $normalizer->extractDates($haystack);
            $year = $dates !== [] ? (int) explode('-', $dates[0]['date'])[0] : (int) Carbon::now()->year;
        }

        $organ = $this->guessOrgan($raw->title, $acronym, $year);
        $slug = Str::slug(($acronym ?? $organ).'-'.$year);

        $existing = Contest::query()->where('slug', $slug)->first();

        if ($existing === null) {
            $candidates = Contest::query()->where('year', $year)->pluck('organ', 'id');
            $similarOrgan = $normalizer->findSimilarOrgan($organ, $candidates->values()->all());

            if ($similarOrgan !== null) {
                $existing = Contest::query()->where('year', $year)->where('organ', $similarOrgan)->first();
            }
        }

        if ($existing !== null) {
            $wasNew = false;

            return $existing;
        }

        $wasNew = true;

        return new Contest([
            'slug' => $slug,
            'organ' => $organ,
            'organ_acronym' => $acronym,
            'year' => $year,
            'title' => $raw->title,
            'areas' => $areas,
            'status' => EditalStatus::Expected->value,
            'status_changed_at' => Carbon::now(),
            'regime' => Regime::Unknown->value,
            'locations' => [],
            'field_sources' => [],
            'enrichment_status' => 'pending',
            'enrollment_status' => EnrollmentStatus::NotEnrolled->value,
            'last_seen_at' => Carbon::now(),
        ]);
    }

    /**
     * Heurística mínima pra nome do órgão: o título sem a sigla e sem o ano,
     * aparado de separadores — [inferido], sem outro sinal na spec.
     */
    private function guessOrgan(string $title, ?string $acronym, int $year): string
    {
        $organ = $title;

        if ($acronym !== null) {
            $organ = str_replace($acronym, '', $organ);
        }

        $organ = str_replace((string) $year, '', $organ);

        return trim($organ, " \t\n\r\0\x0B-–—|·:");
    }

    /**
     * @param  array<int, string>  $existingAreas
     * @param  array<int, string>  $matchedAreas
     */
    private function applyExtractedFields(
        ContestNormalizer $normalizer,
        ContestMerger $merger,
        SearchRun $run,
        Contest $contest,
        RawListing $raw,
        string $haystack,
        array $existingAreas,
        array $matchedAreas,
    ): void {
        if ($existingAreas !== []) {
            $contest->areas = array_values(array_unique([...$existingAreas, ...$matchedAreas]));
            $contest->last_seen_at = Carbon::now();
        }

        $status = $normalizer->extractStatus($haystack);
        $dates = $normalizer->extractDates($haystack);
        $states = $normalizer->extractStates($haystack);

        $registrationDates = array_column(array_filter($dates, fn (array $d) => $d['type'] === 'registration'), 'date');
        $examDates = array_column(array_filter($dates, fn (array $d) => $d['type'] === 'exam'), 'date');

        $fields = array_filter([
            'status' => $status->value,
            'registration_start' => count($registrationDates) > 1 ? min($registrationDates) : null,
            'registration_end' => $registrationDates !== [] ? max($registrationDates) : null,
            'exam_date' => $examDates !== [] ? min($examDates) : null,
            'edital_url' => $normalizer->extractEditalUrl($raw->payload['links'] ?? []),
            'states' => $states['states'] !== [] ? $states['states'] : null,
            'nationwide' => $states['nationwide'] ? true : null,
        ], fn ($v) => $v !== null);

        $extraEvents = array_values(array_map(
            fn (array $d) => ['type' => $d['type'], 'date' => $d['date']],
            array_filter($dates, fn (array $d) => in_array($d['type'], ['fee_deadline', 'result', 'other'], true)),
        ));

        $merger->merge($contest, $fields, origin: 'scrape', sourceUrl: $raw->external_url, confidence: 'medium', extraEvents: $extraEvents, searchRunId: $run->id);
    }
}
