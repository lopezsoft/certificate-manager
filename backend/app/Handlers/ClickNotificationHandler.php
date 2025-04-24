<?php

namespace App\Handlers;

use App\Handlers\Contracts\NotificationHandlerInterface;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Log;

class ClickNotificationHandler implements NotificationHandlerInterface
{
    public function handle(array $notification): void
    {
        $messageId = $notification['mail']['messageId'];
        $click = $notification['click'];
        $link = $click['link'];

        $emailLog = EmailLog::query()->where('message_id', $messageId)->first();

        if ($emailLog) {
            $emailLog->clicks += 1;
            $emailLog->last_clicked_at = now();
            $emailLog->save();

            // Log::info("Email link clicked: Message ID $messageId, Link: $link");
        } else {
            Log::warning("Email log not found for Message ID $messageId");
        }
    }
}
