<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $businessTables = ['users', 'products', 'suppliers', 'departments', 'stock_movements', 'product_supplier'];
        $hasExistingData = collect($businessTables)->contains(fn (string $tableName): bool => DB::table($tableName)->exists());
        $legacyOrganizationId = $hasExistingData
            ? DB::table('organizations')->insertGetId([
                'name' => 'Organização legada',
                'created_at' => now(),
                'updated_at' => now(),
            ])
            : null;

        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 32)->default('admin')->after('email');
        });

        foreach ($businessTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            });
        }

        if ($legacyOrganizationId !== null) {
            foreach ($businessTables as $tableName) {
                DB::table($tableName)->update(['organization_id' => $legacyOrganizationId]);
            }
        }

        foreach ($businessTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('organization_id')->nullable(false)->change();
            });
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_sku_unique');
            $table->unique(['organization_id', 'sku']);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropUnique('suppliers_cnpj_unique');
            $table->unique(['organization_id', 'cnpj']);
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropUnique('departments_code_unique');
            $table->unique(['organization_id', 'code']);
        });

        Schema::table('product_supplier', function (Blueprint $table): void {
            $table->dropUnique('product_supplier_product_id_supplier_id_unique');
            $table->unique(['organization_id', 'product_id', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ([
            ['products', 'sku'],
            ['suppliers', 'cnpj'],
            ['departments', 'code'],
        ] as [$tableName, $column]) {
            $hasTenantDuplicates = DB::table($tableName)
                ->select($column)
                ->groupBy($column)
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if ($hasTenantDuplicates) {
                throw new RuntimeException("Cannot roll back organizations while duplicate {$column} values exist across organizations.");
            }
        }

        $hasTenantDuplicateAssociations = DB::table('product_supplier')
            ->select(['product_id', 'supplier_id'])
            ->groupBy('product_id', 'supplier_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasTenantDuplicateAssociations) {
            throw new RuntimeException('Cannot roll back organizations while duplicate product-supplier associations exist across organizations.');
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'sku']);
            $table->unique('sku');
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'cnpj']);
            $table->unique('cnpj');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'code']);
            $table->unique('code');
        });

        Schema::table('product_supplier', function (Blueprint $table): void {
            $table->dropUnique(['organization_id', 'product_id', 'supplier_id']);
            $table->unique(['product_id', 'supplier_id']);
        });

        foreach (['users', 'products', 'suppliers', 'departments', 'stock_movements', 'product_supplier'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('organization_id');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });

        Schema::dropIfExists('organizations');

    }
};
