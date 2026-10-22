<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->organization = $user->organization;
        Sanctum::actingAs($user);
    }

    public function test_creates_lists_and_shows_departments(): void
    {
        $this->postJson('/api/departments', [
            'name' => 'Manutenção',
            'code' => 'MAINT',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Manutenção')
            ->assertJsonPath('data.code', 'MAINT');

        $department = Department::query()->where('code', 'MAINT')->firstOrFail();

        $this->getJson('/api/departments')->assertOk()
            ->assertJsonPath('data.0.id', $department->id)
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->getJson("/api/departments/{$department->id}")->assertOk()
            ->assertJsonPath('data.name', 'Manutenção');
    }

    public function test_rejects_missing_duplicate_and_oversized_department_fields(): void
    {
        Department::factory()->for($this->organization)->create(['code' => 'MAINT']);

        $this->postJson('/api/departments', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code']);

        $this->postJson('/api/departments', [
            'name' => 'Another department',
            'code' => 'MAINT',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->postJson('/api/departments', [
            'name' => str_repeat('a', 101),
            'code' => str_repeat('B', 33),
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'code']);
    }

    public function test_returns_not_found_for_missing_department(): void
    {
        $this->getJson('/api/departments/999999')->assertNotFound();
    }
}
