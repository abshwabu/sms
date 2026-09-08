<?php

namespace App\Services;

use App\Exceptions\BookUnavailableException;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\LibraryFine;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LibraryService
{
    /**
     * Check out a book to a student or staff member.
     * Decrements copies_available on the book.
     */
    public function checkoutBook(Book $book, array $data, User $actor): BookLoan
    {
        return DB::transaction(function () use ($book, $data, $actor) {
            // Lock book row to prevent concurrent race condition on available copies
            /** @var Book $lockedBook */
            $lockedBook = Book::where('id', $book->id)->lockForUpdate()->firstOrFail();

            if ($lockedBook->copies_available < 1) {
                throw new BookUnavailableException(
                    "No copies of '{$lockedBook->title}' are currently available for checkout."
                );
            }

            // Resolve borrower
            $studentId = null;
            $staffId = null;
            $userId = null;

            if (! empty($data['student_id'])) {
                /** @var Student $student */
                $student = Student::where('id', $data['student_id'])->firstOrFail();
                $studentId = $student->id;
                $userId = $student->user_id;
            } elseif (! empty($data['staff_id'])) {
                /** @var Staff $staff */
                $staff = Staff::where('id', $data['staff_id'])->firstOrFail();
                $staffId = $staff->id;
                $userId = $staff->user_id;
            } elseif (! empty($data['user_id'])) {
                /** @var User $user */
                $user = User::where('id', $data['user_id'])->firstOrFail();
                $userId = $user->id;
                $studentId = $user->student?->id;
                $staffId = $user->staff?->id;
            } else {
                throw ValidationException::withMessages([
                    'borrower' => 'A valid student or staff member must be specified.',
                ]);
            }

            $borrowedAt = ! empty($data['borrowed_at'])
                ? Carbon::parse($data['borrowed_at'])
                : now();

            $dueAt = ! empty($data['due_at'])
                ? Carbon::parse($data['due_at'])
                : $borrowedAt->copy()->addDays(14);

            // Decrement copies available
            $lockedBook->decrement('copies_available');

            /** @var BookLoan $loan */
            $loan = BookLoan::create([
                'school_id' => $lockedBook->school_id,
                'book_id' => $lockedBook->id,
                'student_id' => $studentId,
                'staff_id' => $staffId,
                'user_id' => $userId,
                'borrowed_at' => $borrowedAt,
                'due_at' => $dueAt,
                'status' => 'borrowed',
                'fine_amount' => 0.00,
                'fine_paid' => false,
                'checked_out_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);

            return $loan->load(['book', 'student.user', 'staff.user', 'user', 'checkedOutBy']);
        });
    }

    /**
     * Check in a borrowed book.
     * Increments copies_available on the book and automatically computes any overdue fines.
     */
    public function checkinBook(BookLoan $loan, array $data, User $actor): BookLoan
    {
        return DB::transaction(function () use ($loan, $data, $actor) {
            if ($loan->returned_at !== null || $loan->status === 'returned') {
                throw ValidationException::withMessages([
                    'loan' => 'This book loan has already been checked in and returned.',
                ]);
            }

            // Lock book row to restore available copy count
            /** @var Book $lockedBook */
            $lockedBook = Book::where('id', $loan->book_id)->lockForUpdate()->firstOrFail();

            if ($lockedBook->copies_available < $lockedBook->copies_total) {
                $lockedBook->increment('copies_available');
            }

            $returnedAt = ! empty($data['returned_at'])
                ? Carbon::parse($data['returned_at'])
                : now();

            // Calculate overdue days and fine
            $dueAt = $loan->due_at;
            $daysOverdue = 0;
            $calculatedFine = 0.00;

            if ($returnedAt->startOfDay()->gt($dueAt->startOfDay())) {
                $daysOverdue = (int) $dueAt->startOfDay()->diffInDays($returnedAt->startOfDay());
                $dailyRate = isset($data['daily_fine_rate']) ? (float) $data['daily_fine_rate'] : 0.50;
                $calculatedFine = round($daysOverdue * $dailyRate, 2);
            }

            $fineAmount = isset($data['fine_amount']) && $data['fine_amount'] !== null
                ? (float) $data['fine_amount']
                : $calculatedFine;

            $finePaid = (bool) ($data['fine_paid'] ?? false);

            if ($fineAmount > 0) {
                LibraryFine::create([
                    'school_id' => $loan->school_id,
                    'book_loan_id' => $loan->id,
                    'student_id' => $loan->student_id,
                    'staff_id' => $loan->staff_id,
                    'user_id' => $loan->user_id,
                    'amount' => $fineAmount,
                    'type' => $data['fine_type'] ?? 'overdue',
                    'status' => $finePaid ? 'paid' : 'unpaid',
                    'paid_at' => $finePaid ? now() : null,
                    'payment_method' => $finePaid ? ($data['payment_method'] ?? 'cash') : null,
                    'notes' => $data['fine_notes'] ?? ($daysOverdue > 0 ? "Overdue by {$daysOverdue} day(s) (due {$dueAt->format('Y-m-d')})" : 'Library fine'),
                    'created_by' => $actor->id,
                ]);

                $loan->fine_amount = $fineAmount;
                $loan->fine_paid = $finePaid;
                $loan->fine_paid_at = $finePaid ? now() : null;
            }

            $loan->returned_at = $returnedAt;
            $loan->status = 'returned';
            $loan->checked_in_by = $actor->id;

            if (! empty($data['notes'])) {
                $loan->notes = $loan->notes ? $loan->notes . "\n" . $data['notes'] : $data['notes'];
            }

            $loan->save();

            return $loan->fresh(['book', 'student.user', 'staff.user', 'user', 'fines', 'checkedInBy']);
        });
    }

    /**
     * Record fine payment in the fines ledger.
     */
    public function payFine(LibraryFine $fine, array $data, User $actor): LibraryFine
    {
        return DB::transaction(function () use ($fine, $data, $actor) {
            $fine->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_method' => $data['payment_method'] ?? 'cash',
                'notes' => ! empty($data['notes'])
                    ? ($fine->notes ? $fine->notes . ' | ' . $data['notes'] : $data['notes'])
                    : $fine->notes,
            ]);

            // If all fines on the parent loan are paid/waived, mark loan as fine_paid
            $unpaidCount = $fine->loan->fines()->where('status', 'unpaid')->count();
            if ($unpaidCount === 0) {
                $fine->loan->update([
                    'fine_paid' => true,
                    'fine_paid_at' => now(),
                ]);
            }

            return $fine->fresh(['loan.book', 'student.user', 'staff.user']);
        });
    }

    /**
     * Waive a library fine.
     */
    public function waiveFine(LibraryFine $fine, string $reason, User $actor): LibraryFine
    {
        return DB::transaction(function () use ($fine, $reason, $actor) {
            $fine->update([
                'status' => 'waived',
                'payment_method' => 'waived',
                'notes' => $fine->notes ? $fine->notes . " | Waived: {$reason}" : "Waived: {$reason}",
            ]);

            $unpaidCount = $fine->loan->fines()->where('status', 'unpaid')->count();
            if ($unpaidCount === 0) {
                $fine->loan->update([
                    'fine_paid' => true,
                ]);
            }

            return $fine->fresh(['loan.book', 'student.user', 'staff.user']);
        });
    }

    /**
     * Get summary metrics for the library dashboard.
     */
    public function getSummaryStats(): array
    {
        $totalBooks = Book::count();
        $totalCopies = (int) Book::sum('copies_total');
        $availableCopies = (int) Book::sum('copies_available');
        $activeLoans = BookLoan::active()->count();
        $overdueLoans = BookLoan::overdue()->count();
        $unpaidFinesTotal = (float) LibraryFine::unpaid()->sum('amount');

        return [
            'total_books' => $totalBooks,
            'total_copies' => $totalCopies,
            'available_copies' => $availableCopies,
            'borrowed_copies' => max(0, $totalCopies - $availableCopies),
            'active_loans' => $activeLoans,
            'overdue_loans' => $overdueLoans,
            'unpaid_fines_total' => $unpaidFinesTotal,
        ];
    }
}
