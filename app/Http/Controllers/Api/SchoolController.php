<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\HasApiResponse;
use App\Models\School;
use Illuminate\Http\JsonResponse;

class SchoolController extends Controller
{
    use HasApiResponse;

    /**
     * Display a listing of available schools.
     */
    public function index(): JsonResponse
    {
        $schools = School::select(['id', 'name', 'subdomain', 'logo', 'subscription_status', 'timezone', 'created_at'])
            ->withCount('courses')
            ->get();

        return $this->respondWithSuccess($schools, 'Schools retrieved successfully.');
    }

    /**
     * Display the specified school.
     */
    public function show(School $school): JsonResponse
    {
        $school->loadCount('courses');

        return $this->respondWithSuccess($school, 'School details retrieved successfully.');
    }
}
