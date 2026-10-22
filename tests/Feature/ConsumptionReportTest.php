<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Organization;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumptionReportTest extends TestCase
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

    public function test_aggregates_out_movements_by_department_and_product_and_excludes_entries(): void
    {
        $operations = Department::factory()->for($this->organization)->create(['name' => 'Operations', 'code' => 'OPS']);
        $sales = Department::factory()->for($this->organization)->create(['name' => 'Sales', 'code' => 'SALES']);
        $desk = Product::factory()->for($this->organization)->create(['name' => 'Desk']);
        $chair = Product::factory()->for($this->organization)->create(['name' => 'Chair']);

        $this->recordMovement($desk, 'OUT', 2, $operations->id, '2026-04-01 00:00:00');
        $this->recordMovement($desk, 'OUT', 4, $operations->id, '2026-04-30 23:59:59');
        $this->recordMovement($chair, 'OUT', 2, $operations->id, '2026-04-15 12:00:00');
        $this->recordMovement($chair, 'OUT', 3, $sales->id, '2026-04-15 12:00:00');
        $this->recordMovement($desk, 'IN', 50, null, '2026-04-15 12:00:00');
        $this->recordMovement($desk, 'OUT', 100, $operations->id, '2026-03-31 23:59:59');

        $this->getJson('/api/reports/consumption?from=2026-04-01&to=2026-04-30')
            ->assertOk()
            ->assertJsonPath('data.from', '2026-04-01')
            ->assertJsonPath('data.to', '2026-04-30')
            ->assertJsonPath('data.departments.0.name', 'Operations')
            ->assertJsonPath('data.departments.0.consumed_quantity', 8)
            ->assertJsonPath('data.departments.0.movement_count', 3)
            ->assertJsonPath('data.departments.0.products.0.name', 'Chair')
            ->assertJsonPath('data.departments.0.products.0.consumed_quantity', 2)
            ->assertJsonPath('data.departments.0.products.1.name', 'Desk')
            ->assertJsonPath('data.departments.0.products.1.consumed_quantity', 6)
            ->assertJsonPath('data.departments.0.products.1.movement_count', 2)
            ->assertJsonPath('data.departments.1.name', 'Sales')
            ->assertJsonPath('data.departments.1.consumed_quantity', 3)
            ->assertJsonStructure(['data' => ['from', 'to', 'department_id', 'departments']]);
    }

    public function test_filters_report_by_inclusive_date_range_and_department(): void
    {
        $targetDepartment = Department::factory()->for($this->organization)->create(['name' => 'Assembly', 'code' => 'ASSY']);
        $otherDepartment = Department::factory()->for($this->organization)->create(['name' => 'Shipping', 'code' => 'SHIP']);
        $product = Product::factory()->for($this->organization)->create();

        $this->recordMovement($product, 'OUT', 2, $targetDepartment->id, '2026-05-10 00:00:00');
        $this->recordMovement($product, 'OUT', 5, $targetDepartment->id, '2026-05-10 23:59:59');
        $this->recordMovement($product, 'OUT', 7, $otherDepartment->id, '2026-05-10 10:00:00');
        $this->recordMovement($product, 'OUT', 11, $targetDepartment->id, '2026-05-11 00:00:00');

        $this->getJson('/api/reports/consumption?from=2026-05-10&to=2026-05-10&department_id='.$targetDepartment->id)
            ->assertOk()
            ->assertJsonPath('data.department_id', $targetDepartment->id)
            ->assertJsonCount(1, 'data.departments')
            ->assertJsonPath('data.departments.0.id', $targetDepartment->id)
            ->assertJsonPath('data.departments.0.consumed_quantity', 7)
            ->assertJsonPath('data.departments.0.movement_count', 2);
    }

    public function test_rejects_missing_invalid_and_reversed_report_dates(): void
    {
        $this->getJson('/api/reports/consumption')->assertUnprocessable()
            ->assertJsonValidationErrors(['from', 'to']);

        $this->getJson('/api/reports/consumption?from=10-05-2026&to=2026-05-09')
            ->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);

        $this->getJson('/api/reports/consumption?from=2026-05-10&to=2026-05-09')
            ->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_rejects_unknown_department_filter(): void
    {
        $this->getJson('/api/reports/consumption?from=2026-05-10&to=2026-05-10&department_id=999999')
            ->assertUnprocessable()->assertJsonValidationErrors('department_id');
    }

    private function recordMovement(Product $product, string $type, int $quantity, ?int $departmentId, string $createdAt): void
    {
        $movement = $product->movements()->create([
            'organization_id' => $product->organization_id,
            'type' => $type,
            'quantity' => $quantity,
            'department_id' => $departmentId,
        ]);

        StockMovement::query()->whereKey($movement->id)->update(['created_at' => $createdAt]);
    }
}
