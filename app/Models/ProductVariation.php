<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariation extends Model
{
    protected $fillable = ['product_id', 'type', 'value'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function cartItems()
    {
        return $this->belongsToMany(CartItem::class, 'cart_item_variations', 'product_variation_id', 'cart_item_id');
    }
}
