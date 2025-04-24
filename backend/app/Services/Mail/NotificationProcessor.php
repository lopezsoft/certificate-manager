<?php

namespace App\Services\Mail;

use App\Handlers\Contracts\NotificationHandlerInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class NotificationProcessor
{
    protected $handlers = [];

    public function __construct()
    {
        $this->handlers = [
            'Delivery' => \App\Handlers\DeliveryNotificationHandler::class,
            'Bounce' => \App\Handlers\BounceNotificationHandler::class,
            'Complaint' => \App\Handlers\ComplaintNotificationHandler::class,
            'Open' => \App\Handlers\OpenNotificationHandler::class,
            'Click' => \App\Handlers\ClickNotificationHandler::class,
        ];
    }

    public function process(string $eventType, array $notification): void
    {
        if (isset($this->handlers[$eventType])) {
            $handlerClass = $this->handlers[$eventType];
            /** @var NotificationHandlerInterface $handler */
            // $handler = new $handlerClass();
            $handler = App::make($handlerClass);
            $handler->handle($notification);
        } else {
            Log::warning("Unhandled SNS notification type: $eventType");
        }
    }
}
