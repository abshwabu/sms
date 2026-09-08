<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Enums\UserStatus;
use App\Models\School;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Create Super Admin role (platform level, school_id = 0)
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);
        $superAdminRole = Role::firstOrCreate([
            'name' => RoleEnum::SUPER_ADMIN->value,
            'guard_name' => 'web',
            'school_id' => 0,
        ]);

        // Create Super Admin user
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@bina.test'],
            [
                'school_id' => null,
                'name' => 'Platform Super Admin',
                'password' => Hash::make('password123'),
                'role' => RoleEnum::SUPER_ADMIN->value,
                'status' => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->assignRole($superAdminRole);

        // 2. Base permissions for schools
        $permissions = [
            'manage-school',
            'invite-staff',
            'create-users',
            'manage-courses',
            'view-courses',
            'view-students',
            'manage-students',
            'view-attendance',
            'record-attendance',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
        }

        // 3. For each school, seed roles and assign permissions
        $schools = School::all();

        foreach ($schools as $school) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

            // Create Roles for this school
            $schoolAdminRole = Role::firstOrCreate([
                'name' => RoleEnum::SCHOOL_ADMIN->value,
                'guard_name' => 'web',
                'school_id' => $school->id,
            ]);
            $schoolAdminRole->syncPermissions([
                'manage-school',
                'invite-staff',
                'create-users',
                'manage-courses',
                'view-courses',
                'view-students',
                'manage-students',
                'view-attendance',
                'record-attendance',
            ]);

            $teacherRole = Role::firstOrCreate([
                'name' => RoleEnum::TEACHER->value,
                'guard_name' => 'web',
                'school_id' => $school->id,
            ]);
            $teacherRole->syncPermissions([
                'view-courses',
                'manage-courses',
                'view-students',
                'view-attendance',
                'record-attendance',
            ]);

            $studentRole = Role::firstOrCreate([
                'name' => RoleEnum::STUDENT->value,
                'guard_name' => 'web',
                'school_id' => $school->id,
            ]);
            $studentRole->syncPermissions([
                'view-courses',
                'view-attendance',
            ]);

            $parentRole = Role::firstOrCreate([
                'name' => RoleEnum::PARENT->value,
                'guard_name' => 'web',
                'school_id' => $school->id,
            ]);
            $parentRole->syncPermissions([
                'view-courses',
                'view-attendance',
                'view-students',
            ]);

            // Seed sample accounts for this school
            $users = [
                [
                    'name' => match ($school->subdomain) {
                        'greenwood' => 'Principal Skinner',
                        'oakridge' => 'Dean Thomas',
                        'maplewood' => 'Principal Audrey Chen',
                        default => 'School Administrator',
                    },
                    'email' => "admin@{$school->subdomain}.edu",
                    'role' => RoleEnum::SCHOOL_ADMIN,
                    'role_model' => $schoolAdminRole,
                ],
                [
                    'name' => match ($school->subdomain) {
                        'greenwood' => 'Edna Krabappel',
                        'oakridge' => 'Minerva McGonagall',
                        'maplewood' => 'Clara Johnson',
                        default => 'Faculty Teacher',
                    },
                    'email' => "teacher@{$school->subdomain}.edu",
                    'role' => RoleEnum::TEACHER,
                    'role_model' => $teacherRole,
                ],
                [
                    'name' => match ($school->subdomain) {
                        'greenwood' => 'Bart Simpson',
                        'oakridge' => 'Harry Potter',
                        'maplewood' => 'Tommy Vance',
                        default => 'Student User',
                    },
                    'email' => "student@{$school->subdomain}.edu",
                    'role' => RoleEnum::STUDENT,
                    'role_model' => $studentRole,
                ],
                [
                    'name' => match ($school->subdomain) {
                        'greenwood' => 'Homer Simpson',
                        'oakridge' => 'James Potter',
                        'maplewood' => 'Sarah Vance',
                        default => 'Parent User',
                    },
                    'email' => "parent@{$school->subdomain}.edu",
                    'role' => RoleEnum::PARENT,
                    'role_model' => $parentRole,
                ],
            ];

            foreach ($users as $userData) {
                $user = User::updateOrCreate(
                    ['email' => $userData['email']],
                    [
                        'school_id' => $school->id,
                        'name' => $userData['name'],
                        'password' => Hash::make('password123'),
                        'role' => $userData['role']->value,
                        'status' => UserStatus::ACTIVE,
                        'email_verified_at' => now(),
                    ]
                );

                // Assign tenant-scoped role
                $user->assignRole($userData['role_model']);
            }
        }

        // Reset tenant team context
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }
}
