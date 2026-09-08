<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Books Catalog
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('author');
            $table->string('isbn')->nullable();
            $table->string('category')->default('General');
            $table->unsignedInteger('copies_total')->default(1);
            $table->unsignedInteger('copies_available')->default(1);
            $table->string('shelf_location')->nullable();
            $table->text('description')->nullable();
            $table->string('publisher')->nullable();
            $table->smallInteger('publication_year')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'title']);
            $table->index(['school_id', 'category']);
            $table->index(['school_id', 'isbn']);
        });

        // 2. Book Loans (Circulation)
        Schema::create('book_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('borrowed_at');
            $table->dateTime('due_at');
            $table->dateTime('returned_at')->nullable();
            $table->string('status')->default('borrowed'); // borrowed, returned, overdue, lost
            $table->decimal('fine_amount', 8, 2)->default(0.00);
            $table->boolean('fine_paid')->default(false);
            $table->dateTime('fine_paid_at')->nullable();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'staff_id']);
            $table->index(['school_id', 'book_id']);
        });

        // 3. Simple Fines Ledger
        Schema::create('library_fines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_loan_id')->constrained('book_loans')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 8, 2);
            $table->string('type')->default('overdue'); // overdue, damage, lost
            $table->string('status')->default('unpaid'); // unpaid, paid, waived
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_method')->nullable(); // cash, online, waived
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'book_loan_id']);
            $table->index(['school_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('library_fines');
        Schema::dropIfExists('book_loans');
        Schema::dropIfExists('books');
    }
};
