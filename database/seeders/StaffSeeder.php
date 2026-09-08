<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Enums\UserStatus;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionSubjectTeacher;
use App\Models\Staff;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $tenantManager = app(TenantManager::class);

        // 1. Greenwood High Staff
        $greenwood = School::where('subdomain', 'greenwood')->first();
        if ($greenwood) {
            $tenantManager->setTenant($greenwood);
            app(PermissionRegistrar::class)->setPermissionsTeamId($greenwood->id);

            $teacherRole = Role::firstOrCreate([
                'name' => RoleEnum::TEACHER->value,
                'guard_name' => 'web',
                'school_id' => $greenwood->id,
            ]);

            $ednaUser = User::where('email', 'teacher@greenwood.edu')->first();
            if ($ednaUser) {
                $ednaStaff = Staff::updateOrCreate(
                    ['school_id' => $greenwood->id, 'user_id' => $ednaUser->id],
                    [
                        'staff_number' => 'STF-25-00001',
                        'role_title' => 'Grade 9 Homeroom & Literature Teacher',
                        'department' => 'Humanities',
                        'hire_date' => '2024-08-15',
                        'status' => 'active',
                        'phone' => '+1 (555) 732-2345',
                        'qualification' => 'M.Ed in Secondary Curriculum & Instruction',
                        'subjects_taught' => ['English Literature', 'AP Biology'],
                    ]
                );

                $courses = Course::whereIn('code', ['ENG-102', 'BIO-101'])->pluck('id');
                $syncData = [];
                foreach ($courses as $cId) {
                    $syncData[$cId] = ['school_id' => $greenwood->id];
                }
                $ednaStaff->courses()->sync($syncData);

                // Assign Edna as subject teacher for BIO-101 in Grade 9 - Section A
                $secA = Section::where('name', 'Grade 9 - Section A')->first();
                $bioCourse = Course::where('code', 'BIO-101')->first();
                if ($secA && $bioCourse) {
                    SectionSubjectTeacher::updateOrCreate(
                        [
                            'section_id' => $secA->id,
                            'course_id' => $bioCourse->id,
                            'staff_id' => $ednaStaff->id,
                        ],
                        [
                            'school_id' => $greenwood->id,
                            'academic_year_id' => $secA->academic_year_id,
                        ]
                    );
                }
            }

            // Additional Teacher: Elizabeth Hoover
            $hooverUser = User::updateOrCreate(
                ['email' => 'hoover@greenwood.edu'],
                [
                    'school_id' => $greenwood->id,
                    'name' => 'Elizabeth Hoover',
                    'password' => Hash::make('password123'),
                    'role' => RoleEnum::TEACHER->value,
                    'status' => UserStatus::ACTIVE,
                    'email_verified_at' => now(),
                ]
            );
            $hooverUser->assignRole($teacherRole);

            $hooverStaff = Staff::updateOrCreate(
                ['school_id' => $greenwood->id, 'user_id' => $hooverUser->id],
                [
                    'staff_number' => 'STF-25-00002',
                    'role_title' => 'Calculus & Mathematics Teacher',
                    'department' => 'Mathematics',
                    'hire_date' => '2024-09-01',
                    'status' => 'active',
                    'phone' => '+1 (555) 732-8822',
                    'qualification' => 'B.S. in Applied Mathematics',
                    'subjects_taught' => ['AP Calculus AB'],
                ]
            );

            $mathCourse = Course::where('code', 'MATH-301')->first();
            if ($mathCourse) {
                $hooverStaff->courses()->sync([$mathCourse->id => ['school_id' => $greenwood->id]]);
            }

            $tenantManager->clearTenant();
        }

        // 2. Oakridge Academy Staff
        $oakridge = School::where('subdomain', 'oakridge')->first();
        if ($oakridge) {
            $tenantManager->setTenant($oakridge);
            app(PermissionRegistrar::class)->setPermissionsTeamId($oakridge->id);

            $mcgonagall = User::where('email', 'teacher@oakridge.edu')->first();
            if ($mcgonagall) {
                $mcStaff = Staff::updateOrCreate(
                    ['school_id' => $oakridge->id, 'user_id' => $mcgonagall->id],
                    [
                        'staff_number' => 'OAK-STF-01',
                        'role_title' => 'Deputy Head & Senior Robotics Instructor',
                        'department' => 'Computer Science & Robotics',
                        'hire_date' => '2023-01-10',
                        'status' => 'active',
                        'phone' => '+1 (555) 444-1199',
                        'qualification' => 'Ph.D. in Autonomous Systems',
                        'subjects_taught' => ['Intro to Robotics & AI'],
                    ]
                );

                $robCourse = Course::where('code', 'ROB-101')->first();
                if ($robCourse) {
                    $mcStaff->courses()->sync([$robCourse->id => ['school_id' => $oakridge->id]]);
                }
            }

            $tenantManager->clearTenant();
        }
    }
}
