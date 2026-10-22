<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class OrganizationBackfillTest extends TestCase
{
    use DatabaseMigrations;

    public function test_backfills_existing_business_data_before_requiring_organization_ids(): void
    {
        $migration = require database_path('migrations/2026_10_22_144342_add_organization_id_to_business_tables.php');
        $migration->down();

        $timestamp = now();
        DB::table('users')->insert([
            'name' => 'Legacy User',
            'email' => 'legacy@example.test',
            'password' => Hash::make('a-secure-password'),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        DB::table('products')->insert([
            'name' => 'Legacy Product',
            'sku' => 'LEGACY-001',
            'minimum_stock' => 2,
            'current_stock' => 7,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        DB::table('suppliers')->insert([
            'name' => 'Legacy Supplier',
            'cnpj' => '12345678000190',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        DB::table('departments')->insert([
            'name' => 'Legacy Department',
            'code' => 'LEGACY',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $productId = DB::table('products')->value('id');
        $supplierId = DB::table('suppliers')->value('id');
        $departmentId = DB::table('departments')->value('id');
        DB::table('stock_movements')->insert([
            'product_id' => $productId,
            'department_id' => $departmentId,
            'type' => 'OUT',
            'quantity' => 1,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        DB::table('product_supplier')->insert([
            'product_id' => $productId,
            'supplier_id' => $supplierId,
            'price' => '10.00',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $organizationMigration = require database_path('migrations/2026_10_22_144341_create_organizations_table.php');
        $organizationMigration->up();
        $this->assertDatabaseHas('stock_movements', ['product_id' => $productId]);
        $migration->up();

        $organizationId = DB::table('organizations')->where('name', 'Organização legada')->value('id');

        $this->assertNotNull($organizationId);
        $this->assertDatabaseHas('users', ['email' => 'legacy@example.test', 'organization_id' => $organizationId, 'role' => 'admin']);
        $this->assertDatabaseHas('products', ['sku' => 'LEGACY-001', 'current_stock' => 7, 'organization_id' => $organizationId]);
        $this->assertDatabaseHas('suppliers', ['cnpj' => '12345678000190', 'organization_id' => $organizationId]);
        $this->assertDatabaseHas('departments', ['code' => 'LEGACY', 'organization_id' => $organizationId]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $productId, 'organization_id' => $organizationId]);
        $this->assertDatabaseHas('product_supplier', ['product_id' => $productId, 'supplier_id' => $supplierId, 'organization_id' => $organizationId]);

        foreach (['users', 'products', 'suppliers', 'departments', 'stock_movements', 'product_supplier'] as $tableName) {
            $columns = DB::select("PRAGMA table_info('{$tableName}')");
            $organizationColumn = collect($columns)->firstWhere('name', 'organization_id');

            $this->assertSame(1, $organizationColumn->notnull, "{$tableName}.organization_id must be required after the backfill.");
        }
    }

    public function test_rollback_refuses_duplicate_product_supplier_associations_without_changing_schema(): void
    {
        $organizations = DB::table('organizations')->insertGetId([
            'name' => 'Organization A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $secondOrganizationId = DB::table('organizations')->insertGetId([
            'name' => 'Organization B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'organization_id' => $organizations,
            'name' => 'Shared product',
            'sku' => 'SHARED-001',
            'minimum_stock' => 0,
            'current_stock' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $supplierId = DB::table('suppliers')->insertGetId([
            'organization_id' => $organizations,
            'name' => 'Shared supplier',
            'cnpj' => '12345678000190',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_supplier')->insert([
            [
                'organization_id' => $organizations,
                'product_id' => $productId,
                'supplier_id' => $supplierId,
                'price' => '10.00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organization_id' => $secondOrganizationId,
                'product_id' => $productId,
                'supplier_id' => $supplierId,
                'price' => '12.00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration = require database_path('migrations/2026_10_22_144342_add_organization_id_to_business_tables.php');

        try {
            $migration->down();
            $this->fail('Expected rollback to refuse duplicate product-supplier associations.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Cannot roll back organizations while duplicate product-supplier associations exist across organizations.',
                $exception->getMessage(),
            );
        }

        foreach (['users', 'products', 'suppliers', 'departments', 'stock_movements', 'product_supplier'] as $tableName) {
            $columns = collect(DB::select("PRAGMA table_info('{$tableName}')"))->pluck('name');
            $this->assertContains('organization_id', $columns, "{$tableName}.organization_id should remain after the refused rollback.");
        }

        $productSupplierIndexes = collect(DB::select("PRAGMA index_list('product_supplier')"));
        $this->assertTrue($productSupplierIndexes->contains(fn (object $index): bool => $index->name === 'product_supplier_organization_id_product_id_supplier_id_unique'));
        $this->assertFalse($productSupplierIndexes->contains(fn (object $index): bool => $index->name === 'product_supplier_product_id_supplier_id_unique'));
        $this->assertDatabaseCount('product_supplier', 2);
        $this->assertDatabaseHas('organizations', ['id' => $organizations]);
        $this->assertDatabaseHas('organizations', ['id' => $secondOrganizationId]);

        DB::table('product_supplier')
            ->where('organization_id', $secondOrganizationId)
            ->where('product_id', $productId)
            ->where('supplier_id', $supplierId)
            ->delete();
    }
}
