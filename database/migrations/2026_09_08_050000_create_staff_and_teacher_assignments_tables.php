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
        // 1. Staff table
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->index();
            $table->string('staff_number')->index();
            $table->string('role_title')->default('Teacher')->index();
            $table->string('department')->nullable()->index();
            $table->date('hire_date')->nullable();
            $table->string('status')->default('active')->index();
            $table->string('phone')->nullable();
            $table->text('qualification')->nullable();
            $table->json('subjects_taught')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'user_id']);
            $table->unique(['school_id', 'staff_number']);
        });

        // 2. Staff Course Pivot (General subjects staff is qualified to teach)
        Schema::create('course_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete()->index();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete()->index();
            $table->timestamps();

            $table->unique(['staff_id', 'course_id']);
        });

        // 3. Section Subject Teachers (Teacher-to-subject in a section for grading permissions)
        Schema::create('section_subject_teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete()->index();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete()->index();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete()->index();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete()->index();
            $table->timestamps();

            $table->unique(['section_id', 'course_id', 'staff_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('section_subject_teachers');
        Schema::dropIfExists('course_staff');
        Schema::dropIfExists('staff');
    }
};
