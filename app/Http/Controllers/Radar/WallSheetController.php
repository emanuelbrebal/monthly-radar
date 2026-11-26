<?php

namespace App\Http\Controllers\Radar;

use App\Enums\EditalStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Contest;
use App\Models\ContestEvent;
use App\Services\Radar\ContestMerger;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `GET /wall-sheet` — folha A4 para imprimir e colar na parede. Só leitura,
 * sem LLM. "Acompanhado" = visível e (na maleta ou com "quero fazer"/inscrito
 * ou além); ignora o filtro de estados, porque a folha é sobre o que eu já
 * escolhi acompanhar, não sobre a varredura.
 */
class WallSheetController
{
    public function index(): Response
    {
        $today = CarbonImmutable::now(config('radar.timezone'))->startOfDay();

        $contests = self::tracked()
            ->with('events')
            ->orderBy('organ')
            ->get()
            ->sortBy(fn (Contest $c) => self::sortKey($c, $today))
            ->values();

        return Inertia::render('radar/wall-sheet', [
            'generatedAt' => $today->toDateString(),
            'timeline' => $this->timeline($contests, $today),
            'contests' => $contests
                ->map(fn (Contest $c) => $this->row($c, $today))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return Builder<Contest>
     */
    public static function tracked(): Builder
    {
        $statuses = [EnrollmentStatus::Interested->value, ...EnrollmentStatus::enrolledValues()];

        return Contest::query()
            ->visible()
            ->where(fn (Builder $q) => $q
                ->whereNotNull('pinned_slot')
                ->orWhereIn('enrollment_status', $statuses));
    }

    /**
     * Ordem da tabela de fases: prova futura mais próxima primeiro; depois quem
     * só tem outros eventos futuros (prova já passou ou sem data), pelo próximo
     * evento; por último quem não tem nada à vista. Empate: órgão (já vem da query).
     */
    private static function sortKey(Contest $contest, CarbonImmutable $today): string
    {
        $todayIso = $today->toDateString();
        $exam = $contest->exam_date?->toDateString();

        if ($exam !== null && $exam >= $todayIso) {
            return '0 '.$exam;
        }

        $next = $contest->events
            ->map(fn (ContestEvent $e) => $e->date->toDateString())
            ->filter(fn (string $d) => $d >= $todayIso)
            ->min();

        return $next !== null ? '1 '.$next : '2';
    }

    /**
     * Datas de hoje em diante, em ordem crescente (empate: concurso, depois tipo).
     *
     * @param  Collection<int, Contest>  $contests
     * @return array<int, array<string, mixed>>
     */
    private function timeline(Collection $contests, CarbonImmutable $today): array
    {
        return $contests
            ->flatMap(fn (Contest $c) => $c->events
                ->filter(fn (ContestEvent $e) => $e->date->toDateString() >= $today->toDateString())
                ->map(fn (ContestEvent $e) => [
                    'date' => $e->date->toDateString(),
                    'days_left' => (int) $today->diffInDays(CarbonImmutable::parse($e->date->toDateString(), config('radar.timezone')), false),
                    'contest' => self::shortName($c),
                    'event' => $e->label ?? $e->type->label(),
                    'type' => $e->type->value,
                ]))
            ->sortBy([['date', 'asc'], ['contest', 'asc']])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Contest $contest, CarbonImmutable $today): array
    {
        $hasUpcoming = $contest->events->contains(
            fn (ContestEvent $e) => $e->date->toDateString() >= $today->toDateString(),
        );

        [$qrUrl, $qrTarget] = self::qrLink($contest);

        return [
            'id' => $contest->id,
            'name' => self::shortName($contest),
            'title' => $contest->title,
            'board' => $contest->board,
            'exam_date' => $contest->exam_date?->toDateString(),
            'exam_city' => $contest->exam_city,
            'phase_done' => self::phaseDone($contest->effectiveStatus(), $contest->enrollment_status),
            'has_upcoming' => $hasUpcoming,
            'qr_svg' => self::qrSvg($qrUrl),
            'qr_target' => $qrTarget,
            'qr_host' => $qrUrl !== null ? preg_replace('/^www\./', '', (string) parse_url($qrUrl, PHP_URL_HOST)) : null,
        ];
    }

    /**
     * Para onde o QR leva: edital → inscrição → fonte salva (`field_sources`),
     * para quem ainda não tem edital — é onde a novidade deve aparecer. Entre
     * fontes, oficial primeiro (mesma regra do merge), depois a mais recente.
     *
     * @return array{0: ?string, 1: 'edital'|'inscrição'|'fonte'|null}
     */
    public static function qrLink(Contest $contest): array
    {
        if (filled($contest->edital_url)) {
            return [$contest->edital_url, 'edital'];
        }

        if (filled($contest->registration_url)) {
            return [$contest->registration_url, 'inscrição'];
        }

        $source = collect($contest->field_sources ?? [])
            ->filter(fn ($s) => is_array($s) && filled($s['url'] ?? null))
            ->sortByDesc(fn (array $s) => [
                ContestMerger::isOfficialUrl($s['url']) ? 1 : 0,
                (string) ($s['fetched_at'] ?? ''),
            ])
            ->first();

        return $source !== null ? [$source['url'], 'fonte'] : [null, null];
    }

    /**
     * QR do edital (ou da inscrição, se não houver edital) para abrir no
     * celular direto da parede. bacon/bacon-qr-code já vem com o Fortify (2FA),
     * sem dependência nova. SVG sem a declaração XML, pronto pra inline.
     */
    private static function qrSvg(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $svg = (new Writer(new ImageRenderer(new RendererStyle(120, 0), new SvgImageBackEnd)))->writeString($url);

        return trim(substr($svg, strpos($svg, "\n") + 1));
    }

    /**
     * Quantas colunas da folha (autorizado, edital publicado, inscrito, taxa
     * paga, local de prova, prova feita, gabarito, resultado, nomeação) o
     * sistema já sabe que foram cumpridas — saem impressas preenchidas e o
     * lápis continua dali. Só fatos que não "desacontecem": nada de "hoje: X".
     * Gabarito/resultado/nomeação não têm estado pessoal no sistema → 0 a 6.
     * Uma etapa posterior implica as anteriores (inscrito ⇒ edital publicado).
     */
    public static function phaseDone(EditalStatus $edital, EnrollmentStatus $enrollment): int
    {
        $byEnrollment = match ($enrollment) {
            EnrollmentStatus::Enrolled => 3,
            EnrollmentStatus::FeePaid => 4,
            EnrollmentStatus::VenueReleased => 5,
            EnrollmentStatus::ExamTaken => 6,
            default => 0,
        };

        $byEdital = match (true) {
            $edital === EditalStatus::Suspended => 0,
            $edital->order() >= EditalStatus::Published->order() => 2,
            $edital->order() >= EditalStatus::Authorized->order() => 1,
            default => 0,
        };

        return max($byEnrollment, $byEdital);
    }

    private static function shortName(Contest $contest): string
    {
        return trim(($contest->organ_acronym ?: $contest->organ).' '.$contest->year);
    }
}
