<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\Contest;
use App\Models\ContestEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContestEvent>
 */
class ContestEventFactory extends Factory
{
    protected $model = ContestEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contest_id' => Contest::factory(),
            'type' => EventType::Other->value,
            'date' => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'label' => null,
            'source' => 'scrape',
        ];
    }
}
