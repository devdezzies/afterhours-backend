<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone_number',
        'default_address',
        'address_city',
        'address_country_region',
        'address_postcode',
        'address_lat',
        'address_lng',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed', 
            'address_lat' => 'decimal:7',
            'address_lng' => 'decimal:7',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
