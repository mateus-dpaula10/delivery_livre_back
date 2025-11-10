<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'cep',
        'street',
        'neighborhood',
        'city',
        'state',
        'number',
        'complement',
        'note'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
