<?php

namespace App\Handlers;

use App\Handlers\Contracts\NotificationHandlerInterface;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Log;

class OpenNotificationHandler implements NotificationHandlerInterface
{
    public function handle(array $notification): void
    {
        $messageId = $notification['mail']['messageId'];

        $emailLog = EmailLog::where('message_id', $messageId)->first();

        if ($emailLog) {
            $emailLog->opens += 1;
            $emailLog->last_opened_at = now();
            $emailLog->save();

            // Log::info("Email opened: Message ID $messageId");
        } else {
            Log::warning("Email log not found for Message ID $messageId");
        }
    }
}
