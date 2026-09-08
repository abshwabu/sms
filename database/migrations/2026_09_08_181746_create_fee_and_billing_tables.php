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
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_level_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->default('tuition'); // tuition, transport, registration, activity, etc.
            $table->decimal('amount', 10, 2);
            $table->boolean('is_mandatory')->default(true);
            $table->string('condition_type')->default('none'); // none, transport_enrollment, etc.
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'academic_year_id', 'term_id']);
            $table->index(['school_id', 'grade_level_id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0.00);
            $table->date('due_date');
            $table->string('status')->default('unpaid'); // unpaid, partial, paid, overdue, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'invoice_number']);
            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'term_id']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_structure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('payment_number');
            $table->decimal('amount', 10, 2);
            $table->string('method'); // cash, bank_transfer, online, telebirr, etc.
            $table->dateTime('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('gateway')->nullable(); // chapa, offline, etc.
            $table->string('gateway_reference')->nullable();
            $table->string('gateway_status')->nullable(); // pending, success, failed
            $table->json('gateway_metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'payment_number']);
            $table->index(['school_id', 'invoice_id']);
            $table->index('gateway_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('fee_structures');
    }
};
