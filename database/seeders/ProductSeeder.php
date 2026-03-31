<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/products.json');

        if (!file_exists($path)) {
            $this->command->error("products.json not found at: {$path}");
            return;
        }

        $products = json_decode(file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('Invalid JSON: ' . json_last_error_msg());
            return;
        }

        $rows = array_map(function (array $product) {
            return [
                'id'          => $product['id'],
                'name'        => $product['name'],
                'description' => $product['description'],
                'price'       => $product['price'],
                'stock'       => $product['stock'],
                'category'    => $product['category'],
                'image_url'   => $product['image_url'],
                'created_at'  => $product['created_at'],
                'updated_at'  => $product['updated_at'],
            ];
        }, $products);

        DB::table('products')->upsert(
            $rows,
            ['id'],                                                  
            ['name', 'description', 'price', 'stock', 'category',  
             'image_url', 'updated_at']
        );

        $this->command->info("Seeded " . count($rows) . " products.");
    }
}