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

    public function index(Request $request): JsonResponse
    {
        $orders = CertificateOrder::where('company_id', $request->user()->company_id)
            ->with('latestTransaction')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json(['data' => $orders]);
    }

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

    public function show(int $id): JsonResponse
    {
        $order = CertificateOrder::where('company_id', request()->user()->company_id)
            ->with(['items', 'latestTransaction'])
            ->findOrFail($id);

        return response()->json(['data' => $order]);
    }

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
