<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\GradeLevel;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    /**
     * Create or update a fee structure item.
     */
    public function createFeeStructure(School $school, array $data): FeeStructure
    {
        return FeeStructure::create(array_merge($data, [
            'school_id' => $school->id,
        ]));
    }

    /**
     * Bulk generate invoices for a grade level or section in a given term.
     * Acceptance criterion:
     * Admin defines a term's fee structure once and bulk-generates invoices for an entire grade level,
     * correctly including/excluding conditional items like transport based on each student's actual enrollment.
     */
    public function bulkGenerateInvoices(
        School $school,
        AcademicYear $academicYear,
        Term $term,
        ?GradeLevel $gradeLevel = null,
        ?Section $section = null,
        ?Carbon $dueDate = null,
        bool $overrideExisting = false
    ): array {
        $dueDate = $dueDate ?: Carbon::now()->addDays(30);

        // Fetch students in scope
        $query = Student::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('status', 'active');

        if ($section) {
            $query->where('current_section_id', $section->id);
        } elseif ($gradeLevel) {
            $query->whereHas('currentSection', function (Builder $q) use ($gradeLevel) {
                $q->where('grade_level_id', $gradeLevel->id);
            });
        }

        $students = $query->with(['currentSection.gradeLevel', 'user'])->get();

        // Fetch fee structures for this academic year and term
        $feeStructuresQuery = FeeStructure::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where('academic_year_id', $academicYear->id)
            ->where('term_id', $term->id);

        if ($gradeLevel) {
            $feeStructuresQuery->where(function (Builder $q) use ($gradeLevel) {
                $q->where('grade_level_id', $gradeLevel->id)
                  ->orWhereNull('grade_level_id');
            });
        }

        $feeStructures = $feeStructuresQuery->get();

        $generatedCount = 0;
        $skippedCount = 0;
        $totalBilled = 0.0;
        $createdInvoices = [];

        DB::transaction(function () use (
            $students,
            $feeStructures,
            $school,
            $academicYear,
            $term,
            $dueDate,
            $overrideExisting,
            &$generatedCount,
            &$skippedCount,
            &$totalBilled,
            &$createdInvoices
        ) {
            foreach ($students as $student) {
                // Check existing invoice for this student, term, and year
                $existingInvoice = Invoice::withoutGlobalScopes()
                    ->where('school_id', $school->id)
                    ->where('student_id', $student->id)
                    ->where('academic_year_id', $academicYear->id)
                    ->where('term_id', $term->id)
                    ->first();

                if ($existingInvoice && ! $overrideExisting) {
                    $skippedCount++;
                    continue;
                }

                // Determine applicable line items for this specific student
                $applicableFees = $feeStructures->filter(function (FeeStructure $fee) use ($student, $academicYear) {
                    return $fee->appliesToStudent($student, $academicYear);
                });

                if ($applicableFees->isEmpty()) {
                    $skippedCount++;
                    continue;
                }

                $invoiceTotal = (float) $applicableFees->sum('amount');

                if ($existingInvoice && $overrideExisting) {
                    // Update existing invoice
                    $existingInvoice->items()->delete();
                    $existingInvoice->update([
                        'total_amount' => $invoiceTotal,
                        'due_date' => $dueDate->toDateString(),
                    ]);
                    $invoice = $existingInvoice;
                } else {
                    // Create new invoice
                    $invoiceNumber = $this->generateInvoiceNumber($school, $academicYear, $student);

                    $invoice = Invoice::create([
                        'school_id' => $school->id,
                        'student_id' => $student->id,
                        'academic_year_id' => $academicYear->id,
                        'term_id' => $term->id,
                        'invoice_number' => $invoiceNumber,
                        'total_amount' => $invoiceTotal,
                        'paid_amount' => 0.00,
                        'due_date' => $dueDate->toDateString(),
                        'status' => 'unpaid',
                        'notes' => "Invoice for {$term->name} - {$academicYear->name}",
                    ]);
                }

                // Create line items
                foreach ($applicableFees as $fee) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'fee_structure_id' => $fee->id,
                        'name' => $fee->name,
                        'amount' => $fee->amount,
                        'description' => $fee->description,
                    ]);
                }

                $invoice->recalculateStatus();

                $generatedCount++;
                $totalBilled += $invoiceTotal;
                $createdInvoices[] = $invoice->load(['items', 'student.user']);
            }
        });

        return [
            'generated_count' => $generatedCount,
            'skipped_count' => $skippedCount,
            'total_billed' => round($totalBilled, 2),
            'invoices' => $createdInvoices,
        ];
    }

    /**
     * Record a payment against an invoice.
     */
    public function recordPayment(
        Invoice $invoice,
        array $data,
        ?User $recordedBy = null
    ): Payment {
        $amount = (float) ($data['amount'] ?? $invoice->balance());

        $paymentNumber = $this->generatePaymentNumber($invoice->school_id);

        $payment = Payment::create([
            'school_id' => $invoice->school_id,
            'invoice_id' => $invoice->id,
            'payment_number' => $paymentNumber,
            'amount' => $amount,
            'method' => $data['method'] ?? 'cash',
            'paid_at' => $data['paid_at'] ?? now(),
            'recorded_by' => $recordedBy?->id,
            'gateway' => $data['gateway'] ?? null,
            'gateway_reference' => $data['gateway_reference'] ?? null,
            'gateway_status' => $data['gateway_status'] ?? 'success',
            'gateway_metadata' => $data['gateway_metadata'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $invoice->recalculateStatus();

        return $payment;
    }

    /**
     * Generate collections dashboard metrics and breakdowns.
     * Acceptance criterion:
     * Collections dashboard correctly totals paid vs. outstanding across a grade or the whole school.
     */
    public function getCollectionsDashboard(School $school, array $filters = []): array
    {
        $query = Invoice::withoutGlobalScopes()
            ->where('school_id', $school->id);

        if (! empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (! empty($filters['term_id'])) {
            $query->where('term_id', $filters['term_id']);
        }

        if (! empty($filters['grade_level_id'])) {
            $query->whereHas('student.currentSection', function (Builder $q) use ($filters) {
                $q->where('grade_level_id', $filters['grade_level_id']);
            });
        }

        if (! empty($filters['section_id'])) {
            $query->whereHas('student', function (Builder $q) use ($filters) {
                $q->where('current_section_id', $filters['section_id']);
            });
        }

        $invoices = $query->with(['student.currentSection.gradeLevel', 'student.user', 'term'])->get();

        $totalBilled = (float) $invoices->sum('total_amount');
        $totalCollected = (float) $invoices->sum('paid_amount');
        $totalOutstanding = (float) max(0, $totalBilled - $totalCollected);
        $collectionRate = $totalBilled > 0 ? round(($totalCollected / $totalBilled) * 100, 1) : 0.0;

        $statusCounts = [
            'total' => $invoices->count(),
            'paid' => $invoices->where('status', 'paid')->count(),
            'partial' => $invoices->where('status', 'partial')->count(),
            'unpaid' => $invoices->where('status', 'unpaid')->count(),
            'overdue' => $invoices->where('status', 'overdue')->count(),
        ];

        // Breakdown by Grade Level
        $gradeBreakdown = $this->aggregateByGradeLevel($school, $invoices);

        // Breakdown by Section
        $sectionBreakdown = $this->aggregateBySection($school, $invoices);

        // Recent Payments
        $recentPayments = Payment::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->where(function (Builder $q) {
                $q->whereNull('gateway_status')->orWhere('gateway_status', 'success');
            })
            ->with(['invoice.student.user', 'invoice.student.currentSection'])
            ->orderBy('paid_at', 'desc')
            ->limit(10)
            ->get();

        return [
            'summary' => [
                'total_billed' => round($totalBilled, 2),
                'total_collected' => round($totalCollected, 2),
                'total_outstanding' => round($totalOutstanding, 2),
                'collection_rate' => $collectionRate,
                'invoices_count' => $statusCounts,
            ],
            'by_grade' => $gradeBreakdown,
            'by_section' => $sectionBreakdown,
            'recent_payments' => $recentPayments,
        ];
    }

    /**
     * Aggregate invoices by grade level.
     */
    protected function aggregateByGradeLevel(School $school, Collection $invoices): array
    {
        $gradeLevels = GradeLevel::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->orderBy('sequence')
            ->get();

        $result = [];

        foreach ($gradeLevels as $grade) {
            $gradeInvoices = $invoices->filter(function (Invoice $inv) use ($grade) {
                return (int) $inv->student?->currentSection?->grade_level_id === (int) $grade->id;
            });

            $billed = (float) $gradeInvoices->sum('total_amount');
            $collected = (float) $gradeInvoices->sum('paid_amount');
            $outstanding = (float) max(0, $billed - $collected);
            $rate = $billed > 0 ? round(($collected / $billed) * 100, 1) : 0.0;

            $result[] = [
                'grade_level_id' => $grade->id,
                'name' => $grade->name,
                'code' => $grade->code,
                'invoices_count' => $gradeInvoices->count(),
                'total_billed' => round($billed, 2),
                'total_collected' => round($collected, 2),
                'total_outstanding' => round($outstanding, 2),
                'collection_rate' => $rate,
            ];
        }

        return $result;
    }

    /**
     * Aggregate invoices by section.
     */
    protected function aggregateBySection(School $school, Collection $invoices): array
    {
        $sections = Section::withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->with('gradeLevel')
            ->get();

        $result = [];

        foreach ($sections as $section) {
            $secInvoices = $invoices->filter(function (Invoice $inv) use ($section) {
                return (int) $inv->student?->current_section_id === (int) $section->id;
            });

            $billed = (float) $secInvoices->sum('total_amount');
            $collected = (float) $secInvoices->sum('paid_amount');
            $outstanding = (float) max(0, $billed - $collected);
            $rate = $billed > 0 ? round(($collected / $billed) * 100, 1) : 0.0;

            $result[] = [
                'section_id' => $section->id,
                'name' => $section->name,
                'grade_level' => $section->gradeLevel?->name,
                'invoices_count' => $secInvoices->count(),
                'total_billed' => round($billed, 2),
                'total_collected' => round($collected, 2),
                'total_outstanding' => round($outstanding, 2),
                'collection_rate' => $rate,
            ];
        }

        return $result;
    }

    /**
     * Generate a unique sequential invoice number.
     */
    protected function generateInvoiceNumber(School $school, AcademicYear $year, Student $student): string
    {
        $yearSlug = preg_replace('/[^0-9]/', '', $year->name) ?: date('Y');
        $random = strtoupper(Str::random(4));
        return sprintf('INV-%s-%s-%s', $yearSlug, $student->id, $random);
    }

    /**
     * Generate a unique payment receipt number.
     */
    protected function generatePaymentNumber(int $schoolId): string
    {
        $date = date('Ymd');
        $random = strtoupper(Str::random(5));
        return sprintf('RCP-%s-%s', $date, $random);
    }
}
