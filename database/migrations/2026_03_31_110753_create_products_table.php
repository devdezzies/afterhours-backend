<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new \Illuminate\Database\Query\Expression('gen_random_uuid()'));
            $table->string('name');
            $table->text('description');
            $table->decimal('price', 15, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->enum('category', [
                'peripherals',
                'furniture',
                'desk_accessories',
                'audio',
                'eyewear',
            ]);

            $table->string('image_url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};