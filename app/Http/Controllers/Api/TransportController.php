<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignStudentTransportRequest;
use App\Http\Requests\BulkAssignSectionTransportRequest;
use App\Http\Requests\StoreTransportRouteRequest;
use App\Http\Requests\StoreTransportStopRequest;
use App\Http\Requests\UpdateTransportRouteRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Traits\HasApiResponse;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Services\TransportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class TransportController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected TransportService $transportService
    ) {}

    /**
     * List all transport routes for the school.
     */
    public function routes(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TransportRoute::class);

        $query = TransportRoute::withCount(['stops', 'studentAssignments' => function ($q) {
            $q->where('status', 'active');
        }])->with('stops');

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $routes = $query->get();

        return $this->respondWithSuccess($routes, 'Transport routes retrieved successfully.');
    }

    /**
     * Show a single transport route with its ordered stops and assigned students.
     */
    public function showRoute(TransportRoute $route): JsonResponse
    {
        Gate::authorize('viewAny', $route);

        $route->load([
            'stops' => function ($q) {
                $q->orderBy('sequence', 'asc');
            },
            'studentAssignments.student.user:id,name,email',
            'studentAssignments.student.currentSection:id,name',
            'studentAssignments.stop:id,stop_name,pickup_time,dropoff_time',
        ]);

        return $this->respondWithSuccess($route, 'Transport route details retrieved.');
    }

    /**
     * Create a new transport route with optional initial stops.
     * Acceptance criterion: Admin can build a route with ordered stops.
     */
    public function storeRoute(StoreTransportRouteRequest $request): JsonResponse
    {
        Gate::authorize('manage', TransportRoute::class);

        $data = $request->validated();
        $stops = $data['stops'] ?? [];
        unset($data['stops']);

        $route = $this->transportService->createRoute($data, $stops);

        return $this->respondWithSuccess($route, 'Transport route created successfully.', Response::HTTP_CREATED);
    }

    /**
     * Update an existing route and its stops.
     */
    public function updateRoute(UpdateTransportRouteRequest $request, TransportRoute $route): JsonResponse
    {
        Gate::authorize('manage', $route);

        $data = $request->validated();
        $stops = $data['stops'] ?? null;
        unset($data['stops']);

        $updatedRoute = $this->transportService->updateRoute($route, $data, $stops);

        return $this->respondWithSuccess($updatedRoute, 'Transport route updated successfully.');
    }

    /**
     * Delete a transport route.
     */
    public function destroyRoute(TransportRoute $route): JsonResponse
    {
        Gate::authorize('manage', $route);

        $this->transportService->deleteRoute($route);

        return $this->respondWithSuccess(null, 'Transport route deleted successfully.');
    }

    /**
     * Add a stop to a route.
     */
    public function storeStop(StoreTransportStopRequest $request, TransportRoute $route): JsonResponse
    {
        Gate::authorize('manage', $route);

        $stop = $this->transportService->addStop($route, $request->validated());

        return $this->respondWithSuccess($stop, 'Route stop added successfully.', Response::HTTP_CREATED);
    }

    /**
     * Update a stop.
     */
    public function updateStop(StoreTransportStopRequest $request, TransportStop $stop): JsonResponse
    {
        Gate::authorize('manage', $stop);

        $stop->update($request->validated());

        return $this->respondWithSuccess($stop, 'Route stop updated successfully.');
    }

    /**
     * Remove a stop from a route.
     */
    public function destroyStop(TransportStop $stop): JsonResponse
    {
        Gate::authorize('manage', $stop);

        $stop->delete();

        return $this->respondWithSuccess(null, 'Route stop deleted successfully.');
    }

    /**
     * Assign an individual student to a transport route and stop.
     */
    public function assignStudent(AssignStudentTransportRequest $request): JsonResponse
    {
        Gate::authorize('manage', StudentTransport::class);

        $student = Student::findOrFail($request->input('student_id'));
        $route = TransportRoute::findOrFail($request->input('transport_route_id'));
        $stop = TransportStop::findOrFail($request->input('transport_stop_id'));

        $assignment = $this->transportService->assignStudent($student, $route, $stop, $request->validated());

        return $this->respondWithSuccess($assignment, 'Student assigned to transport route successfully.');
    }

    /**
     * Bulk assign all (or selected) students in a section to a route and stop.
     * Acceptance criterion: Admin can assign a section's students in bulk.
     */
    public function bulkAssignSection(
        BulkAssignSectionTransportRequest $request,
        Section $section,
        TransportRoute $route
    ): JsonResponse {
        Gate::authorize('manage', StudentTransport::class);

        $stop = TransportStop::findOrFail($request->input('transport_stop_id'));
        $studentIds = $request->input('student_ids');

        $result = $this->transportService->bulkAssignSection($section, $route, $stop, $studentIds);

        return $this->respondWithSuccess($result, 'Section students bulk assigned to transport route.');
    }

    /**
     * Unassign a student from transport.
     */
    public function unassignStudent(Student $student): JsonResponse
    {
        Gate::authorize('manage', StudentTransport::class);

        $this->transportService->unassignStudent($student);

        return $this->respondWithSuccess(null, 'Student unassigned from transport.');
    }

    /**
     * List all student transport assignments.
     */
    public function assignments(Request $request): JsonResponse
    {
        Gate::authorize('manage', StudentTransport::class);

        $query = StudentTransport::with([
            'student.user:id,name,email',
            'student.currentSection:id,name',
            'route',
            'stop',
        ]);

        if ($request->filled('route_id')) {
            $query->where('transport_route_id', $request->input('route_id'));
        }

        if ($request->filled('stop_id')) {
            $query->where('transport_stop_id', $request->input('stop_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $assignments = $query->get();

        return $this->respondWithSuccess($assignments, 'Transport assignments retrieved successfully.');
    }

    /**
     * Student or Parent self-view of assigned bus route and stop.
     */
    public function myStudentTransport(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isStudent() && $user->student) {
            $payload = $this->transportService->getStudentTransportPayload($user->student);
            return $this->respondWithSuccess($payload, 'Student transport details retrieved.');
        }

        if ($user->isParent() && $user->parentProfile) {
            $query = $user->parentProfile->students();
            if ($request->filled('student_id')) {
                $student = $query->where('students.id', $request->input('student_id'))->first();
            } else {
                $student = $query->first();
            }

            if ($student) {
                $payload = $this->transportService->getStudentTransportPayload($student);
                return $this->respondWithSuccess($payload, 'Child transport details retrieved.');
            }

            return $this->respondWithSuccess([
                'assigned' => false,
                'has_transport' => false,
                'message' => 'No linked students found.',
            ], 'No linked students found.');
        }

        return ApiResponse::error(
            'Only student or parent accounts can view transport schedules.',
            'FORBIDDEN_STUDENT_ACCESS',
            Response::HTTP_FORBIDDEN
        );
    }

    /**
     * Parent view: shows child's route and stop/time.
     * Acceptance criterion: Parent sees pickup/dropoff time and stop name for their child.
     */
    public function parentChildTransport(Request $request, Student $student): JsonResponse
    {
        Gate::authorize('viewStudentTransport', [StudentTransport::class, $student]);

        $payload = $this->transportService->getStudentTransportPayload($student);

        return $this->respondWithSuccess($payload, 'Child transport schedule retrieved successfully.');
    }
}
