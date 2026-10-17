<?php

namespace Database\Factories;

use App\Enums\ChangeKind;
use App\Models\Contest;
use App\Models\ContestChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContestChange>
 */
class ContestChangeFactory extends Factory
{
    protected $model = ContestChange::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contest_id' => Contest::factory(),
            'search_run_id' => null,
            'kind' => ChangeKind::Updated,
            'field' => 'board',
            'old_value' => null,
            'new_value' => 'FGV',
            'origin' => 'scrape',
            'note' => null,
            'created_at' => now(),
        ];
    }
}
