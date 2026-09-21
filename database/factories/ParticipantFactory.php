<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Participant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Participant>
 */
class ParticipantFactory extends Factory
{
    protected $model = Participant::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'username' => $this->faker->unique()->userName(),
            'code' => strtoupper(uniqid('PC-')),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'status' => 'active',
        ];
    }
}
