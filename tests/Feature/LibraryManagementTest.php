<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\LibraryFine;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\AttendanceSeeder;
use Database\Seeders\GradingSeeder;
use Database\Seeders\LibrarySeeder;
use Database\Seeders\ParentSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SchoolSeeder;
use Database\Seeders\StaffSeeder;
use Database\Seeders\StudentSeeder;
use Database\Seeders\TimetableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $oakridge;
    protected User $greenwoodAdmin;
    protected User $greenwoodLibrarian;
    protected User $ednaTeacher;
    protected User $homerParent;
    protected Student $bartStudent;
    protected Student $lisaStudent;
    protected Student $milhouseStudent;
    protected Book $gatsbyBook;
    protected Book $algorithmsBook;
    protected User $oakridgeLibrarian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SchoolSeeder::class,
            RolesAndPermissionsSeeder::class,
            AcademicStructureSeeder::class,
            StudentSeeder::class,
            StaffSeeder::class,
            ParentSeeder::class,
            AttendanceSeeder::class,
            GradingSeeder::class,
            TimetableSeeder::class,
            LibrarySeeder::class,
        ]);

        $this->greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $this->oakridge = School::where('subdomain', 'oakridge')->firstOrFail();

        app(\App\Tenancy\TenantManager::class)->setTenant($this->greenwood);

        $this->greenwoodAdmin = User::where('email', 'admin@greenwood.edu')->firstOrFail();
        $this->greenwoodLibrarian = User::where('email', 'librarian@greenwood.edu')->firstOrFail();
        $this->ednaTeacher = User::where('email', 'teacher@greenwood.edu')->firstOrFail();
        $this->homerParent = User::where('email', 'parent@greenwood.edu')->firstOrFail();

        $this->bartStudent = Student::where('admission_number', 'GRE-25-00101')->firstOrFail();
        $this->lisaStudent = Student::where('admission_number', 'GRE-25-00102')->firstOrFail();
        $this->milhouseStudent = Student::where('admission_number', 'GRE-25-00103')->firstOrFail();

        $this->gatsbyBook = Book::where('school_id', $this->greenwood->id)
            ->where('title', 'The Great Gatsby')
            ->firstOrFail();

        $this->algorithmsBook = Book::where('school_id', $this->greenwood->id)
            ->where('title', 'Introduction to Algorithms')
            ->firstOrFail();

        $this->oakridgeLibrarian = User::where('email', 'librarian@oakridge.edu')->firstOrFail();
    }

    /**
     * Acceptance Criterion 1 (Part A):
     * Checking out a book decrements available copies.
     */
    public function test_checking_out_book_decrements_available_copies(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        $initialCopies = $this->gatsbyBook->copies_available;
        $this->assertGreaterThan(0, $initialCopies);

        $response = $this->actingAs($this->greenwoodAdmin)
            ->postJson('/api/library/loans/checkout', [
                'book_id' => $this->gatsbyBook->id,
                'student_id' => $this->milhouseStudent->id,
                'due_at' => Carbon::now()->addDays(14)->format('Y-m-d H:i:s'),
                'notes' => 'Milhouse literature assignment',
            ], $headers);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'borrowed');
        $response->assertJsonPath('data.student_id', $this->milhouseStudent->id);

        // Verify database decremented available copies
        $this->gatsbyBook->refresh();
        $this->assertEquals($initialCopies - 1, $this->gatsbyBook->copies_available);

        // Verify loan record in DB
        $this->assertDatabaseHas('book_loans', [
            'school_id' => $this->greenwood->id,
            'book_id' => $this->gatsbyBook->id,
            'student_id' => $this->milhouseStudent->id,
            'status' => 'borrowed',
        ]);
    }

    /**
     * Cannot checkout a book when copies_available is 0.
     */
    public function test_cannot_checkout_book_when_no_copies_available(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Set available copies to 0
        $this->algorithmsBook->update(['copies_available' => 0]);

        $response = $this->actingAs($this->greenwoodAdmin)
            ->postJson('/api/library/loans/checkout', [
                'book_id' => $this->algorithmsBook->id,
                'student_id' => $this->milhouseStudent->id,
            ], $headers);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'BOOK_UNAVAILABLE');
        $this->assertStringContainsString('No copies', $response->json('error.message'));

        $this->algorithmsBook->refresh();
        $this->assertEquals(0, $this->algorithmsBook->copies_available);
    }

    /**
     * Acceptance Criterion 1 (Part B):
     * Returning a book increments available copies.
     */
    public function test_returning_book_increments_available_copies(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // First checkout
        $initialCopies = $this->gatsbyBook->copies_available;
        $loan = BookLoan::create([
            'school_id' => $this->greenwood->id,
            'book_id' => $this->gatsbyBook->id,
            'student_id' => $this->milhouseStudent->id,
            'user_id' => $this->milhouseStudent->user_id,
            'borrowed_at' => Carbon::now()->subDays(2),
            'due_at' => Carbon::now()->addDays(12),
            'status' => 'borrowed',
            'checked_out_by' => $this->greenwoodAdmin->id,
        ]);
        $this->gatsbyBook->decrement('copies_available');

        $this->assertEquals($initialCopies - 1, $this->gatsbyBook->fresh()->copies_available);

        // Now return the book on time
        $response = $this->actingAs($this->greenwoodAdmin)
            ->postJson("/api/library/loans/{$loan->id}/checkin", [
                'returned_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'notes' => 'Returned in great shape',
            ], $headers);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'returned');
        $response->assertJsonPath('data.fine_amount', '0.00');

        // Copies available must be incremented back
        $this->gatsbyBook->refresh();
        $this->assertEquals($initialCopies, $this->gatsbyBook->copies_available);

        $loan->refresh();
        $this->assertEquals('returned', $loan->status);
        $this->assertNotNull($loan->returned_at);
        $this->assertEquals(0.00, (float) $loan->fine_amount);
    }

    /**
     * Acceptance Criterion 1 (Part C):
     * Returning an overdue book calculates fine and records in fines ledger.
     */
    public function test_returning_overdue_book_calculates_fine_and_records_in_fines_ledger(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Loan with due date 5 days ago
        $loan = BookLoan::create([
            'school_id' => $this->greenwood->id,
            'book_id' => $this->gatsbyBook->id,
            'student_id' => $this->milhouseStudent->id,
            'user_id' => $this->milhouseStudent->user_id,
            'borrowed_at' => Carbon::now()->subDays(19),
            'due_at' => Carbon::now()->subDays(5),
            'status' => 'borrowed',
            'checked_out_by' => $this->greenwoodAdmin->id,
        ]);
        $this->gatsbyBook->decrement('copies_available');

        // Check in now (5 days overdue at default $0.50/day = $2.50)
        $response = $this->actingAs($this->greenwoodAdmin)
            ->postJson("/api/library/loans/{$loan->id}/checkin", [
                'returned_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'daily_fine_rate' => 0.50,
            ], $headers);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'returned');
        $this->assertEquals(2.50, (float) $response->json('data.fine_amount'));

        // Verify fine ledger record created
        $this->assertDatabaseHas('library_fines', [
            'school_id' => $this->greenwood->id,
            'book_loan_id' => $loan->id,
            'student_id' => $this->milhouseStudent->id,
            'amount' => 2.50,
            'type' => 'overdue',
            'status' => 'unpaid',
        ]);
    }

    /**
     * Librarian role can check out and check in books.
     */
    public function test_librarian_role_can_checkout_and_checkin_books(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Checkout using Librarian account
        $checkoutResponse = $this->actingAs($this->greenwoodLibrarian)
            ->postJson('/api/library/loans/checkout', [
                'book_id' => $this->gatsbyBook->id,
                'student_id' => $this->lisaStudent->id,
            ], $headers);

        $checkoutResponse->assertStatus(201);
        $loanId = $checkoutResponse->json('data.id');

        // Checkin using Librarian account
        $checkinResponse = $this->actingAs($this->greenwoodLibrarian)
            ->postJson("/api/library/loans/{$loanId}/checkin", [], $headers);

        $checkinResponse->assertStatus(200);
        $this->assertEquals('returned', $checkinResponse->json('data.status'));
    }

    /**
     * Teacher and Student cannot perform circulation checkout (403 Forbidden).
     */
    public function test_teacher_and_student_cannot_perform_circulation_checkout(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Teacher attempt
        $teacherResponse = $this->actingAs($this->ednaTeacher)
            ->postJson('/api/library/loans/checkout', [
                'book_id' => $this->gatsbyBook->id,
                'student_id' => $this->lisaStudent->id,
            ], $headers);

        $teacherResponse->assertStatus(403);

        // Student attempt
        $studentResponse = $this->actingAs($this->bartStudent->user)
            ->postJson('/api/library/loans/checkout', [
                'book_id' => $this->gatsbyBook->id,
                'student_id' => $this->bartStudent->id,
            ], $headers);

        $studentResponse->assertStatus(403);
    }

    /**
     * Acceptance Criterion 2 (Part A):
     * Student portal shows currently borrowed books and due dates.
     */
    public function test_student_can_view_own_borrowed_books_and_due_dates(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];
        $bartUser = $this->bartStudent->user;

        $response = $this->actingAs($bartUser)
            ->getJson('/api/student/borrowed-books', $headers);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertEquals($this->bartStudent->id, $response->json('data.student.id'));

        // Bart was seeded with 2 active loans (one on-time, one overdue)
        $activeLoans = $response->json('data.active_loans');
        $this->assertNotEmpty($activeLoans);

        // Check that due date and book details are present
        $firstLoan = $activeLoans[0];
        $this->assertArrayHasKey('due_at', $firstLoan);
        $this->assertArrayHasKey('book', $firstLoan);
        $this->assertNotNull($firstLoan['book']['title']);
    }

    /**
     * Acceptance Criterion 2 (Part B):
     * Parent portal shows linked child's currently borrowed books and due dates.
     */
    public function test_parent_can_view_linked_child_borrowed_books_but_not_unlinked(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Homer is linked to Bart
        $response = $this->actingAs($this->homerParent)
            ->getJson("/api/parent/children/{$this->bartStudent->id}/borrowed-books", $headers);

        $response->assertStatus(200);
        $this->assertEquals($this->bartStudent->id, $response->json('data.student.id'));
        $this->assertNotEmpty($response->json('data.active_loans'));

        // Homer is NOT linked to Milhouse -> 403 Forbidden
        $unlinkedResponse = $this->actingAs($this->homerParent)
            ->getJson("/api/parent/children/{$this->milhouseStudent->id}/borrowed-books", $headers);

        $unlinkedResponse->assertStatus(403);
    }

    /**
     * Fines Ledger: Recording fine payment and waiving fine.
     */
    public function test_fines_ledger_pay_and_waive(): void
    {
        $headers = ['X-School-Id' => $this->greenwood->id];

        // Seeded overdue loan for Bart has a fine in library_fines
        $fine = LibraryFine::where('school_id', $this->greenwood->id)
            ->where('status', 'unpaid')
            ->firstOrFail();

        $this->assertEquals('unpaid', $fine->status);

        // 1. Pay fine
        $payResponse = $this->actingAs($this->greenwoodLibrarian)
            ->postJson("/api/library/fines/{$fine->id}/pay", [
                'payment_method' => 'cash',
                'notes' => 'Paid in full at library counter',
            ], $headers);

        $payResponse->assertStatus(200);
        $payResponse->assertJsonPath('data.status', 'paid');
        $payResponse->assertJsonPath('data.payment_method', 'cash');

        $fine->refresh();
        $this->assertEquals('paid', $fine->status);
        $this->assertNotNull($fine->paid_at);

        // 2. Create another fine to test waiving
        $fine2 = LibraryFine::create([
            'school_id' => $this->greenwood->id,
            'book_loan_id' => $fine->book_loan_id,
            'student_id' => $this->bartStudent->id,
            'user_id' => $this->bartStudent->user_id,
            'amount' => 1.50,
            'type' => 'overdue',
            'status' => 'unpaid',
            'created_by' => $this->greenwoodLibrarian->id,
        ]);

        $waiveResponse = $this->actingAs($this->greenwoodLibrarian)
            ->postJson("/api/library/fines/{$fine2->id}/waive", [
                'reason' => 'First-time offense amnesty',
            ], $headers);

        $waiveResponse->assertStatus(200);
        $waiveResponse->assertJsonPath('data.status', 'waived');

        $fine2->refresh();
        $this->assertEquals('waived', $fine2->status);
    }

    /**
     * Cross-tenant isolation: User from School A cannot access or mutate School B's library.
     */
    public function test_cross_tenant_isolation_on_library(): void
    {
        $headers = ['X-School-Id' => $this->oakridge->id];

        // Oakridge librarian attempting to checkout a Greenwood book
        $response = $this->actingAs($this->oakridgeLibrarian)
            ->postJson('/api/library/loans/checkout', [
                'book_id' => $this->gatsbyBook->id, // Greenwood book
                'student_id' => $this->bartStudent->id,
            ], $headers);

        // Scoping causes Greenwood book to be not found in Oakridge tenant
        $this->assertContains($response->status(), [404, 422]);
    }
}
