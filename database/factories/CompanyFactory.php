<?php

namespace Database\Factories;

use App\Enums\CompanyStatus;
use App\Enums\CompanyType;
use App\Enums\VerificationLevel;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'name_ar' => $name.' (AR)',
            'registration_number' => 'CR-'.fake()->unique()->numerify('######'),
            'tax_number' => '1000'.fake()->unique()->numerify('##########'),
            'type' => CompanyType::BUYER->value,
            'status' => CompanyStatus::ACTIVE->value,
            'verification_level' => VerificationLevel::BRONZE->value,
            'email' => fake()->unique()->companyEmail(),
            'phone' => '+971-'.fake()->numerify('#########'),
            'country' => 'AE',
            'city' => fake()->randomElement(['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman']),
            'is_free_zone' => false,
            'is_designated_zone' => false,
        ];
    }

    public function buyer(): self
    {
        return $this->state(['type' => CompanyType::BUYER->value]);
    }

    public function supplier(): self
    {
        return $this->state(['type' => CompanyType::SUPPLIER->value]);
    }

    public function logistics(): self
    {
        return $this->state(['type' => CompanyType::LOGISTICS->value]);
    }

    public function freeZone(string $authority = 'DMCC'): self
    {
        return $this->state([
            'is_free_zone' => true,
            'free_zone_authority' => $authority,
        ]);
    }

    public function designatedZone(): self
    {
        return $this->state([
            'is_free_zone' => true,
            'is_designated_zone' => true,
        ]);
    }

    public function sanctioned(): self
    {
        return $this->state(['sanctions_status' => 'blocked']);
    }

    public function pending(): self
    {
        return $this->state(['status' => CompanyStatus::PENDING->value]);
    }
}
