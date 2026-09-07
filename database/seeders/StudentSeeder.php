<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Enums\UserStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $tenantManager = app(TenantManager::class);

        $greenwood = School::where('subdomain', 'greenwood')->first();
        if (! $greenwood) {
            return;
        }

        $tenantManager->setTenant($greenwood);
        app(PermissionRegistrar::class)->setPermissionsTeamId($greenwood->id);

        $studentRole = Role::firstOrCreate([
            'name' => RoleEnum::STUDENT->value,
            'guard_name' => 'web',
            'school_id' => $greenwood->id,
        ]);

        $year2024 = AcademicYear::where('name', '2024/2025')->first();
        $year2025 = AcademicYear::where('name', '2025/2026')->first();

        $g9SecA = Section::where('name', 'Grade 9 - Section A')->first();
        $g10SecA = Section::where('name', 'Grade 10 - Section A')->first();

        $studentsData = [
            [
                'name' => 'Bart Simpson',
                'email' => 'bart.simpson@greenwood.edu',
                'admission_number' => 'GRE-25-00101',
                'dob' => '2010-04-01',
                'gender' => 'male',
                'address' => '742 Evergreen Terrace',
                'guardian_name' => 'Homer Simpson',
                'guardian_phone' => '+1 (555) 733-4663',
                'guardian_email' => 'parent@greenwood.edu',
                'section' => $g9SecA,
            ],
            [
                'name' => 'Lisa Simpson',
                'email' => 'lisa.simpson@greenwood.edu',
                'admission_number' => 'GRE-25-00102',
                'dob' => '2012-05-09',
                'gender' => 'female',
                'address' => '742 Evergreen Terrace',
                'guardian_name' => 'Marge Simpson',
                'guardian_phone' => '+1 (555) 733-4664',
                'guardian_email' => 'marge@greenwood.edu',
                'section' => $g9SecA,
            ],
            [
                'name' => 'Milhouse Van Houten',
                'email' => 'milhouse@greenwood.edu',
                'admission_number' => 'GRE-25-00103',
                'dob' => '2010-07-01',
                'gender' => 'male',
                'address' => '316 Pikeland Ave',
                'guardian_name' => 'Kirk Van Houten',
                'guardian_phone' => '+1 (555) 832-1111',
                'guardian_email' => 'kirk@greenwood.edu',
                'section' => $g9SecA,
            ],
            [
                'name' => 'Nelson Muntz',
                'email' => 'nelson@greenwood.edu',
                'admission_number' => 'GRE-25-00104',
                'dob' => '2009-10-15',
                'gender' => 'male',
                'address' => '12 High Street',
                'guardian_name' => 'Mrs. Muntz',
                'guardian_phone' => '+1 (555) 999-3322',
                'guardian_email' => 'muntz@greenwood.edu',
                'section' => $g10SecA,
            ],
            [
                'name' => 'Martin Prince',
                'email' => 'martin@greenwood.edu',
                'admission_number' => 'GRE-25-00105',
                'dob' => '2010-08-12',
                'gender' => 'male',
                'address' => '444 Academic Blvd',
                'guardian_name' => 'Gloria Prince',
                'guardian_phone' => '+1 (555) 234-8899',
                'guardian_email' => 'gloria@greenwood.edu',
                'section' => $g10SecA,
            ],
        ];

        foreach ($studentsData as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'school_id' => $greenwood->id,
                    'name' => $data['name'],
                    'password' => Hash::make('password123'),
                    'role' => RoleEnum::STUDENT->value,
                    'status' => UserStatus::ACTIVE,
                    'email_verified_at' => now(),
                ]
            );
            $user->assignRole($studentRole);

            $sectionId = $data['section'] ? $data['section']->id : null;

            $student = Student::updateOrCreate(
                ['school_id' => $greenwood->id, 'admission_number' => $data['admission_number']],
                [
                    'user_id' => $user->id,
                    'date_of_birth' => $data['dob'],
                    'gender' => $data['gender'],
                    'address' => $data['address'],
                    'admission_date' => '2025-09-01',
                    'current_section_id' => $sectionId,
                    'status' => 'active',
                    'guardian_info' => [
                        'name' => $data['guardian_name'],
                        'phone' => $data['guardian_phone'],
                        'email' => $data['guardian_email'],
                        'relationship' => 'Parent',
                    ],
                ]
            );

            if ($year2025 && $sectionId) {
                Enrollment::updateOrCreate(
                    [
                        'academic_year_id' => $year2025->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'school_id' => $greenwood->id,
                        'section_id' => $sectionId,
                        'enrolled_at' => '2025-09-01',
                        'status' => 'enrolled',
                    ]
                );
            }
        }

        $tenantManager->clearTenant();
    }
}
