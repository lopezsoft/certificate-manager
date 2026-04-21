<?php

namespace App\Quotas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Quotas\Models\CertificateOrder;
use App\Quotas\Services\OrderService;
use App\Quotas\Services\PaymentOrchestrator;
use App\Payments\Services\WompiPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OrderController — Sprint 4
 * Gestiona la compra de certificados PREPAID vía WOMPI.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService        $orderService,
        private readonly PaymentOrchestrator $orchestrator,
        private readonly WompiPaymentService $wompi,
    ) {}

    /**
     * @OA\Get(
     *     path="/v2/orders",
     *     tags={"v2 - Órdenes"},
     *     summary="Listar órdenes de compra de la empresa",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Lista paginada de órdenes",
     *         @OA\JsonContent(@OA\Property(property="data", type="object"))
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $orders = CertificateOrder::where('company_id', $request->user()->company_id)
            ->with('latestTransaction')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json(['data' => $orders]);
    }

    /**
     * @OA\Post(
     *     path="/v2/orders",
     *     tags={"v2 - Órdenes"},
     *     summary="Crear orden de compra de certificados",
     *     description="Crea una orden PENDING y devuelve los datos necesarios para el widget de WOMPI.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"quantity","vigencia"},
     *         @OA\Property(property="quantity", type="integer", minimum=1, example=5, description="Cantidad de certificados"),
     *         @OA\Property(property="vigencia", type="integer", enum={1,2}, example=1, description="Vigencia en años")
     *     )),
     *     @OA\Response(response=201, description="Orden creada",
     *         @OA\JsonContent(@OA\Property(property="data", type="object",
     *             @OA\Property(property="order_id", type="integer", example=1),
     *             @OA\Property(property="total_amount", type="integer", example=743750),
     *             @OA\Property(property="total_in_cents", type="integer", example=74375000),
     *             @OA\Property(property="wompi_reference", type="string", example="CERT-MGR-1745123456-ABCD"),
     *             @OA\Property(property="wompi_public_key", type="string", example="pub_test_XXXX"),
     *             @OA\Property(property="acceptance_token", type="string"),
     *             @OA\Property(property="acceptance_url", type="string", format="uri"),
     *             @OA\Property(property="integrity_hash", type="string", description="SHA-256 para integridad del widget")
     *         ))
     *     ),
     *     @OA\Response(response=422, description="Parámetros inválidos")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'vigencia' => ['required', 'integer', 'in:1,2'],
        ]);

        $user  = $request->user();
        $order = $this->orderService->createOrder(
            companyId: $user->company_id,
            userId:    $user->id,
            quantity:  $data['quantity'],
            vigencia:  $data['vigencia'],
        );

        $acceptanceDto = $this->wompi->getAcceptanceToken();

        return response()->json([
            'data' => [
                'order_id'         => $order->id,
                'total_amount'     => $order->total_amount,
                'total_in_cents'   => $order->getTotalInCents(),
                'wompi_reference'  => $order->wompi_reference,
                'wompi_public_key' => config('wompi.public_key'),
                'acceptance_token' => $acceptanceDto->token,
                'acceptance_url'   => $acceptanceDto->permalink,
                'integrity_hash'   => $this->wompi->generateIntegrityHash(
                    $order->wompi_reference,
                    $order->getTotalInCents(),
                    $order->currency,
                ),
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/v2/orders/{id}",
     *     tags={"v2 - Órdenes"},
     *     summary="Ver detalle de una orden",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\Response(response=200, description="Detalle de la orden",
     *         @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/CertificateOrder"))
     *     ),
     *     @OA\Response(response=404, description="Orden no encontrada")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $order = CertificateOrder::where('company_id', request()->user()->company_id)
            ->with(['items', 'latestTransaction'])
            ->findOrFail($id);

        return response()->json(['data' => $order]);
    }

    /**
     * @OA\Post(
     *     path="/v2/orders/{id}/pay",
     *     tags={"v2 - Órdenes"},
     *     summary="Ejecutar pago de una orden vía WOMPI",
     *     description="Crea la transacción en WOMPI. El estado final llega asíncronamente por webhook.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"payment_source_id","acceptance_token","payment_method"},
     *         @OA\Property(property="payment_source_id", type="string", example="tok_test_XXXX", description="Token de tarjeta tokenizada en WOMPI"),
     *         @OA\Property(property="acceptance_token", type="string", description="Token de aceptación de T&C de WOMPI"),
     *         @OA\Property(property="payment_method", type="string", enum={"CARD","NEQUI","PSE","BANCOLOMBIA_TRANSFER"}, example="CARD"),
     *         @OA\Property(property="installments", type="integer", nullable=true, minimum=1, maximum=36, example=1, description="Cuotas (solo tarjeta)")
     *     )),
     *     @OA\Response(response=200, description="Transacción iniciada",
     *         @OA\JsonContent(@OA\Property(property="data", type="object",
     *             @OA\Property(property="transaction_id", type="string", example="12345-abcd"),
     *             @OA\Property(property="transaction_status", type="string", enum={"PENDING","APPROVED","DECLINED","ERROR"}, example="PENDING"),
     *             @OA\Property(property="order_status", type="string", example="PENDING")
     *         ))
     *     ),
     *     @OA\Response(response=404, description="Orden no encontrada o no está PENDING"),
     *     @OA\Response(response=502, description="Error al comunicarse con WOMPI")
     * )
     */
    public function pay(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'payment_source_id' => ['required', 'string'],
            'acceptance_token'  => ['required', 'string'],
            'payment_method'    => ['required', 'string', 'in:CARD,NEQUI,PSE,BANCOLOMBIA_TRANSFER'],
            'installments'      => ['nullable', 'integer', 'min:1', 'max:36'],
        ]);

        $order = CertificateOrder::where('company_id', $request->user()->company_id)
            ->where('status', 'PENDING')
            ->findOrFail($id);

        try {
            $transaction = $this->orchestrator->initiatePayment(
                order:           $order,
                paymentSourceId: $data['payment_source_id'],
                acceptanceToken: $data['acceptance_token'],
                paymentMethod:   $data['payment_method'],
                installments:    $data['installments'] ?? 1,
            );

            return response()->json([
                'data' => [
                    'transaction_id'     => $transaction->wompi_transaction_id,
                    'transaction_status' => $transaction->status,
                    'order_status'       => $order->fresh()->status,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }
}
