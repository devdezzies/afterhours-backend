<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number', 30)->nullable();
            $table->string('default_address', 500)->nullable();
            $table->string('address_city', 100)->nullable();
            $table->string('address_country_region', 100)->nullable();
            $table->string('address_postcode', 20)->nullable();
            $table->decimal('address_lat', 10, 7)->nullable();
            $table->decimal('address_lng', 10, 7)->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_country_region', 100)->nullable();
            $table->string('shipping_postcode', 20)->nullable();
            $table->string('shipping_phone_number', 30)->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->unique(['user_id', 'idempotency_key']);
            $table->decimal('shipping_lat', 10, 7)->nullable()->change();
            $table->decimal('shipping_lng', 10, 7)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'idempotency_key']);
            $table->dropColumn([
                'shipping_city',
                'shipping_country_region',
                'shipping_postcode',
                'shipping_phone_number',
                'idempotency_key',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone_number',
                'default_address',
                'address_city',
                'address_country_region',
                'address_postcode',
                'address_lat',
                'address_lng',
            ]);
        });
    }
};
