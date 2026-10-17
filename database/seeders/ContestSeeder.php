<?php

namespace Database\Seeders;

use App\Enums\Confidence;
use App\Enums\EnrichmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Regime;
use App\Models\Contest;
use App\Services\Radar\ContestMerger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Semeia os concursos mapeados em pesquisa manual — ver docs/05-seed-inicial.md.
 * Idempotente por slug, e seguro de rodar de novo depois que o app está em uso:
 *
 * - Concurso novo: criado inteiro a partir do JSON, com enrichment_status =
 *   pending e last_enriched_at = null (a primeira varredura da área "all"
 *   confere e corrige o que vier de fonte não oficial).
 * - Concurso que já existe: só os fatos são atualizados. Campos com fonte
 *   rastreada passam pelo ContestMerger::merge (prioridade de fonte, CLAUDE.md
 *   regra 7); curadoria manual e o resumo do dossiê vêm do JSON. O que é do
 *   dono do radar — enrollment_status, pinned_slot, descarte e personal_notes —
 *   nunca é sobrescrito (personal_notes só é preenchido se estiver vazio).
 */
class ContestSeeder extends Seeder
{
    /**
     * Campos com `field_sources` — escritos via ContestMerger em concursos que
     * já existem, para respeitar a prioridade de fonte.
     */
    private const TRACKED_FIELDS = [
        'organ', 'organ_acronym', 'year', 'title', 'board', 'areas', 'status',
        'registration_start', 'registration_end', 'exam_date', 'fee_cents',
        'salary_min_cents', 'salary_max_cents', 'it_vacancies', 'regime',
        'locations', 'states', 'nationwide', 'edital_url', 'registration_url',
    ];

    /**
     * Curadoria manual (docs/01-modelo-de-dados.md → "Campos de curadoria
     * manual"): só a pesquisa preenche, então o JSON manda.
     */
    private const CURATION_FIELDS = [
        'sphere', 'education_level', 'is_it_role', 'immediate_vacancies',
        'reserve_vacancies', 'open_competition_vacancies', 'salary_note',
        'board_affinity', 'board_affinity_note', 'tier', 'tier_note', 'workplace_city', 'workplace_state',
        'distance_from_home_km', 'exam_city', 'essay_exam_date', 'validity_months',
        'validity_extendable', 'source_checked_at', 'confidence',
    ];

    public function run(): void
    {
        $path = database_path('seeders/data/contests-seed.json');
        $data = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

        $this->seedFromArray($data);
    }

    /**
     * @param  array{_meta?: array<string, mixed>, contests: array<int, array<string, mixed>>}  $data
     */
    public function seedFromArray(array $data): void
    {
        $collectedBetween = $data['_meta']['collected_between'] ?? null;

        foreach ($data['contests'] as $row) {
            $existing = Contest::query()->where('slug', $row['slug'])->first();

            if ($existing === null) {
                $this->createContest($row, $collectedBetween);
            } else {
                $this->refreshContest($existing, $row, $collectedBetween);
            }
        }
    }

    /**
     * Atualiza um concurso que já existe sem apagar o que o dono marcou pela tela.
     *
     * @param  array<string, mixed>  $row
     */
    private function refreshContest(Contest $contest, array $row, ?string $collectedBetween): void
    {
        $sourceUrl = $row['source_url'] ?? null;
        $confidence = $row['confidence'] ?? Confidence::Low->value;

        foreach (self::CURATION_FIELDS as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== null) {
                $contest->{$field} = $row[$field];
            }
        }

        if (blank($contest->personal_notes) && ! blank($row['personal_notes'] ?? null)) {
            $contest->personal_notes = $row['personal_notes'];
        }

        $contest->dossier = $this->refreshDossier($contest->dossier ?? [], $row, $collectedBetween);
        $contest->last_seen_at = Carbon::now();

        $previousStatus = $contest->status;

        $tracked = array_intersect_key($row, array_flip(self::TRACKED_FIELDS));

        // merge() ignora valores nulos (= "sem informação"), aplica a prioridade
        // de fonte campo a campo e salva via apply() — eventos, closed_at e log
        // de status incluídos.
        $merger = app(ContestMerger::class);
        $contest = $merger->merge($contest, $tracked, origin: 'seed', sourceUrl: $sourceUrl, confidence: $confidence, extraEvents: $row['extra_events'] ?? []);

