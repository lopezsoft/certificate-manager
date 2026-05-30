<?php

namespace App\Webhooks\Services;

use App\Webhooks\Contracts\WebhookRepositoryContract;
use App\Webhooks\Enums\WebhookEventType;
use App\Webhooks\Models\WebhookEndpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebhookService
{
    public function __construct(
        private readonly WebhookRepositoryContract $repository,
    ) {}

    public function create(int $companyId, array $data): WebhookEndpoint
    {
        $this->validateEvents($data['events']);
        $this->checkEndpointLimit($companyId);

        return $this->repository->create([
            'company_id'  => $companyId,
            'name'        => $data['name'],
            'url'         => $data['url'],
            'secret'      => Str::random(40),
            'events'      => $data['events'],
            'description' => $data['description'] ?? null,
            'is_active'   => true,
        ]);
    }

    public function update(int $endpointId, int $companyId, array $data): WebhookEndpoint
    {
        $endpoint = $this->findForCompany($endpointId, $companyId);

        if (isset($data['events'])) {
            $this->validateEvents($data['events']);
        }

        return $this->repository->update($endpointId, $data);
    }

    public function rotateSecret(int $endpointId, int $companyId): WebhookEndpoint
    {
        $this->findForCompany($endpointId, $companyId);

        $endpoint = $this->repository->update($endpointId, ['secret' => Str::random(40)]);

        // Exponer el secret solo en este endpoint (única vez permitida)
        $endpoint->makeVisible('secret');

        return $endpoint;
    }

    public function delete(int $endpointId, int $companyId): void
    {
        $this->findForCompany($endpointId, $companyId);
        $this->repository->delete($endpointId);
    }

    public function listForCompany(int $companyId, int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->repository->listByCompany($companyId, $perPage);
    }

    public function findForCompany(int $endpointId, int $companyId): WebhookEndpoint
    {
        $endpoint = $this->repository->findById($endpointId);

        if ($endpoint === null || $endpoint->company_id !== $companyId) {
            throw new \DomainException('Webhook endpoint not found.', 404);
        }

        return $endpoint;
    }

    private function validateEvents(array $events): void
    {
        $invalid = array_diff($events, WebhookEventType::all());

        if (!empty($invalid)) {
            throw ValidationException::withMessages([
                'events' => 'Tipos de evento inválidos: ' . implode(', ', $invalid),
            ]);
        }
    }

    private function checkEndpointLimit(int $companyId): void
    {
        $limit = config('webhooks.max_endpoints_per_company', 5);
        $count = $this->repository->countByCompany($companyId);

        if ($count >= $limit) {
            throw ValidationException::withMessages([
                'url' => "Se ha alcanzado el límite de {$limit} webhooks por compañía.",
            ]);
        }
    }
}
