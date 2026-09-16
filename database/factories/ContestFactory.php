<?php

namespace Database\Factories;

use App\Enums\EditalStatus;
use App\Enums\EnrichmentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Regime;
use App\Enums\TiArea;
use App\Models\Contest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Contest>
 */
class ContestFactory extends Factory
{
    protected $model = Contest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organ = fake()->unique()->company();
        $year = fake()->numberBetween(2025, 2027);

        return [
            'slug' => Str::slug("{$organ}-{$year}-".fake()->unique()->numerify('###')),
            'organ' => $organ,
            'organ_acronym' => null,
            'year' => $year,
            'title' => "{$organ} {$year} — Analista de TI",
            'board' => fake()->company(),
            'areas' => [TiArea::Development->value],
            'status' => EditalStatus::Published->value,
            'status_changed_at' => now(),
            'closed_at' => null,
            'registration_start' => null,
            'registration_end' => null,
            'exam_date' => null,
            'fee_cents' => null,
            'salary_min_cents' => null,
            'salary_max_cents' => null,
            'it_vacancies' => null,
            'regime' => Regime::Unknown->value,
            'locations' => [],
            'states' => [],
            'nationwide' => false,
            'edital_url' => null,
            'registration_url' => null,
            'dossier' => null,
            'field_sources' => [],
            'enrichment_status' => EnrichmentStatus::Pending->value,
            'enrollment_status' => EnrollmentStatus::NotEnrolled->value,
            'dismissed_at' => null,
            'dismissed_reason' => null,
            'pinned_slot' => null,
            'last_seen_at' => now(),
            'last_enriched_at' => null,
        ];
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'enrollment_status' => EnrollmentStatus::Dismissed->value,
            'dismissed_at' => now(),
            'dismissed_reason' => 'motivo de teste',
            'pinned_slot' => null,
        ]);
    }

    public function enrolled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enrollment_status' => EnrollmentStatus::Enrolled->value,
        ]);
    }

    public function pinned(?int $slot = null): static
    {
        return $this->state(fn (array $attributes) => [
            'pinned_slot' => $slot ?? fake()->unique()->numberBetween(0, Contest::PIN_SLOTS - 1),
        ]);
    }

    public function closedAt(\DateTimeInterface $when): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EditalStatus::RegistrationClosed->value,
            'closed_at' => $when,
        ]);
    }

    /**
     * @param  array<int, string>  $states
     */
    public function inStates(array $states): static
    {
        return $this->state(fn (array $attributes) => [
            'states' => $states,
        ]);
    }

    public function nationwide(): static
    {
        return $this->state(fn (array $attributes) => [
            'nationwide' => true,
        ]);
    }
}
