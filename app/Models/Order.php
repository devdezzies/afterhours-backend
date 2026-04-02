<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'total_amount',
        'status',
        'shipping_address',
        'shipping_lat',
        'shipping_lng',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'shipping_lat' => 'decimal:7',
            'shipping_lng' => 'decimal:7',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}