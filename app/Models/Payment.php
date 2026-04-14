<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'swap_id',          // ✅ مضاف
        'car_id',
        'amount',
        'payment_type',
        'payment_method',
        'payment_provider',
        'installment_number',
        'total_installments',
        'status',
    ];

    public function user()  
    {
        return $this->belongsTo(User::class);
    }
    
    public function order() { return $this->belongsTo(Order::class); }
    public function car()   { return $this->belongsTo(Car::class); }
    public function swap()  { return $this->belongsTo(CarSwap::class, 'swap_id'); } // ✅ مضاف
}