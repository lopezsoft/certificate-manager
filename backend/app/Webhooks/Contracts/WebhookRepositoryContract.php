<?php

namespace App\Webhooks\Contracts;

use App\Webhooks\Models\WebhookEndpoint;
use Illuminate\Support\Collection;

interface WebhookRepositoryContract
{
    public function findActiveByCompanyAndEvent(int $companyId, string $eventType): Collection;

    public function findById(int $id): ?WebhookEndpoint;

    public function create(array $data): WebhookEndpoint;

    public function update(int $id, array $data): WebhookEndpoint;

    public function delete(int $id): void;

    public function listByCompany(int $companyId): Collection;

    public function countByCompany(int $companyId): int;
}
