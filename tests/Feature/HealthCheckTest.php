<?php

namespace Tests\Feature;

use App\Models\School;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SchoolSeeder::class);
    }

    public function test_health_check_returns_200_without_tenant_context(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonPath('data.database', 'connected')
            ->assertJsonPath('data.tenant.resolved', false);
    }

    public function test_health_check_returns_200_with_tenant_context_resolved_via_header(): void
    {
        $school = School::where('subdomain', 'greenwood')->firstOrFail();

        $response = $this->withHeader('X-School-Id', 'greenwood')
            ->getJson('/api/health');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonPath('data.tenant.resolved', true)
            ->assertJsonPath('data.tenant.id', $school->id)
            ->assertJsonPath('data.tenant.name', 'Greenwood High')
            ->assertJsonPath('data.tenant.subdomain', 'greenwood')
            ->assertJsonPath('meta.tenant.id', $school->id);
    }

    public function test_health_check_returns_200_with_tenant_context_resolved_via_subdomain(): void
    {
        $school = School::where('subdomain', 'oakridge')->firstOrFail();

        $response = $this->getJson('http://oakridge.localhost/api/health');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonPath('data.tenant.resolved', true)
            ->assertJsonPath('data.tenant.id', $school->id)
            ->assertJsonPath('data.tenant.name', 'Oakridge Academy')
            ->assertJsonPath('data.tenant.subdomain', 'oakridge');
    }

    public function test_tenant_strict_health_endpoint_requires_tenant(): void
    {
        // Without header -> should fail with 400 TENANT_REQUIRED
        $response = $this->getJson('/api/tenant/health');
        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'TENANT_REQUIRED');

        // With header -> should succeed
        $responseWithTenant = $this->withHeader('X-School-Id', 'greenwood')
            ->getJson('/api/tenant/health');
        $responseWithTenant->assertOk()
            ->assertJsonPath('data.tenant.resolved', true);
    }
}
