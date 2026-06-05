<?php

namespace App\Http\Controllers;

use App\Common\HttpResponseMessages;
use App\Enums\CertificateRequestStatusEnum;
use App\Jobs\SendAdminExpiringCertificatesReportJob;
use App\Jobs\SendExpiringCertificatesNotificationsJob;
use App\Models\CertificateRequest;
use App\Modules\Company\CompanyQueries;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/certificates/expiring",
     *     tags={"Notificaciones"},
     *     summary="Certificados próximos a vencer",
     *     description="Retorna los certificados PROCESSED que vencen dentro del umbral configurado.
     *                  Empresas ven solo los suyos; administradores ven todos.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="days", in="query", description="Días de antelación (default: 30)", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Lista de certificados próximos a vencer",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Certificados próximos a vencer"),
     *             @OA\Property(property="total", type="integer", example=5),
     *             @OA\Property(property="dataRecords", type="array", @OA\Items(ref="#/components/schemas/ExpiringCertificate"))
     *         )
     *     )
     * )
     */
    public function expiring(Request $request): JsonResponse
    {
        try {
            $days = (int) $request->input('days', config('certificate.notification_days', 30));

            $query = CertificateRequest::with(['company', 'city'])
                ->whereNotNull('expiration_date')
                ->where('request_status', CertificateRequestStatusEnum::PROCESSED->value);

            if ($days >= 0) {
                // Positivo: certificados que vencen en los próximos N días
                $threshold = now()->addDays($days);
                $query->where('expiration_date', '>', now())
                      ->where('expiration_date', '<=', $threshold);
                $message = 'Certificados próximos a vencer';
            } else {
                // Negativo: certificados que vencieron en los últimos N días SIN renovar
                $threshold = now()->subDays(abs($days));
                $query->where('expiration_date', '<', now())
                      ->where('expiration_date', '>=', $threshold)
                      ->whereNotExists(function ($sub) {
                          $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                              ->from('certificate_requests as renewed')
                              ->whereColumn('renewed.company_id', 'certificate_requests.company_id')
                              ->whereColumn('renewed.dni', 'certificate_requests.dni')
                              ->whereColumn('renewed.updated_at', '>', 'certificate_requests.updated_at')
                              ->where('renewed.request_status', CertificateRequestStatusEnum::PROCESSED->value);
                      });
                $message = 'Certificados vencidos sin renovar en los últimos ' . abs($days) . ' días';
            }

            $query->orderBy('expiration_date', 'asc');

            // Si el usuario no es admin, filtra solo los de su empresa
            $user = Auth::user();
            if (!$user->is_admin) {
                $company = CompanyQueries::getCompany();
                $query->where('company_id', $company->id);
            }

            $certificates = $query->get()->map(function ($cert) {
                $daysLeft = now()->diffInDays(Carbon::parse($cert->expiration_date), false);

                return [
                    'id'                       => $cert->id,
                    'company_name'             => $cert->company_name,
                    'dni'                      => $cert->dni,
                    'dv'                       => $cert->dv,
                    'email'                    => $cert->company->email ?? null,
                    'phone'                    => $cert->phone,
                    'expiration_date'          => $cert->expiration_date,
                    'expiration_date_formatted'=> $cert->expiration_date_formatted,
                    'days_remaining'           => $daysLeft,
                    'urgency_level'            => $this->getUrgencyLevel($daysLeft),
                    'city'                     => $cert->city->city_name ?? null,
                    'legal_representative'     => $cert->legal_representative,
                ];
            });

            return HttpResponseMessages::getResponse([
                'message'      => $message,
                'total'        => $certificates->count(),
                'dataRecords'  => $certificates,
            ]);
        } catch (Exception $e) {
            return HttpResponseMessages::getResponse500(['message' => $e->getMessage()]);
        }
    }

    /**
     * @OA\Get(
     *     path="/notifications",
     *     tags={"Notificaciones"},
     *     summary="Listar notificaciones del usuario autenticado",
     *     description="Retorna las notificaciones persistidas en base de datos para el usuario actual.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="unread_only", in="query", description="Solo no leídas (true/false)", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="limit", in="query", description="Registros por página (default: 20)", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Lista paginada de notificaciones",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Notificaciones del usuario"),
     *             @OA\Property(property="unread_count", type="integer", example=3),
     *             @OA\Property(property="dataRecords", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/NotificationItem")),
     *                 @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user       = Auth::user();
            $unreadOnly = filter_var($request->input('unread_only', false), FILTER_VALIDATE_BOOLEAN);
            $limit      = (int) $request->input('limit', 20);

            $query = $user->notifications();

            if ($unreadOnly) {
                $query = $user->unreadNotifications();
            }

            $notifications = $query->paginate($limit);

            return HttpResponseMessages::getResponse([
                'message'      => 'Notificaciones del usuario',
                'unread_count' => $user->unreadNotifications()->count(),
                'dataRecords'  => $notifications,
            ]);
        } catch (Exception $e) {
            return HttpResponseMessages::getResponse500(['message' => $e->getMessage()]);
        }
    }

    /**
     * @OA\Post(
     *     path="/notifications/{id}/read",
     *     tags={"Notificaciones"},
     *     summary="Marcar notificación como leída",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Notificación marcada como leída", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse")),
     *     @OA\Response(response=404, description="Notificación no encontrada")
     * )
     */
    public function markAsRead(string $id): JsonResponse
    {
        try {
            $user         = Auth::user();
            $notification = $user->notifications()->where('id', $id)->first();

            if (!$notification) {
                return HttpResponseMessages::getResponse404(['message' => 'Notificación no encontrada']);
            }

            $notification->markAsRead();

            return HttpResponseMessages::getResponse(['message' => 'Notificación marcada como leída']);
        } catch (Exception $e) {
            return HttpResponseMessages::getResponse500(['message' => $e->getMessage()]);
        }
    }

    /**
     * @OA\Post(
     *     path="/notifications/read-all",
     *     tags={"Notificaciones"},
     *     summary="Marcar todas las notificaciones como leídas",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Todas las notificaciones marcadas como leídas", @OA\JsonContent(ref="#/components/schemas/ApiSuccessResponse"))
     * )
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            Auth::user()->unreadNotifications->markAsRead();

            return HttpResponseMessages::getResponse(['message' => 'Todas las notificaciones marcadas como leídas']);
        } catch (Exception $e) {
            return HttpResponseMessages::getResponse500(['message' => $e->getMessage()]);
        }
    }

    /**
     * @OA\Post(
     *     path="/admin/certificates/notify-now",
     *     tags={"Notificaciones"},
     *     summary="Disparar notificaciones de vencimiento manualmente (solo admin)",
     *     description="Despacha los jobs de notificación y reporte al administrador de forma inmediata.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="include_admin_report", type="boolean", description="Incluir reporte al admin (default: true)")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Jobs despachados correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Jobs de notificación despachados correctamente"),
     *             @OA\Property(property="include_admin_report", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(response=403, description="Sin permisos de administrador")
     * )
     */
    public function triggerNow(Request $request): JsonResponse
    {
        try {

            SendExpiringCertificatesNotificationsJob::dispatch();

            $includeAdminReport = filter_var(
                $request->input('include_admin_report', true),
                FILTER_VALIDATE_BOOLEAN
            );

            if ($includeAdminReport) {
                SendAdminExpiringCertificatesReportJob::dispatch(false);
            }

            return HttpResponseMessages::getResponse([
                'message'              => 'Jobs de notificación despachados correctamente',
                'include_admin_report' => $includeAdminReport,
            ]);
        } catch (Exception $e) {
            return HttpResponseMessages::getResponse500(['message' => $e->getMessage()]);
        }
    }

    /**
     * Determinar nivel de urgencia según días restantes.
     */
    private function getUrgencyLevel(int $daysRemaining): string
    {
        if ($daysRemaining <= 7) {
            return 'critical';
        } elseif ($daysRemaining <= 15) {
            return 'high';
        } elseif ($daysRemaining <= 30) {
            return 'medium';
        }

        return 'low';
    }
}
