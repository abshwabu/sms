<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InitiateOnlinePaymentRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ParentBillingController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected PaymentGatewayInterface $paymentGateway
    ) {}

    /**
     * Parent view: Outstanding and past invoices for a linked child.
     */
    public function invoices(Request $request, Student $student): JsonResponse
    {
        Gate::authorize('view', $student);

        $query = Invoice::where('student_id', $student->id)
            ->with(['items.feeStructure', 'term.academicYear', 'payments'])
            ->orderBy('due_date', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $invoices = $query->get();

        $totalBilled = (float) $invoices->sum('total_amount');
        $totalPaid = (float) $invoices->sum('paid_amount');
        $totalOutstanding = (float) max(0, $totalBilled - $totalPaid);

        return $this->respondWithSuccess([
            'student' => [
                'id' => $student->id,
                'name' => $student->user?->name,
                'admission_number' => $student->admission_number,
                'grade_level' => $student->currentSection?->gradeLevel?->name,
                'section' => $student->currentSection?->name,
            ],
            'summary' => [
                'total_invoices' => $invoices->count(),
                'total_billed' => round($totalBilled, 2),
                'total_paid' => round($totalPaid, 2),
                'total_outstanding' => round($totalOutstanding, 2),
                'unpaid_count' => $invoices->whereIn('status', ['unpaid', 'partial', 'overdue'])->count(),
            ],
            'invoices' => $invoices,
        ], 'Child invoices retrieved successfully.');
    }

    /**
     * Parent view: Payment history for a linked child.
     */
    public function payments(Request $request, Student $student): JsonResponse
    {
        Gate::authorize('view', $student);

        $payments = Payment::whereHas('invoice', function ($q) use ($student) {
                $q->where('student_id', $student->id);
            })
            ->with(['invoice.term', 'recordedByUser'])
            ->orderBy('paid_at', 'desc')
            ->get();

        return $this->respondWithSuccess([
            'student' => [
                'id' => $student->id,
                'name' => $student->user?->name,
                'admission_number' => $student->admission_number,
            ],
            'total_payments' => $payments->count(),
            'total_amount_paid' => round((float) $payments->sum('amount'), 2),
            'payments' => $payments,
        ], 'Payment history retrieved successfully.');
    }

    /**
     * Parent view: Initiate online payment via payment gateway (Chapa).
     * Acceptance criterion:
     * A parent can pay an invoice online and see it move to "paid" with a downloadable receipt.
     */
    public function payOnline(InitiateOnlinePaymentRequest $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('payOnline', $invoice);

        if ($invoice->isPaid()) {
            return ApiResponse::error(
                'This invoice is already fully paid.',
                'INVOICE_ALREADY_PAID',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $payer = $request->user();
        $options = array_filter([
            'amount' => $request->input('amount'),
            'phone_number' => $request->input('phone_number'),
            'return_url' => $request->input('return_url'),
        ]);

        $session = $this->paymentGateway->initializePayment($invoice, $payer, $options);

        if (! $session['success']) {
            return ApiResponse::error(
                $session['message'] ?? 'Failed to initialize online payment.',
                'PAYMENT_INITIALIZATION_FAILED',
                Response::HTTP_BAD_GATEWAY
            );
        }

        return $this->respondWithSuccess([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'amount' => $session['amount'],
            'currency' => $session['currency'],
            'tx_ref' => $session['tx_ref'],
            'checkout_url' => $session['checkout_url'],
            'message' => $session['message'],
        ], 'Payment initialized successfully. Redirect parent to checkout.');
    }
}
