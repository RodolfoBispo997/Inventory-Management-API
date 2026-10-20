<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Product;
use App\Models\User;
use App\Notifications\StockMinimumReachedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockMinimumAlertTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_queues_notification_when_exit_crosses_minimum_stock(): void
    {
        Queue::fake();
        config()->set('services.stock_alert.email', 'warehouse@example.test');

        $product = Product::factory()->create(['minimum_stock' => 5, 'current_stock' => 8]);
        $department = Department::factory()->create();

        $this->postJson("/api/products/{$product->id}/movements", [
            'type' => 'OUT',
            'quantity' => 3,
            'department_id' => $department->id,
        ])->assertCreated();

        Queue::assertPushed(SendQueuedNotifications::class, function (SendQueuedNotifications $job): bool {
            return $job->notification instanceof StockMinimumReachedNotification
                && $job->notification->currentStock === 5
                && $job->notification->minimumStock === 5
                && $job->notification instanceof ShouldQueue
                && $job->afterCommit === true
                && $job->notifiables->first()->routeNotificationFor('mail') === 'warehouse@example.test';
        });

        $this->assertDatabaseHas('products', ['id' => $product->id, 'current_stock' => 5]);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_does_not_queue_repeated_exits_while_below_minimum(): void
    {
        Queue::fake();
        config()->set('services.stock_alert.email', 'warehouse@example.test');

        $product = Product::factory()->create(['minimum_stock' => 5, 'current_stock' => 8]);
        $department = Department::factory()->create();

        $this->postJson("/api/products/{$product->id}/movements", ['type' => 'OUT', 'quantity' => 4, 'department_id' => $department->id])->assertCreated();
        $this->postJson("/api/products/{$product->id}/movements", ['type' => 'OUT', 'quantity' => 1, 'department_id' => $department->id])->assertCreated();

        Queue::assertPushed(SendQueuedNotifications::class, 1);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'current_stock' => 3]);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_queues_another_alert_after_entry_recovers_stock_above_minimum(): void
    {
        Queue::fake();
        config()->set('services.stock_alert.email', 'warehouse@example.test');

        $product = Product::factory()->create(['minimum_stock' => 5, 'current_stock' => 8]);
        $department = Department::factory()->create();

        $this->postJson("/api/products/{$product->id}/movements", ['type' => 'OUT', 'quantity' => 4, 'department_id' => $department->id])->assertCreated();
        $this->postJson("/api/products/{$product->id}/movements", ['type' => 'IN', 'quantity' => 3])->assertCreated();
        $this->postJson("/api/products/{$product->id}/movements", ['type' => 'OUT', 'quantity' => 2, 'department_id' => $department->id])->assertCreated();

        Queue::assertPushed(SendQueuedNotifications::class, 2);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'current_stock' => 5]);
        $this->assertDatabaseCount('stock_movements', 3);
    }

    public function test_rejected_exit_does_not_queue_notification_or_change_stock_history(): void
    {
        Queue::fake();
        config()->set('services.stock_alert.email', 'warehouse@example.test');

        $product = Product::factory()->create(['minimum_stock' => 5, 'current_stock' => 6]);
        $department = Department::factory()->create();

        $this->postJson("/api/products/{$product->id}/movements", ['type' => 'OUT', 'quantity' => 7, 'department_id' => $department->id])
            ->assertUnprocessable();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'current_stock' => 6]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_does_not_queue_alert_when_recipient_is_not_configured(): void
    {
        Queue::fake();
        config()->set('services.stock_alert.email', null);

        $product = Product::factory()->create(['minimum_stock' => 5, 'current_stock' => 8]);
        $department = Department::factory()->create();

        $this->postJson("/api/products/{$product->id}/movements", ['type' => 'OUT', 'quantity' => 3, 'department_id' => $department->id])->assertCreated();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'current_stock' => 5]);
        $this->assertDatabaseCount('stock_movements', 1);
    }
}
