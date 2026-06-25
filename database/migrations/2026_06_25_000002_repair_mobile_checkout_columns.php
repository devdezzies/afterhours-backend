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

        $this->addUserColumn(
            'phone_number',
            fn (Blueprint $table) => $table->string('phone_number', 30)->nullable()
        );
        $this->addUserColumn(
            'default_address',
            fn (Blueprint $table) => $table->string('default_address', 500)->nullable()
        );
        $this->addUserColumn(
            'address_city',
            fn (Blueprint $table) => $table->string('address_city', 100)->nullable()
        );
        $this->addUserColumn(
            'address_country_region',
            fn (Blueprint $table) => $table->string('address_country_region', 100)->nullable()
        );
        $this->addUserColumn(
            'address_postcode',
            fn (Blueprint $table) => $table->string('address_postcode', 20)->nullable()
        );
        $this->addUserColumn(
            'address_lat',
            fn (Blueprint $table) => $table->decimal('address_lat', 10, 7)->nullable()
        );
        $this->addUserColumn(
            'address_lng',
            fn (Blueprint $table) => $table->decimal('address_lng', 10, 7)->nullable()
        );

        $this->addOrderColumn(
            'shipping_city',
            fn (Blueprint $table) => $table->string('shipping_city', 100)->nullable()
        );
        $this->addOrderColumn(
            'shipping_country_region',
            fn (Blueprint $table) => $table->string('shipping_country_region', 100)->nullable()
        );
        $this->addOrderColumn(
            'shipping_postcode',
            fn (Blueprint $table) => $table->string('shipping_postcode', 20)->nullable()
        );
        $this->addOrderColumn(
            'shipping_phone_number',
            fn (Blueprint $table) => $table->string('shipping_phone_number', 30)->nullable()
        );
        $this->addOrderColumn(
            'idempotency_key',
            fn (Blueprint $table) => $table->string('idempotency_key', 100)->nullable()
        );

        if (
            Schema::hasColumn('orders', 'user_id')
            && Schema::hasColumn('orders', 'idempotency_key')
        ) {
            $this->ensureOrderIdempotencyIndex();
        }

        if (
            DB::getDriverName() === 'pgsql'
            && Schema::hasColumn('orders', 'shipping_lat')
            && Schema::hasColumn('orders', 'shipping_lng')
        ) {
            DB::statement('ALTER TABLE orders ALTER COLUMN shipping_lat DROP NOT NULL');
            DB::statement('ALTER TABLE orders ALTER COLUMN shipping_lng DROP NOT NULL');
        } elseif (Schema::hasColumn('orders', 'shipping_lat') && Schema::hasColumn('orders', 'shipping_lng')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('shipping_lat', 10, 7)->nullable()->change();
                $table->decimal('shipping_lng', 10, 7)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        //
    }

    private function addUserColumn(string $column, callable $definition): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', $column)) {
            Schema::table('users', $definition);
        }
    }

    private function addOrderColumn(string $column, callable $definition): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', $column)) {
            Schema::table('orders', $definition);
        }
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
