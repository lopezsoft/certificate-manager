<?php

namespace App\Http\Controllers\Api;

use App\Common\HttpResponseMessages;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTokenRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    /**
     * @OA\Get(
     *     path="/tokens",
     *     tags={"Tokens"},
     *     summary="Listar tokens activos del usuario",
     *     description="Retorna todos los Personal Access Tokens activos y no expirados del usuario autenticado.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de tokens",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/PersonalAccessToken"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()
            ->tokens()
            ->where('revoked', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get(['id', 'name', 'scopes', 'created_at', 'expires_at']);

        return HttpResponseMessages::getResponse([
            'dataRecords' => ['data' => $tokens],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/tokens",
     *     tags={"Tokens"},
     *     summary="Crear Personal Access Token",
     *     description="Genera un nuevo PAT de larga duración. El valor del token solo se expone en esta respuesta — guardarlo de inmediato. Límite: 10 tokens por día.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="Token ERP Producción"),
     *             @OA\Property(property="expires_in_days", type="integer", minimum=1, maximum=365, example=90, description="Días de validez (default: 90, máximo: 365)"),
     *             @OA\Property(property="abilities", type="array", @OA\Items(type="string"), example={"*"}, description="Permisos del token. Usar ['*'] para acceso completo.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token creado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="9d471f3b-8c6e-4a2d-b9f0-1234567890ab"),
     *                 @OA\Property(property="name", type="string", example="Token ERP Producción"),
     *                 @OA\Property(property="token", type="string", example="1|aB3cD4eF5gH6iJ7kL8mN9oP0qR1sT2uV"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time", example="2026-05-19 10:00:00"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-19 10:00:00")
     *             ),
     *             @OA\Property(property="message", type="string", example="Token creado. Guárdelo ahora, no se mostrará de nuevo.")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validación fallida", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=429, description="Límite diario de creación alcanzado"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function store(CreateTokenRequest $request): JsonResponse
    {
        $expiresInDays = min(
            (int) $request->input('expires_in_days', config('tokens.expiration_days', 90)),
            config('tokens.max_expiration_days', 365)
        );

        $tokenResult = $request->user()->createToken(
            $request->input('name'),
            $request->input('abilities', ['*'])
        );

        $token              = $tokenResult->token;
        $token->expires_at  = Carbon::now()->addDays($expiresInDays);
        $token->save();

        return HttpResponseMessages::getResponse([
            'data' => [
                'id'         => $token->id,
                'name'       => $token->name,
                'token'      => $tokenResult->accessToken,
                'expires_at' => $token->expires_at->toDateTimeString(),
                'created_at' => $token->created_at->toDateTimeString(),
            ],
            'message' => 'Token creado. Guárdelo ahora, no se mostrará de nuevo.',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/tokens/{id}",
     *     tags={"Tokens"},
     *     summary="Detalle de un token",
     *     description="Retorna los metadatos de un PAT específico. El valor del token no está disponible.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID UUID del token", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Detalle del token",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/PersonalAccessToken")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Token no encontrado"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $token = $request->user()->tokens()->findOrFail($id);

        return HttpResponseMessages::getResponse([
            'data' => [
                'id'         => $token->id,
                'name'       => $token->name,
                'scopes'     => $token->scopes,
                'revoked'    => $token->revoked,
                'expires_at' => optional($token->expires_at)?->toDateTimeString(),
                'created_at' => $token->created_at->toDateTimeString(),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/tokens/{id}",
     *     tags={"Tokens"},
     *     summary="Revocar un token",
     *     description="Revoca e invalida inmediatamente el token indicado.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID UUID del token", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Token revocado", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse")),
     *     @OA\Response(response=404, description="Token no encontrado"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $token = $request->user()->tokens()->findOrFail($id);
        $token->revoke();

        return HttpResponseMessages::getResponse([
            'message' => 'Token revocado exitosamente.',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/tokens/revoke-all",
     *     tags={"Tokens"},
     *     summary="Revocar todos los tokens",
     *     description="Revoca e invalida todos los tokens activos del usuario. Útil cuando las credenciales se ven comprometidas.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Todos los tokens revocados",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="3 token(s) revocado(s) exitosamente.")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function revokeAll(Request $request): JsonResponse
    {
        $count = $request->user()->tokens()->where('revoked', false)->count();
        $request->user()->tokens()->update(['revoked' => true]);

        return HttpResponseMessages::getResponse([
            'message' => "{$count} token(s) revocado(s) exitosamente.",
        ]);
    }

    /**
     * @OA\Post(
     *     path="/tokens/{id}/renew",
     *     tags={"Tokens"},
     *     summary="Renovar un token",
     *     description="Crea un nuevo token con el mismo nombre y permisos, revoca el anterior. El nuevo valor solo se expone en esta respuesta.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="ID UUID del token a renovar", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Token renovado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="a1b2c3d4-..."),
     *                 @OA\Property(property="name", type="string", example="Token ERP Producción (renovado)"),
     *                 @OA\Property(property="token", type="string", example="2|xY9zA0bC1dE2fG3h..."),
     *                 @OA\Property(property="expires_at", type="string", format="date-time"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="message", type="string", example="Token renovado. Guárdelo ahora, no se mostrará de nuevo.")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Token no encontrado"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function renew(Request $request, string $id): JsonResponse
    {
        $oldToken       = $request->user()->tokens()->findOrFail($id);
        $expiresInDays  = config('tokens.expiration_days', 90);

        $tokenResult = $request->user()->createToken(
            $oldToken->name . ' (renovado)',
            $oldToken->scopes ?: ['*']
        );

        $newToken               = $tokenResult->token;
        $newToken->expires_at   = Carbon::now()->addDays($expiresInDays);
        $newToken->save();

        $oldToken->revoke();

        return HttpResponseMessages::getResponse([
            'data' => [
                'id'         => $newToken->id,
                'name'       => $newToken->name,
                'token'      => $tokenResult->accessToken,
                'expires_at' => $newToken->expires_at->toDateTimeString(),
                'created_at' => $newToken->created_at->toDateTimeString(),
            ],
            'message' => 'Token renovado. Guárdelo ahora, no se mostrará de nuevo.',
        ]);
    }
}
