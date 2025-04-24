<?php

namespace App\Modules\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;

class SendingEmail
{
    public static function toUser(User $user): void {
        event(new Registered($user));
    }
}
