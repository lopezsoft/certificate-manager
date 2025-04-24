<?php

namespace App\Http\Controllers\Auth;

use App\Common\HttpResponseMessages;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();
        if(!$user) {
            return HttpResponseMessages::getResponse404([
                'message'   => 'El usuario no existe.'
            ]);
        }
        if ($user->hasVerifiedEmail()) {
            return HttpResponseMessages::getResponse400([
                'message'   => 'El correo electrónico ya fue verificado.'
            ]);
        }
        $user->sendEmailVerificationNotification();
        Auth::login($user);
        return response()->json(['message' => 'Se ha enviado un correo electrónico de verificación']);
    }
}
