<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\ReceiptService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PaymentReceiptController extends Controller
{
    /**
     * Download an official PDF receipt for a payment.
     * Acceptance criterion:
     * A parent can pay an invoice online and see it move to "paid" with a downloadable receipt.
     */
    public function downloadReceipt(Payment $payment, ReceiptService $receiptService): Response
    {
        Gate::authorize('viewReceipt', $payment);

        $pdf = $receiptService->generatePdf($payment);

        return $pdf->download("Receipt-{$payment->payment_number}.pdf");
    }

    /**
     * Stream or preview the PDF receipt in the browser.
     */
    public function streamReceipt(Payment $payment, ReceiptService $receiptService): Response
    {
        Gate::authorize('viewReceipt', $payment);

        $pdf = $receiptService->generatePdf($payment);

        return $pdf->stream("Receipt-{$payment->payment_number}.pdf");
    }
}
