<?php

namespace Tests\Unit\Commands\Certificate;

use App\Commands\Certificate\CertificateCommandInterface;
use App\Commands\Certificate\CreateCertificateRequestCommand;
use App\Commands\Certificate\DeleteCertificateRequestCommand;
use App\Commands\Certificate\UpdateCertificateRequestCommand;
use App\Commands\Certificate\UpdateCertificateStatusCommand;
use Tests\TestCase;

/**
 * Tests unitarios para los Command DTOs de Certificate.
 *
 * REGLA: Solo mocks/fakes — sin RefreshDatabase, sin migraciones, sin escrituras reales en DB.
 * Verifica: inmutabilidad readonly, tipos de propiedades, implementación de interfaz.
 */
class CertificateCommandTest extends TestCase
{
    // ── DeleteCertificateRequestCommand ─────────────────────────────────────

    public function test_delete_command_implementa_interface(): void
    {
        $command = new DeleteCertificateRequestCommand(certificateId: 10, companyId: 5);

        $this->assertInstanceOf(CertificateCommandInterface::class, $command);
    }

    public function test_delete_command_almacena_propiedades_correctamente(): void
    {
        $command = new DeleteCertificateRequestCommand(certificateId: 42, companyId: 7);

        $this->assertSame(42, $command->certificateId);
        $this->assertSame(7,  $command->companyId);
    }

    public function test_delete_command_propiedades_son_de_tipo_int(): void
    {
        $command = new DeleteCertificateRequestCommand(certificateId: 1, companyId: 2);

        $this->assertIsInt($command->certificateId);
        $this->assertIsInt($command->companyId);
    }

    public function test_delete_command_es_clase_final(): void
    {
        $reflection = new \ReflectionClass(DeleteCertificateRequestCommand::class);

        $this->assertTrue($reflection->isFinal());
    }

    // ── UpdateCertificateStatusCommand ──────────────────────────────────────

    public function test_update_status_command_implementa_interface(): void
    {
        $command = $this->buildUpdateStatusCommand();

        $this->assertInstanceOf(CertificateCommandInterface::class, $command);
    }

    public function test_update_status_command_almacena_propiedades(): void
    {
        $command = $this->buildUpdateStatusCommand(
            certificateId: 99,
            companyId:     3,
            requestStatus: 'PROCESSED',
            comments:      'OK',
            userOfChange:  'MANAGER',
            userId:        11,
        );

        $this->assertSame(99,          $command->certificateId);
        $this->assertSame(3,           $command->companyId);
        $this->assertSame('PROCESSED', $command->requestStatus);
        $this->assertSame('OK',        $command->comments);
        $this->assertSame('MANAGER',   $command->userOfChange);
        $this->assertSame(11,          $command->userId);
    }

    public function test_update_status_command_comments_puede_ser_null(): void
    {
        $command = $this->buildUpdateStatusCommand(comments: null);

        $this->assertNull($command->comments);
    }

    public function test_update_status_command_es_clase_final(): void
    {
        $reflection = new \ReflectionClass(UpdateCertificateStatusCommand::class);

        $this->assertTrue($reflection->isFinal());
    }

    // ── UpdateCertificateRequestCommand ─────────────────────────────────────

    public function test_update_request_command_implementa_interface(): void
    {
        $command = $this->buildUpdateRequestCommand();

        $this->assertInstanceOf(CertificateCommandInterface::class, $command);
    }

    public function test_update_request_command_almacena_propiedades_basicas(): void
    {
        $command = $this->buildUpdateRequestCommand(
            certificateId: 55,
            companyId:     8,
            dni:           '900123456',
        );

        $this->assertSame(55,          $command->certificateId);
        $this->assertSame(8,           $command->companyId);
        $this->assertSame('900123456', $command->dni);
    }

    public function test_update_request_command_nullable_aceptan_null(): void
    {
        $command = $this->buildUpdateRequestCommand(info: null, postalCode: null, phone: null, mobile: null);

        $this->assertNull($command->info);
        $this->assertNull($command->postalCode);
        $this->assertNull($command->phone);
        $this->assertNull($command->mobile);
    }

    public function test_update_request_command_es_clase_final(): void
    {
        $reflection = new \ReflectionClass(UpdateCertificateRequestCommand::class);

        $this->assertTrue($reflection->isFinal());
    }

    // ── CreateCertificateRequestCommand ─────────────────────────────────────

    public function test_create_command_implementa_interface(): void
    {
        $command = $this->buildCreateCommand();

        $this->assertInstanceOf(CertificateCommandInterface::class, $command);
    }

    public function test_create_command_almacena_files(): void
    {
        $dummyFiles = ['file1.pdf', 'file2.pdf'];
        $command    = $this->buildCreateCommand(files: $dummyFiles);

        $this->assertSame($dummyFiles, $command->files);
    }

