<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamOutcome;
use App\Enums\ResultSource;
use App\Models\Contest;
use App\Models\ExamResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamResult>
 */
class ExamResultFactory extends Factory
{
    protected $model = ExamResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contest_id' => Contest::factory()->state(['enrollment_status' => EnrollmentStatus::ExamTaken->value]),
            'stage' => ExamResult::STAGE_OBJECTIVE,
            'score' => 60,
            'max_score' => 80,
            'cutoff_score' => null,
            'outcome' => ExamOutcome::Pending,
            'source' => ResultSource::SelfGraded,
            'position' => null,
            'candidates' => null,
            'breakdown' => null,
            'taken_on' => now()->subWeek()->toDateString(),
        ];
    }
}
