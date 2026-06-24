<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (
            DB::getDriverName() !== 'pgsql'
            || ! Schema::hasTable('products')
            || ! Schema::hasTable('categories')
            || ! Schema::hasColumn('products', 'category_id')
        ) {
            return;
        }

        DB::statement('ALTER TABLE IF EXISTS products DROP CONSTRAINT IF EXISTS fk_products_category');
        DB::statement('ALTER TABLE products ALTER COLUMN category_id DROP NOT NULL');
        DB::statement('ALTER TABLE products ADD CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        if (
            DB::getDriverName() !== 'pgsql'
            || ! Schema::hasTable('products')
            || ! Schema::hasTable('categories')
            || ! Schema::hasColumn('products', 'category_id')
        ) {
            return;
        }

        DB::statement('ALTER TABLE IF EXISTS products DROP CONSTRAINT IF EXISTS fk_products_category');
        DB::statement('ALTER TABLE products ADD CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)');
    }
};
