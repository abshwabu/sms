<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateInvoicesRequest;
use App\Http\Requests\RecordPaymentRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Invoice;
use App\Models\Section;
use App\Models\Term;
use App\Services\BillingService;
use App\Tenancy\TenantManager;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected BillingService $billingService,
        protected TenantManager $tenantManager
    ) {}

    /**
     * Admin view: List all invoices with filters.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        $query = Invoice::query()
            ->with(['student.user', 'student.currentSection.gradeLevel', 'term', 'items']);

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->query('academic_year_id'));
        }

        if ($request->filled('term_id')) {
            $query->where('term_id', $request->query('term_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('grade_level_id')) {
            $query->whereHas('student.currentSection', function (Builder $q) use ($request) {
                $q->where('grade_level_id', $request->query('grade_level_id'));
            });
        }

        if ($request->filled('section_id')) {
            $query->whereHas('student', function (Builder $q) use ($request) {
                $q->where('current_section_id', $request->query('section_id'));
            });
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function (Builder $q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('student', function (Builder $sq) use ($search) {
                      $sq->where('admission_number', 'like', "%{$search}%")
                         ->orWhereHas('user', function (Builder $uq) use ($search) {
                             $uq->where('name', 'like', "%{$search}%");
                         });
                  });
            });
        }

        $perPage = (int) $request->query('per_page', 25);
        $invoices = $query->orderBy('due_date', 'asc')->paginate($perPage);

        return $this->respondWithSuccess($invoices, 'Invoices retrieved successfully.');
    }

    /**
     * Show an individual invoice with full items and payment history.
     */
    public function show(Invoice $invoice): JsonResponse
    {
        Gate::authorize('view', $invoice);

        $invoice->load([
            'items.feeStructure',
            'payments.recordedByUser',
            'student.user',
            'student.currentSection.gradeLevel',
            'term',
            'academicYear',
        ]);

        return $this->respondWithSuccess($invoice, 'Invoice retrieved successfully.');
    }

    /**
     * Admin view: Bulk-generate invoices for a grade level, section, or term.
     * Acceptance criterion:
     * Admin defines a term's fee structure once and bulk-generates invoices for an entire grade level,
     * correctly including/excluding conditional items like transport based on each student's actual enrollment.
     */
    public function bulkGenerate(GenerateInvoicesRequest $request): JsonResponse
    {
        Gate::authorize('bulkGenerate', Invoice::class);

        $school = $this->tenantManager->getTenant();
        $academicYear = AcademicYear::findOrFail($request->input('academic_year_id'));
        $term = Term::findOrFail($request->input('term_id'));

        $gradeLevel = $request->filled('grade_level_id')
            ? GradeLevel::findOrFail($request->input('grade_level_id'))
            : null;

        $section = $request->filled('section_id')
            ? Section::findOrFail($request->input('section_id'))
            : null;

        $dueDate = $request->filled('due_date')
            ? Carbon::parse($request->input('due_date'))
            : null;

        $overrideExisting = $request->boolean('override_existing', false);

        $result = $this->billingService->bulkGenerateInvoices(
            school: $school,
            academicYear: $academicYear,
            term: $term,
            gradeLevel: $gradeLevel,
            section: $section,
            dueDate: $dueDate,
            overrideExisting: $overrideExisting
        );

        return $this->respondWithSuccess(
            $result,
            "Bulk invoice generation completed. Generated: {$result['generated_count']}, Skipped: {$result['skipped_count']}.",
            Response::HTTP_CREATED
        );
    }

    /**
     * Admin view: Manually record a cash or bank transfer payment against an invoice.
     */
    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('recordPayment', $invoice);

        if ($invoice->isPaid()) {
            return ApiResponse::error(
                'This invoice is already fully paid.',
                'INVOICE_ALREADY_PAID',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $payment = $this->billingService->recordPayment(
            invoice: $invoice,
            data: $request->validated(),
            recordedBy: $request->user()
        );

        return $this->respondWithSuccess([
            'payment' => $payment->load('recordedByUser'),
            'invoice' => $invoice->fresh(['items', 'payments']),
        ], 'Payment recorded successfully.', Response::HTTP_CREATED);
    }

    /**
     * Admin view: Collections dashboard.
     * Acceptance criterion:
     * Collections dashboard correctly totals paid vs. outstanding across a grade or the whole school.
     */
    public function collections(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        $school = $this->tenantManager->getTenant();
        $data = $this->billingService->getCollectionsDashboard($school, $request->all());

        return $this->respondWithSuccess($data, 'Collections dashboard metrics retrieved successfully.');
    }
}
