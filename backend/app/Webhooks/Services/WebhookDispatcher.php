<?php

namespace App\Webhooks\Services;

use App\Webhooks\Contracts\WebhookEventContract;
use App\Webhooks\Contracts\WebhookPayloadBuilderContract;
use App\Webhooks\Contracts\WebhookRepositoryContract;
use App\Webhooks\Models\WebhookDelivery;
use App\Webhooks\Models\WebhookEndpoint;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatcher
{
    /** @param WebhookPayloadBuilderContract[] $builders */
    public function __construct(
        private readonly WebhookRepositoryContract $repository,
        private readonly WebhookSigner $signer,
        private readonly array $builders,
    ) {}

    public function dispatch(WebhookEventContract $event): void
    {
        $endpoints = $this->repository->findActiveByCompanyAndEvent(
            $event->companyId(),
            $event->eventType(),
        );

        if ($endpoints->isEmpty()) {
            return;
        }

        $builder = $this->resolveBuilder($event->eventType());
        $payload = $builder->build($event);

        foreach ($endpoints as $endpoint) {
            $this->deliverToEndpoint($endpoint, $event->eventType(), $payload);
        }
    }

    private function deliverToEndpoint(WebhookEndpoint $endpoint, string $eventType, array $payload): void
    {
        $body      = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $signature = $this->signer->sign($body, $endpoint->secret);

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_type'          => $eventType,
            'payload'             => $payload,
            'signature'           => $signature,
            'status'              => 'pending',
        ]);

        try {
            $response = $this->sendRequest($endpoint->url, $body, $signature, $eventType);
            $this->recordSuccess($delivery, $response);
            $endpoint->update(['last_triggered_at' => now(), 'failure_count' => 0]);
        } catch (\Throwable $e) {
            $this->recordFailure($delivery, $e->getMessage());
            $endpoint->increment('failure_count');
            $this->autoDisableIfExceeded($endpoint);
            Log::warning("Webhook delivery failed for endpoint {$endpoint->id}: {$e->getMessage()}");
        }
    }

    private function sendRequest(string $url, string $body, string $signature, string $eventType): Response
    {
        return Http::withHeaders([
            'Content-Type'     => 'application/json',
            'X-Webhook-Sig'    => $signature,
            'X-Webhook-Source' => config('app.name'),
            'X-Webhook-Event'  => $eventType,
        ])
        ->timeout(config('webhooks.timeout', 10))
        ->withBody($body, 'application/json')
        ->post($url);
    }

    private function recordSuccess(WebhookDelivery $delivery, Response $response): void
    {
        $delivery->update([
            'http_status'   => $response->status(),
            'response_body' => substr($response->body(), 0, 1000),
            'status'        => 'delivered',
            'delivered_at'  => now(),
        ]);
    }

    private function recordFailure(WebhookDelivery $delivery, string $error): void
    {
        $delivery->update([
            'response_body' => substr($error, 0, 1000),
            'status'        => 'failed',
        ]);
    }

    private function autoDisableIfExceeded(WebhookEndpoint $endpoint): void
    {
        if ($endpoint->failure_count >= config('webhooks.max_failures', 10)) {
            $endpoint->update(['is_active' => false]);
            Log::warning("Webhook endpoint {$endpoint->id} auto-disabled after {$endpoint->failure_count} consecutive failures.");
        }
    }

    private function resolveBuilder(string $eventType): WebhookPayloadBuilderContract
    {
        $builder = collect($this->builders)
            ->first(fn(WebhookPayloadBuilderContract $b) => $b->supports() === $eventType);

        if ($builder === null) {
            throw new \RuntimeException("No payload builder registered for event: {$eventType}");
        }

        return $builder;
    }
}
