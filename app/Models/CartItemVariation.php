<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItemVariation extends Model
{
    protected $fillable = ['cart_item_id', 'product_variation_id'];

    public function cartItem()
    {
        return $this->belongsTo(CartItem::class);
    }

    public function productVariation()
    {
        return $this->belongsTo(ProductVariation::class);
    }
}
