<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Enums\UserStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\ParentProfile;
use App\Models\School;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ParentSeeder extends Seeder
{
    public function run(): void
    {
        $tenantManager = app(TenantManager::class);

        // 1. Greenwood High Parents
        $greenwood = School::where('subdomain', 'greenwood')->first();
        if ($greenwood) {
            $tenantManager->setTenant($greenwood);
            app(PermissionRegistrar::class)->setPermissionsTeamId($greenwood->id);

            $parentRole = Role::firstOrCreate([
                'name' => RoleEnum::PARENT->value,
                'guard_name' => 'web',
                'school_id' => $greenwood->id,
            ]);

            // Homer Simpson
            $homerUser = User::where('email', 'parent@greenwood.edu')->first();
            if ($homerUser) {
                $homerProfile = ParentProfile::updateOrCreate(
                    ['school_id' => $greenwood->id, 'user_id' => $homerUser->id],
                    [
                        'occupation' => 'Nuclear Safety Inspector',
                        'phone' => '+1 (555) 733-4663',
                        'address' => '742 Evergreen Terrace, Springfield',
                        'emergency_contact' => '+1 (555) 733-4663',
                    ]
                );

                $bart = Student::where('school_id', $greenwood->id)
                    ->where('admission_number', 'GRE-25-00101')
                    ->first();
                $lisa = Student::where('school_id', $greenwood->id)
                    ->where('admission_number', 'GRE-25-00102')
                    ->first();

                if ($bart) {
                    $homerProfile->linkStudent($bart->id, 'father', true);
                }
                if ($lisa) {
                    $homerProfile->linkStudent($lisa->id, 'father', true);
                }
            }

            // Marge Simpson
            $margeUser = User::updateOrCreate(
                ['email' => 'marge@greenwood.edu'],
                [
                    'school_id' => $greenwood->id,
                    'name' => 'Marge Simpson',
                    'password' => Hash::make('password123'),
                    'role' => RoleEnum::PARENT->value,
                    'status' => UserStatus::ACTIVE,
                    'email_verified_at' => now(),
                ]
            );
            $margeUser->assignRole($parentRole);

            $margeProfile = ParentProfile::updateOrCreate(
                ['school_id' => $greenwood->id, 'user_id' => $margeUser->id],
                [
                    'occupation' => 'Homemaker',
                    'phone' => '+1 (555) 733-4664',
                    'address' => '742 Evergreen Terrace, Springfield',
                    'emergency_contact' => '+1 (555) 733-4664',
                ]
            );

            if (isset($bart) && $bart) {
                $margeProfile->linkStudent($bart->id, 'mother', false);
            }
            if (isset($lisa) && $lisa) {
                $margeProfile->linkStudent($lisa->id, 'mother', false);
            }

            $tenantManager->clearTenant();
        }

        // 2. Oakridge Academy Parents
        $oakridge = School::where('subdomain', 'oakridge')->first();
        if ($oakridge) {
            $tenantManager->setTenant($oakridge);
            app(PermissionRegistrar::class)->setPermissionsTeamId($oakridge->id);

            $parentRoleOak = Role::firstOrCreate([
                'name' => RoleEnum::PARENT->value,
                'guard_name' => 'web',
                'school_id' => $oakridge->id,
            ]);

            $studentRoleOak = Role::firstOrCreate([
                'name' => RoleEnum::STUDENT->value,
                'guard_name' => 'web',
                'school_id' => $oakridge->id,
            ]);

            // Ensure Oakridge has a student Harry Potter
            $harryUser = User::where('email', 'student@oakridge.edu')->first();
            if ($harryUser) {
                $harryUser->assignRole($studentRoleOak);
                $oakSec = Section::where('school_id', $oakridge->id)->first();
                $oakYear = AcademicYear::where('school_id', $oakridge->id)->first();

                $harryStudent = Student::updateOrCreate(
                    ['school_id' => $oakridge->id, 'admission_number' => 'OAK-25-00001'],
                    [
                        'user_id' => $harryUser->id,
                        'date_of_birth' => '2010-07-31',
                        'gender' => 'male',
                        'address' => '4 Privet Drive, Little Whinging',
                        'admission_date' => '2025-09-01',
                        'current_section_id' => $oakSec?->id,
                        'status' => 'active',
                        'guardian_info' => [
                            'name' => 'James Potter',
                            'phone' => '+1 (555) 444-7788',
                            'email' => 'parent@oakridge.edu',
                            'relationship' => 'Father',
                        ],
                    ]
                );

                if ($oakYear && $oakSec) {
                    Enrollment::updateOrCreate(
                        [
                            'academic_year_id' => $oakYear->id,
                            'student_id' => $harryStudent->id,
                        ],
                        [
                            'school_id' => $oakridge->id,
                            'section_id' => $oakSec->id,
                            'enrolled_at' => '2025-09-01',
                            'status' => 'enrolled',
                        ]
                    );
                }
            }

            // James Potter
            $jamesUser = User::where('email', 'parent@oakridge.edu')->first();
            if ($jamesUser && isset($harryStudent)) {
                $jamesProfile = ParentProfile::updateOrCreate(
                    ['school_id' => $oakridge->id, 'user_id' => $jamesUser->id],
                    [
                        'occupation' => 'Auror & Consultant',
                        'phone' => '+1 (555) 444-7788',
                        'address' => 'Godric Hollow',
                        'emergency_contact' => '+1 (555) 444-7788',
                    ]
                );

                $jamesProfile->linkStudent($harryStudent->id, 'father', true);
            }

            $tenantManager->clearTenant();
        }
    }
}
