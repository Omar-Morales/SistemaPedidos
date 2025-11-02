<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory;

    protected $appends = ['url'];

    protected $fillable = [
        'product_id',
        'image_path',
    ];

    public function product(){
        return $this->belongsTo(Product::class);
    }

        public function getUrlAttribute()
    {
        if ($this->image_path && file_exists(storage_path('app/public/' . $this->image_path))) {
            return asset('storage/' . $this->image_path);
        }

        return asset('assets/images/product.png');
    }
}
