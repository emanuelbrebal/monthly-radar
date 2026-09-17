<?php

namespace App\Models;

use App\Enums\EditalStatus;
use App\Enums\EnrichmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Regime;
use App\Services\Radar\ContestStatusResolver;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\ContestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $organ
 * @property string|null $organ_acronym
 * @property int $year
 * @property string $title
 * @property string|null $board
 * @property array<int, string> $areas
 * @property EditalStatus $status
 * @property Carbon $status_changed_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $registration_start
 * @property Carbon|null $registration_end
 * @property Carbon|null $exam_date
 * @property int|null $fee_cents
 * @property int|null $salary_min_cents
 * @property int|null $salary_max_cents
 * @property int|null $it_vacancies
 * @property Regime $regime
 * @property array<int, string> $locations
 * @property array<int, string> $states
 * @property bool $nationwide
 * @property string|null $edital_url
 * @property string|null $registration_url
 * @property array<string, mixed>|null $dossier
 * @property array<string, mixed> $field_sources
 * @property EnrichmentStatus $enrichment_status
 * @property EnrollmentStatus $enrollment_status
 * @property Carbon|null $dismissed_at
 * @property string|null $dismissed_reason
 * @property int|null $pinned_slot
 * @property Carbon $last_seen_at
 * @property Carbon|null $last_enriched_at
 */
class Contest extends Model
{
    /** @use HasFactory<ContestFactory> */
    use HasFactory;

    /** Número de slots da maleta. */
    public const int PIN_SLOTS = 12;

