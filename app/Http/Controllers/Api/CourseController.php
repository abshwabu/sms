<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Traits\HasApiResponse;
use App\Models\Course;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CourseController extends Controller
{
    use HasApiResponse;

    /**
     * Display a listing of courses for the active tenant.
     */
    public function index(): JsonResponse
    {
        // Eloquent TenantScope automatically limits this to the active school
        $courses = Course::orderBy('name')->get();

        return $this->respondWithSuccess($courses, 'Courses retrieved successfully.');
    }

    /**
     * Store a newly created course for the active tenant.
     */
    public function store(StoreCourseRequest $request): JsonResponse
    {
        // TenantScoped trait automatically injects active tenant's school_id
        $course = Course::create($request->validated());

        // Automatically create a corresponding Subject so courses show up in Timetable Scheduling
        Subject::firstOrCreate(
            [
                'school_id' => $course->school_id,
                'course_id' => $course->id,
                'grade_level_id' => null,
            ],
            [
                'name' => $course->name,
                'code' => $course->code,
                'credit_hours' => 1.0,
                'description' => $course->description,
                'is_elective' => false,
            ]
        );

        return $this->respondWithSuccess($course, 'Course created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Display the specified course for the active tenant.
     */
    public function show(Course $course): JsonResponse
    {
        return $this->respondWithSuccess($course, 'Course details retrieved successfully.');
    }

    /**
     * Remove the specified course for the active tenant.
     */
    public function destroy(Course $course): JsonResponse
    {
        Subject::where('course_id', $course->id)
            ->whereNull('grade_level_id')
            ->delete();

        $course->delete();

        return $this->respondWithSuccess(null, 'Course deleted successfully.');
    }
}
