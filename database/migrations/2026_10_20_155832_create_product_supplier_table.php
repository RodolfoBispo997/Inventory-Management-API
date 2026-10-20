<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_supplier', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2)->unsigned();
            $table->string('supplier_product_code', 100)->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'supplier_id']);
            $table->index(['product_id', 'price']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_supplier');
    }
};
