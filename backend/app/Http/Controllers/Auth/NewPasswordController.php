<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class NewPasswordController extends Controller
{
    /**
     * @OA\Post(
     *     path="/reset-password",
     *     tags={"Autenticación"},
     *     summary="Restablecer contraseña",
     *     description="Restablece la contraseña del usuario usando el token recibido por correo electrónico.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token","email","password","password_confirmation"},
     *             @OA\Property(property="token", type="string", example="a1b2c3d4e5f6..."),
     *             @OA\Property(property="email", type="string", format="email", example="usuario@empresa.com"),
     *             @OA\Property(property="password", type="string", format="password", example="nuevaContraseña123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="nuevaContraseña123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Contraseña restablecida exitosamente", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse")),
     *     @OA\Response(response=422, description="Token inválido o expirado", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     *
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token'     => ['required'],
            'email'     => ['required', 'email'],
            'password'  => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __($status)
        ]);
    }
}
