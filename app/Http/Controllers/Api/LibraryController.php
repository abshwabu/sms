<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckinBookRequest;
use App\Http\Requests\CheckoutBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\LibraryFine;
use App\Models\Student;
use App\Services\LibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class LibraryController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected LibraryService $libraryService
    ) {}

    /**
     * Get paginated/searchable catalog of library books.
     */
    public function books(Request $request): JsonResponse
    {
        Gate::authorize('viewCatalog', Book::class);

        $query = Book::query()
            ->search($request->input('search'))
            ->category($request->input('category'));

        if ($request->boolean('available_only')) {
            $query->availableOnly();
        }

        $sortField = in_array($request->input('sort_by'), ['title', 'author', 'category', 'copies_available', 'publication_year'])
            ? $request->input('sort_by')
            : 'title';
        $sortOrder = $request->input('order') === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sortField, $sortOrder);

        if ($request->boolean('all')) {
            $books = $query->get();
            return $this->respondWithSuccess($books, 'Library books retrieved successfully.');
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $books = $query->paginate($perPage);

        return $this->respondWithPagination($books, 'Library books retrieved successfully.');
    }

    /**
     * Get detailed information for a single book.
     */
    public function showBook(Book $book): JsonResponse
    {
        Gate::authorize('viewCatalog', $book);

        $book->loadCount('activeLoans');

        return $this->respondWithSuccess([
            'book' => $book,
            'is_available' => $book->isAvailable(),
            'borrowed_copies' => $book->borrowedCopies(),
        ], 'Book details retrieved successfully.');
    }

    /**
     * Store a new book in the catalog.
     */
    public function storeBook(StoreBookRequest $request): JsonResponse
    {
        Gate::authorize('manageCatalog', Book::class);

        $data = $request->validated();
        if (! isset($data['copies_available'])) {
            $data['copies_available'] = $data['copies_total'];
        }

        $book = Book::create($data);

        return $this->respondWithSuccess($book, 'Book created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Update an existing book in the catalog.
     */
    public function updateBook(UpdateBookRequest $request, Book $book): JsonResponse
    {
        Gate::authorize('manageCatalog', $book);

        $data = $request->validated();

        // If copies_total adjusted, calculate new copies_available based on active loans
        if (isset($data['copies_total'])) {
            $activeLoansCount = $book->activeLoans()->count();
            $newTotal = (int) $data['copies_total'];
            $data['copies_available'] = max(0, $newTotal - $activeLoansCount);
        }

        $book->update($data);

        return $this->respondWithSuccess($book, 'Book updated successfully.');
    }

    /**
     * Delete a book from the catalog.
     */
    public function destroyBook(Book $book): JsonResponse
    {
        Gate::authorize('manageCatalog', $book);

        if ($book->activeLoans()->exists()) {
            return ApiResponse::error(
                "Cannot delete '{$book->title}' because it currently has active borrowed loans.",
                'BOOK_HAS_ACTIVE_LOANS',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $book->delete();

        return $this->respondWithSuccess(null, 'Book deleted successfully.');
    }

    /**
     * Get paginated book loans for circulation desk.
     */
    public function loans(Request $request): JsonResponse
    {
        Gate::authorize('circulate', BookLoan::class);

        $query = BookLoan::with([
            'book',
            'student.user:id,name,email',
            'staff.user:id,name,email',
            'user:id,name,email',
            'checkedOutBy:id,name',
            'checkedInBy:id,name',
            'fines',
        ]);

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'overdue') {
                $query->overdue();
            } elseif ($status === 'borrowed') {
                $query->active();
            } elseif ($status === 'returned') {
                $query->returned();
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        if ($request->filled('book_id')) {
            $query->where('book_id', $request->input('book_id'));
        }

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->whereHas('book', function ($bq) use ($term) {
                    $bq->where('title', 'like', "%{$term}%")
                       ->orWhere('author', 'like', "%{$term}%")
                       ->orWhere('isbn', 'like', "%{$term}%");
                })->orWhereHas('student.user', function ($uq) use ($term) {
                    $uq->where('name', 'like', "%{$term}%");
                })->orWhereHas('staff.user', function ($uq) use ($term) {
                    $uq->where('name', 'like', "%{$term}%");
                });
            });
        }

        $query->orderBy('borrowed_at', 'desc');

        $perPage = min((int) $request->input('per_page', 15), 100);
        $loans = $query->paginate($perPage);

        return $this->respondWithPagination($loans, 'Book loans retrieved successfully.');
    }

    /**
     * Check out a book to a borrower.
     * Acceptance criterion: Checking out a book decrements available copies.
     */
    public function checkout(CheckoutBookRequest $request): JsonResponse
    {
        Gate::authorize('circulate', BookLoan::class);

        $book = Book::findOrFail($request->input('book_id'));
        $loan = $this->libraryService->checkoutBook($book, $request->validated(), $request->user());

        return $this->respondWithSuccess($loan, 'Book checked out successfully.', Response::HTTP_CREATED);
    }

    /**
     * Check in a returned book.
     * Acceptance criterion: Returning it increments available copies and calculates any overdue fine.
     */
    public function checkin(CheckinBookRequest $request, BookLoan $loan): JsonResponse
    {
        Gate::authorize('circulate', $loan);

        $updatedLoan = $this->libraryService->checkinBook($loan, $request->validated(), $request->user());

        return $this->respondWithSuccess($updatedLoan, 'Book returned successfully.');
    }

    /**
     * Get fines ledger records.
     */
    public function fines(Request $request): JsonResponse
    {
        Gate::authorize('manageFines', LibraryFine::class);

        $query = LibraryFine::with([
            'loan.book',
            'student.user:id,name,email',
            'staff.user:id,name,email',
            'user:id,name,email',
            'createdBy:id,name',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->whereHas('loan.book', function ($bq) use ($term) {
                    $bq->where('title', 'like', "%{$term}%");
                })->orWhereHas('student.user', function ($uq) use ($term) {
                    $uq->where('name', 'like', "%{$term}%");
                });
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min((int) $request->input('per_page', 15), 100);
        $fines = $query->paginate($perPage);

        return $this->respondWithPagination($fines, 'Library fines retrieved successfully.');
    }

    /**
     * Record fine payment.
     */
    public function payFine(Request $request, LibraryFine $fine): JsonResponse
    {
        Gate::authorize('manageFines', $fine);

        $request->validate([
            'payment_method' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $updatedFine = $this->libraryService->payFine($fine, $request->all(), $request->user());

        return $this->respondWithSuccess($updatedFine, 'Library fine payment recorded successfully.');
    }

    /**
     * Waive a fine.
     */
    public function waiveFine(Request $request, LibraryFine $fine): JsonResponse
    {
        Gate::authorize('manageFines', $fine);

        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $updatedFine = $this->libraryService->waiveFine($fine, $request->input('reason'), $request->user());

        return $this->respondWithSuccess($updatedFine, 'Library fine waived successfully.');
    }

    /**
     * Library summary statistics.
     */
    public function summary(): JsonResponse
    {
        Gate::authorize('circulate', BookLoan::class);

        $stats = $this->libraryService->getSummaryStats();

        return $this->respondWithSuccess($stats, 'Library summary statistics retrieved.');
    }

    /**
     * Student portal: currently borrowed books and history.
     * Acceptance criterion: Student portal shows currently borrowed books and due dates.
     */
    public function myBorrowedBooks(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isStudent() || ! $user->student) {
            return ApiResponse::error(
                'Only student accounts can view their student library record.',
                'FORBIDDEN_STUDENT_ACCESS',
                Response::HTTP_FORBIDDEN
            );
        }

        $student = $user->student;

        $loans = BookLoan::where('student_id', $student->id)
            ->with(['book', 'fines'])
            ->orderByRaw("CASE WHEN returned_at IS NULL THEN 0 ELSE 1 END")
            ->orderBy('due_at', 'asc')
            ->get();

        $activeLoans = $loans->whereNull('returned_at')->values();
        $historyLoans = $loans->whereNotNull('returned_at')->values();

        return $this->respondWithSuccess([
            'student' => [
                'id' => $student->id,
                'name' => $user->name,
                'admission_number' => $student->admission_number,
            ],
            'active_loans' => $activeLoans,
            'history_loans' => $historyLoans,
            'total_active' => $activeLoans->count(),
            'total_overdue' => $activeLoans->where('is_overdue', true)->count(),
            'total_unpaid_fines' => (float) $loans->sum(fn ($l) => $l->fines->where('status', 'unpaid')->sum('amount')),
        ], 'Student borrowed books retrieved successfully.');
    }

    /**
     * Parent portal: linked child's currently borrowed books and due dates.
     * Acceptance criterion: Parent portal shows currently borrowed books and due dates.
     */
    public function childBorrowedBooks(Request $request, Student $student): JsonResponse
    {
        Gate::authorize('viewStudentLoans', [BookLoan::class, $student]);

        $loans = BookLoan::where('student_id', $student->id)
            ->with(['book', 'fines'])
            ->orderByRaw("CASE WHEN returned_at IS NULL THEN 0 ELSE 1 END")
            ->orderBy('due_at', 'asc')
            ->get();

        $activeLoans = $loans->whereNull('returned_at')->values();
        $historyLoans = $loans->whereNotNull('returned_at')->values();

        return $this->respondWithSuccess([
            'student' => [
                'id' => $student->id,
                'name' => $student->user?->name,
                'admission_number' => $student->admission_number,
            ],
            'active_loans' => $activeLoans,
            'history_loans' => $historyLoans,
            'total_active' => $activeLoans->count(),
            'total_overdue' => $activeLoans->where('is_overdue', true)->count(),
            'total_unpaid_fines' => (float) $loans->sum(fn ($l) => $l->fines->where('status', 'unpaid')->sum('amount')),
        ], 'Child borrowed books retrieved successfully.');
    }
}
