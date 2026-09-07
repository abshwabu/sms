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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('admission_number')->index();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->text('address')->nullable();
            $table->date('admission_date')->nullable();
            $table->foreignId('current_section_id')->nullable()->constrained('sections')->nullOnDelete()->index();
            $table->string('status', 30)->default('active'); // active, graduated, transferred, withdrawn
            $table->text('medical_notes')->nullable();
            $table->string('photo')->nullable();
            $table->json('guardian_info')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'admission_number']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete()->index();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete()->index();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete()->index();
            $table->date('enrolled_at');
            $table->string('status', 30)->default('enrolled'); // enrolled, promoted, graduated, retained, withdrawn
            $table->timestamps();

            // A student has exactly one enrollment per academic year
            $table->unique(['academic_year_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('students');
    }
};
