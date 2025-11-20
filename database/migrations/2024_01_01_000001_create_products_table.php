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
        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sku', 64)->unique();
            $table->string('name', 200)->index();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->index();
            $table->integer('stock')->default(0);
            $table->timestamps();

            // Performance: Composite index for common query pattern
            $table->index(['price', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

