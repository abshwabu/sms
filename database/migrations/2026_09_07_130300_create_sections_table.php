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
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete()->index();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete()->index();
            $table->foreignId('grade_level_id')->constrained('grade_levels')->cascadeOnDelete()->index();
            $table->string('name');
            $table->unsignedInteger('capacity')->default(30);
            $table->foreignId('homeroom_teacher_id')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->timestamps();

            $table->unique(['academic_year_id', 'grade_level_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
