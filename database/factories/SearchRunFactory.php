<?php

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Enums\TiArea;
use App\Models\SearchRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchRun>
 */
class SearchRunFactory extends Factory
{
    protected $model = SearchRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'area' => TiArea::All->value,
            'status' => RunStatus::Queued->value,
            'started_at' => now(),
            'finished_at' => null,
            'counts' => ['scraped' => 0, 'matched' => 0, 'created' => 0, 'updated' => 0, 'enriched' => 0, 'failed' => 0],
            'meta' => ['input_tokens' => 0, 'output_tokens' => 0, 'web_searches' => 0, 'errors' => []],
        ];
    }
}
