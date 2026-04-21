<?php

namespace Tests\Unit\Listeners;

use App\Andes\Events\AndesCertificateEmitted;
use App\Andes\Events\AndesIdentityValidated;
use App\Andes\Models\AndesCertificateRequest;
use App\Andes\Models\AndesIdentityValidation;
use App\Listeners\LogAndesIdentityValidated;
use App\Listeners\SendAndesCertificateEmittedNotification;
use App\Listeners\SendPaymentApprovedNotification;
use App\Listeners\SendPaymentFailedNotification;
use App\Models\CertificateRequest;
use App\Models\Company;
use App\Models\User;
use App\Notifications\AndesCertificateEmittedNotification;
use App\Notifications\PaymentApprovedNotification;
use App\Notifications\PaymentFailedNotification;
use App\Payments\Events\PaymentApproved;
use App\Payments\Events\PaymentFailed;
use App\Payments\Models\PaymentTransaction;
use App\Quotas\Models\CertificateOrder;
use App\Webhooks\Jobs\DeliverWebhookJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tests unitarios para los 4 Listeners de eventos nuevos.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 */
class NewEventListenersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        Log::spy();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────
    // LogAndesIdentityValidated
    // ──────────────────────────────────────────────────────────────

    public function test_log_andes_identity_validated_despacha_webhook(): void
    {
        $validation = $this->makeValidation();

        (new LogAndesIdentityValidated())->handle(new AndesIdentityValidated($validation));

        Queue::assertPushed(DeliverWebhookJob::class);
    }

    public function test_log_andes_identity_validated_registra_log_info(): void
    {
        $validation = $this->makeValidation();
        $logged     = false;

        Log::shouldReceive('info')
            ->once()
            ->with('ANDES identity validated successfully', \Mockery::any())
            ->andReturnUsing(function () use (&$logged) { $logged = true; });

        (new LogAndesIdentityValidated())->handle(new AndesIdentityValidated($validation));

        $this->assertTrue($logged, 'Se esperaba un log::info de validación de identidad ANDES');
    }

    // ──────────────────────────────────────────────────────────────
    // SendPaymentApprovedNotification
    // ──────────────────────────────────────────────────────────────

    public function test_payment_approved_despacha_webhook_y_notifica_usuario(): void
    {
        [$transaction, $user] = $this->makeTransactionWithUser('APPROVED');

        (new SendPaymentApprovedNotification())->handle(new PaymentApproved($transaction));

        Queue::assertPushed(DeliverWebhookJob::class);
        Notification::assertSentTo($user, PaymentApprovedNotification::class);
    }

    // ──────────────────────────────────────────────────────────────
    // SendPaymentFailedNotification
    // ──────────────────────────────────────────────────────────────

    public function test_payment_failed_despacha_webhook_y_notifica_usuario(): void
    {
        [$transaction, $user] = $this->makeTransactionWithUser('DECLINED');

        (new SendPaymentFailedNotification())->handle(new PaymentFailed($transaction, 'Fondos insuficientes'));

        Queue::assertPushed(DeliverWebhookJob::class);
        Notification::assertSentTo($user, PaymentFailedNotification::class);
    }

    // ──────────────────────────────────────────────────────────────
    // SendAndesCertificateEmittedNotification
    // ──────────────────────────────────────────────────────────────

    public function test_andes_certificate_emitted_despacha_webhook_y_notifica(): void
    {
        $user    = User::make(['name' => 'Test User', 'email' => 'user@test.com']);
        $company = Company::make(['id' => 5]);
        $company->setRelation('user', $user);

        $certRequest = CertificateRequest::make(['company_id' => 5]);
        $certRequest->setRelation('company', $company);

        $andesRequest = AndesCertificateRequest::make([
            'id'                      => 1,
            'certificate_request_id'  => 10,
            'andes_solicitud_id'      => 'SOL-001',
            'certificate_serial'      => 'CERT-001',
            'tipo_cert'               => 10,
            'vigencia_cert'           => 3,
        ]);
        $andesRequest->emitted_at = now();

        // setRelation para bypassar loadMissing (ya está cargado)
        $andesRequest->setRelation('certificateRequest', $certRequest);

        (new SendAndesCertificateEmittedNotification())->handle(new AndesCertificateEmitted($andesRequest));

        Queue::assertPushed(DeliverWebhookJob::class);
        Notification::assertSentTo($user, AndesCertificateEmittedNotification::class);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    private function makeValidation(): AndesIdentityValidation
    {
        $v = AndesIdentityValidation::make([
            'id'                           => 1,
            'andes_certificate_request_id' => 1,
            'validation_type'              => 'OTP',
            'estado'                       => 1,
        ]);
        $v->validated_at = now();
        return $v;
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



