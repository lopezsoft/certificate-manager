<?php

namespace App\Listeners;

use App\Notifications\SendPasswordResetEmailNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class SendPasswordResetEmail
{
    /**
     * Handle the event.
     */
    public function handle(PasswordReset $event): void
    {
        $user = $event->user;
        Notification::send($user, new SendPasswordResetEmailNotification($user));
    }
}
