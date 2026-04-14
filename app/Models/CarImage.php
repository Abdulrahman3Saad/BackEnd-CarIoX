<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CarImage extends Model
{
    use HasFactory;

    protected $fillable = ['car_id', 'image_path', 'image_url'];

    // ✅ Accessor: حساب الـ URL ديناميكياً بدل تخزينه — لو غيرت الـ domain مش هيتكسر
    public function getImageUrlAttribute(): string
    {
        return asset(Storage::url($this->image_path));
    }

    public function car()
    {
        return $this->belongsTo(Car::class);
    }
}
