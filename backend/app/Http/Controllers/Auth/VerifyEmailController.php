<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;


class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function verify(Request $request)
    {
        // Validar que el ID y el hash en la URL coincidan con los del usuario
        $user = User::findOrFail($request->route('id'));

        if (! URL::hasValidSignature($request)) {
            return redirect(config('app.frontend_url')."/#/auth/email-resend");
        }

        if ($user->hasVerifiedEmail()) {
            return redirect(config('app.frontend_url')."/#/auth/login");
        }

        if ($user->markEmailAsVerified()) {
            $user->active = 1;
            $user->save();
            event(new Verified($user));
        }
        return redirect(config('app.frontend_url')."/#/auth/login");
    }
}
