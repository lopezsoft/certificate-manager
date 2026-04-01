<?php

namespace Tests\Unit\Services;

use App\Mail\SendMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tests unitarios para el mailable SendMail utilizado por CertificateRequestMailService.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 */
class CertificateRequestMailServiceTest extends TestCase
{
    // ── Estructura del Mailable ──────────────────────────────────────────────

    public function test_send_mail_implementa_should_queue(): void
    {
        $messageData = (object) [
            'company'    => (object) ['name' => 'Empresa Test'],
            'data'       => (object) ['id' => 1, 'dni' => '900123456', 'dv' => '1'],
            'subject'    => 'Solicitud certificado 900123456-1',
            'files'      => collect(),
            'email_from' => 'test@test.com',
            'replyTo'    => 'reply@test.com',
        ];

        $mailable = new SendMail($messageData);

        $this->assertInstanceOf(ShouldQueue::class, $mailable);
    }

    public function test_send_mail_tiene_dos_intentos(): void
    {
        $messageData = (object) ['company' => null, 'data' => null, 'subject' => 'test',
            'files' => collect(), 'email_from' => 'a@a.com', 'replyTo' => 'b@b.com'];

        $mailable = new SendMail($messageData);

        $this->assertSame(2, $mailable->tries);
    }

    public function test_send_mail_tiene_backoff_de_tres_etapas(): void
    {
        $messageData = (object) ['company' => null, 'data' => null, 'subject' => 'test',
            'files' => collect(), 'email_from' => 'a@a.com', 'replyTo' => 'b@b.com'];

        $mailable = new SendMail($messageData);

        $this->assertIsArray($mailable->backoff);
        $this->assertCount(3, $mailable->backoff);
    }

    // ── Mail::fake ───────────────────────────────────────────────────────────

    public function test_mail_fake_captura_mailables_encolados(): void
    {
        Mail::fake();

        $messageData = (object) [
            'company'    => (object) ['name' => 'Empresa Test'],
            'data'       => (object) ['id' => 1, 'dni' => '900123456', 'dv' => '1'],
            'subject'    => 'Solicitud certificado 900123456-1',
            'files'      => collect(),
            'email_from' => 'noreply@test.com',
            'replyTo'    => 'soporte@test.com',
        ];

        Mail::to('destino@test.com')->queue(new SendMail($messageData));

        Mail::assertQueued(SendMail::class);
    }

    public function test_mail_fake_no_reporta_envios_cuando_no_se_envia_nada(): void
    {
        Mail::fake();

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_mail_fake_cuenta_envios_multiples(): void
    {
        Mail::fake();

        $messageData = (object) [
            'company'    => null,
            'data'       => (object) ['id' => 1, 'dni' => '900000001', 'dv' => '7'],
            'subject'    => 'Test',
            'files'      => collect(),
            'email_from' => 'a@a.com',
            'replyTo'    => 'b@b.com',
        ];

        Mail::to('uno@test.com')->queue(new SendMail($messageData));
        Mail::to('dos@test.com')->queue(new SendMail($messageData));

        Mail::assertQueued(SendMail::class, 2);
    }
}
