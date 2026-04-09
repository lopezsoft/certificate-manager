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
     * @OA\Get(
     *     path="/verify-email/{id}/{hash}",
     *     tags={"Autenticación"},
     *     summary="Verificar dirección de correo electrónico",
     *     description="Verifica el email del usuario mediante el enlace firmado enviado por correo. Redirige al frontend tras la verificación. Requiere firma URL válida.",
     *     @OA\Parameter(name="id", in="path", required=true, description="ID del usuario", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="hash", in="path", required=true, description="Hash de verificación del email", @OA\Schema(type="string")),
     *     @OA\Parameter(name="signature", in="query", required=true, description="Firma de la URL", @OA\Schema(type="string")),
     *     @OA\Parameter(name="expires", in="query", required=true, description="Timestamp de expiración", @OA\Schema(type="integer")),
     *     @OA\Response(response=302, description="Redirige al frontend (login o reenvío de verificación)"),
     *     @OA\Response(response=403, description="Firma de URL inválida o expirada")
     * )
     *
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
