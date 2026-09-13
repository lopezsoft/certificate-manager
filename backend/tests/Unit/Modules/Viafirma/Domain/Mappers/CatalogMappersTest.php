<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Viafirma\Domain\Mappers;

use App\Models\IdentityDocument;
use App\Models\TypeOrganization;
use App\Modules\Viafirma\Domain\Enums\CertificateProfile;
use App\Modules\Viafirma\Domain\Enums\IdentityType;
use App\Modules\Viafirma\Domain\Exceptions\UnsupportedIdentityDocumentException;
use App\Modules\Viafirma\Domain\Exceptions\UnsupportedOrganizationTypeException;
use App\Modules\Viafirma\Domain\Mappers\IdentityTypeMapper;
use App\Modules\Viafirma\Domain\Mappers\ProfileTypeMapper;
use PHPUnit\Framework\TestCase;

final class CatalogMappersTest extends TestCase
{
    private function identityDoc(string $code, string $abbr): IdentityDocument
    {
        $m = new IdentityDocument();
        $m->forceFill(['code' => $code, 'abbreviation' => $abbr]);
        return $m;
    }

    private function orgType(int $code, string $description = ''): TypeOrganization
    {
        $m = new TypeOrganization();
        $m->forceFill(['code' => $code, 'description' => $description]);
        return $m;
    }

    public function test_identity_cc_maps_to_idc(): void
    {
        $this->assertSame(IdentityType::IDC, (new IdentityTypeMapper())->fromIdentityDocument($this->identityDoc('13', 'CC')));
    }

    public function test_identity_ce_maps_to_idc(): void
    {
        $this->assertSame(IdentityType::IDC, (new IdentityTypeMapper())->fromIdentityDocument($this->identityDoc('22', 'CE')));
    }

    public function test_identity_pas_maps_to_pas(): void
    {
        $this->assertSame(IdentityType::PAS, (new IdentityTypeMapper())->fromIdentityDocument($this->identityDoc('41', 'PAS')));
    }

    /**
     * NIT (código DIAN '31') mapea a IDC. Viafirma sólo acepta IDC o PAS como
     * identityType; el NIT de una persona jurídica viaja como IDC. Este test
     * antes esperaba una excepción, comportamiento retirado al añadir '31' a
     * IDC_CODES en IdentityTypeMapper.
     */
    public function test_identity_nit_maps_to_idc(): void
    {
        $this->assertSame(
            IdentityType::IDC,
            (new IdentityTypeMapper())->fromIdentityDocument($this->identityDoc('31', 'NIT')),
        );
    }

    /** Un documento fuera del catálogo sí debe rechazarse. */
    public function test_unsupported_identity_document_throws(): void
    {
        $this->expectException(UnsupportedIdentityDocumentException::class);
        (new IdentityTypeMapper())->fromIdentityDocument($this->identityDoc('99', 'XYZ'));
    }

    public function test_type_organization_pj_maps_to_fe_pj(): void
    {
        $this->assertSame(CertificateProfile::FE_PJ, (new ProfileTypeMapper())->fromTypeOrganization($this->orgType(1, 'Persona Jurídica')));
    }

    public function test_type_organization_pn_maps_to_fe_pn(): void
    {
        $this->assertSame(CertificateProfile::FE_PN, (new ProfileTypeMapper())->fromTypeOrganization($this->orgType(2, 'Persona Natural')));
    }

    public function test_type_organization_unknown_throws(): void
    {
        $this->expectException(UnsupportedOrganizationTypeException::class);
        (new ProfileTypeMapper())->fromTypeOrganization($this->orgType(99));
    }
}


