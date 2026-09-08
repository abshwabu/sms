<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Enums\UserStatus;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\LibraryFine;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Tenancy\TenantManager;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class LibrarySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantManager = app(TenantManager::class);

        $greenwood = School::where('subdomain', 'greenwood')->first();
        $oakridge = School::where('subdomain', 'oakridge')->first();
        $maplewood = School::where('subdomain', 'maplewood')->first();

        if ($greenwood) {
            $this->seedGreenwoodLibrary($greenwood, $tenantManager);
        }

        if ($oakridge) {
            $this->seedOakridgeLibrary($oakridge, $tenantManager);
        }

        if ($maplewood) {
            $this->seedMaplewoodLibrary($maplewood, $tenantManager);
        }
    }

    protected function seedGreenwoodLibrary(School $school, TenantManager $tenantManager): void
    {
        $tenantManager->setTenant($school);

        // 1. Create Librarian user account
        $librarian = User::updateOrCreate(
            ['email' => 'librarian@greenwood.edu'],
            [
                'school_id' => $school->id,
                'name' => 'Dewey Decimal',
                'password' => Hash::make('password123'),
                'role' => RoleEnum::LIBRARIAN->value,
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        // 2. Books Catalog
        $booksData = [
            [
                'title' => 'To Kill a Mockingbird',
                'author' => 'Harper Lee',
                'isbn' => '9780061120084',
                'category' => 'Literature',
                'copies_total' => 4,
                'copies_available' => 4,
                'shelf_location' => 'LIT-LEE-01',
                'description' => 'The unforgettable novel of a childhood in a sleepy Southern town and the crisis of conscience that rocked it.',
                'publisher' => 'Harper Perennial',
                'publication_year' => 1960,
            ],
            [
                'title' => '1984',
                'author' => 'George Orwell',
                'isbn' => '9780451524935',
                'category' => 'Fiction',
                'copies_total' => 5,
                'copies_available' => 5,
                'shelf_location' => 'FIC-ORW-02',
                'description' => 'A startling and haunting vision of the world, 1984 is so powerfully persuasive from the first sentence to the last.',
                'publisher' => 'Signet Classic',
                'publication_year' => 1949,
            ],
            [
                'title' => 'A Brief History of Time',
                'author' => 'Stephen Hawking',
                'isbn' => '9780553380163',
                'category' => 'Science',
                'copies_total' => 3,
                'copies_available' => 3,
                'shelf_location' => 'SCI-HAW-03',
                'description' => 'From the Big Bang to Black Holes, an essential exploration of cosmology.',
                'publisher' => 'Bantam',
                'publication_year' => 1988,
            ],
            [
                'title' => 'The Great Gatsby',
                'author' => 'F. Scott Fitzgerald',
                'isbn' => '9780743273565',
                'category' => 'Literature',
                'copies_total' => 4,
                'copies_available' => 4,
                'shelf_location' => 'LIT-FIT-04',
                'description' => 'The exemplary novel of the Jazz Age and the American dream.',
                'publisher' => 'Scribner',
                'publication_year' => 1925,
            ],
            [
                'title' => 'Introduction to Algorithms',
                'author' => 'Thomas H. Cormen',
                'isbn' => '9780262033848',
                'category' => 'Computer Science',
                'copies_total' => 3,
                'copies_available' => 3,
                'shelf_location' => 'CS-COR-05',
                'description' => 'Comprehensive textbook covering modern computer algorithms.',
                'publisher' => 'MIT Press',
                'publication_year' => 2009,
            ],
            [
                'title' => 'Calculus: Early Transcendentals',
                'author' => 'James Stewart',
                'isbn' => '9781285741550',
                'category' => 'Mathematics',
                'copies_total' => 4,
                'copies_available' => 4,
                'shelf_location' => 'MAT-STE-06',
                'description' => 'Rigorous mathematical foundations for higher education calculus.',
                'publisher' => 'Cengage Learning',
                'publication_year' => 2015,
            ],
            [
                'title' => 'Sapiens: A Brief History of Humankind',
                'author' => 'Yuval Noah Harari',
                'isbn' => '9780062316097',
                'category' => 'History',
                'copies_total' => 3,
                'copies_available' => 3,
                'shelf_location' => 'HIS-HAR-07',
                'description' => 'A ground-breaking narrative of human history and cognitive revolutions.',
                'publisher' => 'Harper',
                'publication_year' => 2014,
            ],
        ];

        $createdBooks = [];
        foreach ($booksData as $bData) {
            $createdBooks[$bData['isbn']] = Book::updateOrCreate(
                ['school_id' => $school->id, 'isbn' => $bData['isbn']],
                $bData
            );
        }

        // 3. Seed sample loans
        $bartStudent = Student::where('school_id', $school->id)
            ->where('admission_number', 'GRE-25-00101')
            ->first();

        $lisaStudent = Student::where('school_id', $school->id)
            ->where('admission_number', 'GRE-25-00102')
            ->first();

        if ($bartStudent) {
            // Loan 1: Active, on-time loan
            $mockingbird = $createdBooks['9780061120084'] ?? null;
            if ($mockingbird && $mockingbird->copies_available > 0) {
                $mockingbird->decrement('copies_available');
                BookLoan::updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'book_id' => $mockingbird->id,
                        'student_id' => $bartStudent->id,
                        'status' => 'borrowed',
                    ],
                    [
                        'user_id' => $bartStudent->user_id,
                        'borrowed_at' => Carbon::now()->subDays(3),
                        'due_at' => Carbon::now()->addDays(11),
                        'checked_out_by' => $librarian->id,
                        'notes' => 'English class reading assignment',
                    ]
                );
            }

            // Loan 2: Overdue loan with fine in ledger
            $orwell = $createdBooks['9780451524935'] ?? null;
            if ($orwell && $orwell->copies_available > 0) {
                $orwell->decrement('copies_available');
                $overdueLoan = BookLoan::updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'book_id' => $orwell->id,
                        'student_id' => $bartStudent->id,
                    ],
                    [
                        'user_id' => $bartStudent->user_id,
                        'borrowed_at' => Carbon::now()->subDays(20),
                        'due_at' => Carbon::now()->subDays(6),
                        'status' => 'borrowed',
                        'fine_amount' => 3.00,
                        'fine_paid' => false,
                        'checked_out_by' => $librarian->id,
                        'notes' => 'Book is 6 days overdue',
                    ]
                );

                LibraryFine::updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'book_loan_id' => $overdueLoan->id,
                    ],
                    [
                        'student_id' => $bartStudent->id,
                        'user_id' => $bartStudent->user_id,
                        'amount' => 3.00,
                        'type' => 'overdue',
                        'status' => 'unpaid',
                        'notes' => 'Overdue by 6 day(s) ($0.50/day)',
                        'created_by' => $librarian->id,
                    ]
                );
            }
        }

        if ($lisaStudent) {
            // Loan 3: Past returned loan
            $hawking = $createdBooks['9780553380163'] ?? null;
            if ($hawking) {
                BookLoan::updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'book_id' => $hawking->id,
                        'student_id' => $lisaStudent->id,
                        'status' => 'returned',
                    ],
                    [
                        'user_id' => $lisaStudent->user_id,
                        'borrowed_at' => Carbon::now()->subDays(14),
                        'due_at' => Carbon::now()->subDays(2),
                        'returned_at' => Carbon::now()->subDays(3), // returned on time!
                        'fine_amount' => 0.00,
                        'fine_paid' => true,
                        'checked_out_by' => $librarian->id,
                        'checked_in_by' => $librarian->id,
                        'notes' => 'Returned in pristine condition',
                    ]
                );
            }
        }
    }

    protected function seedOakridgeLibrary(School $school, TenantManager $tenantManager): void
    {
        $tenantManager->setTenant($school);

        $librarian = User::updateOrCreate(
            ['email' => 'librarian@oakridge.edu'],
            [
                'school_id' => $school->id,
                'name' => 'Madam Irma Pince',
                'password' => Hash::make('password123'),
                'role' => RoleEnum::LIBRARIAN->value,
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );

        $oakridgeBooks = [
            [
                'title' => 'Fantastic Beasts and Where to Find Them',
                'author' => 'Newt Scamander',
                'isbn' => '9780545850568',
                'category' => 'Zoology',
                'copies_total' => 6,
                'copies_available' => 6,
                'shelf_location' => 'MAG-ZOO-01',
                'description' => 'An indispensable guide to magical beasts and creatures.',
                'publisher' => 'Bloomsbury',
                'publication_year' => 2001,
            ],
            [
                'title' => 'A History of Magic',
                'author' => 'Bathilda Bagshot',
                'isbn' => '9780747532743',
                'category' => 'History',
                'copies_total' => 4,
                'copies_available' => 4,
                'shelf_location' => 'HIS-BAG-02',
                'description' => 'Comprehensive textbook used by students at Oakridge.',
                'publisher' => 'Little Red Books',
                'publication_year' => 1947,
            ],
        ];

        foreach ($oakridgeBooks as $bData) {
            Book::updateOrCreate(
                ['school_id' => $school->id, 'isbn' => $bData['isbn']],
                $bData
            );
        }
    }

    protected function seedMaplewoodLibrary(School $school, TenantManager $tenantManager): void
    {
        $tenantManager->setTenant($school);

        $librarianRole = Role::firstOrCreate([
            'name' => RoleEnum::LIBRARIAN->value,
            'guard_name' => 'web',
            'school_id' => $school->id,
        ]);

        $librarian = User::updateOrCreate(
            ['email' => 'librarian@maplewood.edu'],
            [
                'school_id' => $school->id,
                'name' => 'Beverly Cleary',
                'password' => Hash::make('password123'),
                'role' => RoleEnum::LIBRARIAN->value,
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );
        $librarian->assignRole($librarianRole);

        $books = [
            [
                'title' => "Charlotte's Web",
                'author' => 'E.B. White',
                'isbn' => '9780064400558',
                'category' => "Children's Literature",
                'copies_total' => 8,
                'copies_available' => 7,
                'shelf_location' => 'JUV-WHI-01',
                'description' => 'The story of a pig named Wilbur and his friendship with a barn spider named Charlotte.',
                'publisher' => 'HarperCollins',
                'publication_year' => 1952,
            ],
            [
                'title' => 'The Giving Tree',
                'author' => 'Shel Silverstein',
                'isbn' => '9780060256654',
                'category' => 'Picture Books',
                'copies_total' => 5,
                'copies_available' => 5,
                'shelf_location' => 'PIC-SIL-02',
                'description' => 'A moving parable about the gift of giving and acceptance.',
                'publisher' => 'Harper & Row',
                'publication_year' => 1964,
            ],
            [
                'title' => 'Where the Wild Things Are',
                'author' => 'Maurice Sendak',
                'isbn' => '9780060254926',
                'category' => 'Picture Books',
                'copies_total' => 6,
                'copies_available' => 6,
                'shelf_location' => 'PIC-SEN-03',
                'description' => 'Max sails off to where the wild things are and becomes king of all wild things.',
                'publisher' => 'Harper & Row',
                'publication_year' => 1963,
            ],
        ];

        $created = [];
        foreach ($books as $b) {
            $created[$b['isbn']] = Book::updateOrCreate(
                ['school_id' => $school->id, 'isbn' => $b['isbn']],
                $b
            );
        }

        $tommy = Student::where('school_id', $school->id)->where('admission_number', 'MAP-25-00101')->first();
        if ($tommy && isset($created['9780064400558'])) {
            BookLoan::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'book_id' => $created['9780064400558']->id,
                    'student_id' => $tommy->id,
                    'status' => 'borrowed',
                ],
                [
                    'user_id' => $tommy->user_id,
                    'borrowed_at' => Carbon::now()->subDays(3),
                    'due_at' => Carbon::now()->addDays(11),
                    'checked_out_by' => $librarian->id,
                    'notes' => 'Early reading assignment',
                ]
            );
        }

        $tenantManager->clearTenant();
    }
}
