<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'product_code',
        'price',
        'quantity',
        'category_id',
        'status',
        'sku'
    ];

    public function category(){
        return $this->belongsTo(Category::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function images(){
        return $this->hasMany(ProductImage::class);
    }

    public function mainImage()
    {
        return $this->hasOne(ProductImage::class)->orderBy('id');
    }

    public function getMainImageUrlAttribute()
    {
        $image = $this->mainImage()->first();
        if ($image) {
            return $image->url; // usa el accessor del ProductImage
        }
        return asset('assets/images/product.png');
    }

}
