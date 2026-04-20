<?php

namespace App\Andes\DTOs;

class IdentityValidationRequest
{
    public function __construct(
        public string $idExpeditionDate, // Fecha expedición del documento AAAA-MM-DD
        public string $idNumber,         // Número de documento
        public string $idType,           // Tipo de documento (código ANDES)
        public string $recentPhoneNumber,// Celular del titular
        public string $lastName,         // Apellido del titular
    ) {}

    public function toArray(): array
    {
        return [
            'IdExpeditionDate'  => $this->idExpeditionDate,
            'IdNumber'          => $this->idNumber,
            'IdType'            => $this->idType,
            'RecentPhoneNumber' => $this->recentPhoneNumber,
            'LastName'          => $this->lastName,
        ];
    }
}


