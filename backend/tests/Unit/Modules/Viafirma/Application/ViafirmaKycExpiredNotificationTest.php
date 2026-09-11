<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Application;

use App\Modules\Viafirma\Application\Notifications\ViafirmaKycExpiredNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * No toca base de datos ni envía correo real — solo construye la
 * notificación e inspecciona su contenido.
 */
final class ViafirmaKycExpiredNotificationTest extends TestCase
{
    private function makeNotification(): ViafirmaKycExpiredNotification
    {
        return new ViafirmaKycExpiredNotification(
            viafirmaRequestId: 65,
            companyName:       'LOPEZSOFT S.A.S',
            applicantName:     'Juan Perez',
            codRequest:        'ABC123',
        );
    }

    #[Test]
    public function usa_solo_el_canal_mail_para_notificables_anonimos(): void
    {
        $notification = $this->makeNotification();

        $this->assertSame(['mail'], $notification->via(new AnonymousNotifiable()));
    }

    #[Test]
    public function el_correo_no_incluye_ningun_enlace_kyc(): void
    {
        $notification = $this->makeNotification();
        $mail = $notification->toMail(new AnonymousNotifiable());

        $this->assertStringContainsString('ABC123', $mail->subject);
        $renderedLines = implode(' ', $mail->introLines);
        $this->assertStringContainsString('Juan Perez', $renderedLines);
        $this->assertStringContainsString('LOPEZSOFT S.A.S', $renderedLines);
        $this->assertStringContainsString('reintegrado', $renderedLines);
        $this->assertNull($mail->actionUrl);
    }

    #[Test]
    public function toArray_incluye_los_datos_clave(): void
    {
        $notification = $this->makeNotification();
        $array = $notification->toArray(new AnonymousNotifiable());

        $this->assertSame('viafirma_kyc_expired', $array['type']);
        $this->assertSame(65, $array['viafirma_request_id']);
        $this->assertSame('ABC123', $array['cod_request']);
    }
}
