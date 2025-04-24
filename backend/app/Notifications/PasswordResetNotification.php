<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

class PasswordResetNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;
    public function toMail($notifiable): MailMessage
    {
        $url        = $this->resetUrl($notifiable);
        $subject    = Lang::get('Reset Password Notification');
        return (new MailMessage)
            ->subject($subject)
            ->line(Lang::get('You are receiving this email because we received a password reset request for your account.'))
            ->action(Lang::get('Reset Password'), $url)
            ->line(Lang::get('This password reset link will expire in :count minutes.', ['count' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]))
            ->line(Lang::get('If you did not request a password reset, no further action is required.'))
            ->markdown('mail.password.reset', [
                'subject' => $subject
            ]);
    }
}