    public function test_create_command_info_puede_ser_null(): void
    {
        $command = $this->buildCreateCommand(info: null);

        $this->assertNull($command->info);
    }

    public function test_create_command_es_clase_final(): void
    {
        $reflection = new \ReflectionClass(CreateCertificateRequestCommand::class);

        $this->assertTrue($reflection->isFinal());
    }

    // ── Interfaz marcadora ───────────────────────────────────────────────────

    public function test_interface_certificateCommandInterface_existe(): void
    {
        $this->assertTrue(interface_exists(CertificateCommandInterface::class));
    }

    public function test_todos_los_commands_implementan_la_interfaz_marcadora(): void
    {
        $classes = [
            DeleteCertificateRequestCommand::class,
            UpdateCertificateStatusCommand::class,
            UpdateCertificateRequestCommand::class,
            CreateCertificateRequestCommand::class,
        ];

        foreach ($classes as $class) {
            $this->assertInstanceOf(CertificateCommandInterface::class, new $class(...$this->defaultArgsFor($class)));
        }
    }

    // ── Builders privados ────────────────────────────────────────────────────

    private function buildUpdateStatusCommand(
        int     $certificateId = 1,
        int     $companyId     = 1,
        string  $requestStatus = 'PENDING',
        ?string $comments      = 'Algún comentario',
        string  $userOfChange  = 'USER',
        int     $userId        = 1,
    ): UpdateCertificateStatusCommand {
        return new UpdateCertificateStatusCommand(
            certificateId: $certificateId,
            companyId:     $companyId,
            requestStatus: $requestStatus,
            comments:      $comments,
            userOfChange:  $userOfChange,
            userId:        $userId,
        );
    }

    private function buildUpdateRequestCommand(
        int     $certificateId       = 1,
        int     $companyId           = 1,
        int     $cityId              = 1,
        int     $identityDocumentId  = 1,
        int     $typeOrganizationId  = 1,
        string  $documentNumber      = 'DOC-001',
        string  $address             = 'Calle 1 # 2-3',
        string  $legalRepresentative = 'Juan Pérez',
        string  $companyName         = 'Empresa S.A.S',
        string  $dni                 = '900123456',
        int     $life                = 1,
        ?string $info                = 'Info opcional',
        ?string $postalCode          = '110111',
        ?string $phone               = '6013456789',
        ?string $mobile              = '3001234567',
    ): UpdateCertificateRequestCommand {
        return new UpdateCertificateRequestCommand(
            certificateId:       $certificateId,
            companyId:           $companyId,
            cityId:              $cityId,
            identityDocumentId:  $identityDocumentId,
            typeOrganizationId:  $typeOrganizationId,
            documentNumber:      $documentNumber,
            address:             $address,
            legalRepresentative: $legalRepresentative,
            companyName:         $companyName,
            dni:                 $dni,
            life:                $life,
            info:                $info,
            postalCode:          $postalCode,
            phone:               $phone,
            mobile:              $mobile,
        );
    }

    private function buildCreateCommand(
        int     $companyId           = 1,
        int     $cityId              = 1,
        int     $identityDocumentId  = 1,
        int     $typeOrganizationId  = 1,
        string  $documentNumber      = 'DOC-002',
        string  $address             = 'Av. Siempre Viva 742',
        string  $legalRepresentative = 'Homero Simpson',
        string  $companyName         = 'Springfield Nuclear',
        string  $dni                 = '800555123',
        int     $life                = 1,
        ?string $info                = null,
        array   $files               = [],
        int     $userId              = 1,
    ): CreateCertificateRequestCommand {
        return new CreateCertificateRequestCommand(
            companyId:           $companyId,
            cityId:              $cityId,
            identityDocumentId:  $identityDocumentId,
            typeOrganizationId:  $typeOrganizationId,
            documentNumber:      $documentNumber,
            address:             $address,
            legalRepresentative: $legalRepresentative,
            companyName:         $companyName,
            dni:                 $dni,
            life:                $life,
            info:                $info,
            files:               $files,
            userId:              $userId,
        );
    }

    /** Devuelve el array mínimo de args para instanciar cada Command. */
    private function defaultArgsFor(string $class): array
    {
        return match ($class) {
            DeleteCertificateRequestCommand::class  => [1, 1],
            UpdateCertificateStatusCommand::class   => [1, 1, 'PENDING', null, 'USER', 1],
            UpdateCertificateRequestCommand::class  => [1, 1, 1, 1, 1, 'DOC', 'Dir', 'Rep', 'Co', '900', 1, null, null, null, null],
            CreateCertificateRequestCommand::class  => [1, 1, 1, 1, 'DOC', 'Dir', 'Rep', 'Co', '900', 1, null, [], 1],
            default                                 => [],
        };
    }
}
