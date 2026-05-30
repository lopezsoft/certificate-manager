<?php

namespace App\Webhooks\Repositories;

use App\Webhooks\Contracts\WebhookRepositoryContract;
use App\Webhooks\Models\WebhookEndpoint;
use Illuminate\Support\Collection;

class WebhookEndpointRepository implements WebhookRepositoryContract
{
    public function findActiveByCompanyAndEvent(int $companyId, string $eventType): Collection
    {
        return WebhookEndpoint::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereJsonContains('events', $eventType)
            ->orWhere(fn($q) => $q
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereJsonContains('events', '*')
            )
            ->get();
    }

    public function findById(int $id): ?WebhookEndpoint
    {
        return WebhookEndpoint::find($id);
    }

    public function create(array $data): WebhookEndpoint
    {
        return WebhookEndpoint::create($data);
    }

    public function update(int $id, array $data): WebhookEndpoint
    {
        $endpoint = WebhookEndpoint::findOrFail($id);
        $endpoint->update($data);

        return $endpoint->fresh();
    }

    public function delete(int $id): void
    {
        WebhookEndpoint::findOrFail($id)->delete();
    }

    public function listByCompany(int $companyId, int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return WebhookEndpoint::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function countByCompany(int $companyId): int
    {
        return WebhookEndpoint::query()
            ->where('company_id', $companyId)
            ->count();
    }
}
