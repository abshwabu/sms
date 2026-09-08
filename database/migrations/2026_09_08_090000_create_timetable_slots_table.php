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
        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete()->index();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete()->index();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete()->index();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->string('day_of_week', 15)->index(); // monday, tuesday, wednesday, thursday, friday, saturday, sunday
            $table->unsignedSmallInteger('period_number')->index(); // 1, 2, 3, 4, 5, 6, 7, 8
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('room', 50)->nullable();
            $table->string('color', 20)->nullable();
            $table->timestamps();

            // A section can only have one subject scheduled at a given day and period within an academic year
            $table->unique(['academic_year_id', 'section_id', 'day_of_week', 'period_number'], 'section_slot_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
    }
};
