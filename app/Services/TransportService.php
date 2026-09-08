<?php

namespace App\Services;

use App\Models\Section;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransportService
{
    /**
     * Create a new transport route with optional initial ordered stops.
     */
    public function createRoute(array $data, array $stops = []): TransportRoute
    {
        return DB::transaction(function () use ($data, $stops) {
            /** @var TransportRoute $route */
            $route = TransportRoute::create($data);

            if (! empty($stops)) {
                foreach ($stops as $idx => $stopData) {
                    TransportStop::create([
                        'school_id' => $route->school_id,
                        'transport_route_id' => $route->id,
                        'stop_name' => $stopData['stop_name'],
                        'pickup_time' => $stopData['pickup_time'],
                        'dropoff_time' => $stopData['dropoff_time'],
                        'sequence' => $stopData['sequence'] ?? ($idx + 1),
                        'landmark' => $stopData['landmark'] ?? null,
                    ]);
                }
            }

            return $route->load('stops');
        });
    }

    /**
     * Update an existing transport route and optionally sync/replace stops.
     */
    public function updateRoute(TransportRoute $route, array $data, ?array $stops = null): TransportRoute
    {
        return DB::transaction(function () use ($route, $data, $stops) {
            $route->update($data);

            if ($stops !== null) {
                // Delete existing stops and re-create ordered sequence
                $route->stops()->delete();
                foreach ($stops as $idx => $stopData) {
                    TransportStop::create([
                        'school_id' => $route->school_id,
                        'transport_route_id' => $route->id,
                        'stop_name' => $stopData['stop_name'],
                        'pickup_time' => $stopData['pickup_time'],
                        'dropoff_time' => $stopData['dropoff_time'],
                        'sequence' => $stopData['sequence'] ?? ($idx + 1),
                        'landmark' => $stopData['landmark'] ?? null,
                    ]);
                }
            }

            return $route->fresh('stops');
        });
    }

    /**
     * Delete a transport route.
     */
    public function deleteRoute(TransportRoute $route): bool
    {
        return (bool) $route->delete();
    }

    /**
     * Add a single stop to an existing route.
     */
    public function addStop(TransportRoute $route, array $data): TransportStop
    {
        if (empty($data['sequence'])) {
            $maxSeq = (int) $route->stops()->max('sequence');
            $data['sequence'] = $maxSeq + 1;
        }

        $data['school_id'] = $route->school_id;
        $data['transport_route_id'] = $route->id;

        return TransportStop::create($data);
    }

    /**
     * Assign a single student to a bus route and specific stop.
     */
    public function assignStudent(Student $student, TransportRoute $route, TransportStop $stop, array $data = []): StudentTransport
    {
        if ((int) $stop->transport_route_id !== (int) $route->id) {
            throw ValidationException::withMessages([
                'stop_id' => "Stop '{$stop->stop_name}' does not belong to route '{$route->name}'.",
            ]);
        }

        return DB::transaction(function () use ($student, $route, $stop, $data) {
            return StudentTransport::updateOrCreate(
                [
                    'school_id' => $student->school_id,
                    'student_id' => $student->id,
                ],
                [
                    'transport_route_id' => $route->id,
                    'transport_stop_id' => $stop->id,
                    'academic_year_id' => $data['academic_year_id'] ?? $student->currentSection?->academic_year_id,
                    'status' => $data['status'] ?? 'active',
                    'notes' => $data['notes'] ?? null,
                ]
            )->load(['student.user', 'route', 'stop']);
        });
    }

    /**
     * Bulk assign an entire section's roster of students to a bus route and stop.
     * Acceptance criterion: Admin can build a route with ordered stops and assign a section's students in bulk.
     */
    public function bulkAssignSection(Section $section, TransportRoute $route, TransportStop $stop, ?array $studentIds = null): array
    {
        if ((int) $stop->transport_route_id !== (int) $route->id) {
            throw ValidationException::withMessages([
                'stop_id' => "Stop '{$stop->stop_name}' does not belong to route '{$route->name}'.",
            ]);
        }

        return DB::transaction(function () use ($section, $route, $stop, $studentIds) {
            $query = Student::where('school_id', $section->school_id)
                ->where('current_section_id', $section->id)
                ->where('status', 'active');

            if (! empty($studentIds)) {
                $query->whereIn('id', $studentIds);
            }

            $students = $query->get();
            $assigned = [];

            foreach ($students as $student) {
                $assignment = StudentTransport::updateOrCreate(
                    [
                        'school_id' => $section->school_id,
                        'student_id' => $student->id,
                    ],
                    [
                        'transport_route_id' => $route->id,
                        'transport_stop_id' => $stop->id,
                        'academic_year_id' => $section->academic_year_id,
                        'status' => 'active',
                        'notes' => "Bulk assigned from Section {$section->name}",
                    ]
                );

                $assigned[] = [
                    'student_id' => $student->id,
                    'name' => $student->user?->name,
                    'admission_number' => $student->admission_number,
                    'assignment_id' => $assignment->id,
                ];
            }

            return [
                'section' => [
                    'id' => $section->id,
                    'name' => $section->name,
                ],
                'route' => [
                    'id' => $route->id,
                    'name' => $route->name,
                ],
                'stop' => [
                    'id' => $stop->id,
                    'stop_name' => $stop->stop_name,
                    'pickup_time' => $stop->pickup_time,
                    'dropoff_time' => $stop->dropoff_time,
                ],
                'route_id' => $route->id,
                'stop_id' => $stop->id,
                'assigned_count' => count($assigned),
                'students' => $assigned,
            ];
        });
    }

    /**
     * Unassign a student from their transport route.
     */
    public function unassignStudent(Student $student): bool
    {
        return (bool) StudentTransport::where('student_id', $student->id)->delete();
    }

    /**
     * Format full transport payload for a student.
     * Acceptance criterion: Parent sees pickup/dropoff time and stop name for their child.
     */
    public function getStudentTransportPayload(Student $student): array
    {
        $assignment = StudentTransport::where('student_id', $student->id)
            ->with(['route.stops', 'stop'])
            ->first();

        if (! $assignment || ! $assignment->route || ! $assignment->stop) {
            return [
                'assigned' => false,
                'has_transport' => false,
                'student' => [
                    'id' => $student->id,
                    'name' => $student->user?->name,
                    'admission_number' => $student->admission_number,
                ],
                'message' => 'No active transport route assigned for this student.',
            ];
        }

        $route = $assignment->route;
        $stop = $assignment->stop;

        $allStops = $route->stops->map(function ($s) use ($stop) {
            $isAssigned = (int) $s->id === (int) $stop->id;
            return [
                'id' => $s->id,
                'stop_name' => $s->stop_name,
                'pickup_time' => $s->pickup_time,
                'dropoff_time' => $s->dropoff_time,
                'sequence' => $s->sequence,
                'landmark' => $s->landmark,
                'is_assigned_stop' => $isAssigned,
                'is_child_stop' => $isAssigned,
            ];
        })->values();

        return [
            'assigned' => true,
            'has_transport' => true,
            'assignment_id' => $assignment->id,
            'status' => $assignment->status,
            'student' => [
                'id' => $student->id,
                'name' => $student->user?->name,
                'admission_number' => $student->admission_number,
            ],
            'route' => [
                'id' => $route->id,
                'name' => $route->name,
                'vehicle_info' => $route->vehicle_info,
                'driver_name' => $route->driver_name,
                'driver_contact' => $route->driver_contact,
                'capacity' => $route->capacity,
                'status' => $route->status,
            ],
            'stop' => [
                'id' => $stop->id,
                'stop_name' => $stop->stop_name,
                'pickup_time' => $stop->pickup_time,
                'dropoff_time' => $stop->dropoff_time,
                'sequence' => $stop->sequence,
                'landmark' => $stop->landmark,
            ],
            // Direct top-level fields for convenience
            'route_name' => $route->name,
            'vehicle_info' => $route->vehicle_info,
            'driver_name' => $route->driver_name,
            'driver_contact' => $route->driver_contact,
            'stop_name' => $stop->stop_name,
            'pickup_time' => $stop->pickup_time,
            'dropoff_time' => $stop->dropoff_time,
            'sequence' => $stop->sequence,
            'all_route_stops' => $allStops,
        ];
    }
}