    protected $fillable = [
        'slug',
        'organ',
        'organ_acronym',
        'year',
        'title',
        'board',
        'areas',
        'status',
        'status_changed_at',
        'closed_at',
        'registration_start',
        'registration_end',
        'exam_date',
        'fee_cents',
        'salary_min_cents',
        'salary_max_cents',
        'it_vacancies',
        'regime',
        'locations',
        'states',
        'nationwide',
        'edital_url',
        'registration_url',
        'dossier',
        'field_sources',
        'enrichment_status',
        'enrollment_status',
        'dismissed_at',
        'dismissed_reason',
        'pinned_slot',
        'last_seen_at',
        'last_enriched_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'areas' => 'array',
            'status' => EditalStatus::class,
            'status_changed_at' => 'datetime',
            'closed_at' => 'datetime',
            'registration_start' => 'date',
            'registration_end' => 'date',
            'exam_date' => 'date',
            'fee_cents' => 'integer',
            'salary_min_cents' => 'integer',
            'salary_max_cents' => 'integer',
            'it_vacancies' => 'integer',
            'regime' => Regime::class,
            'locations' => 'array',
            'states' => 'array',
            'nationwide' => 'boolean',
            'dossier' => 'array',
            'field_sources' => 'array',
            'enrichment_status' => EnrichmentStatus::class,
            'enrollment_status' => EnrollmentStatus::class,
            'dismissed_at' => 'datetime',
            'pinned_slot' => 'integer',
            'last_seen_at' => 'datetime',
            'last_enriched_at' => 'datetime',
        ];
    }

    /**
     * Descartado nunca fica na maleta (docs/00-spec.md → "Sai da maleta ao
     * ser descartado"). Guarda de integridade no model em vez de action
     * separada: qualquer caminho que grave enrollment_status=dismissed já
     * libera o slot, sem depender de quem chamou.
     */
    protected static function booted(): void
    {
        static::saving(function (Contest $contest) {
            if ($contest->enrollment_status === EnrollmentStatus::Dismissed) {
                $contest->pinned_slot = null;
            }
        });
    }

    /**
     * @return HasMany<ContestEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ContestEvent::class);
    }

    /**
     * @return HasMany<ContestStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(ContestStatusLog::class);
    }

    /**
     * @return HasMany<RawListing, $this>
     */
    public function rawListings(): HasMany
    {
        return $this->hasMany(RawListing::class);
    }

    /**
     * Status efetivo — delega a ContestStatusResolver (puro, testado).
     */
    public function effectiveStatus(?CarbonInterface $now = null): EditalStatus
    {
        return app(ContestStatusResolver::class)->resolve(
            $this->status,
            $this->registration_start,
            $this->registration_end,
            $this->exam_date,
            $now,
        );
    }

    /**
     * Momento de encerramento efetivo:
     * closed_at ?? (registration_end ? registration_end->endOfDay() (America/Maceio) : null).
     */
    public function effectiveClosedAt(): ?CarbonImmutable
    {
        if ($this->closed_at !== null) {
            return CarbonImmutable::parse($this->closed_at);
        }

        if ($this->registration_end !== null) {
            return CarbonImmutable::parse($this->registration_end)
                ->setTimezone(config('radar.timezone'))
                ->endOfDay();
        }

        return null;
    }

    /**
     * Some da listagem? Regra das 24h: encerrado (ou posterior) efetivo, não
     * inscrito, e o encerramento efetivo foi há 24h ou mais.
     */
    public function isExpired(?CarbonInterface $now = null): bool
    {
        $now = $now !== null ? CarbonImmutable::parse($now) : CarbonImmutable::now();

        if (! $this->effectiveStatus($now)->isClosedOrLater()) {
            return false;
        }

        if ($this->enrollment_status->countsAsEnrolled()) {
            return false;
        }

        $closedAt = $this->effectiveClosedAt();

        if ($closedAt === null) {
            return false;
        }

        return $closedAt->lte($now->subDay());
    }

    /**
     * Descartado? enrollment_status === dismissed.
     */
    public function isDismissed(): bool
    {
        return $this->enrollment_status === EnrollmentStatus::Dismissed;
    }

    /**
     * Escopo equivalente a ! isExpired() && ! isDismissed() — precisa bater
     * com isExpired()/isDismissed() (teste de paridade).
     *
     * Segue a simplificação de docs/01-modelo-de-dados.md: encerramento
     * efetivo = coalesce(closed_at, endOfDay(registration_end)); "closed_at
     * <= agora - 24h" ou, quando closed_at é nulo, "registration_end <=
     * (agora - 24h) menos mais um dia" (equivalente, em precisão de data,
     * a endOfDay(registration_end) <= agora - 24h).
     */
    /**
     * @param  Builder<Contest>  $query
     * @return Builder<Contest>
     */
    public function scopeVisible(Builder $query): Builder
    {
        $now = CarbonImmutable::now();
        $cutoff = $now->subDay();
        $registrationEndBoundary = $cutoff->subDay()->toDateString();

        $enrolledStatuses = EnrollmentStatus::enrolledValues();

        return $query
            ->where('enrollment_status', '!=', EnrollmentStatus::Dismissed->value)
            ->where(function (Builder $q) use ($enrolledStatuses, $cutoff, $registrationEndBoundary) {
                $q->whereIn('enrollment_status', $enrolledStatuses)
                    ->orWhere(function (Builder $q2) use ($cutoff, $registrationEndBoundary) {
                        // Não expirou: nem via closed_at, nem via registration_end.
                        $q2->where(function (Builder $q3) use ($cutoff) {
                            $q3->whereNull('closed_at')
                                ->orWhere('closed_at', '>', $cutoff);
                        })->where(function (Builder $q3) use ($registrationEndBoundary) {
                            $q3->whereNotNull('closed_at')
                                ->orWhereNull('registration_end')
                                ->orWhere('registration_end', '>', $registrationEndBoundary);
                        });
                    });
            });
    }

    /**
     * Só os descartados (para o toggle da caixa de itens); ignora a regra das 24h.
     *
     * @param  Builder<Contest>  $query
     * @return Builder<Contest>
     */
    public function scopeDismissed(Builder $query): Builder
    {
        return $query->where('enrollment_status', EnrollmentStatus::Dismissed->value);
    }

    /**
     * Casa com a seleção de estados — equivalente SQL de StateFilter::matches()
     * (docs/06-filtro-estados.md). Precisa bater com StateFilter (teste de
     * paridade).
     *
     * @param  Builder<Contest>  $query
     * @param  array<int, string>  $selection  siglas + "NACIONAL"
     * @return Builder<Contest>
     */
    public function scopeMatchingStates(Builder $query, array $selection): Builder
    {
        if ($selection === []) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($selection) {
            if (in_array('NACIONAL', $selection, true)) {
                $q->orWhere('nationwide', true);
            }

            foreach (array_diff($selection, ['NACIONAL']) as $state) {
                $q->orWhereJsonContains('states', $state);
            }

            $q->orWhere(function (Builder $q2) {
                $q2->where('nationwide', false)
                    ->where(function (Builder $q3) {
                        $q3->whereNull('states')->orWhereJsonLength('states', 0);
                    });
            });
        });
    }
}
