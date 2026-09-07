<?php

namespace Tests\Feature;

use App\Models\School;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiResponseFormatTest extends TestCase
{
    use RefreshDatabase;

    protected School $greenwood;
    protected School $oakridge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SchoolSeeder::class);
        $this->greenwood = School::where('subdomain', 'greenwood')->firstOrFail();
        $this->oakridge = School::where('subdomain', 'oakridge')->firstOrFail();
    }

    public function test_api_success_response_structure(): void
    {
        $response = $this->withHeader('X-School-Id', 'greenwood')
            ->getJson('/api/courses');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'school_id', 'name', 'code', 'description', 'is_active', 'created_at', 'updated_at'],
                ],
                'meta' => [
                    'timestamp',
                    'version',
                    'tenant' => ['id', 'name', 'subdomain'],
                ],
            ])
            ->assertJsonPath('success', true);
    }

    public function test_api_validation_error_response_structure(): void
    {
        $response = $this->withHeader('X-School-Id', 'greenwood')
            ->postJson('/api/courses', [
                // Empty payload to trigger validation errors
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'error' => [
                    'code',
                    'message',
                    'details' => [
                        'name',
                        'code',
                    ],
                ],
            ])
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_duplicate_course_code_is_scoped_to_school(): void
    {
        // 1. Create a course in Greenwood
        $this->withHeader('X-School-Id', 'greenwood')
            ->postJson('/api/courses', [
                'name' => 'Data Structures',
                'code' => 'CS-101',
            ])
            ->assertStatus(201);

        // 2. Creating CS-101 again in Greenwood should fail validation (unique within school)
        $this->withHeader('X-School-Id', 'greenwood')
            ->postJson('/api/courses', [
                'name' => 'Duplicate Course',
                'code' => 'CS-101',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        // 3. Creating CS-101 in Oakridge SHOULD succeed (schools are isolated)
        $this->withHeader('X-School-Id', 'oakridge')
            ->postJson('/api/courses', [
                'name' => 'Data Structures in Oakridge',
                'code' => 'CS-101',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.school_id', $this->oakridge->id);
    }
}
