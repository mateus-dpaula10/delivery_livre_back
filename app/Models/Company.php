<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'legal_name',
        'final_name',
        'cnpj',
        'phone',
        'address',
        'cep',
        'street',
        'number',
        'neighborhood',
        'city',
        'state',
        'plan',
        'active',
        'email',
        'category',
        'status',
        'logo',
        'delivery_fee',
        'delivery_radius',
        'opening_hours',
        'free_shipping',
        'first_purchase_discount_store',
        'first_purchase_discount_store_value',
        'first_purchase_discount_app',
        'first_purchase_discount_app_value',
        'pix_key',
        'pix_key_type'
    ];

    protected $casts = [
        'free_shipping'                       => 'boolean',
        'first_purchase_discount_store'       => 'boolean',
        'first_purchase_discount_store_value' => 'integer',
        'first_purchase_discount_app'         => 'boolean',
        'first_purchase_discount_app_value'   => 'integer',
        'opening_hours'                       => 'array'
    ];

    public function admin()
    {
        return $this->hasOne(User::class)->where('role', 'store');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function drivers()
    {
        return $this->hasMany(Driver::class);
    }
}
