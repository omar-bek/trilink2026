<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone' => '+971-'.fake()->numerify('#########'),
            'role' => UserRole::BUYER->value,
            'status' => UserStatus::ACTIVE->value,
            'company_id' => Company::factory(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function buyer(): static
    {
        return $this->state(['role' => UserRole::BUYER->value]);
    }

    public function supplier(): static
    {
        return $this->state(['role' => UserRole::SUPPLIER->value]);
    }

    public function admin(): static
    {
        return $this->state(['role' => UserRole::ADMIN->value]);
    }

    public function companyManager(): static
    {
        return $this->state(['role' => UserRole::COMPANY_MANAGER->value]);
    }

    public function inCompany(Company|int $company): static
    {
        return $this->state([
            'company_id' => $company instanceof Company ? $company->id : $company,
        ]);
    }
}
