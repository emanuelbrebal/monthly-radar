<?php

namespace Database\Factories;

use App\Models\RawListing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RawListing>
 */
class RawListingFactory extends Factory
{
    protected $model = RawListing::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'provas_de_ti',
            'external_url' => fake()->url(),
            'title' => fake()->sentence(),
            'payload' => [],
            'content_hash' => fake()->unique()->sha256(),
            'contest_id' => null,
            'fetched_at' => now(),
        ];
    }
}
