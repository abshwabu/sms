<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\GradeLevel;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\Term;
use App\Models\User;
use App\Services\Payments\ChapaPaymentGateway;
use App\Services\Payments\PaymentGatewayInterface;
use App\Tenancy\TenantManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeAndBillingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $oakridge;
    protected School $maplewood;

    protected User $greenwoodAdmin;
    protected User $oakridgeAdmin;
    protected User $ednaTeacher;
    protected User $homerParent;
    protected User $sarahParent;
    protected User $bartUser;

    protected Student $bartStudent;
    protected Student $lisaStudent;
    protected Student $milhouseStudent;
    protected Student $tommyStudent;

    protected AcademicYear $year2025;
    protected Term $fallTerm;
    protected GradeLevel $grade10;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $this->oakridge = School::where('subdomain', 'oakridge')->firstOrFail();
        $this->maplewood = School::where('subdomain', 'maplewood')->firstOrFail();

        $this->greenwoodAdmin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $this->oakridgeAdmin = User::where('email', 'admin@oakridge.edu')->firstOrFail();
        $this->ednaTeacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $this->homerParent = User::where('email', 'parent@greenwood.edu')->firstOrFail();
        $this->sarahParent = User::where('email', 'parent@maplewood.edu')->firstOrFail();
        $this->bartUser = User::where('email', 'student@greenwood.edu')->firstOrFail();

        app(TenantManager::class)->bypass(function () {
            $this->bartStudent = Student::where('school_id', $this->greenwood->id)->where('admission_number', 'GRE-25-00101')->firstOrFail();
            $this->lisaStudent = Student::where('school_id', $this->greenwood->id)->where('admission_number', 'GRE-25-00102')->firstOrFail();
            $this->milhouseStudent = Student::where('school_id', $this->greenwood->id)->where('admission_number', 'GRE-25-00103')->firstOrFail();
            $this->tommyStudent = Student::where('school_id', $this->maplewood->id)->where('admission_number', 'MAP-25-00101')->firstOrFail();

            $this->year2025 = AcademicYear::where('school_id', $this->greenwood->id)->where('name', '2025/2026')->firstOrFail();
            $this->fallTerm = Term::where('school_id', $this->greenwood->id)->where('academic_year_id', $this->year2025->id)->where('name', 'Fall Semester')->firstOrFail();
            $this->grade10 = GradeLevel::where('school_id', $this->greenwood->id)->where('code', 'G10')->firstOrFail();
        });
    }

    protected function actingAsTenant(User $user, School $school): static
    {
        $this->app['auth']->forgetGuards();
        app(TenantManager::class)->setTenant($school);
        $token = $user->createToken('test-billing-token')->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-School-Id', (string) $school->id);
    }

    /**
     * Acceptance Criterion 1:
     * Admin defines a term's fee structure once and bulk-generates invoices for an entire grade level,
     * correctly including/excluding conditional items like transport based on each student's actual enrollment.
     */
    public function test_admin_can_define_fee_structures_and_bulk_generate_invoices_with_conditional_transport(): void
    {
        // 1. Admin configures a new optional/conditional lab fee for Grade 10
        $response = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/billing/fee-structures', [
                'academic_year_id' => $this->year2025->id,
                'term_id' => $this->fallTerm->id,
                'grade_level_id' => $this->grade10->id,
                'name' => 'Grade 10 Science Lab Kit',
                'category' => 'activity',
                'amount' => 1200.00,
                'is_mandatory' => true,
                'condition_type' => 'none',
                'description' => 'Dissection and chemistry laboratory supply fee.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Grade 10 Science Lab Kit')
            ->assertJsonPath('data.amount', '1200.00');

        // 2. Admin bulk-generates invoices for Grade 10 with overrideExisting = true
        $bulkResponse = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson('/api/billing/invoices/bulk-generate', [
                'academic_year_id' => $this->year2025->id,
                'term_id' => $this->fallTerm->id,
                'grade_level_id' => $this->grade10->id,
                'due_date' => '2025-11-15',
                'override_existing' => true,
            ]);

        $bulkResponse->assertCreated()
            ->assertJsonPath('success', true);

        // Verify Bart (enrolled in transport) vs Milhouse (not enrolled in transport)
        app(TenantManager::class)->bypass(function () {
            $bartInvoice = Invoice::where('student_id', $this->bartStudent->id)->firstOrFail();
            $milhouseInvoice = Invoice::where('student_id', $this->milhouseStudent->id)->firstOrFail();

            $bartItemNames = $bartInvoice->items->pluck('name')->all();
            $milhouseItemNames = $milhouseInvoice->items->pluck('name')->all();

            // Both have Tuition, Facility, and the new Lab Kit
            $this->assertContains('Grade 10 Tuition Fee', $bartItemNames);
            $this->assertContains('Grade 10 Tuition Fee', $milhouseItemNames);
            $this->assertContains('Grade 10 Science Lab Kit', $bartItemNames);
            $this->assertContains('Grade 10 Science Lab Kit', $milhouseItemNames);

            // Bart has Transport Fee (because Bart is enrolled in Route 101)
            $this->assertContains('School Bus Transport Fee', $bartItemNames);

            // Milhouse DOES NOT have Transport Fee (not enrolled in any route)
            $this->assertNotContains('School Bus Transport Fee', $milhouseItemNames);

            // Bart total = 16,500 + 2,500 + 4,000 + 1,200 = 24,200
            $this->assertEquals(24200.00, (float) $bartInvoice->total_amount);

            // Milhouse total = 16,500 + 2,500 + 1,200 = 20,200
            $this->assertEquals(20200.00, (float) $milhouseInvoice->total_amount);
        });
    }

    /**
     * Acceptance Criterion 2:
     * A parent can pay an invoice online and see it move to "paid" with a downloadable receipt.
     */
    public function test_parent_can_pay_invoice_online_and_download_receipt(): void
    {
        // Get an unpaid or partially paid invoice for Bart
        $invoice = app(TenantManager::class)->bypass(fn () => 
            Invoice::where('student_id', $this->bartStudent->id)->firstOrFail()
        );

        // 1. Homer initiates online payment via Chapa gateway
        $payResponse = $this->actingAsTenant($this->homerParent, $this->greenwood)
            ->postJson("/api/parent/invoices/{$invoice->id}/pay-online", [
                'phone_number' => '+251911223344',
            ]);

        $payResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['checkout_url', 'tx_ref', 'amount', 'currency'],
            ]);

        $txRef = $payResponse->json('data.tx_ref');
        $this->assertStringStartsWith("BINA-INV-{$invoice->id}-", $txRef);

        // 2. Chapa webhook / callback verifies payment completion
        $webhookResponse = $this->postJson('/api/webhooks/chapa', [
            'tx_ref' => $txRef,
            'amount' => $invoice->balance(),
            'status' => 'success',
        ], [
            'x-chapa-signature' => 'simulated_test_signature',
        ]);

        $webhookResponse->assertOk()
            ->assertJsonPath('status', 'success');

        // Verify invoice is now fully PAID
        $updatedInvoice = app(TenantManager::class)->bypass(fn () => $invoice->fresh(['payments']));
        $this->assertEquals('paid', $updatedInvoice->status);
        $this->assertEquals(0.00, $updatedInvoice->balance());

        $payment = $updatedInvoice->payments->first();
        $this->assertNotNull($payment);
        $this->assertEquals('chapa', $payment->gateway);
        $this->assertEquals($txRef, $payment->gateway_reference);

        // 3. Homer downloads official PDF receipt
        $receiptResponse = $this->actingAsTenant($this->homerParent, $this->greenwood)
            ->get("/api/payments/{$payment->id}/receipt");

        $receiptResponse->assertOk();
        $this->assertEquals('application/pdf', $receiptResponse->headers->get('content-type'));
        $this->assertStringContainsString("Receipt-{$payment->payment_number}.pdf", $receiptResponse->headers->get('content-disposition'));
    }

    /**
     * Acceptance Criterion 3:
     * Collections dashboard correctly totals paid vs. outstanding across a grade or the whole school.
     */
    public function test_collections_dashboard_correctly_totals_paid_vs_outstanding(): void
    {
        $response = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->getJson('/api/billing/collections');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_billed',
                        'total_collected',
                        'total_outstanding',
                        'collection_rate',
                        'invoices_count' => ['total', 'paid', 'partial', 'unpaid', 'overdue'],
                    ],
                    'by_grade',
                    'by_section',
                    'recent_payments',
                ],
            ]);

        $summary = $response->json('data.summary');

        // Billed = Collected + Outstanding
        $this->assertEquals(
            round($summary['total_billed'], 2),
            round($summary['total_collected'] + $summary['total_outstanding'], 2)
        );

        // Verification of Grade 10 aggregate breakdown
        $gradeBreakdown = collect($response->json('data.by_grade'));
        $g10Data = $gradeBreakdown->firstWhere('code', 'G10');

        $this->assertNotNull($g10Data);
        $this->assertGreaterThan(0, $g10Data['total_billed']);
        $this->assertEquals(
            round($g10Data['total_billed'], 2),
            round($g10Data['total_collected'] + $g10Data['total_outstanding'], 2)
        );
    }

    /**
     * Parent views invoices and payment history for their linked child.
     */
    public function test_parent_can_view_child_invoices_and_payment_history(): void
    {
        $response = $this->actingAsTenant($this->homerParent, $this->greenwood)
            ->getJson("/api/parent/children/{$this->bartStudent->id}/invoices");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'student',
                    'summary' => ['total_invoices', 'total_billed', 'total_paid', 'total_outstanding'],
                    'invoices',
                ],
            ]);

        $payHistoryResponse = $this->actingAsTenant($this->homerParent, $this->greenwood)
            ->getJson("/api/parent/children/{$this->bartStudent->id}/payments");

        $payHistoryResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['student', 'total_payments', 'total_amount_paid', 'payments'],
            ]);
    }

    /**
     * Admin manually records cash / bank transfer payment.
     */
    public function test_admin_can_record_manual_payment(): void
    {
        // Find an invoice with outstanding balance
        $invoice = app(TenantManager::class)->bypass(fn () =>
            Invoice::where('student_id', $this->milhouseStudent->id)->firstOrFail()
        );

        $initialBalance = $invoice->balance();
        $this->assertGreaterThan(0, $initialBalance);

        // 1. Record partial cash payment (5,000 ETB)
        $paymentResponse1 = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson("/api/billing/invoices/{$invoice->id}/payments", [
                'amount' => 5000.00,
                'method' => 'cash',
                'paid_at' => '2025-10-01',
                'notes' => 'Cash received at front desk',
            ]);

        $paymentResponse1->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment.amount', '5000.00')
            ->assertJsonPath('data.invoice.status', 'partial');

        // 2. Record remaining payment via bank transfer
        $remaining = $initialBalance - 5000.00;
        $paymentResponse2 = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson("/api/billing/invoices/{$invoice->id}/payments", [
                'amount' => $remaining,
                'method' => 'bank_transfer',
                'paid_at' => '2025-10-05',
                'gateway_reference' => 'CBE-TX-998811',
                'notes' => 'CBE Birr direct bank transfer',
            ]);

        $paymentResponse2->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invoice.status', 'paid');

        // 3. Attempting to record payment on an already fully paid invoice returns 422
        $excessResponse = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->postJson("/api/billing/invoices/{$invoice->id}/payments", [
                'amount' => 100.00,
                'method' => 'cash',
            ]);

        $excessResponse->assertStatus(422);
    }

    /**
     * RBAC and Multi-Tenant Isolation on Fee & Billing.
     */
    public function test_tenant_and_role_boundaries_on_billing(): void
    {
        $mapleInvoice = app(TenantManager::class)->bypass(fn () =>
            Invoice::where('student_id', $this->tommyStudent->id)->firstOrFail()
        );

        // 1. Greenwood Admin cannot access Maplewood invoice -> 403 / 404
        $crossTenantResponse = $this->actingAsTenant($this->greenwoodAdmin, $this->greenwood)
            ->getJson("/api/billing/invoices/{$mapleInvoice->id}");
        $this->assertContains($crossTenantResponse->status(), [403, 404]);

        // 2. Homer (parent) cannot view unlinked child's invoices (Milhouse) -> 403
        $unlinkedChildResponse = $this->actingAsTenant($this->homerParent, $this->greenwood)
            ->getJson("/api/parent/children/{$this->milhouseStudent->id}/invoices");
        $this->assertEquals(403, $unlinkedChildResponse->status());

        // 3. Student cannot access admin billing configuration -> 403
        $studentResponse = $this->actingAsTenant($this->bartUser, $this->greenwood)
            ->getJson('/api/billing/fee-structures');
        $this->assertEquals(403, $studentResponse->status());

        // 4. Teacher cannot record payments or bulk generate invoices -> 403
        $teacherResponse = $this->actingAsTenant($this->ednaTeacher, $this->greenwood)
            ->postJson('/api/billing/invoices/bulk-generate', [
                'academic_year_id' => $this->year2025->id,
                'term_id' => $this->fallTerm->id,
            ]);
        $this->assertEquals(403, $teacherResponse->status());
    }
}
