<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'car_id',
        'full_name',
        'phone',
        'governorate',
        'address',
        'expected_delivery_date',
        'pickup_date',
        'return_date',
        'with_driver',
        'car_price',
        'discount',
        'total_amount',
        'order_status',
    ];

    protected $casts = [
        'with_driver'             => 'boolean',
        'expected_delivery_date'  => 'date',
        'pickup_date'             => 'date',
        'return_date'             => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}