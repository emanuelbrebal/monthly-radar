<?php

namespace Database\Factories;

use App\Models\Contest;
use App\Models\ContestStatusLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContestStatusLog>
 */
class ContestStatusLogFactory extends Factory
{
    protected $model = ContestStatusLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contest_id' => Contest::factory(),
            'from' => null,
            'to' => 'published',
            'origin' => 'scrape',
            'note' => null,
            'created_at' => now(),
        ];
    }
}
