<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\Sensitivity;
use App\Models\ApprovalRequest;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ApprovalRequest>
     */
    protected $model = ApprovalRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'type' => fake()->randomElement(ApprovalType::cases()),
            'status' => ApprovalStatus::Pending,
            'title' => fake()->sentence(3),
            'summary' => fake()->sentence(),
            'sensitivity' => fake()->randomElement(Sensitivity::cases()),
            'environment' => null,
            'requested_by' => null,
            'requested_label' => fake()->userName(),
        ];
    }

    /**
     * Indicate that the request has been approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ApprovalStatus::Approved,
            'decided_label' => fake()->userName(),
            'decided_at' => Carbon::now(),
        ]);
    }

    /**
     * Indicate that the request has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ApprovalStatus::Rejected,
            'decided_label' => fake()->userName(),
            'decision_note' => fake()->sentence(),
            'decided_at' => Carbon::now(),
        ]);
    }
}
