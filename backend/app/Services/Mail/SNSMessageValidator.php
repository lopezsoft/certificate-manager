<?php

namespace App\Services\Mail;

use Aws\Sns\Message;
use Aws\Sns\MessageValidator;

class SNSMessageValidator
{
    public function validate(string $payload): Message
    {
        $message = Message::fromJsonString($payload);

        $validator = new MessageValidator();

        // Validar la firma
        $validator->validate($message);

        return $message;
    }
}
