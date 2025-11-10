<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'store_id',
        'status',
        'code',
        'total',
        'address_id'
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function store()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}
