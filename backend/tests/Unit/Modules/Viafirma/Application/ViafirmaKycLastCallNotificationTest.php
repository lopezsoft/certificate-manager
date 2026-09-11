<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Application;

use App\Modules\Viafirma\Application\Notifications\ViafirmaKycLastCallNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * No toca base de datos ni envía correo real — solo construye la
 * notificación e inspecciona su contenido.
 */
final class ViafirmaKycLastCallNotificationTest extends TestCase
{
    private function makeNotification(): ViafirmaKycLastCallNotification
    {
        return new ViafirmaKycLastCallNotification(
            viafirmaRequestId: 65,
            companyName:       'LOPEZSOFT S.A.S',
            applicantName:     'Juan Perez',
            codRequest:        'ABC123',
            kycUrl:            'https://signup.metamap.com?merchantToken=xyz',
        );
    }

    #[Test]
    public function usa_solo_el_canal_mail_para_notificables_anonimos(): void
    {
        $notification = $this->makeNotification();

        $this->assertSame(['mail'], $notification->via(new AnonymousNotifiable()));
    }

    #[Test]
    public function el_correo_incluye_el_codigo_solicitante_y_enlace(): void
    {
        $notification = $this->makeNotification();
        $mail = $notification->toMail(new AnonymousNotifiable());

        $this->assertStringContainsString('ABC123', $mail->subject);
        $renderedLines = implode(' ', $mail->introLines);
        $this->assertStringContainsString('Juan Perez', $renderedLines);
        $this->assertStringContainsString('LOPEZSOFT S.A.S', $renderedLines);
        $this->assertContains('https://signup.metamap.com?merchantToken=xyz', $mail->introLines);
    }

    #[Test]
    public function toArray_incluye_los_datos_clave(): void
    {
        $notification = $this->makeNotification();
        $array = $notification->toArray(new AnonymousNotifiable());

        $this->assertSame('viafirma_kyc_last_call', $array['type']);
        $this->assertSame(65, $array['viafirma_request_id']);
        $this->assertSame('ABC123', $array['cod_request']);
        $this->assertSame('Juan Perez', $array['applicant_name']);
    }
}
