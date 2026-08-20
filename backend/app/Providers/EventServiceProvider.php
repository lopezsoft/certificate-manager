<?php

namespace App\Providers;

use App\Events\CertificateFileUploaded;
use App\Events\CertificateProcessedWithAI;
use App\Events\CertificateRequestCreated;
use App\Events\CertificateRequestDeleted;
use App\Events\CertificateStatusChanged;
use App\Listeners\HandleCertificateAIProcessing;
use App\Listeners\LogVerifiedUser;
use App\Listeners\SendPasswordResetEmail;
use App\Listeners\SendPaymentApprovedNotification;
use App\Listeners\SendPaymentFailedNotification;
use App\Payments\Events\PaymentApproved;
use App\Payments\Events\PaymentFailed;
use App\Webhooks\Listeners\DispatchWebhookOnAIProcessed;
use App\Webhooks\Listeners\DispatchWebhookOnCertificateCreated;
use App\Webhooks\Listeners\DispatchWebhookOnCertificateDeleted;
use App\Webhooks\Listeners\DispatchWebhookOnFileUploaded;
use App\Webhooks\Listeners\DispatchWebhookOnStatusChanged;
use App\Listeners\SendCertificateStatusNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Auth events
        Verified::class => [
            LogVerifiedUser::class,
        ],
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        PasswordReset::class => [
            SendPasswordResetEmail::class,
        ],

        // Domain events → Webhook listeners
        CertificateRequestCreated::class => [
            DispatchWebhookOnCertificateCreated::class,
        ],
         CertificateStatusChanged::class => [
             DispatchWebhookOnStatusChanged::class,
             SendCertificateStatusNotification::class,
         ],
        CertificateFileUploaded::class => [
            DispatchWebhookOnFileUploaded::class,
        ],
        CertificateRequestDeleted::class => [
            DispatchWebhookOnCertificateDeleted::class,
        ],

        // AI event → existing listener + webhook listener
        CertificateProcessedWithAI::class => [
            HandleCertificateAIProcessing::class,
            DispatchWebhookOnAIProcessed::class,
        ],

        // Payment events (WOMPI)
        PaymentApproved::class => [
            SendPaymentApprovedNotification::class,
        ],
        PaymentFailed::class => [
            SendPaymentFailedNotification::class,
        ],

        // Viafirma events (Sprint 3)
        \App\Modules\Viafirma\Domain\Events\ViafirmaStatusChanged::class => [
            \App\Modules\Viafirma\Application\Listeners\ViafirmaRequestStateChangedListener::class,
        ],

        // Viafirma events (Sprint 4)
        \App\Modules\Viafirma\Domain\Events\ViafirmaReadyToDownload::class => [
            \App\Modules\Viafirma\Application\Listeners\DispatchDownloadOnReadyListener::class,
        ],

        // Viafirma events (V-308: KYC accreditation link persistence)
        \App\Modules\Viafirma\Domain\Events\ViafirmaAccreditationReached::class => [
            \App\Modules\Viafirma\Application\Listeners\DispatchKycLinkFetchListener::class,
        ],

        // Viafirma events (V-311: Detección y notificación de fallos)
        \App\Modules\Viafirma\Domain\Events\ViafirmaRequestFailed::class => [
            \App\Modules\Viafirma\Application\Listeners\ViafirmaRequestFailedListener::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
