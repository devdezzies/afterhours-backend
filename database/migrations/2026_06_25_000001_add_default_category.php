<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        if (! Schema::hasColumn('categories', 'is_default')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('name')->index();
            });
        }

        DB::table('categories')->updateOrInsert(
            ['name' => 'default'],
            ['is_default' => true]
        );

        $defaultCategoryId = DB::table('categories')
            ->where('name', 'default')
            ->value('id');

        DB::table('categories')
            ->where('id', '!=', $defaultCategoryId)
            ->update(['is_default' => false]);

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'category_id')) {
            DB::table('products')
                ->whereNull('category_id')
                ->update(['category_id' => $defaultCategoryId]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('categories') || ! Schema::hasColumn('categories', 'is_default')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
