<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarSwap extends Model
{
    use HasFactory;

    protected $fillable = [
        'requester_id',
        'requester_car_id',
        'receiver_id',
        'receiver_car_id',
        'requester_phone',
        'requester_governorate',
        'requester_address',
        'notes',
        'price_difference',
        'who_pays_difference',
        'status',
        'swap_date',
    ];

    protected $casts = [
        'swap_date'        => 'date',
        'price_difference' => 'decimal:2',
    ];

    // صاحب الطلب
    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    // المستقبل
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    // سيارة صاحب الطلب
    public function requesterCar()
    {
        return $this->belongsTo(Car::class, 'requester_car_id');
    }

    // سيارة المستقبل
    public function receiverCar()
    {
        return $this->belongsTo(Car::class, 'receiver_car_id');
    }
}