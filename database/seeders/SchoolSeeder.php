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

        // Seed courses within Greenwood context
        $tenantManager->setTenant($greenwood);

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

        // Seed courses within Oakridge context
        $tenantManager->setTenant($oakridge);

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

        // 3. Seed Maplewood Elementary School (Elementary grades demo)
        $maplewood = School::updateOrCreate(
            ['subdomain' => 'maplewood'],
            [
                'name' => 'Maplewood Elementary School',
                'subdomain' => 'maplewood',
                'logo' => 'https://images.unsplash.com/photo-1509062522246-3755977927d7?w=150',
                'address' => '452 Maple Ave, Springfield, OR',
                'contact_info' => [
                    'email' => 'admin@maplewood.edu',
                    'phone' => '+1 (555) 456-7890',
                    'website' => 'https://maplewood.edu',
                ],
                'subscription_status' => 'active',
                'timezone' => 'America/New_York',
            ]
        );

        $tenantManager->setTenant($maplewood);

        $maplewoodCourses = [
            ['code' => 'ENG-ELEM', 'name' => 'Elementary English & Reading', 'description' => 'Phonics, reading comprehension, spelling, and creative writing.'],
            ['code' => 'MATH-ELEM', 'name' => 'Elementary Mathematics', 'description' => 'Arithmetic, basic geometry, fractions, and problem solving.'],
            ['code' => 'SCI-ELEM', 'name' => 'General Science & Nature', 'description' => 'Hands-on discovery of plants, animals, earth, and physical sciences.'],
            ['code' => 'ART-ELEM', 'name' => 'Arts & Crafts', 'description' => 'Drawing, painting, sculpture, and visual appreciation.'],
            ['code' => 'PE-ELEM', 'name' => 'Physical Education', 'description' => 'Health, motor skill development, sportsmanship, and games.'],
        ];

        foreach ($maplewoodCourses as $courseData) {
            Course::updateOrCreate(
                ['code' => $courseData['code'], 'school_id' => $maplewood->id],
                $courseData
            );
        }

        // Clear tenant context after seeding
        $tenantManager->clearTenant();
    }
}
