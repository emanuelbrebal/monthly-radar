<?php

namespace Database\Seeders;

use App\Enums\Confidence;
use App\Enums\EnrichmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Regime;
use App\Models\Contest;
use App\Services\Radar\ContestMerger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Semeia os 12 concursos mapeados em pesquisa manual — ver docs/05-seed-inicial.md.
 * Idempotente (updateOrCreate por slug). Todo item nasce com
 * enrichment_status = pending e last_enriched_at = null: a primeira
 * varredura da área "all" confere e corrige o que vier de fonte não oficial.
 */
class ContestSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/contests-seed.json');
        $data = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

        $collectedBetween = $data['_meta']['collected_between'] ?? null;

        foreach ($data['contests'] as $row) {
            $this->seedContest($row, $collectedBetween);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function seedContest(array $row, ?string $collectedBetween): void
    {
        $now = now();
        $sourceUrl = $row['source_url'] ?? null;
        $confidence = $row['confidence'] ?? Confidence::Low->value;
        $isDismissed = ($row['enrollment_status'] ?? null) === EnrollmentStatus::Dismissed->value;

        $contest = Contest::query()->updateOrCreate(
            ['slug' => $row['slug']],
            [
                'organ' => $row['organ'],
                'organ_acronym' => $row['organ_acronym'] ?? null,
                'year' => $row['year'],
                'title' => $row['title'],
                'board' => $row['board'] ?? null,
                'areas' => $row['areas'],
                'status' => $row['status'],
                'status_changed_at' => $now,
                'registration_start' => $row['registration_start'] ?? null,
                'registration_end' => $row['registration_end'] ?? null,
                'exam_date' => $row['exam_date'] ?? null,
                'fee_cents' => $row['fee_cents'] ?? null,
                'salary_min_cents' => $row['salary_min_cents'] ?? null,
                'salary_max_cents' => $row['salary_max_cents'] ?? null,
                'it_vacancies' => $row['it_vacancies'] ?? null,
                'regime' => $row['regime'] ?? Regime::Unknown->value,
                'locations' => $row['locations'] ?? [],
                // 'states'/'nationwide' do JSON são ignorados por ora: essas
                // colunas só entram na T2 (regra 1 de CLAUDE.md — filtro de
                // estados fora de escopo). O dado sobrevive no JSON de seed
                // e será aplicado quando a coluna existir. [inferido]
                'edital_url' => $row['edital_url'] ?? null,
                'registration_url' => $row['registration_url'] ?? null,
                'dossier' => $this->buildDossier($row, $collectedBetween),
                'field_sources' => $this->buildFieldSources($row, $sourceUrl, $confidence),
                'enrichment_status' => EnrichmentStatus::Pending->value,
                'enrollment_status' => $row['enrollment_status'] ?? EnrollmentStatus::NotEnrolled->value,
                'dismissed_at' => $isDismissed ? $now : null,
                'dismissed_reason' => $row['dismissed_reason'] ?? null,
                'pinned_slot' => $row['pinned_slot'] ?? null,
                'last_seen_at' => $now,
                'last_enriched_at' => null,
            ],
        );

        app(ContestMerger::class)->apply($contest, $row['extra_events'] ?? [], origin: 'seed');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function buildDossier(array $row, ?string $collectedBetween): array
    {
        $sourceUrl = $row['source_url'] ?? null;

        return [
            'summary' => $row['summary'] ?? [],
            'divergences' => $row['divergences'] ?? [],
            'gaps' => $row['gaps'] ?? [],
            'it_roles' => [],
            'syllabus' => [],
            'sources' => $sourceUrl === null ? [] : [[
                'url' => $sourceUrl,
                'name' => null,
                'kind' => $this->sourceKind($sourceUrl),
                'accessed' => $collectedBetween,
            ]],
        ];
    }

    /**
     * `.gov.br` conta como oficial; bancas conhecidas (config/radar.php →
     * boards) entrarão na T2. Por ora só a regra do domínio .gov.br.
     */
    private function sourceKind(string $url): string
    {
        return Str::contains($url, '.gov.br') ? 'official' : 'aggregator';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, array{url: string|null, fetched_at: string|null, origin: string, confidence: string}>
     */
    private function buildFieldSources(array $row, ?string $sourceUrl, string $confidence): array
    {
        $fields = [
            'organ', 'organ_acronym', 'year', 'title', 'board', 'areas', 'status',
            'registration_start', 'registration_end', 'exam_date', 'fee_cents',
            'salary_min_cents', 'salary_max_cents', 'it_vacancies', 'regime',
            'locations', 'edital_url', 'registration_url',
        ];

        $sources = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $row) || $row[$field] === null) {
                continue;
            }

            $sources[$field] = [
                'url' => $sourceUrl,
                'fetched_at' => null,
                'origin' => 'seed',
                'confidence' => $confidence,
            ];
        }

        return $sources;
    }
}
