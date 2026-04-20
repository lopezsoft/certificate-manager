<?php

namespace App\Andes\Contracts;

use App\Andes\DTOs\IdentityValidationRequest;
use App\Andes\DTOs\IdentityValidationResponse;

interface AndesIdentityServiceContract
{
    public function startValidation(IdentityValidationRequest $dto): IdentityValidationResponse;
    public function resendOtp(string $token, string $method): void;
    public function verifyOtp(string $token, string $code): IdentityValidationResponse;
    public function verifyQuestions(string $token, string $answersXml): IdentityValidationResponse;
    public function bypassToQuestions(string $token): IdentityValidationResponse;
    public function checkTokenStatus(string $idType, string $idNumber, string $token): int;
}

