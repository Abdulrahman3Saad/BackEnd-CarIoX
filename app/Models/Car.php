<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Car extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'service_id', 'car_number', 'brand', 'model',
        'manufacture_year', 'car_condition', 'fuel_type', 'transmission',
        'mileage', 'seats', 'color', 'price', 'description', 'status',
        'is_available', 'city', 'address', 'contact_phone',
    ];

    protected $casts = [
        'is_available'     => 'boolean',
        'price'            => 'decimal:2',
        'manufacture_year' => 'integer',
        'mileage'          => 'integer',
        'seats'            => 'integer',
    ];

    public function user()          { return $this->belongsTo(User::class); }
    public function service()       { return $this->belongsTo(Service::class); }
    public function images()        { return $this->hasMany(CarImage::class); }
    public function orders()        { return $this->hasMany(Order::class); }
    public function payments()      { return $this->hasMany(Payment::class); }
    public function sentSwaps()     { return $this->hasMany(CarSwap::class, 'requester_car_id'); }
    public function receivedSwaps() { return $this->hasMany(CarSwap::class, 'receiver_car_id'); }

    // ✅ بيعتمد على service->name مباشرة بدل type
    public function isRent(): bool { return strtolower($this->service?->name ?? '') === 'rent'; }
    public function isSale(): bool { return strtolower($this->service?->name ?? '') === 'sale'; }
    public function isSwap(): bool { return strtolower($this->service?->name ?? '') === 'exchange'; }
}
