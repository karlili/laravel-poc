<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notable_type' => 'company',
            'notable_id' => Company::factory(),
            'body' => fake()->paragraph(),
            'author_id' => User::factory(),
        ];
    }
}
