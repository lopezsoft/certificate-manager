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
     * @OA\Post(
     *     path="/email/verification-notification",
     *     tags={"Autenticación"},
     *     summary="Reenviar correo de verificación de email",
     *     description="Envía nuevamente el correo de verificación al usuario indicado. Si el email ya fue verificado, retorna error 400.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="usuario@empresa.com")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Correo de verificación enviado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Se ha enviado un correo electrónico de verificación")
     *         )
     *     ),
     *     @OA\Response(response=400, description="El correo ya fue verificado", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="Usuario no encontrado", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     *
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
