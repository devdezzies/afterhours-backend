<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100)->unique();
        });

        DB::table('categories')->insert([
            ['name' => 'peripherals'],
            ['name' => 'audio'],
            ['name' => 'furniture'],
            ['name' => 'eyewear'],
            ['name' => 'desk_accessories'],
        ]);

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description');
            $table->decimal('price', 15, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('category_id')->nullable();
            $table->foreign('category_id', 'fk_products_category')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();

            $table->string('image_url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