        if ($contest->status !== $previousStatus) {
            $contest->status_changed_at = Carbon::now();
            $contest->save();
        }
    }

    /**
     * Resumo, divergências e lacunas vêm da pesquisa (o JSON manda); o que o
     * enriquecimento gerou (it_roles, syllabus) fica; fontes são somadas por URL.
     *
     * @param  array<string, mixed>  $dossier
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function refreshDossier(array $dossier, array $row, ?string $collectedBetween): array
    {
        foreach (['summary', 'divergences', 'gaps'] as $key) {
            if (array_key_exists($key, $row)) {
                $dossier[$key] = $row[$key];
            }
        }

        $sources = $dossier['sources'] ?? [];
        $sourceUrl = $row['source_url'] ?? null;

        if ($sourceUrl !== null && ! in_array($sourceUrl, array_column($sources, 'url'), true)) {
            $sources[] = [
                'url' => $sourceUrl,
                'name' => null,
                'kind' => $this->sourceKind($sourceUrl),
                'accessed' => $collectedBetween,
            ];
        }

        $dossier['sources'] = $sources;

        return $dossier + ['it_roles' => [], 'syllabus' => []];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createContest(array $row, ?string $collectedBetween): void
    {
        $now = now();
        $sourceUrl = $row['source_url'] ?? null;
        $confidence = $row['confidence'] ?? Confidence::Low->value;
        $isDismissed = ($row['enrollment_status'] ?? null) === EnrollmentStatus::Dismissed->value;

        $contest = Contest::query()->create(
            [
                'slug' => $row['slug'],
                'organ' => $row['organ'],
                'organ_acronym' => $row['organ_acronym'] ?? null,
                'year' => $row['year'],
                'title' => $row['title'],
                'board' => $row['board'] ?? null,
                'areas' => $row['areas'],
                'status' => $row['status'],
                'status_changed_at' => $now,
                'closed_at' => $row['closed_at'] ?? null,
                'registration_start' => $row['registration_start'] ?? null,
                'registration_end' => $row['registration_end'] ?? null,
                'exam_date' => $row['exam_date'] ?? null,
                'fee_cents' => $row['fee_cents'] ?? null,
                'salary_min_cents' => $row['salary_min_cents'] ?? null,
                'salary_max_cents' => $row['salary_max_cents'] ?? null,
                'it_vacancies' => $row['it_vacancies'] ?? null,
                'regime' => $row['regime'] ?? Regime::Unknown->value,
                'sphere' => $row['sphere'] ?? null,
                'education_level' => $row['education_level'] ?? null,
                'is_it_role' => $row['is_it_role'] ?? null,
                'immediate_vacancies' => $row['immediate_vacancies'] ?? null,
                'reserve_vacancies' => $row['reserve_vacancies'] ?? null,
                'open_competition_vacancies' => $row['open_competition_vacancies'] ?? null,
                'salary_note' => $row['salary_note'] ?? null,
                'board_affinity' => $row['board_affinity'] ?? null,
                'board_affinity_note' => $row['board_affinity_note'] ?? null,
                'tier' => $row['tier'] ?? null,
                'tier_note' => $row['tier_note'] ?? null,
                'locations' => $row['locations'] ?? [],
                'workplace_city' => $row['workplace_city'] ?? null,
                'workplace_state' => $row['workplace_state'] ?? null,
                'distance_from_home_km' => $row['distance_from_home_km'] ?? null,
                'states' => $row['states'] ?? [],
                'nationwide' => $row['nationwide'] ?? false,
                'exam_city' => $row['exam_city'] ?? null,
                'essay_exam_date' => $row['essay_exam_date'] ?? null,
                'validity_months' => $row['validity_months'] ?? null,
                'validity_extendable' => $row['validity_extendable'] ?? null,
                'edital_url' => $row['edital_url'] ?? null,
                'registration_url' => $row['registration_url'] ?? null,
                'source_checked_at' => $row['source_checked_at'] ?? null,
                'confidence' => $confidence,
                'dossier' => $this->buildDossier($row, $collectedBetween),
                'personal_notes' => $row['personal_notes'] ?? null,
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

        app(ContestMerger::class)->apply($contest, $row['extra_events'] ?? [], origin: 'seed', created: true);
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
        $sources = [];

        foreach (self::TRACKED_FIELDS as $field) {
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
