<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\User;
use App\Models\UserMenuAcces;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserMenuAcces>
 */
class UserMenuAccesFactory extends Factory
{
    protected $model = UserMenuAcces::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'menu_id' => Menu::factory(),
            'can_view' => true,
            'can_add' => false,
            'can_edit' => false,
            'can_delete' => false,
        ];
    }

    /**
     * Full access state.
     */
    public function fullAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_view' => true,
            'can_add' => true,
            'can_edit' => true,
            'can_delete' => true,
        ]);
    }
}
