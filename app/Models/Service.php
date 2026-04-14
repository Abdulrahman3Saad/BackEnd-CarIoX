<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Service extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'service_fees', 'description'];

    protected $casts = [
        'service_fees' => 'decimal:2',
    ];

    public function cars()
    {
        return $this->hasMany(Car::class);
    }

    // ✅ Cache الخدمات — بتتغير نادراً
    public static function allCached()
    {
        return Cache::remember('services_all', 3600, fn() => static::all());
    }

    // ✅ إلغاء الـ cache عند أي تعديل أو حذف
    protected static function booted(): void
    {
        static::saved(fn()   => Cache::forget('services_all'));
        static::deleted(fn() => Cache::forget('services_all'));
    }
}
