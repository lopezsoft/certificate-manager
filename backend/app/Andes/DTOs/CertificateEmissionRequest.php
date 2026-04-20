<?php

namespace App\Andes\DTOs;

/**
 * DTO de Request para emisión de certificado de Facturación Electrónica vía ANDES PKI SOAP.
 * Alcance MVP: tipoCert 10 (P.Jurídica) y 11 (P.Natural).
 */
class CertificateEmissionRequest
{
    public function __construct(
        // ── Campos comunes ───────────────────────────────────────────
        public int    $tipoCert,       // 10=P.Jurídica, 11=P.Natural
        public int    $tipoDoc,        // ANDES TipoDoc: 1=CC, 3=CE, 6=Pasaporte
        public string $documento,      // Número de documento sin puntos
        public string $nombres,
        public string $apellidos,
        public int    $municipio,      // Código DANE (cities.city_code)
        public string $direccion,
        public string $email,
        public string $emailEnt,
        public string $celular,
        public string $fechaCert,      // AAAA-MM-DD (fecha de vigencia hasta)
        public int    $formato,        // 2=físico, 3=PKCS10, 4=virtual
        public int    $vigenciaCert,   // 3=1año, 4=2años
        public string $soporte,        // ZIP base64 con documentos de soporte
        public ?string $telefono = null,
        public ?string $pin      = null,

        // ── Campos adicionales solo para P.Jurídica (tipoCert=10) ───
        public ?int    $tipoDocEnt     = null,  // 2=NIT
        public ?string $documentoEnt   = null,  // NIT sin DV
        public ?string $razonSocial    = null,
        public ?int    $municipioEnt   = null,  // Código DANE de la entidad
        public ?string $direccionEnt   = null,
        public ?string $cargo          = null,
    ) {}

    public function isPersonaJuridica(): bool
    {
        return $this->tipoCert === 10;
    }

    public function toSoapArray(): array
    {
        $params = [
            'tipoCert'    => $this->tipoCert,
            'TipoDoc'     => $this->tipoDoc,
            'Documento'   => $this->documento,
            'Nombres'     => $this->nombres,
            'Apellidos'   => $this->apellidos,
            'Municipio'   => $this->municipio,
            'Dirección'   => $this->direccion,
            'Email'       => $this->email,
            'EmailEnt'    => $this->emailEnt,
            'Celular'     => $this->celular,
            'fechaCert'   => $this->fechaCert,
            'formato'     => $this->formato,
            'vigenciaCert'=> $this->vigenciaCert,
            'soporte'     => $this->soporte,
        ];

        if ($this->telefono !== null) {
            $params['Teléfono'] = $this->telefono;
        }
        if ($this->pin !== null) {
            $params['pin'] = $this->pin;
        }

        if ($this->isPersonaJuridica()) {
            $params['TipoDocEnt']   = $this->tipoDocEnt;
            $params['documentoEnt'] = $this->documentoEnt;
            $params['razonsocial']  = $this->razonSocial;
            $params['municipioEnt'] = $this->municipioEnt;
            $params['direccionEnt'] = $this->direccionEnt;
            $params['Cargo']        = $this->cargo;
        }

        return $params;
    }
}


