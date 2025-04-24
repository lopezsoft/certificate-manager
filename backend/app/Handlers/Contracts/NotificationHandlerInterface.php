<?php

namespace App\Handlers\Contracts;

interface NotificationHandlerInterface
{
    public function handle(array $notification): void;
}
