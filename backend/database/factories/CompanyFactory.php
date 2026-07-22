<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition()
    {
        return [
            'country_id'           => 1,
            'city_id'              => 1,
            'identity_document_id' => 1,
            'type_organization_id' => 1,
            'company_name'         => $this->faker->company(),
            'dni'                  => $this->faker->numerify('##########'),
            'dv'                   => $this->faker->randomDigit(),
            'address'              => $this->faker->address(),
            'postal_code'          => $this->faker->postcode(),
            'phone'                => $this->faker->phoneNumber(),
            'email'                => $this->faker->companyEmail(),
            'active'               => true,
        ];
    }
}
