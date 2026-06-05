<?php

declare(strict_types=1);

namespace App\Quotas\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Payments\Contracts\PaymentGatewayContract;
use App\Quotas\Models\CertificateOrder;
use App\Quotas\Services\OrderService;
use App\Quotas\Services\PaymentOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OrderController — Sprint 5
 *
 * Gestiona la compra de certificados PREPAID.
 * Agnóstico de pasarela: usa PaymentGatewayContract.
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService           $orderService,
        private readonly PaymentOrchestrator    $orchestrator,
        private readonly PaymentGatewayContract $gateway,
    ) {}

    /**
     * @OA\Get(
     *     path="/orders",
     *     tags={"Órdenes"},
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
     *     path="/orders",
     *     tags={"Órdenes"},
     *     summary="Crear orden de compra de certificados",
     *     description="Crea una orden PENDING y devuelve los datos necesarios para el widget de pago.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"quantity","vigencia"},
     *         @OA\Property(property="quantity", type="integer", minimum=1, example=5, description="Cantidad de certificados"),
     *         @OA\Property(property="vigencia", type="integer", enum={1,2}, example=1, description="Vigencia en años")
     *     )),
     *     @OA\Response(response=201, description="Orden creada",
     *         @OA\JsonContent(@OA\Property(property="data", type="object",
     *             @OA\Property(property="order_id", type="integer", example=1),
     *             @OA\Property(property="total_amount", type="number", format="float", example=743750.00),
     *             @OA\Property(property="provider_reference", type="string", example="ORD-ABCD1234EFGH"),
     *             @OA\Property(property="payment_provider", type="string", example="WOMPI"),
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
            companyId:  $user->company_id,
            userId:     $user->id,
            quantity:   $data['quantity'],
            vigencia:   $data['vigencia'],
            userTypeId: (int) $user->type_id,
        );

        $acceptanceDto = $this->gateway->getAcceptanceToken();
        $totalInCents  = (int) round((float) $order->total_amount * 100);

        return response()->json([
            'data' => [
                'order_id'           => $order->id,
                'total_amount'       => $order->total_amount,
                'provider_reference' => $order->provider_reference,
                'payment_provider'   => $order->payment_provider,
                'acceptance_token'   => $acceptanceDto->token,
                'acceptance_url'     => $acceptanceDto->permalink,
                'integrity_hash'     => $this->gateway->generateIntegrityHash(
                    $order->provider_reference,
                    $totalInCents,
                    $order->currency,
                ),
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/orders/{id}",
     *     tags={"Órdenes"},
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
     *     path="/orders/{id}/pay",
     *     tags={"Órdenes"},
     *     summary="Ejecutar pago de una orden",
     *     description="Crea la transacción de pago. El estado final llega asíncronamente por webhook.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer"), example=1),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"payment_source_id","acceptance_token","payment_method"},
     *         @OA\Property(property="payment_source_id", type="string", example="tok_test_XXXX"),
     *         @OA\Property(property="acceptance_token", type="string"),
     *         @OA\Property(property="payment_method", type="string", enum={"CARD","NEQUI","PSE","BANCOLOMBIA_TRANSFER"}, example="CARD"),
     *         @OA\Property(property="installments", type="integer", nullable=true, minimum=1, maximum=36, example=1)
     *     )),
     *     @OA\Response(response=200, description="Transacción iniciada"),
     *     @OA\Response(response=404, description="Orden no encontrada o no está PENDING"),
     *     @OA\Response(response=502, description="Error al comunicarse con la pasarela")
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
                    'transaction_id'     => $transaction->provider_transaction_id,
                    'transaction_status' => $transaction->status,
                    'order_status'       => $order->fresh()->status,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }
}
