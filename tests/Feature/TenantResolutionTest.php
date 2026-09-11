<?php

namespace Tests\Feature;

use App\Models\School;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SchoolSeeder::class);
    }

    public function test_resolves_tenant_via_x_school_id_numeric_header(): void
    {
        $greenwood = School::where('subdomain', 'greenwood')->firstOrFail();

        $response = $this->withHeader('X-School-Id', (string) $greenwood->id)
            ->getJson('/api/health');

        $response->assertOk()
            ->assertJsonPath('data.tenant.resolved', true)
            ->assertJsonPath('data.tenant.id', $greenwood->id)
            ->assertJsonPath('data.tenant.subdomain', 'greenwood');
    }

    public function test_resolves_tenant_via_x_school_id_subdomain_string_header(): void
    {
        $oakridge = School::where('subdomain', 'oakridge')->firstOrFail();

        $response = $this->withHeader('X-School-Id', 'oakridge')
            ->getJson('/api/health');

        $response->assertOk()
            ->assertJsonPath('data.tenant.resolved', true)
            ->assertJsonPath('data.tenant.id', $oakridge->id)
            ->assertJsonPath('data.tenant.name', 'Oakridge Academy');
    }

    public function test_resolves_tenant_via_subdomain_host(): void
    {
        $greenwood = School::where('subdomain', 'greenwood')->firstOrFail();

        $response = $this->getJson('http://greenwood.localhost/api/health');

        $response->assertOk()
            ->assertJsonPath('data.tenant.resolved', true)
            ->assertJsonPath('data.tenant.id', $greenwood->id)
            ->assertJsonPath('data.tenant.subdomain', 'greenwood');
    }

    public function test_returns_404_when_invalid_x_school_id_is_provided(): void
    {
        $response = $this->withHeader('X-School-Id', 'non-existent-school')
            ->getJson('/api/health');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'TENANT_NOT_FOUND');
    }

    public function test_returns_404_when_unknown_subdomain_is_accessed(): void
    {
        $response = $this->getJson('http://unknown-school.localhost/api/health');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'TENANT_NOT_FOUND');
    }

    public function test_does_not_treat_sms_subdomain_as_tenant(): void
    {
        $response = $this->getJson('https://sms.abshewabu.dev/api/health');

        $response->assertOk()
            ->assertJsonPath('data.tenant.resolved', false);
    }

    public function test_matches_app_domain_and_resolves_nested_subdomain(): void
    {
        config(['app.domain' => 'sms.abshewabu.dev']);
        $greenwood = School::where('subdomain', 'greenwood')->firstOrFail();

        // Base domain is not a tenant
        $baseResponse = $this->getJson('https://sms.abshewabu.dev/api/health');
        $baseResponse->assertOk()
            ->assertJsonPath('data.tenant.resolved', false);

        // Nested tenant subdomain resolves
        $tenantResponse = $this->getJson('https://greenwood.sms.abshewabu.dev/api/health');
        $tenantResponse->assertOk()
            ->assertJsonPath('data.tenant.resolved', true)
            ->assertJsonPath('data.tenant.id', $greenwood->id);
    }

    public function test_ignores_subdomain_when_resolution_is_disabled(): void
    {
        config(['app.resolve_tenant_subdomain' => false]);

        $response = $this->getJson('http://unknown-school.localhost/api/health');

        $response->assertOk()
            ->assertJsonPath('data.tenant.resolved', false);
    }
}
