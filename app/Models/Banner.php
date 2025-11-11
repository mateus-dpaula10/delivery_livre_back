<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'image_url',
        'target_company_id'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'target_company_id');
    }
}
