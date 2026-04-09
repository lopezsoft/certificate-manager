<?php

namespace Tests\Unit\Enums;

use App\Enums\CertificateRequestStatusEnum;
use Tests\TestCase;

/**
 * Tests unitarios para CertificateRequestStatusEnum.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 */
class CertificateRequestStatusEnumTest extends TestCase
{
    public function test_todos_los_estados_son_strings_validos(): void
    {
        $values = CertificateRequestStatusEnum::values();

        $this->assertCount(7, $values);
        $this->assertContains('DRAFT', $values);
        $this->assertContains('SENT', $values);
        $this->assertContains('PENDING', $values);
        $this->assertContains('ACCEPTED', $values);
        $this->assertContains('PROCESSING', $values);
        $this->assertContains('PROCESSED', $values);
        $this->assertContains('REJECTED', $values);
    }

    public function test_active_statuses_no_incluye_processed_ni_rejected(): void
    {
        $active = CertificateRequestStatusEnum::activeStatuses();

        $this->assertNotContains('PROCESSED', $active);
        $this->assertNotContains('REJECTED', $active);
        $this->assertContains('DRAFT', $active);
        $this->assertContains('PROCESSING', $active);
    }

    public function test_issued_statuses_contiene_processed_y_processing(): void
    {
        $issued = CertificateRequestStatusEnum::issuedStatuses();

        $this->assertContains('PROCESSED', $issued);
        $this->assertContains('PROCESSING', $issued);
        $this->assertNotContains('DRAFT', $issued);
    }

    public function test_admin_default_statuses_es_el_subset_correcto(): void
    {
        $admin = CertificateRequestStatusEnum::adminDefaultStatuses();

        $this->assertContains('SENT', $admin);
        $this->assertContains('PENDING', $admin);
        $this->assertContains('PROCESSING', $admin);
        $this->assertContains('ACCEPTED', $admin);
        $this->assertNotContains('DRAFT', $admin);
        $this->assertNotContains('PROCESSED', $admin);
    }

    public function test_cada_estado_tiene_descripcion_legible(): void
    {
        foreach (CertificateRequestStatusEnum::cases() as $status) {
            $description = $status->description();

            $this->assertNotEmpty($description);
            $this->assertIsString($description);
        }
    }

    public function test_se_puede_instanciar_desde_string_valido(): void
    {
        $status = CertificateRequestStatusEnum::from('PROCESSED');

        $this->assertEquals(CertificateRequestStatusEnum::PROCESSED, $status);
        $this->assertEquals('PROCESSED', $status->value);
    }

    public function test_try_from_retorna_null_para_string_invalido(): void
    {
        $status = CertificateRequestStatusEnum::tryFrom('INVALIDO');

        $this->assertNull($status);
    }
}

