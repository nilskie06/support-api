<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserAssigned;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAssigned>
 */
class UserAssignedFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reference' => 'company',
            'reference_code' => 'AAI-WL',
            'default' => false,
        ];
    }
}
