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
        // 1. Transport Routes
        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. "Route 1 - Downtown Express"
            $table->string('vehicle_info'); // e.g. "Bus #42 (License: SPR-1042)"
            $table->string('driver_name'); // e.g. "Otto Mann"
            $table->string('driver_contact'); // e.g. "+1 (555) 348-7288"
            $table->unsignedInteger('capacity')->default(40);
            $table->string('status')->default('active'); // active, inactive
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'name']);
        });

        // 2. Route Stops
        Schema::create('transport_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transport_route_id')->constrained('transport_routes')->cascadeOnDelete();
            $table->string('stop_name'); // e.g. "Evergreen Terrace & Elm St"
            $table->string('pickup_time'); // e.g. "07:15"
            $table->string('dropoff_time'); // e.g. "15:45"
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->string('landmark')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'transport_route_id']);
            $table->index(['transport_route_id', 'sequence']);
        });

        // 3. Student Transport Assignment
        Schema::create('student_transport', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('transport_route_id')->constrained('transport_routes')->cascadeOnDelete();
            $table->foreignId('transport_stop_id')->constrained('transport_stops')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('status')->default('active'); // active, suspended, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id']);
            $table->index(['school_id', 'transport_route_id']);
            $table->index(['transport_route_id', 'transport_stop_id']);
            $table->unique(['school_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_transport');
        Schema::dropIfExists('transport_stops');
        Schema::dropIfExists('transport_routes');
    }
};
