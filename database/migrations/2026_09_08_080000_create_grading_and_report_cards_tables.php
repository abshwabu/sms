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
        // 1. Subjects table (per grade level)
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->cascadeOnDelete()->index();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete()->index();
            $table->string('name');
            $table->string('code', 30);
            $table->decimal('credit_hours', 4, 1)->default(1.0);
            $table->text('description')->nullable();
            $table->boolean('is_elective')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'grade_level_id', 'code']);
        });

        // 2. Grading Scales table (configurable per school: A-F, percentage, GPA)
        Schema::create('grading_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->string('name'); // e.g. "Standard Letter (A-F)", "4.0 GPA Scale"
            $table->string('scale_type', 20)->default('letter'); // letter, gpa, percentage
            $table->boolean('is_default')->default(false);
            $table->json('rules'); // Array of brackets: min_score, max_score, grade, gpa_point, description
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        // 3. Exams / Assessments table
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete()->index();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete()->index();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->cascadeOnDelete()->index();
            $table->string('name'); // e.g. "Midterm Exam", "Final Exam", "CAT 1"
            $table->string('type', 30)->default('midterm'); // quiz, midterm, final, cat, assignment
            $table->decimal('weight', 5, 2)->default(50.00); // percentage weight in term
            $table->decimal('max_marks', 6, 2)->default(100.00);
            $table->date('date')->nullable();
            $table->string('status', 20)->default('scheduled'); // scheduled, active, completed, published
            $table->timestamps();

            $table->unique(['school_id', 'term_id', 'grade_level_id', 'name']);
        });

        // 4. Grades table
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete()->index();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete()->index();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete()->index();
            $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete()->index();
            $table->decimal('marks_obtained', 6, 2);
            $table->decimal('max_marks', 6, 2)->default(100.00);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'student_id', 'subject_id', 'exam_id']);
        });

        // 5. Report Cards table
        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete()->index();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete()->index();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete()->index();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete()->index();
            $table->foreignId('grading_scale_id')->nullable()->constrained('grading_scales')->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft, published
            $table->decimal('total_marks_obtained', 8, 2)->default(0);
            $table->decimal('total_max_marks', 8, 2)->default(0);
            $table->decimal('average_percentage', 5, 2)->default(0);
            $table->string('overall_grade', 20)->nullable();
            $table->decimal('gpa', 4, 2)->nullable();
            $table->unsignedInteger('rank_in_section')->nullable();
            $table->unsignedInteger('total_students_in_section')->nullable();
            $table->text('homeroom_remarks')->nullable();
            $table->text('principal_remarks')->nullable();
            $table->json('attendance_summary')->nullable(); // total_days, present, absent, late, percentage
            $table->boolean('all_teachers_submitted')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'student_id', 'term_id']);
        });

        // 6. Report Card Items table
        Schema::create('report_card_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_card_id')->constrained('report_cards')->cascadeOnDelete()->index();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete()->index();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('marks_obtained', 6, 2)->default(0);
            $table->decimal('max_marks', 6, 2)->default(100);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->string('letter_grade', 20)->nullable();
            $table->decimal('gpa_point', 4, 2)->nullable();
            $table->text('teacher_remarks')->nullable();
            $table->json('exam_breakdown')->nullable();
            $table->timestamps();

            $table->unique(['report_card_id', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_card_items');
        Schema::dropIfExists('report_cards');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('grading_scales');
        Schema::dropIfExists('subjects');
    }
};
