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
        // 1. School Calendar (for non-school days, weekends, holidays, special events)
        Schema::create('school_calendar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete()->index();
            $table->date('date')->index();
            $table->string('day_type', 30)->default('school_day'); // school_day, weekend, holiday, staff_only, event
            $table->boolean('is_school_day')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'date']);
        });

        // 2. Attendance Records (grade/section-based daily attendance)
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete()->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete()->index();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete()->index();
            $table->date('date')->index();
            $table->string('status', 20)->default('present'); // present, absent, late, excused
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            // A student has at most one attendance record per school date
            $table->unique(['school_id', 'student_id', 'date']);
            $table->index(['school_id', 'section_id', 'date']);
            $table->index(['school_id', 'student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('school_calendar');
    }
};
