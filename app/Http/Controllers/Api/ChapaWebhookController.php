<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingService;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ChapaWebhookController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected PaymentGatewayInterface $paymentGateway,
        protected BillingService $billingService
    ) {}

    /**
     * Handle incoming Chapa webhook notification.
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('x-chapa-signature', '');

        if (! $this->paymentGateway->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Chapa webhook signature verification failed.');
            return response()->json(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        $data = $request->all();
        $txRef = $data['tx_ref'] ?? $request->input('data.tx_ref');

        if (! $txRef) {
            return response()->json(['error' => 'Missing transaction reference'], Response::HTTP_BAD_REQUEST);
        }

        $payment = $this->processTransactionReference($txRef, $data);

        if (! $payment) {
            return response()->json(['error' => 'Invoice not found for reference'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['status' => 'success', 'payment_id' => $payment->id]);
    }

    /**
     * Handle redirect callback from Chapa after parent checkout.
     */
    public function handleCallback(Request $request): JsonResponse
    {
        $txRef = $request->query('tx_ref');

        if (! $txRef) {
            return ApiResponse::error('Missing transaction reference in callback.', 'MISSING_TX_REF', Response::HTTP_BAD_REQUEST);
        }

        $verification = $this->paymentGateway->verifyPayment($txRef);

        if (! $verification['success'] || $verification['status'] !== 'success') {
            return ApiResponse::error('Payment verification failed or payment was not completed.', 'PAYMENT_NOT_VERIFIED', Response::HTTP_PAYMENT_REQUIRED);
        }

        $payment = $this->processTransactionReference($txRef, $verification);

        if (! $payment) {
            return ApiResponse::error('Invoice could not be resolved from transaction reference.', 'INVOICE_NOT_FOUND', Response::HTTP_NOT_FOUND);
        }

        return $this->respondWithSuccess([
            'payment' => $payment->load('invoice.student.user'),
            'invoice' => $payment->invoice->fresh(),
            'message' => 'Payment successfully verified and recorded.',
        ], 'Payment completed successfully.');
    }

    /**
     * Resolve invoice from txRef and record/confirm payment.
     */
    protected function processTransactionReference(string $txRef, array $metadata = []): ?Payment
    {
        // Check if payment with this gateway reference was already created
        $existingPayment = Payment::withoutGlobalScopes()
            ->where('gateway_reference', $txRef)
            ->first();

        if ($existingPayment) {
            $existingPayment->update([
                'gateway_status' => 'success',
                'gateway_metadata' => $metadata,
            ]);
            $existingPayment->invoice->recalculateStatus();
            return $existingPayment;
        }

        // Parse invoice ID from tx_ref (e.g. BINA-INV-{invoice_id}-{timestamp}-...)
        if (! preg_match('/^BINA-INV-(\d+)-/', $txRef, $matches)) {
            return null;
        }

        $invoiceId = (int) $matches[1];
        $invoice = Invoice::withoutGlobalScopes()->find($invoiceId);

        if (! $invoice) {
            return null;
        }

        $amount = ! empty($metadata['amount']) ? (float) $metadata['amount'] : $invoice->balance();

        return $this->billingService->recordPayment(
            invoice: $invoice,
            data: [
                'amount' => $amount,
                'method' => 'online',
                'paid_at' => now(),
                'gateway' => 'chapa',
                'gateway_reference' => $txRef,
                'gateway_status' => 'success',
                'gateway_metadata' => $metadata,
                'notes' => 'Chapa online checkout payment',
            ]
        );
    }
}
