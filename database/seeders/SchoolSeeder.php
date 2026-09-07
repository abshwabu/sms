<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\School;
use App\Models\User;
use App\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SchoolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantManager = app(TenantManager::class);

        // 1. Seed Greenwood High
        $greenwood = School::updateOrCreate(
            ['subdomain' => 'greenwood'],
            [
                'name' => 'Greenwood High',
                'subdomain' => 'greenwood',
                'logo' => 'https://images.unsplash.com/photo-1546410531-bb4caa6b424d?w=150',
                'address' => '742 Evergreen Terrace, Springfield, OR',
                'contact_info' => [
                    'email' => 'admin@greenwood.edu',
                    'phone' => '+1 (555) 123-4567',
                    'website' => 'https://greenwood.edu',
                ],
                'subscription_status' => 'active',
                'timezone' => 'America/New_York',
            ]
        );

        // Seed users and courses within Greenwood context
        $tenantManager->setTenant($greenwood);

        User::updateOrCreate(
            ['email' => 'principal@greenwood.edu'],
            [
                'school_id' => $greenwood->id,
                'name' => 'Principal Skinner',
                'password' => Hash::make('password123'),
            ]
        );

        $greenwoodCourses = [
            ['code' => 'BIO-101', 'name' => 'AP Biology', 'description' => 'Advanced placement study of molecular, cellular, and organismal biology.'],
            ['code' => 'HIST-201', 'name' => 'World History', 'description' => 'Comprehensive survey of major world civilizations and historical eras.'],
            ['code' => 'MATH-301', 'name' => 'AP Calculus AB', 'description' => 'Calculus focusing on derivatives, integrals, and fundamental theorems.'],
            ['code' => 'ENG-102', 'name' => 'English Literature', 'description' => 'Analysis and critical discussion of classic and modern literature.'],
        ];

        foreach ($greenwoodCourses as $courseData) {
            Course::updateOrCreate(
                ['code' => $courseData['code'], 'school_id' => $greenwood->id],
                $courseData
            );
        }

        // 2. Seed Oakridge Academy
        $oakridge = School::updateOrCreate(
            ['subdomain' => 'oakridge'],
            [
                'name' => 'Oakridge Academy',
                'subdomain' => 'oakridge',
                'logo' => 'https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=150',
                'address' => '100 Oakridge Parkway, Silicon Valley, CA',
                'contact_info' => [
                    'email' => 'office@oakridge.org',
                    'phone' => '+1 (555) 987-6543',
                    'website' => 'https://oakridge.org',
                ],
                'subscription_status' => 'active',
                'timezone' => 'America/Los_Angeles',
            ]
        );

        // Seed users and courses within Oakridge context
        $tenantManager->setTenant($oakridge);

        User::updateOrCreate(
            ['email' => 'dean@oakridge.org'],
            [
                'school_id' => $oakridge->id,
                'name' => 'Dean Thomas',
                'password' => Hash::make('password123'),
            ]
        );

        $oakridgeCourses = [
            ['code' => 'ROB-101', 'name' => 'Intro to Robotics & AI', 'description' => 'Fundamentals of autonomous robotics, kinematics, and intelligent agents.'],
            ['code' => 'ECON-201', 'name' => 'Principles of Economics', 'description' => 'Micro and macroeconomic foundations of market structures and fiscal policies.'],
            ['code' => 'ART-105', 'name' => 'Digital Media & Arts', 'description' => 'Digital illustration, UI prototyping, and visual storytelling.'],
            ['code' => 'ENV-202', 'name' => 'Environmental Science', 'description' => 'Investigation into sustainability, ecology, and climate systems.'],
        ];

        foreach ($oakridgeCourses as $courseData) {
            Course::updateOrCreate(
                ['code' => $courseData['code'], 'school_id' => $oakridge->id],
                $courseData
            );
        }

        // Clear tenant context after seeding
        $tenantManager->clearTenant();
    }
}
