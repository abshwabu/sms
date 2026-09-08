<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;

class TransportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantManager = app(TenantManager::class);

        $greenwood = School::where('subdomain', 'greenwood')->first();
        $oakridge = School::where('subdomain', 'oakridge')->first();
        $maplewood = School::where('subdomain', 'maplewood')->first();

        if ($greenwood) {
            $this->seedGreenwoodTransport($greenwood, $tenantManager);
        }

        if ($oakridge) {
            $this->seedOakridgeTransport($oakridge, $tenantManager);
        }

        if ($maplewood) {
            $this->seedMaplewoodTransport($maplewood, $tenantManager);
        }
    }

    protected function seedGreenwoodTransport(School $school, TenantManager $tenantManager): void
    {
        $tenantManager->setTenant($school);

        $academicYear = AcademicYear::where('school_id', $school->id)
            ->where('is_active', true)
            ->first() ?? AcademicYear::where('school_id', $school->id)->first();

        // 1. Route 101 - North Springfield Express
        $route1 = TransportRoute::updateOrCreate(
            [
                'school_id' => $school->id,
                'name' => 'Route 101 - North Springfield Express',
            ],
            [
                'vehicle_info' => 'Yellow Bus #14 (Ford Transit 35-Seater, Plate: SP-7821)',
                'driver_name' => 'Otto Mann',
                'driver_contact' => '+1-555-019-8765',
                'capacity' => 35,
                'status' => 'active',
                'description' => 'Covers North residential quadrant and town mall interchange.',
            ]
        );

        $stop1_1 = TransportStop::updateOrCreate(
            [
                'school_id' => $school->id,
                'transport_route_id' => $route1->id,
                'sequence' => 1,
            ],
            [
                'stop_name' => 'Evergreen Terrace & Maple St',
                'pickup_time' => '07:15:00',
                'dropoff_time' => '15:45:00',
                'landmark' => 'Near 742 Evergreen Terrace / Elementary Crossing',
            ]
        );

        $stop1_2 = TransportStop::updateOrCreate(
            [
                'school_id' => $school->id,
                'transport_route_id' => $route1->id,
                'sequence' => 2,
            ],
            [
                'stop_name' => 'Springfield Mall Transit Hub',
                'pickup_time' => '07:30:00',
                'dropoff_time' => '15:30:00',
                'landmark' => 'North entrance by Food Court clock tower',
            ]
        );

        $stop1_3 = TransportStop::updateOrCreate(
            [
                'school_id' => $school->id,
                'transport_route_id' => $route1->id,
                'sequence' => 3,
            ],
            [
                'stop_name' => 'Highland Heights Community Center',
                'pickup_time' => '07:45:00',
                'dropoff_time' => '15:15:00',
                'landmark' => 'Roundabout near community park pavilion',
            ]
        );

        // 2. Route 102 - South Hills & Valley Shuttle
        $route2 = TransportRoute::updateOrCreate(
            [
                'school_id' => $school->id,
                'name' => 'Route 102 - South Hills & Valley Shuttle',
            ],
            [
                'vehicle_info' => 'Blue Minibus #08 (Mercedes Sprinter 20-Seater, Plate: SP-4412)',
                'driver_name' => 'Carl Carlson',
                'driver_contact' => '+1-555-019-3321',
                'capacity' => 20,
                'status' => 'active',
                'description' => 'Serves southern subdivisions and riverside estates.',
            ]
        );

        $stop2_1 = TransportStop::updateOrCreate(
            [
                'school_id' => $school->id,
                'transport_route_id' => $route2->id,
                'sequence' => 1,
            ],
            [
                'stop_name' => 'Westwood Park & 14th Ave',
                'pickup_time' => '07:20:00',
                'dropoff_time' => '15:50:00',
                'landmark' => 'Recreation center parking gate',
            ]
        );

        $stop2_2 = TransportStop::updateOrCreate(
            [
                'school_id' => $school->id,
                'transport_route_id' => $route2->id,
                'sequence' => 2,
            ],
            [
                'stop_name' => 'Riverfront Promenade',
                'pickup_time' => '07:35:00',
                'dropoff_time' => '15:35:00',
                'landmark' => 'Library pavilion kiosk',
            ]
        );

        // 3. Assign students
        // Bart Simpson
        $bart = Student::where('school_id', $school->id)
            ->where('admission_number', 'GRE-25-00101')
            ->first();

        if ($bart) {
            StudentTransport::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'student_id' => $bart->id,
                ],
                [
                    'transport_route_id' => $route1->id,
                    'transport_stop_id' => $stop1_1->id,
                    'academic_year_id' => $academicYear?->id,
                    'status' => 'active',
                    'notes' => 'Morning pickup at Evergreen Terrace front gate',
                ]
            );
        }

        // Lisa Simpson
        $lisa = Student::where('school_id', $school->id)
            ->where('admission_number', 'GRE-25-00102')
            ->first();

        if ($lisa) {
            StudentTransport::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'student_id' => $lisa->id,
                ],
                [
                    'transport_route_id' => $route1->id,
                    'transport_stop_id' => $stop1_1->id,
                    'academic_year_id' => $academicYear?->id,
                    'status' => 'active',
                    'notes' => 'Carries saxophone; sits front row',
                ]
            );
        }

        // Assign additional section students if available
        $section = Section::where('school_id', $school->id)->first();
        if ($section) {
            $otherStudents = Student::where('school_id', $school->id)
                ->where('current_section_id', $section->id)
                ->whereNotIn('id', array_filter([$bart?->id, $lisa?->id]))
                ->take(3)
                ->get();

            foreach ($otherStudents as $idx => $student) {
                $stop = ($idx % 2 === 0) ? $stop1_2 : $stop1_3;
                StudentTransport::updateOrCreate(
                    [
                        'school_id' => $school->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'transport_route_id' => $route1->id,
                        'transport_stop_id' => $stop->id,
                        'academic_year_id' => $academicYear?->id,
                        'status' => 'active',
                        'notes' => 'Assigned via section roster',
                    ]
                );
            }
        }
    }

    protected function seedOakridgeTransport(School $school, TenantManager $tenantManager): void
    {
        $tenantManager->setTenant($school);

        $academicYear = AcademicYear::where('school_id', $school->id)
            ->where('is_active', true)
            ->first() ?? AcademicYear::where('school_id', $school->id)->first();

        $route = TransportRoute::updateOrCreate(
            [
                'school_id' => $school->id,
                'name' => 'Route 201 - Oakridge Westliner',
            ],
            [
                'vehicle_info' => 'Executive Coach #01 (Volvo 40-Seater, Plate: OA-9901)',
                'driver_name' => 'Arthur Pendelton',
                'driver_contact' => '+1-555-024-5500',
                'capacity' => 40,
                'status' => 'active',
                'description' => 'Exclusive Oakridge cross-county luxury express.',
            ]
        );

        $stop1 = TransportStop::updateOrCreate(
            [
                'school_id' => $school->id,
                'transport_route_id' => $route->id,
                'sequence' => 1,
            ],
            [
                'stop_name' => 'Oakridge Estate Gates',
                'pickup_time' => '07:25:00',
                'dropoff_time' => '15:40:00',
                'landmark' => 'Private gated entrance security booth',
            ]
        );

        $stop2 = TransportStop::updateOrCreate(
            [
                'school_id' => $school->id,
                'transport_route_id' => $route->id,
                'sequence' => 2,
            ],
            [
                'stop_name' => 'Pineview Club Square',
                'pickup_time' => '07:40:00',
                'dropoff_time' => '15:25:00',
                'landmark' => 'Golf clubhouse fountain',
            ]
        );

        $oakridgeStudent = Student::where('school_id', $school->id)->first();
        if ($oakridgeStudent) {
            StudentTransport::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'student_id' => $oakridgeStudent->id,
                ],
                [
                    'transport_route_id' => $route->id,
                    'transport_stop_id' => $stop1->id,
                    'academic_year_id' => $academicYear?->id,
                    'status' => 'active',
                    'notes' => 'Oakridge daily commuter',
                ]
            );
        }
    }

    protected function seedMaplewoodTransport(School $school, TenantManager $tenantManager): void
    {
        $tenantManager->setTenant($school);

        $academicYear = AcademicYear::where('school_id', $school->id)
            ->where('is_active', true)
            ->first() ?? AcademicYear::where('school_id', $school->id)->first();

        $route = TransportRoute::updateOrCreate(
            [
                'school_id' => $school->id,
                'name' => 'Route 10 - Maplewood Yellow Bus',
            ],
            [
                'vehicle_info' => 'Mini Bus #10 (Capacity: 25)',
                'driver_name' => 'Otto Mann (Elementary Route)',
                'driver_contact' => '+1 (555) 333-2211',
                'description' => 'Neighborhood morning pickup and afternoon dropoff for elementary grades.',
                'status' => 'active',
            ]
        );

        $stop1 = TransportStop::updateOrCreate(
            [
                'transport_route_id' => $route->id,
                'stop_name' => 'Elm Street & 5th Ave',
            ],
            [
                'school_id' => $school->id,
                'pickup_time' => '07:45:00',
                'dropoff_time' => '15:15:00',
                'sequence' => 1,
            ]
        );

        $stop2 = TransportStop::updateOrCreate(
            [
                'transport_route_id' => $route->id,
                'stop_name' => 'Maplewood Main Entrance',
            ],
            [
                'school_id' => $school->id,
                'pickup_time' => '08:05:00',
                'dropoff_time' => '15:00:00',
                'sequence' => 2,
            ]
        );

        $tommy = Student::where('school_id', $school->id)->where('admission_number', 'MAP-25-00101')->first();
        if ($tommy) {
            StudentTransport::updateOrCreate(
                [
                    'school_id' => $school->id,
                    'student_id' => $tommy->id,
                ],
                [
                    'transport_route_id' => $route->id,
                    'transport_stop_id' => $stop1->id,
                    'academic_year_id' => $academicYear?->id,
                    'status' => 'active',
                    'notes' => 'Elementary bus rider (Morning & Afternoon)',
                ]
            );
        }

        $tenantManager->clearTenant();
    }
}
