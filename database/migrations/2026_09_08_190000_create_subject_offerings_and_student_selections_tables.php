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
        Schema::create('subject_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('elective'); // 'core', 'elective'
            $table->unsignedInteger('max_students')->nullable(); // capacity cap for electives
            $table->dateTime('enrollment_start')->nullable(); // window start
            $table->dateTime('enrollment_end')->nullable(); // window end
            $table->boolean('is_open')->default(true); // manual window toggle
            $table->timestamps();

            $table->unique(['academic_year_id', 'grade_level_id', 'subject_id'], 'subject_offerings_unique');
            $table->index(['school_id', 'grade_level_id']);
            $table->index(['school_id', 'academic_year_id']);
        });

        Schema::create('student_subject_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('enrolled'); // 'enrolled', 'dropped'
            $table->dateTime('selected_at');
            $table->foreignId('selected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id', 'subject_id'], 'student_subject_selections_unique');
            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'academic_year_id', 'subject_id']);
            $table->index(['school_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_subject_selections');
        Schema::dropIfExists('subject_offerings');
    }
};
