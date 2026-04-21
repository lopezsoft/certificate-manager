<?php

namespace Tests\Unit\Listeners;

use App\Listeners\SendPaymentApprovedNotification;
use App\Listeners\SendPaymentFailedNotification;
use App\Models\User;
use App\Notifications\PaymentApprovedNotification;
use App\Notifications\PaymentFailedNotification;
use App\Payments\Events\PaymentApproved;
use App\Payments\Events\PaymentFailed;
use App\Payments\Models\PaymentTransaction;
use App\Quotas\Models\CertificateOrder;
use App\Webhooks\Jobs\DeliverWebhookJob;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tests unitarios para los Listeners de eventos de pago WOMPI.
 */
class NewEventListenersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function test_payment_approved_despacha_webhook_y_notifica_usuario(): void
    {
        [$transaction, $user] = $this->makeTransactionWithUser('APPROVED');

        (new SendPaymentApprovedNotification())->handle(new PaymentApproved($transaction));

        Queue::assertPushed(DeliverWebhookJob::class);
        Notification::assertSentTo($user, PaymentApprovedNotification::class);
    }

    public function test_payment_failed_despacha_webhook_y_notifica_usuario(): void
    {
        [$transaction, $user] = $this->makeTransactionWithUser('DECLINED');

        (new SendPaymentFailedNotification())->handle(new PaymentFailed($transaction, 'Fondos insuficientes'));

        Queue::assertPushed(DeliverWebhookJob::class);
        Notification::assertSentTo($user, PaymentFailedNotification::class);
    }

    /** @return array{PaymentTransaction, User} */
    private function makeTransactionWithUser(string $status): array
    {
        $user = User::make(['name' => 'Test User', 'email' => 'user@test.com']);

        $order = CertificateOrder::make([
            'id'           => 1,
            'company_id'   => 5,
            'user_id'      => 1,
            'quantity'     => 3,
            'vigencia'     => 1,
            'total_amount' => 375000,
            'currency'     => 'COP',
        ]);
        $order->setRelation('user', $user);

        $transaction = PaymentTransaction::make([
            'id'                   => 1,
            'certificate_order_id' => 1,
            'wompi_transaction_id' => 'txn-001',
            'wompi_reference'      => 'REF-001',
            'status'               => $status,
            'amount_in_cents'      => 37500000,
            'currency'             => 'COP',
            'payment_method_type'  => 'CARD',
        ]);
        $transaction->paid_at = now();
        $transaction->setRelation('order', $order);

        return [$transaction, $user];
    }
}

