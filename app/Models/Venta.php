<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'user_id',
        'tipodocumento_id',
        'total_price',
        'amount_paid',
        'sale_date',
        'status',
        'payment_status',
        'payment_method',
        'delivery_type',
        'difference',
        'warehouse',
        'codigo',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'total_price' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function customer(){
        return $this->belongsTo(Customer::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function tipodocumento(){
        return $this->belongsTo(TipoDocumento::class);
    }

    public function detalles(){
        return $this->hasMany(DetalleVenta::class,'sale_id');
    }
}
