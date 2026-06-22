<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    private const IDR_PRICES = [
        '29.99' => 499000,
        '69' => 1099000,
        '80' => 1299000,
        '99.99' => 1599000,
        '110' => 1799000,
        '129' => 2099000,
        '142' => 2299000,
        '149' => 2399000,
        '150' => 2499000,
        '160' => 2599000,
        '169' => 2699000,
        '170' => 2799000,
        '179' => 2899000,
        '180' => 2999000,
        '190' => 3099000,
        '194' => 3199000,
        '199' => 3299000,
        '200' => 3399000,
        '220' => 3599000,
        '249' => 4099000,
        '260' => 4299000,
        '269' => 4499000,
        '290' => 4799000,
        '310' => 5099000,
        '349' => 5699000,
        '350' => 5799000,
        '379' => 6199000,
        '399' => 6499000,
        '420' => 6899000,
        '429' => 6999000,
        '459' => 7499000,
        '480' => 7799000,
        '499' => 8199000,
        '520' => 8499000,
        '599' => 9799000,
        '650' => 10599000,
        '699' => 11399000,
        '780' => 12699000,
        '1299' => 21199000,
    ];

    public function run(): void
    {
        $path = database_path('data/products.json');

        $products = json_decode(file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('Invalid JSON: ' . json_last_error_msg());
            return;
        }

        $rows = array_map(function (array $product) {
            return [
                'id'=> $product['id'],
                'name' => $product['name'],
                'description' => $product['description'],
                'price' => self::IDR_PRICES[(string) $product['price']]
                    ?? throw new \RuntimeException("Missing curated IDR price for {$product['name']}"),
                'stock' => $product['stock'],
                'category' => $product['category'],
                'image_url' => $product['image_url'],
                'created_at' => $product['created_at'],
                'updated_at' => $product['updated_at'],
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
