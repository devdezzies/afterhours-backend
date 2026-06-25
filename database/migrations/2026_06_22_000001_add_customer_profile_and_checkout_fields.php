<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('orders')) {
            return;
        }

        if (! Schema::hasColumn('users', 'phone_number')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('phone_number', 30)->nullable());
        }
        if (! Schema::hasColumn('users', 'default_address')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('default_address', 500)->nullable());
        }
        if (! Schema::hasColumn('users', 'address_city')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('address_city', 100)->nullable());
        }
        if (! Schema::hasColumn('users', 'address_country_region')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('address_country_region', 100)->nullable());
        }
        if (! Schema::hasColumn('users', 'address_postcode')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('address_postcode', 20)->nullable());
        }
        if (! Schema::hasColumn('users', 'address_lat')) {
            Schema::table('users', fn (Blueprint $table) => $table->decimal('address_lat', 10, 7)->nullable());
        }
        if (! Schema::hasColumn('users', 'address_lng')) {
            Schema::table('users', fn (Blueprint $table) => $table->decimal('address_lng', 10, 7)->nullable());
        }

        if (! Schema::hasColumn('orders', 'shipping_city')) {
            Schema::table('orders', fn (Blueprint $table) => $table->string('shipping_city', 100)->nullable());
        }
        if (! Schema::hasColumn('orders', 'shipping_country_region')) {
            Schema::table('orders', fn (Blueprint $table) => $table->string('shipping_country_region', 100)->nullable());
        }
        if (! Schema::hasColumn('orders', 'shipping_postcode')) {
            Schema::table('orders', fn (Blueprint $table) => $table->string('shipping_postcode', 20)->nullable());
        }
        if (! Schema::hasColumn('orders', 'shipping_phone_number')) {
            Schema::table('orders', fn (Blueprint $table) => $table->string('shipping_phone_number', 30)->nullable());
        }
        if (! Schema::hasColumn('orders', 'idempotency_key')) {
            Schema::table('orders', fn (Blueprint $table) => $table->string('idempotency_key', 100)->nullable());
        }

        if (
            Schema::hasColumn('orders', 'user_id')
            && Schema::hasColumn('orders', 'idempotency_key')
        ) {
            $this->ensureOrderIdempotencyIndex();
        }

        if (Schema::hasColumn('orders', 'shipping_lat')) {
            Schema::table('orders', fn (Blueprint $table) => $table->decimal('shipping_lat', 10, 7)->nullable()->change());
        }
        if (Schema::hasColumn('orders', 'shipping_lng')) {
            Schema::table('orders', fn (Blueprint $table) => $table->decimal('shipping_lng', 10, 7)->nullable()->change());
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasIndex('orders', ['user_id', 'idempotency_key'])) {
                $table->dropUnique(['user_id', 'idempotency_key']);
            }
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

    private function ensureOrderIdempotencyIndex(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS orders_user_id_idempotency_key_unique ON orders (user_id, idempotency_key)'
            );

            return;
        }

        if (! Schema::hasIndex('orders', ['user_id', 'idempotency_key'])) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unique(['user_id', 'idempotency_key']);
            });
        }
    }
};
