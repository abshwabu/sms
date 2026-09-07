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
        Schema::create('student_section_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete()->index();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete()->index();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete()->index();
            $table->string('roll_number', 50)->nullable();
            $table->string('status', 30)->default('enrolled'); // enrolled, promoted, graduated, retained
            $table->date('enrolled_at')->nullable();
            $table->timestamps();

            // A student belongs to exactly one section per academic year
            $table->unique(['academic_year_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_section_assignments');
    }
};
