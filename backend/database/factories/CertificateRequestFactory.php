<?php

namespace Database\Factories;

use App\Enums\CertificateRequestStatusEnum;
use App\Models\CertificateRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateRequestFactory extends Factory
{
    protected $model = CertificateRequest::class;

    public function definition()
    {
        return [
            'company_id'           => 1,
            'country_id'           => 1,
            'city_id'              => 1,
            'identity_document_id' => 1,
            'type_organization_id' => 1,
            'entity_document_type_id' => 1,
            'company_name'         => $this->faker->company(),
            'dni'                  => $this->faker->numerify('##########'),
            'dv'                   => $this->faker->randomDigit(),
            'document_number'      => $this->faker->numerify('##########'),
            'phone'                => $this->faker->phoneNumber(),
            'mobile'               => $this->faker->phoneNumber(),
            'legal_representative' => $this->faker->name(),
            'legal_rep_first_name' => $this->faker->firstName(),
            'legal_rep_last_name'  => $this->faker->lastName(),
            'legal_rep_email'      => $this->faker->email(),
            'address'              => $this->faker->address(),
            'postal_code'          => $this->faker->postcode(),
            'request_status'       => CertificateRequestStatusEnum::PROCESSED->value,
            'life'                 => 1,
        ];
    }

    public function forCompany(int $companyId): self
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $companyId,
        ]);
    }

    public function processed(): self
    {
        return $this->state(fn (array $attributes) => [
            'request_status' => CertificateRequestStatusEnum::PROCESSED->value,
        ]);
    }

    public function processing(): self
    {
        return $this->state(fn (array $attributes) => [
            'request_status' => CertificateRequestStatusEnum::PROCESSING->value,
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes) => [
            'request_status'  => CertificateRequestStatusEnum::EXPIRED->value,
            'expiration_date' => now()->subDay(),
        ]);
    }

    public function withExpirationDate($date): self
    {
        return $this->state(fn (array $attributes) => [
            'expiration_date' => $date,
        ]);
    }

    public function withIssuanceDates($issuedAt = null, $certValidTo = null): self
    {
        return $this->state(fn (array $attributes) => [
            'issued_at'    => $issuedAt ?? now()->subDays(30),
            'cert_valid_to' => $certValidTo ?? now()->addYears(2),
        ]);
    }
}
