<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\School;
use App\Tenancy\Exceptions\TenantContextRequiredException;
use App\Tenancy\TenantManager;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopingTest extends TestCase
{
    use RefreshDatabase;

    protected TenantManager $tenantManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantManager = app(TenantManager::class);
        $this->tenantManager->clearTenant();
    }

    public function test_querying_tenant_model_without_resolved_tenant_throws_exception(): void
    {
        $this->seed(SchoolSeeder::class);
        $this->tenantManager->clearTenant();

        $this->expectException(TenantContextRequiredException::class);
        $this->expectExceptionMessage('Cannot query tenant-scoped model [App\Models\Course] without an active tenant context.');

        Course::all();
    }

    public function test_creating_tenant_model_without_resolved_tenant_throws_exception(): void
    {
        $this->seed(SchoolSeeder::class);
        $this->tenantManager->clearTenant();

        $this->expectException(TenantContextRequiredException::class);
        $this->expectExceptionMessage('Cannot create tenant model [App\Models\Course] without an active tenant context.');

        Course::create([
            'name' => 'Physics 101',
            'code' => 'PHYS-101',
        ]);
    }

    public function test_two_seeded_schools_cannot_see_each_others_data(): void
    {
        $this->seed(SchoolSeeder::class);

        $greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $oakridge = School::where('subdomain', 'oakridge')->firstOrFail();

        // 1. Act as Greenwood
        $this->tenantManager->setTenant($greenwood);
        $greenwoodCourses = Course::all();

        $this->assertCount(4, $greenwoodCourses);
        $this->assertTrue($greenwoodCourses->contains('code', 'BIO-101'));
        $this->assertTrue($greenwoodCourses->contains('code', 'HIST-201'));
        $this->assertFalse($greenwoodCourses->contains('code', 'ROB-101'));
        $this->assertFalse($greenwoodCourses->contains('code', 'ECON-201'));

        foreach ($greenwoodCourses as $course) {
            $this->assertEquals($greenwood->id, $course->school_id);
        }

        // 2. Act as Oakridge
        $this->tenantManager->setTenant($oakridge);
        $oakridgeCourses = Course::all();

        $this->assertCount(4, $oakridgeCourses);
        $this->assertTrue($oakridgeCourses->contains('code', 'ROB-101'));
        $this->assertTrue($oakridgeCourses->contains('code', 'ECON-201'));
        $this->assertFalse($oakridgeCourses->contains('code', 'BIO-101'));
        $this->assertFalse($oakridgeCourses->contains('code', 'HIST-201'));

        foreach ($oakridgeCourses as $course) {
            $this->assertEquals($oakridge->id, $course->school_id);
        }
    }

    public function test_creating_course_auto_assigns_active_tenant_id(): void
    {
        $this->seed(SchoolSeeder::class);
        $greenwood = School::where('subdomain', 'greenwood')->firstOrFail();

        $this->tenantManager->setTenant($greenwood);

        $course = Course::create([
            'name' => 'Chemistry Honors',
            'code' => 'CHEM-202',
            'description' => 'Organic and inorganic chemistry principles.',
        ]);

        $this->assertEquals($greenwood->id, $course->school_id);

        // Verify that Oakridge cannot see this new course
        $oakridge = School::where('subdomain', 'oakridge')->firstOrFail();
        $this->tenantManager->setTenant($oakridge);

        $this->assertNull(Course::where('code', 'CHEM-202')->first());
    }

    public function test_scoping_can_be_bypassed_using_tenant_manager(): void
    {
        $this->seed(SchoolSeeder::class);
        $this->tenantManager->clearTenant();

        $allCourses = $this->tenantManager->bypassTenantScoping(function () {
            return Course::all();
        });

        // 4 from Greenwood + 4 from Oakridge = 8 total
        $this->assertCount(8, $allCourses);
    }
}
